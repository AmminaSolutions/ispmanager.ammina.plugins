const Router = require("./router");
const logger = require("../lib/debug");
const Signature = require("../lib/signature");
const License = require("../lib/license");
const HttpRequest = require("../lib/transports/httprequest");
const { ChannelId, IncomingMessage, SenderType, RegisterRequest } = require("../lib/models");
const config = require("../config");
const queryString = require("querystring");
const testConnectionKey = config.debug && config.debug.testConnectionKey ? config.debug.testConnectionKey : null;

class Routing
{
	/**
	 *
	 * @param {Application} application
	 * @param {ServerConfig} serverConfig
	 */
	constructor(application, serverConfig)
	{
		this.router = new Router();
		this.application = application;
		this.maxPayload = config.limits.maxPayload;

		const routes = serverConfig.routes || {};

		if (routes.pub)
		{
			this.router.post(routes.pub, this.processPublishRequest.bind(this));
			this.router.get(routes.pub, this.processChannelStatsRequest.bind(this));
		}

		//Long Polling
		if (routes.sub)
		{
			this.router.get(routes.sub, (request, response) => {
				application.subscribePolling(request, response);
			});

			this.router.options(routes.sub, (request, response) => {
				response.writeHead(200, {
					"Content-Type": "text/plain",
					"Access-Control-Allow-Origin": "*",
					"Access-Control-Allow-Methods": "POST, GET, DELETE, OPTIONS",
					"Access-Control-Allow-Headers": "If-Modified-Since, If-None-Match"
				});
				response.end();
			});
		}

		//Brand New Command System
		if (routes.rest)
		{
			this.router.post(routes.rest, this.processRestRequest.bind(this));
		}

		//Server Stats
		if (routes.stat)
		{
			this.router.get(routes.stat, (request, response) => {
				const connection = new HttpRequest(request, response);
				application.getServerStats(connection);

				this.getApplication().getStatistics().incrementRequest("serverStats");
			});
		}

		if (routes.register && config.licenseServer && config.cloudMode)
		{
			this.router.post(routes.register, this.processRegisterRequest.bind(this));
		}

		if (routes.systemctl)
		{
			this.router.post(routes.systemctl, this.processSystemRequest.bind(this));
		}
	}

	/**
	 *
	 * @return {Application}
	 */
	getApplication()
	{
		return this.application;
	}

	processPublishRequest(request, response)
	{
		Routing.processBody(request, response, this.maxPayload, async (requestBody) => {
			let license = null;
			if (config.cloudMode)
			{
				license = await License.getByRequest(request);
			}

			const connection = new HttpRequest(request, response, false, license);
			if (config.cloudMode && !this.validateRequestSignature(requestBody, connection))
			{
				return;
			}

			if (connection.isBinaryMode())
			{
				this.getApplication().processClientRequest(requestBody, connection, true);
			}
			else
			{
				connection.close(200);

				if (!requestBody || requestBody.length < 1)
				{
					return;
				}

				let expiry = request.headers["message-expiry"] && parseInt(request.headers["message-expiry"], 10);
				expiry = (expiry && !isNaN(expiry) && expiry > 0) ? expiry : 0;

				const incomingMessage = IncomingMessage.create({
					receivers: connection.getReceivers(),
					sender: {
						type: SenderType.BACKEND
					},
					body: requestBody.toString(),
					expiry
				});

				this.getApplication().publish(incomingMessage, connection);
			}
		});
	}

	async processChannelStatsRequest(request, response)
	{
		let license = null;
		if (config.cloudMode)
		{
			license = await License.getByRequest(request);
		}

		const connection = new HttpRequest(request, response, false, license);
		if (config.cloudMode)
		{
			if (!this.validateRequestSignature(connection.getUrlSearchParams().get("CHANNEL_ID"), connection))
			{
				return;
			}
		}

		const channels = [];
		connection.getChannels().forEach(channel => {
			channels.push(new ChannelId({
				id: channel.getPrivateId(),
				isPrivate: true
			}));
		});

		this.getApplication().getStatistics().incrementRequest("channelStats");

		this.getApplication().getChannelStats(channels, connection, true);
	}

	processRestRequest(request, response)
	{
		Routing.processBody(request, response, this.maxPayload, async (requestBody) => {
			let license = null;
			if (config.cloudMode)
			{
				license = await License.getByRequest(request);
			}

			const connection = new HttpRequest(request, response, true, license);
			connection.setBinaryMode(true);
			this.getApplication().processClientRequest(requestBody, connection, false);
		});
	}

	processRegisterRequest(request, response)
	{
		Routing.processBody(request, response, 1024, requestBody => {

			const connection = new HttpRequest(request, response);
			if (!requestBody || requestBody.length < 1)
			{
				connection.close(4022, "Empty request.");
				return;
			}

			const params = queryString.parse(requestBody.toString());

			if (typeof(params.verificationQuery) !== "string" || params.verificationQuery.length < 1)
			{
				connection.close(4024, "A verification query is required.");
				return;
			}

			const url = new URL(request.url, `https://${request.headers.host}`);
			const host = url.hostname;

			const registerRequest = RegisterRequest.create({
				verificationQuery: params.verificationQuery,
				host: host
			});

			this.getApplication().register(registerRequest, connection);
		});
	}

	processSystemRequest(request, response)
	{
		Routing.processBody(request, response, this.maxPayload, requestBody => {

			const connection = new HttpRequest(request, response);
			if (!requestBody || requestBody.length < 1)
			{
				connection.close(4022, "Empty request.");
				return;
			}

			if (!Signature.isValid(requestBody, connection.getSignature()))
			{
				connection.close(4026, "Wrong request signature.");
				return;
			}

			let jsonRpc = null;
			try
			{
				jsonRpc = JSON.parse(requestBody);
			}
			catch (e)
			{
				connection.close(4032, "Bad JSON request.");
				return;
			}

			if (!jsonRpc || typeof(jsonRpc) !== "object")
			{
				connection.close(4032, "Bad JSON request.");
				return;
			}

			if (typeof(jsonRpc.method) !== "string")
			{
				connection.close(4033, "JSON object doesn't have 'method' property.");
				return;
			}

			if (!jsonRpc.params || typeof(jsonRpc.params) !== "object")
			{
				connection.close(4034, "JSON object doesn't have 'params' property.");
				return;
			}

			this.getApplication().handleSystemCommand(jsonRpc, connection);
		});
	}

	processRequest(request, response)
	{
		logger.debugHttpRequest(request, response);
		const route = this.router.process(request, response);
		if (!route)
		{
			response.writeHead(404, {
				"Content-Type": "text/plain",
				"Access-Control-Allow-Origin": "*"
			});
			response.end();
		}
	}

	validateRequestSignature(requestBody, connection)
	{
		if (connection.getLicense() === null)
		{
			connection.close(4025, "Client Id is required.");
			return false;
		}
		else if (!Signature.isValid(requestBody, connection.getSignature(), connection.getLicense()))
		{
			connection.close(4026, "Wrong request signature.");
			return false;
		}

		return true;
	}

	static processBody(request, response, maxPayload, callback)
	{
		let queryData = [];
		let bytes = 0;
		let errorOccurred = false;
		request.on("data", (data) => {

			bytes += data.length;
			queryData.push(data);

			if (bytes > maxPayload)
			{
				queryData = [];
				response.writeHead(413, {
					"Content-Type": "text/plain",
					"Access-Control-Allow-Origin": "*"
				});
				response.end();
				request.connection.destroy();

				errorOccurred = true;

				const forwarded = request.headers["x-forwarded-for"];
				const ip = forwarded ? forwarded : request.socket.remoteAddress;

				const searchQuery = request.url.split('?');
				const urlSearchParams = new URLSearchParams(typeof(searchQuery[1]) === "string" ? searchQuery[1] : "");

				if (urlSearchParams.get('testKey') !== testConnectionKey)
				{
					logger.error("HTTP Request: Max payload size exceeded.", ip);
				}
			}
		});

		request.on("end", () => {
			if (!errorOccurred)
			{
				callback(Buffer.concat(queryData));
			}
		});
	}
}

module.exports = Routing;
