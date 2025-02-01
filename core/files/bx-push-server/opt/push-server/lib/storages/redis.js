const Storage = require("./storage");
const redis = require("redis");
const logger = require("../debug");
const { OutgoingMessage } = require("../models");

class RedisStorage extends Storage
{
	static get defaultOptions()
	{
		return {
			port: 6379,
			host: "127.0.0.1",
			messageTTL: 60 * 60 * 24,
			channelTTL: 60 * 60 * 24
		};
	}

	/**
	 *
	 * @param {Application} application
	 */
	constructor(application)
	{
		super(application);

		this.application = application;

		this.options = Object.assign(RedisStorage.defaultOptions, this.application.getOptions().storage);
		this.client = this.options.socket
			? redis.createClient(this.options.socket, { detect_buffers: true })
			: redis.createClient(this.options.port, this.options.host, { detect_buffers: true })
		;

		this.client.on("error", function(error) {
			logger.systemError("Redis Storage Client Error: " + error);
		});

		this.startDate = null;

		this.channelPrefix = Buffer.from("channel:messages:");
		this.publicChannelPrefix = Buffer.from("pubchannel:messages:");
		this.parenthesis = Buffer.from("(");
	}

	/**
	 *
	 * @param {IncomingMessage} incomingMessage
	 * @param {function(Error, OutgoingMessage)} callback
	 */
	set(incomingMessage, callback)
	{
		this.createMessage(incomingMessage, (error, outgoingMessage) => {

			if (error)
			{
				callback(error, null);
			}
			else if (outgoingMessage.expiry === 0)
			{
				callback(null, outgoingMessage);
			}
			else
			{
				this.saveMessage(incomingMessage.receivers, outgoingMessage, callback);
			}

		});
	}

	/**
	 *
	 * @param {IncomingMessage} incomingMessage
	 * @param {function(Error, OutgoingMessage)} callback
	 */
	createMessage(incomingMessage, callback)
	{
		this.getMessageId((error, id) => {
			if (error)
			{
				callback(error, null);
				return;
			}

			const outgoingMessage = new OutgoingMessage();
			outgoingMessage.id = id;
			outgoingMessage.body = incomingMessage.body;
			outgoingMessage.created = Math.floor(Date.now() / 1000);
			outgoingMessage.sender = incomingMessage.sender;
			outgoingMessage.expiry =
				incomingMessage.expiry > 0 ? Math.min(incomingMessage.expiry, this.options.messageTTL) : 0;

			callback(null, outgoingMessage);

		});
	}

	/**
	 *
	 * @param {Receiver[]} receivers
	 * @param {OutgoingMessage} outgoingMessage
	 * @param {function(Error, OutgoingMessage)} callback
	 */
	saveMessage(receivers, outgoingMessage, callback)
	{
		if (receivers.length < 1)
		{
			return callback(null, outgoingMessage);
		}

		const multi = this.client.multi();

		multi.setex(
			RedisStorage.getMessageKey(outgoingMessage.id),
			outgoingMessage.expiry,
			OutgoingMessage.encode(outgoingMessage).finish()
		);

		const channels = [];//new Set();

		for (let i = 0, l = receivers.length; i < l; i++)
		{
			let channelId =
				receivers[i].isPrivate === true ?
					this.getChannelKey(receivers[i].id) :
					this.getPubChannelKey(receivers[i].id)
			;

			channels.push(channelId);
			multi.zadd(channelId, 0, outgoingMessage.id);
		}

		multi.exec((error) => {

			process.nextTick(() => {
				callback(error, outgoingMessage);
			});

			if (!error)
			{
				this.setChannelsTTL(channels);
			}

		});
	}

	/**
	 *
	 * @param {function(Error, ?string)} callback
	 */
	getMessageId(callback)
	{
		this.client.incr("server:messagecounter", (error, messageCounter) => {

			if (error)
			{
				callback(error, null);
				return;
			}

			this.getStartDate((error, startDate) => {
				if (error)
				{
					callback(error, null);
					return;
				}

				const id = Buffer.from(startDate + messageCounter.toString().padStart(16, "0"), "hex");
				callback(null, id);
			});

		});
	}

	setChannelsTTL(channels)
	{
		if (!Array.isArray(channels) || channels.length < 1)
		{
			return;
		}

		const multi = this.client.multi();
		for (let i = 0; i < channels.length; i++)
		{
			multi.ttl(channels[i]);
		}

		multi.exec((error, result) => {

			if (error || !Array.isArray(result))
			{
				logger.systemError("Redis: Set Channels TTL Error: " + error);
				return;
			}

			for (let i = 0; i < result.length; i++)
			{
				if (result[i] === -1)
				{
					this.client.expire(channels[i], this.options.channelTTL, (error) => {
						if (error)
						{
							return logger.systemError("Error expire: " + error);
						}
					});
				}
			}

		});
	}

	/**
	 *
	 * @param {Receiver[]} receivers
	 * @param {string} since
	 * @param {function(Error, OutgoingMessage[])} callback
	 */
	get(receivers, since, callback)
	{
		const multi = this.client.multi();

		for (let i = 0, l = receivers.length; i < l; i++)
		{
			if (receivers[i].isPrivate === true)
			{
				/*multi.zrangebylex([
					this.getChannelKey(receivers[i].id),
					Buffer.concat([this.parenthesis, since]),
					"+"
				]);*/
				multi.zrevrangebylex([
					this.getChannelKey(receivers[i].id),
					"+",
					Buffer.concat([this.parenthesis, since]),
					"LIMIT",
					"0",
					"100"
				]);
			}
			else
			{
				/*
				multi.zrangebylex([
					this.getPubChannelKey(receivers[i].id),
					Buffer.concat([this.parenthesis, since]),
					"+"
				]);*/

				multi.zrevrangebylex([
					this.getPubChannelKey(receivers[i].id),
					"+",
					Buffer.concat([this.parenthesis, since]),
					"LIMIT",
					"0",
					"100"
				]);
			}
		}

		multi.exec((error, result) => {

			if (error)
			{
				callback(error, []);
			}
			else
			{
				const ids = Array.prototype.concat(...result).sort((a, b) => {
					return a.compare(b);
				}).splice(-200); //limit max last messages

				this.getMessages(ids, callback);
			}

		});
	}

	/**
	 *
	 * @param {string[]} ids
	 * @param {function(Error, OutgoingMessage[])} callback
	 */
	getMessages(ids, callback)
	{
		if (!Array.isArray(ids) || ids.length < 1)
		{
			callback(null, []);
			return;
		}

		ids = ids.map(id => RedisStorage.getMessageKey(id));

		this.client.mget(ids, (error, result) => {

			if (error)
			{
				callback(error, []);
				return;
			}

			const outgoingMessages = [];
			if (Array.isArray(result))
			{
				for (let i = 0, len = result.length; i < len; i++)
				{
					if (!Buffer.isBuffer(result[i]))
					{
						continue;
					}

					try
					{
						let outgoingMessage = OutgoingMessage.decode(result[i]);
						outgoingMessages.push(outgoingMessage);
					}
					catch (ex)
					{
						// eslint-disable-next-line no-empty
					}
				}
			}

			process.nextTick(function() {
				callback(error, outgoingMessages);
			});

		});
	}

	static getMessageKey(messageId)
	{
		return Buffer.concat([Buffer.from("message:", "ascii"), messageId]);
	}

	/**
	 *
	 * @param channelId
	 * @return {string|Buffer}
	 */
	getChannelKey(channelId)
	{
		return Buffer.concat([this.channelPrefix, channelId]);
	}

	getPubChannelKey(pubChannelId)
	{
		return Buffer.concat([this.publicChannelPrefix, pubChannelId]);
	}

	getStartDate(callback)
	{
		if (this.startDate !== null)
		{
			callback(null, this.startDate);
			return;
		}

		const startDate = Math.floor(new Date().getTime() / 1000);
		const multi = this.client.multi();

		multi.setnx("server:startdate", startDate);
		multi.get("server:startdate");
		multi.exec((error, result) => {

			if (error || (!Array.isArray(result) && result.length !== 2))
			{
				logger.systemError("Redis: Get Start Date Error: " + error);
				callback(error, null);
			}
			else
			{
				this.startDate = result[1];
				callback(null, this.startDate);
			}

		});
	}

	/**
	 *
	 * @param {Receiver[]} receivers
	 * @param {function(Error, OutgoingMessage)} callback
	 */
	getLastMessage(receivers, callback)
	{
		const multi = this.client.multi();

		for (let i = 0, l = receivers.length; i < l; i++)
		{
			if (receivers[i].isPrivate === true)
			{
				//last element in a ordered set
				multi.zrevrange(this.getChannelKey(receivers[i].id), 0, 0);
			}
			else
			{
				//last element in a ordered set
				multi.zrevrange(this.getPubChannelKey(receivers[i].id), 0, 0);
			}
		}

		multi.exec((error, result) => {
			if (error)
			{
				callback(error, null);
				return;
			}

			const lastMessageId = Array.prototype.concat(...result).reduce((prev, cur) => {
				return cur.compare(prev) === 1 ? cur : prev;
			}, Buffer.from(""));

			if (lastMessageId.length > 0)
			{
				this.getMessages([lastMessageId], (error, messages) => {
					if (error)
					{
						callback(error, null);
					}
					else
					{
						callback(null, Array.isArray(messages) && messages.length ? messages[0] : null);
					}
				});
			}
			else
			{
				callback(null, null);
			}

		});
	}
}

module.exports = RedisStorage;