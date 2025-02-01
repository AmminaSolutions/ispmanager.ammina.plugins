const { EventEmitter } = require("events");
const WebSocket = require("ws");
const { RequestBatch, Request, ResponseBatch } = require("../../lib/models");
const Signature = require("../../lib/signature");
const crypto = require("crypto");
const config = require("../../config");
const argv = require("minimist")(process.argv.slice(2));

const urls = config.test ? config.test : {};

const subUrl = urls.subUrl || argv.subUrl || "http://localhost:1337/sub/";
const securityKey = config.security && config.security.key ? config.security.key : crypto.randomBytes(64).toString("hex");

let trustClients = config.trustClients;
if (argv.trustClients === "true")
{
	trustClients = true;
}
else if (argv.trustClients === "false")
{
	trustClients = false;
}

class Client extends EventEmitter
{
	constructor(id, options)
	{
		super();

		options = options || {};

		this.responses = new Set();

		this.id = id;
		this.privateId = crypto.createHash("md5").update(this.id.toString()).digest("hex");
		this.publicId = crypto.createHash("md5").update(this.privateId).digest("hex");

		this.serverUrl = options.serverUrl ? options.serverUrl : subUrl;
		this.binaryMode = options.binaryMode !== false;

		this.channelId = this.privateId + (this.publicId ? ":" + this.publicId : "");

		this.clientId = options.clientId ? options.clientId : (trustClients ? null : "fd818684484258a5c6f0442a070661d6");
		this.signature = Signature.getDigest(this.channelId, securityKey).toString("hex");

		const testConnectionKey =
			config.debug && config.debug.testConnectionKey ? config.debug.testConnectionKey : ""
		;

		this.url =
			this.serverUrl +
			"?CHANNEL_ID=" + this.channelId + "." + this.signature +
			"&binaryMode=" + this.binaryMode +
			"&testKey=" + testConnectionKey +
			(this.clientId ? "&clientId=" + this.clientId : "")
		;
	}

	connect(params)
	{
		const url = this.url.replace('http:', 'ws:').replace('https:', 'wss:')
		this.websocket = new WebSocket(
			url + (params ? "&" + params : ""),
			{
				rejectUnauthorized: false,
				handshakeTimeout: 3500
			}
		);

		if (this.binaryMode)
		{
			this.websocket.binaryType = "arraybuffer";
		}

		this.websocket.on("open", this.handleOpen.bind(this));
		this.websocket.on("close", this.handleClose.bind(this));
		this.websocket.on("error", this.handleError.bind(this));
		this.websocket.on("message", this.handleMessage.bind(this));
		this.websocket.on("ping", this.handlePing.bind(this));
		this.websocket.on("unexpected-response", this.handleUnexpectedResponse.bind(this));
	}

	disconnect()
	{
		this.websocket.close(1000);
	}

	/**
	 *
	 * @param {Request} request
	 */
	send(request)
	{
		const batch = new RequestBatch();
		batch.requests.push(request);

		this.websocket.send(
			RequestBatch.encode(batch).finish(),
			() => {} //to avoid a possible exception
		);
	}

	getWebsocket()
	{
		return this.websocket;
	}

	getChannelId()
	{
		return this.channelId;
	}

	getSignature()
	{
		return this.signature;
	}

	getPublicId()
	{
		return this.publicId;
	}

	getHexPublicId()
	{
		return Buffer.from(this.publicId, "hex");
	}

	getPrivateId()
	{
		return this.privateId;
	}

	getHexPrivateId()
	{
		return Buffer.from(this.privateId, "hex");
	}

	handleOpen()
	{
		this.emit("connection");
	}

	handleClose(code, reason)
	{
		this.emit("close", code, reason);
	}

	handleError(code, description)
	{
		this.emit("error", new Error(description));
	}

	handleUnexpectedResponse(request, response)
	{
		this.emit("unexpected-response", request, response);
	}

	handlePing()
	{
		this.emit("ping");
	}

	handleMessage(buffer, flags)
	{
		const responseBatch = ResponseBatch.decode(new Uint8Array(buffer));
		responseBatch.responses.forEach((response) => {
			this.responses.add(response);
			if (response.outgoingMessages)
			{
				response.outgoingMessages.messages.forEach((message) => {
					this.emit("message", message);
				});
			}
			else if (response.channelStats)
			{
				this.emit("message", response.channelStats.channels);
			}
		});

		this.emit("response", responseBatch.responses);
	}
}

module.exports = Client;
