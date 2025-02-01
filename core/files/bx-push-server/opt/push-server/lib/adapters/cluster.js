const redis = require("redis");
const Adapter = require("./adapter");
const logger = require("../debug");
const License = require("../license");

const { ChannelStats, NotificationBatch } = require("../models");

/* Connection Manager */
class ClusterAdapter extends Adapter
{
	/**
	 *
	 * @param {Application} application
	 */
	constructor(application)
	{
		super(application);

		this.uid = Math.random().toString(36).substring(2, 10);

		const options = this.getApplication().getOptions();
		const storage = options.storage;
		const host = storage.host || "127.0.0.1";
		const port = Number(storage.port || 6379);
		const socket = typeof(storage.socket) === "string" && storage.socket.length > 0 ? storage.socket : null;

		/** @type {RedisClient} */
		this.client = socket ? redis.createClient(socket) : redis.createClient(port, host);
		this.client.on("error", error => {
			logger.systemError("Redis Pub Client Error: " + error);
		});

		if (!options.publishMode)
		{
			/** @type {RedisClient} */
			this.subClient =
				socket
					? redis.createClient(socket, { return_buffers: true })
					: redis.createClient(port, host, { return_buffers: true })
			;

			this.subClient.on("error", error => {
				logger.systemError("Redis Sub Client Error: " + error);
			});

			this.subClient.psubscribe("pushserver:*", error => {
				if (error)
				{
					logger.systemError("Redis Psubscribe Error: " + error);
				}
			});

			this.subClient.on("pmessage", this.handleMessage.bind(this));
		}

		this.ipcClient = null;
		if (options.cloudMode)
		{
			/** @type {RedisClient} */
			this.ipcClient =
				socket
					? redis.createClient(socket, { return_buffers: true })
					: redis.createClient(port, host, { return_buffers: true })
			;

			this.ipcClient.on("error", error => {
				logger.systemError("IPC Client Error: " + error);
			});

			this.ipcClient.psubscribe("ipc:*", error => {
				if (error)
				{
					logger.systemError("IPC Psubscribe Error: " + error);
				}
			});

			this.ipcClient.on("pmessage", this.handleMessage.bind(this));
		}

		this.onlineTTL = storage.onlineTTL || 120;
		this.onlineDelta = storage.onlineDelta || 30;
		this.statTLLMsec = storage.statTLLMsec || 60000;
		this.statDeltaMsec = storage.statDeltaMsec || 10000;

		this.onlineChannelPrefix = Buffer.from("channel:online:");
		this.onlinePubChannelPrefix = Buffer.from("pubchannel:online:");

		this.setServerStats();
		setInterval(this.setServerStats.bind(this), this.statTLLMsec);
	}

	/**
	 *
	 * @param {Connection} connection
	 */
	add(connection)
	{
		if (super.add(connection))
		{
			this.setOnline(connection);

			if (connection.isWebsocket())
			{
				connection.on("pong", this.setOnline.bind(this, connection));
			}

			return true;
		}

		return false;
	}

	setOnline(connection)
	{
		const channel = connection.getChannels()[0];
		const ttl = this.onlineTTL + this.onlineDelta;

		const multi = this.client.multi();
		multi.setex(this.getOnlineKey(channel.getPrivateId()), ttl, 1);

		if (channel.getPublicId())
		{
			multi.setex(this.getOnlinePubKey(channel.getPublicId()), ttl, 1);
		}

		multi.exec(error => {
			if (error)
			{
				return logger.systemError("Redis Set Online Error: " + error);
			}
		});
	}

	getOnlineKey(channelId)
	{
		return Buffer.concat([this.onlineChannelPrefix, channelId]);
	}

	getOnlinePubKey(pubChannelId)
	{
		return Buffer.concat([this.onlinePubChannelPrefix, pubChannelId]);
	}

	delete(connection)
	{
		if (connection.tm)
		{
			clearInterval(connection.tm);
		}

		super.delete(connection);
	}

	/**
	 *
	 * @param {Receiver[]} receivers
	 * @param {OutgoingMessage} outgoingMessage
	 */
	broadcast(receivers, outgoingMessage)
	{
		if (!this.getApplication().getOptions().publishMode)
		{
			super.broadcast(receivers, outgoingMessage);
		}

		const notificationBatch = ClusterAdapter.packMessage(receivers, outgoingMessage);
		this.client.publish(
			"pushserver:" + this.uid,
			NotificationBatch.encode(notificationBatch).finish()
		);
	}

	/**
	 *
	 * @param {Notification|Notification[]} notification
	 */
	postIpcNotification(notification)
	{
		const notificationBatch = NotificationBatch.create({
			notifications: Array.isArray(notification) ? notification : [notification]
		});

		this.client.publish(
			"ipc:" + this.uid,
			NotificationBatch.encode(notificationBatch).finish()
		);
	}

	handleMessage(pattern, channel, binaryMessage)
	{
		const pieces = channel.toString().split(":");
		if (this.uid === pieces.pop())
		{
			return;
		}

		let batch = null;
		try
		{
			batch = NotificationBatch.decode(binaryMessage);
		}
		catch (exp)
		{
			return;
		}

		for (let i = 0; i < batch.notifications.length; i++)
		{
			let notification = batch.notifications[i];
			if (notification.ipcMessages)
			{
				this.processIpcMessages(notification.ipcMessages.messages);
			}
			else if (notification.ipcLicenses)
			{
				this.processIpcLicenses(notification.ipcLicenses.licenses);
			}
		}
	}

	/**
	 *
	 * @param {Receiver[]} receivers
	 * @param {OutgoingMessage} outgoingMessage
	 * @return {NotificationBatch}
	 */
	static packMessage(receivers, outgoingMessage)
	{
		return NotificationBatch.create({
			notifications: [{
				ipcMessages: {
					messages: [{
						receivers,
						outgoingMessage
					}]
				}
			}]
		});
	}

	/**
	 *
	 * @param {IPCMessage[]} messages
	 */
	processIpcMessages(messages)
	{
		if (!Array.isArray(messages))
		{
			return;
		}

		for (let i = 0; i < messages.length; i++)
		{
			let message = messages[i];
			if (message.outgoingMessage)
			{
				super.broadcast(message.receivers, message.outgoingMessage);
			}
			// else if (message.outgoingMessageId)
			// {
			//
			// }
		}
	}

	/**
	 *
	 * @param {IPCLicense[]} licenses
	 */
	processIpcLicenses(licenses)
	{
		if (!Array.isArray(licenses))
		{
			return;
		}

		for (let i = 0; i < licenses.length; i++)
		{
			const license = licenses[i].license;
			License.refresh(license.clientId);
		}
	}

	/**
	 *
	 * @param {ChannelId[]} channelIds
	 * @param {function(Error, ChannelStats[])} callback
	 */
	getChannelStats(channelIds, callback)
	{
		const channelKeys = channelIds.map(channel => {
			return (
				channel.isPrivate
					? this.getOnlineKey(channel.id)
					: this.getOnlinePubKey(channel.id)
			);
		});

		this.client.mget(channelKeys, (error, result) => {

			if (error)
			{
				callback(error, []);
				return;
			}

			const channels = [];

			channelIds.forEach((channel, index) => {
				channels.push(new ChannelStats({
					id: channel.id,
					isPrivate: channel.isPrivate,
					isOnline: typeof(result[index]) === "string"
				}));
			});

			process.nextTick(function() {
				callback(error, channels);
			});

		});
	}

	getServerStats(callback)
	{
		this.client.hgetall("stats", (error, result) => {

			if (error)
			{
				return callback(error, []);
			}

			const stats = [];
			const oldKeys = [];
			for (let key in result)
			{
				let item = JSON.parse(result[key]);
				if (!item.date || (Date.now() - item.date) > (this.statTLLMsec + this.statDeltaMsec))
				{
					oldKeys.push(key);
				}
				else
				{
					stats.push(item);
				}
			}

			this.deleteOldStatsKeys(oldKeys);
			callback(error, stats);

		});
	}

	setServerStats()
	{
		super.getServerStats((error, stat) => {

			const hash = {};
			hash[stat.processUniqueId] = JSON.stringify(stat);
			this.client.hmset("stats", hash, (error) => {
				if (error)
				{
					return logger.systemError("Redis Set Stat Error: " + error);
				}
			});

		});
	}

	deleteOldStatsKeys(fields)
	{
		if (!Array.isArray(fields) || fields.length < 1)
		{
			return;
		}

		fields.unshift("stats");
		this.client.hdel(fields, (error) => {
			if (error)
			{
				return logger.systemError("Redis Delete Stat Error: " + error);
			}
		});
	}
}

module.exports = ClusterAdapter;