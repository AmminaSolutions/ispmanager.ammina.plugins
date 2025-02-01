const { EventEmitter } = require("events");
const Client = require("./client");
const { Request } = require("../../lib/models");

class Chat extends EventEmitter
{
	/**
	 * @param {number} numberOfClients
	 * @param {number} [startClientId=0]
	 */
	constructor(numberOfClients, startClientId)
	{
		super();

		this.clients = [];
		this.connectedClients = 0;

		this.maxMessages = 0;
		this.numberOfMessages = 0;

		startClientId = startClientId || 0;

		for (let i = 1; i <= numberOfClients; i++)
		{
			const client = new Client(i + startClientId);
			client.on("message", this.handleClientMessage.bind(this, client));
			client.on("connection", this.handleClientConnection.bind(this, client));
			client.on("error", this.handleClientError.bind(this));
			this.clients.push(client);
		}
	}

	connect()
	{
		this.getClients().forEach(client => client.connect());
	}

	disconnect()
	{
		this.getClients().forEach(client => client.disconnect());
	}

	setMaxMessages(n)
	{
		this.maxMessages = n;
	}

	getMaxMessages()
	{
		return this.maxMessages;
	}

	handleClientMessage(client)
	{
		this.numberOfMessages++;
		if (this.numberOfMessages === this.getMaxMessages())
		{
			this.emit("ready");
		}
	}

	handleClientConnection(client)
	{
		this.connectedClients++;
		if (this.connectedClients === this.clients.length)
		{
			this.emit("connection");
		}
	}

	handleClientError(error)
	{
		this.emit("onerror", error);
	}

	/**
	 *
	 * @return {Client[]}
	 */
	getClients()
	{
		return this.clients;
	}
}

module.exports = Chat;