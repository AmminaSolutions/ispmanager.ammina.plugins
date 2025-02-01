const { Response, ChannelStats } = require("../models");
const config = require("../../config");

/* Connection Manager */
class Adapter
{
	/**
	 *
	 * @param {Application} application
	 */
	constructor(application)
	{
		this.application = application;
		this.connections = new Map();
		this.pubChannels = new Map();
	}

	/**
	 *
	 * @param {Connection} connection
	 */
	add(connection)
	{
		if (!connection.isActive())
		{
			return false;
		}

		this.getApplication().getStatistics().incrementConnection(connection);

		let channels = connection.getChannels();
		for (let i = 0; i < channels.length; i++)
		{
			let channel = channels[i];

			let channelConnections = this.connections.get(channel.getHexPrivateId());
			if (!channelConnections)
			{
				channelConnections = new Set();
				this.connections.set(channel.getHexPrivateId(), channelConnections);
			}

			if (channel.getPublicId() && channelConnections.size >= config.limits.maxConnPerChannel)
			{
				const firstConnection = channelConnections.values().next().value;
				if (firstConnection)
				{
					this.delete(firstConnection);
					firstConnection.close(4029, "Too many connections");
				}
			}

			channelConnections.add(connection);

			if (channel.getPublicId())
			{
				this.pubChannels.set(channel.getHexPublicId(), channel.getHexPrivateId());
			}
		}

		connection.on("close", this.delete.bind(this, connection));

		return true;
	}

	/**
	 *
	 * @param {Connection} connection
	 */
	delete(connection)
	{
		let success = false;
		let channels = connection.getChannels();
		for (let i = 0; i < channels.length; i++)
		{
			let channelId = channels[i].getHexPrivateId();

			let channelConnections = this.connections.get(channelId);
			if (!channelConnections)
			{
				continue;
			}

			if (channelConnections.delete(connection))
			{
				success = true;
			}

			if (channelConnections.size === 0)
			{
				this.connections.delete(channelId);
				if (channels[i].getPublicId())
				{
					this.pubChannels.delete(channels[i].getHexPublicId());
				}
			}
		}

		if (success)
		{
			this.getApplication().getStatistics().decrementConnection(connection);
		}
	}

	/**
	 *
	 * @param {Receiver[]} receivers
	 * @param {OutgoingMessage} outgoingMessage
	 */
	broadcast(receivers, outgoingMessage)
	{
		const response = Response.create({
			outgoingMessages: {
				messages: [outgoingMessage]
			}
		});

		receivers.forEach(receiver => {

			const channelId =
				receiver.isPrivate === true
					? receiver.id.toString("hex")
					: this.pubChannels.get(receiver.id.toString("hex"))
			;

			if (typeof(channelId) !== "string")
			{
				return;
			}

			const channelConnections = this.connections.get(channelId);
			if (!channelConnections)
			{
				return;
			}

			for (let connection of channelConnections)
			{
				connection.send(response);
			}
		});
	}

	postIpcNotification()
	{

	}

	/**
	 *
	 * @param {ChannelId[]} channelIds
	 * @param {function(Error, ChannelStats[])} callback
	 */
	getChannelStats(channelIds, callback)
	{
		const channels = [];

		channelIds.forEach(channel => {
			channels.push(new ChannelStats({
				id: channel.id,
				isPrivate: channel.isPrivate,
				isOnline: channel.isPrivate ?
					this.connections.has(channel.id.toString("hex")) :
					this.connections.has(this.pubChannels.get(channel.id.toString("hex")))
			}));
		});

		callback(null, channels);
	}

	getServerStats(callback)
	{
		let processUniqueId = this.getApplication().getOptions().processUniqueId;
		processUniqueId = typeof(processUniqueId) === "string" && processUniqueId.length ? processUniqueId : process.pid;

		const statistics = this.getApplication().getStatistics();

		const fields = {
			pid: process.pid,
			date: Date.now(),
			processUniqueId,
			channels: this.connections.size,
			limits: this.getApplication().getOptions().limits,
			clusterMode: this.getApplication().getOptions().clusterMode,
			websockets: statistics.getWebsocketCount(),
			pollings: statistics.getPollingCount(),
			daily: statistics.getDailyStats()
		};

		callback(null, fields);
	}

	getApplication()
	{
		return this.application;
	}
}

module.exports = Adapter;