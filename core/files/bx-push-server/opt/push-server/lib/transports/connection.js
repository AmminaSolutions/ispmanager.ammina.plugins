const URLSearchParams = require("url").URLSearchParams;
const EventEmitter = require("events").EventEmitter;
const Channel = require("../channel");
const { Receiver } = require("../models");
const logger = require("../debug");
const config = require("../../config");
const Signature = require("../signature");

const channelPattern = new RegExp(
	"^([a-f0-9]{32})(?:\\:([a-f0-9]{32}))?(?:\\.([a-f0-9]{32,128}))?$"
);

const testConnectionKey = config.debug && config.debug.testConnectionKey ? config.debug.testConnectionKey : null;

class Connection extends EventEmitter
{
	constructor(request, verifySignature = true, license = null)
	{
		super();

		this.receivers = [];
		this.publicId = null;
		this.license = license;

		const searchQuery = request.url.split('?');
		this.urlSearchParams = new URLSearchParams(typeof(searchQuery[1]) === "string" ? searchQuery[1] : "");

		const channelIds = this.urlSearchParams.get("CHANNEL_ID");
		this.channels = this.parseChannels(channelIds, verifySignature);

		for (let i = 0; i < this.channels.length; i++)
		{
			const channel = this.channels[i];

			this.receivers.push(new Receiver({
				id: channel.getPrivateId(),
				isPrivate: true
			}));

			if (channel.getPublicId())
			{
				this.receivers.push(new Receiver({
					id: channel.getPublicId(),
					isPrivate: false
				}));

				if (this.publicId === null)
				{
					this.publicId = channel.getPublicId();
				}
			}
		}

		const mid = this.urlSearchParams.get("mid");
		this.mid = typeof(mid) === "string" && mid.match(/^[0-9]{26}$/) ? Buffer.from(mid, "hex") : null;

		this.binaryMode = this.urlSearchParams.get("binaryMode") === "true";

		const signature = this.urlSearchParams.get('signature');
		this.signature =
			config.cloudMode && typeof(signature) === "string" && signature.match(/^[a-f0-9]{40}$/)
				? signature
				: null
		;

		this.socket = request.socket;
		this.active = true;
		this.type = null;

		const forwarded = request.headers["x-forwarded-for"];
		this.ip = forwarded ? forwarded : request.socket.remoteAddress;

		this.testConnection = testConnectionKey !== null && this.urlSearchParams.get('testKey') === testConnectionKey;
	}

	/**
	 *
	 * @param {string} channelIds
	 * @param {Boolean} [verifySignature=true]
	 * @returns Channel[]
	 */
	parseChannels(channelIds, verifySignature = true)
	{
		if (verifySignature !== false && this.getLicense() === null && config.cloudMode)
		{
			return [];
		}

		const channels = [];
		if (!channelIds || channelIds.length < 1)
		{
			return [];
		}

		const channelParts = channelIds.split("/");
		for (let i = 0; i < channelParts.length; i++)
		{
			let result = this.parseChannelIds(channelParts[i], verifySignature);
			if (!result)
			{
				return [];
			}

			const [privateId, publicId] = result;

			channels.push(
				new Channel(
					Buffer.from(privateId, "hex"),
					publicId ? Buffer.from(publicId, "hex") : null
				)
			);
		}

		return channels;
	}

	/**
	 *
	 * @param {string} channel
	 * @param {boolean} [verifySignature=true]
	 * @returns Boolean|Array
	 */
	parseChannelIds(channel, verifySignature = true)
	{
		const match = typeof(channel) === "string" && channel.match(channelPattern);
		if (!match)
		{
			return false;
		}

		const [, privateChannelId, publicChannelId, signature] = match;

		if (publicChannelId)
		{
			// Always verify for the public channel id
			if (!signature || !Signature.isValid(privateChannelId + ":" + publicChannelId, signature, this.getLicense()))
			{
				return false;
			}
		}
		else if (
			verifySignature !== false &&
			(!signature || !Signature.isValid(privateChannelId, signature, this.getLicense()))
		)
		{
			return false;
		}

		return [privateChannelId, publicChannelId];
	}

	getMid()
	{
		return this.mid;
	}

	/**
	 *
	 * @returns {Channel[]}
	 */
	getChannels()
	{
		return this.channels;
	}

	getPubChannelId()
	{
		return this.publicId;
	}

	getReceivers()
	{
		return this.receivers;
	}

	getLicense()
	{
		return this.license;
	}

	getSignature()
	{
		return this.signature;
	}

	getSocket()
	{
		return this.socket;
	}

	getUrlSearchParams()
	{
		return this.urlSearchParams;
	}

	isTestConnection()
	{
		return this.testConnection;
	}

	isActive()
	{
		return this.active;
	}

	isBinaryMode()
	{
		return this.binaryMode;
	}

	setBinaryMode(mode)
	{
		this.binaryMode = mode !== false;
	}

	isWebsocket()
	{
		return this.type === "websocket";
	}

	getType()
	{
		return this.type;
	}

	isDebugMode()
	{
		return this.socket.bxDebugId !== undefined;
	}

	getIp()
	{
		return this.ip;
	}

	/**
	 * @abstract
	 * @param {Response} response
	 */
	send(response)
	{

	}

	debugDispatch(responseBatch, data)
	{
		if (!this.isDebugMode())
		{
			return;
		}

		const commands = {};
		const messageIds = [];
		const contentLength = typeof data === "string" ? Buffer.byteLength(data) : data.length;

		responseBatch.responses.forEach(response => {
			commands[response.command] = (commands[response.command] || 0) + 1;
			if (response.outgoingMessages)
			{
				response.outgoingMessages.messages.forEach(message => {
					messageIds.push(message.id.toString("hex"));
				});
			}
		});

		logger.debug(
			this.getSocket().bxDebugId,
			"[" + this.type.toUpperCase() + "-SEND]",
			contentLength + "B",
			commands,
			messageIds
		);
	}

	static getHttpStatus(status)
	{
		if (status < 1000)
		{
			return status;
		}

		switch (status)
		{
			case 4000:
				return 200;
			case 4029:
				return 429;
			default:
				return 400;
		}
	}

	static getStatusText(status, reason)
	{
		return reason ? status + ": " + reason : null;
	}

	/**
	 * @abstract
	 * @param {number} status
	 * @param {string} [reason]
	 */
	close(status, reason)
	{
		throw new Error("The method is not implemented");
	}

	/**
	 *
	 * @param {ResponseBatch} responseBatch
	 */
	static convertResponseBatch(responseBatch)
	{
		let result = "";

		for (let i = 0; i < responseBatch.responses.length; i++)
		{
			result += Connection.convertResponse(responseBatch.responses[i]) || "";
		}

		return result;
	}
	/**
	 *
	 * @param {Response} response
	 */
	static convertResponse(response)
	{
		if (response.outgoingMessages && Array.isArray(response.outgoingMessages.messages))
		{
			return Connection.convertIncomingMessagesResponse(response.outgoingMessages.messages);
		}
		else if (response.serverStats && response.serverStats.json)
		{
			return response.serverStats.json;
		}
		else if (response.json)
		{
			return response.json;
		}
		else if (response.channelStats && Array.isArray(response.channelStats.channels))
		{
			return Connection.convertChannelStatsResponse(response.channelStats.channels);
		}

		return null;
	}

	static convertIncomingMessagesResponse(messages)
	{
		let result = "";

		for (let i = 0, length = messages.length; i < length; i++)
		{
			/** @type {OutgoingMessage} */
			let message = messages[i];

			message.created = Date.now();

			let tag = message.created.toString();
			tag = tag.substring(tag.length - 3);

			const messageId = message.id.toString("hex");
			let json = {
				"id": parseInt(messageId.substring(10), 10),
				"mid": messageId,
				"channel": "-",
				"tag": tag,
				"time": new Date(message.created).toUTCString(),
				"text": "---replace---" //hack for Bitrix json format
			};

			result += (
				"#!NGINXNMS!#" +
				JSON.stringify(json).replace("\"---replace---\"", function() {
					return message.body;
				}) +
				"#!NGINXNME!#"
			);
		}

		return result;
	}

	static convertChannelStatsResponse(channels)
	{
		const activeChannels = [];

		for (let i = 0; i < channels.length; i++)
		{
			let channel = channels[i];
			if (channel.isOnline && channel.isPrivate)
			{
				activeChannels.push({ channel: channel.id.toString("hex"), subscribers: 1 });
			}
		}

		return JSON.stringify({ infos: activeChannels });
	}
}

module.exports = Connection;