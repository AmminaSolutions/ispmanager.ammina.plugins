const logger = require("./debug");

const RedisStorage = require("./storages/redis");
const Statistics = require("./statistics");
const config = require("../config");
const Channel = require("./channel");
const License = require("./license");
const Signature = require("./signature");
const Connection = require("./transports/connection");

const { RequestBatch, Response, Sender, SenderType } = require("./models");

/*

POST /pub/ -> Application.publish. Trusted request.
POST /pub/?binaryMode=true -> Application.processClientRequest. Trusted request.
GET /pub/ -> Application.getChannelStats. Trusted request.

POST /rest/ -> Application.processClientRequest. Untrusted request.
Client websocket -> Application.processClientRequest. Untrusted request.

GET /server-stat/ -> Application.getServerStats. Trusted request.

GET /sub/ -> Application.subscribe. Long Polling requests.
GET UPGRADE /sub/ -> Application.subscribe. Websocket requests.
 */

let Polling = null;
let WebSocket = null;

class Application
{
	constructor(options)
	{
		this.options = options || {};
		this.statistics = new Statistics();

		/**
		 * @type {Storage}
		 */
		this.storage = new RedisStorage(this);

		const AdapterClass = require("./adapters/" + (this.options.clusterMode ? "cluster" : "adapter"));
		this.adapter = new AdapterClass(this);

		this.publicCommands = new Set([
			"incomingMessages",
			"channelStats"
		]);
	}

	getOptions()
	{
		return this.options;
	}

	/**
	 * @type {Storage}
	 */
	getStorage()
	{
		return this.storage;
	}

	getAdapter()
	{
		return this.adapter;
	}

	getStatistics()
	{
		return this.statistics;
	}

	/**
	 * @param {IncomingMessage} request
	 * @param {ServerResponse} [response]
	 */
	subscribePolling(request, response)
	{
		if (Polling === null)
		{
			Polling = require("./transports/polling");
		}

		if (config.cloudMode)
		{
			License.getByRequest(request).then(license => {
				const connection = new Polling(request, response, license);
				this.addToAdapter(connection);
			});
		}
		else
		{
			const connection = new Polling(request, response);
			this.addToAdapter(connection);
		}
	}

	/**
	 * @param {IncomingMessage} request
	 * @param {WebSocket} [websocket]
	 * @param {?License} license
	 */
	subscribeWebsocket(request, websocket, license)
	{
		if (WebSocket === null)
		{
			WebSocket = require("./transports/websocket");
		}

		const connection = new WebSocket(request, websocket, license);
		this.addToAdapter(connection);
	}

	addToAdapter(connection)
	{
		if (config.cloudMode && connection.getLicense() === null)
		{
			connection.close(4036, "Wrong Client Id.");
			return;
		}

		if (connection.getChannels().length < 1)
		{
			connection.close(4010, "Wrong Channel Id.");
			return;
		}

		const mid = connection.getMid();
		if (mid === null)
		{
			this.adapter.add(connection);
		}
		else
		{
			const startDate = logger.profileStart(connection);
			this.storage.get(connection.getReceivers(), mid, (error, messages) => {

				if (error)
				{
					connection.close(4011, "Couldn't get last messages.");
					logger.systemError("Last Messages Get Error: " + error, connection.getIp());
					return;
				}

				logger.profileEnd(connection, startDate, "[MESSAGE-GET]", messages.length);

				if (messages.length > 0)
				{
					const response = Response.create({
						outgoingMessages: {
							messages: messages
						}
					});

					connection.send(response);
				}

				this.adapter.add(connection);

			});
		}
	}

	/**
	 *
	 * @param {IncomingMessage} incomingMessage
	 * @param {Connection} connection
	 */
	publish(incomingMessage, connection)
	{
		if (incomingMessage.receivers.length === 0)
		{
			return;
		}

		this.getStatistics().incrementMessage(incomingMessage.type);

		const startDate = logger.profileStart(connection);

		this.storage.set(incomingMessage, (error, outgoingMessage) => {

			if (error || !outgoingMessage)
			{
				logger.systemError("Storage: Publishing Error: " + error, connection.getIp());
				return;
			}

			logger.profileEnd(connection, startDate, "[MESSAGE-SET]", outgoingMessage.id);

			this.adapter.broadcast(incomingMessage.receivers, outgoingMessage);

		});

	}

	/**
	 *
	 * @param {RegisterRequest} registerRequest
	 * @param {Connection} connection
	 */
	register(registerRequest, connection)
	{
		License.register(registerRequest, (error, /*License*/license) => {

			if (error)
			{
				if (!connection.isTestConnection())
				{
					logger.error(
						"Registration failed.",
						license && license.siteUrl,
						license && license.id,
						error,
						connection.getIp()
					);
				}

				const response = Response.create({
					json: JSON.stringify({
						status: "error",
						error: typeof(error) === "string" ? error : error.message
					})
				});

				connection.send(response);
			}
			else
			{
				const response = Response.create({
					json: JSON.stringify({
						status: "success",
						securityKey: license.securityKey
					})
				});

				connection.send(response);
			}
		});
	}

	unregister(clientId, host, connection)
	{
		License.unregister(clientId, host, (error, license) => {

			if (error)
			{
				if (!connection.isTestConnection())
				{
					logger.error("Unregistration failed.", error, connection.getIp());
				}

				const response = Response.create({
					json: JSON.stringify({
						result: null,
						error: typeof(error) === "string" ? error : error.message
					})
				});

				connection.send(response);
			}
			else
			{
				const response = Response.create({
					json: JSON.stringify({
						result: {
							status: "success"
						},
						error: null
					})
				});

				connection.send(response);
			}
		});
	}

	/**
	 *
	 * @param jsonRpc
	 * @param connection
	 */
	handleSystemCommand(jsonRpc, connection)
	{
		if (jsonRpc.method === "unregister")
		{
			this.unregister(jsonRpc.params.clientId, jsonRpc.params.host, connection);
		}
		else
		{
			connection.close(4035, "Unknown command.");
		}
	}

	/**
	 *
	 * @param {Buffer} data
	 * @param {Connection} connection
	 * @param trusted
	 */
	processClientRequest(data, connection, trusted = false)
	{
		trusted = trusted === true;

		if (!trusted && !connection.getPubChannelId())
		{
			logger.errorConnection(
				connection,
				Connection.getStatusText(4012, "Public Channel Id is Required.")
			);

			connection.close(4012, "Public Channel Id is Required.");
			return;
		}

		const request = Application.getRequestFromBuffer(data);
		if (request === null || typeof(request.command) !== "string")
		{
			logger.errorConnection(
				connection,
				Connection.getStatusText(4013, "Wrong Request Data.")
			);

			connection.close(4013, "Wrong Request Data.");
			return;
		}

		const command = request.command;
		if (!trusted && !this.publicCommands.has(command))
		{
			logger.errorConnection(
				connection,
				Connection.getStatusText(4014, "Request command is not allowed.")
			);

			connection.close(4014, "Request command is not allowed.");
			return;
		}

		this.getStatistics().incrementRequest(command);

		const capCommand = command.charAt(0).toUpperCase() + command.slice(1);
		const commandAction = "get" + capCommand + "Action";
		if (this[commandAction])
		{
			this[commandAction](request, connection, trusted);
		}
		else
		{
			logger.errorConnection(
				connection,
				Connection.getStatusText(4015, "Wrong Request Command.")
			);

			connection.close(4015, "Wrong Request Command.");
		}
	}

	/**
	 *
	 * @param {Request} request
	 * @param {Connection} connection
	 * @param trusted
	 */
	getIncomingMessagesAction(request, connection, trusted)
	{
		const messages = request.incomingMessages && request.incomingMessages.messages;

		const [errorCode, errorText] = this.validateMessages(messages, trusted, connection);
		if (errorCode)
		{
			logger.errorConnection(connection, errorText);
			connection.close(errorCode, errorText);
			return;
		}

		if (connection.getType() === "httprequest")
		{
			connection.close(200);
		}

		messages.forEach(incomingMessage => {

			if (trusted)
			{
				incomingMessage.sender = new Sender();
				incomingMessage.sender.type = SenderType.BACKEND;
			}
			else
			{
				incomingMessage.sender = new Sender();
				incomingMessage.sender.id = connection.getPubChannelId();
				incomingMessage.sender.type = SenderType.CLIENT;
			}

			this.publish(incomingMessage, connection);
		});
	}

	validateMessages(messages, trusted, connection)
	{
		if (!Array.isArray(messages) || messages.length > this.getOptions().limits.maxMessagesPerRequest)
		{
			return [4016, "Request exceeded the maximum number of messages."];
		}

		for (let i = 0, l = messages.length; i < l; i++)
		{
			const result = this.validateChannels(messages[i].receivers, trusted, connection);
			if (result.length)
			{
				return result;
			}
		}

		return [];
	}

	/**
	 *
	 * @param channels
	 * @param {boolean} trusted
	 * @param {Connection} connection
	 * @returns {*[]|Array}
	 */
	validateChannels(channels, trusted, connection)
	{
		if (!Array.isArray(channels) || channels.length === 0)
		{
			return [4017, "No channels found."];
		}
		else if (channels.length > this.getOptions().limits.maxChannelsPerRequest)
		{
			return [4018, "Request exceeded the maximum number of channels."];
		}

		for (let i = 0, l = channels.length; i < l; i++)
		{
			const channel = channels[i];
			if (trusted)
			{
				if (!Channel.isValid(channel.id))
				{
					return [4019, "Request has an invalid channel id."];
				}
			}
			else
			{
				if (channel.isPrivate)
				{
					return [4020, "Private channel is not allowed."];
				}

				if (
					!Signature.isValid(
						"public:" + channel.id.toString("hex"),
						channel.signature,
						connection.getLicense()
					)
				)
				{
					return [4021, "Channel has an invalid signature."];
				}
			}
		}

		return [];
	}

	/**
	 *
	 * @param {Request} request
	 * @param {Connection} connection
	 * @param trusted
	 */
	getChannelStatsAction(request, connection, trusted)
	{
		if (request.channelStats && request.channelStats.channels)
		{
			this.getChannelStats(request.channelStats.channels, connection, trusted);
		}
		else
		{
			logger.errorConnection(
				connection,
				Connection.getStatusText(4030, "Wrong ChannelStats Request.")
			);

			connection.close(4030, "Wrong ChannelStats Request.");
		}
	}

	getServerStatsAction(request, connection, trusted)
	{
		if (trusted)
		{
			this.getServerStats(connection);
		}
		else
		{
			logger.errorConnection(
				connection,
				Connection.getStatusText(4031, "Wrong ServerStats Request.")
			);

			connection.close(4031, "Wrong ServerStats Request.");
		}
	}

	static getRequestFromBuffer(buffer)
	{
		if (!Buffer.isBuffer(buffer))
		{
			return null;
		}

		let requestBatch = null;
		try
		{
			requestBatch = RequestBatch.decode(buffer);
		}
		catch (exception)
		{
			return null;
		}

		if (!Array.isArray(requestBatch.requests) || requestBatch.requests.length < 1)
		{
			return null;
		}

		return requestBatch.requests[0];
	}

	/**
	 *
	 * @param {ChannelId[]} channels
	 * @param {Connection} connection
	 * @param {boolean} trusted
	 */
	getChannelStats(channels, connection, trusted)
	{
		const [errorCode, errorText] = this.validateChannels(channels, trusted, connection);
		if (errorCode)
		{
			logger.errorConnection(connection, errorText);
			connection.close(errorCode, errorText);
			return;
		}

		const startDate = logger.profileStart(connection);

		this.adapter.getChannelStats(channels, (error, channels) => {
			if (error)
			{
				logger.systemError("Adapter: Get Online Error: " + error, connection.getIp());
				channels = [];
			}

			logger.profileEnd(connection, startDate, "[CHANNELS-STATS]", channels.length);

			const response = Response.create({
				channelStats: {
					channels
				}
			});

			connection.send(response);

		});
	}

	getServerStats(connection)
	{
		const startDate = logger.profileStart(connection);

		this.adapter.getServerStats((error, stats) => {
			if (error)
			{
				logger.systemError("Adapter: Get Stat Error: " + error, connection.getIp());
			}

			if (!Array.isArray(stats))
			{
				stats = [stats];
			}

			logger.profileEnd(connection, startDate, "[SERVER-STATS]");

			const response = Response.create({
				serverStats: {
					json: JSON.stringify(stats)
				}
			});

			connection.send(response);
		});
	}
}

module.exports = new Application(config);
