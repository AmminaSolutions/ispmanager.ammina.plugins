var http = require("http");
var util = require("util");
var request = require("request");
var EventEmitter = require("events").EventEmitter;
var WebSocket = require("ws");

function Client(url, maxCounter)
{
	this.url = url;
	this.maxCounter = maxCounter;
	this.lastMessageId = null;
	this.lastMessageCounter = 0;
	this.history = [];
	this.finished = false;
	this.responses = 0;
	this.reconnects = 0;
	this.tlsSession = null;
	this.closed = null;
	this.reason = null;
}

util.inherits(Client, EventEmitter);
module.exports = Client;

Client.prototype.request = function()
{
	var url = this.url + (this.lastMessageId !== null ? "&mid=" + this.lastMessageId  : "");

	var history = { url : url, status: 0, body: null, error: null, startDate: new Date(), onRequestDate: 0, endDate: 0 };
	//this.history.push(history);
	this.reconnects++;

	var options = {
		url: url,
		agentOptions: {
			keepAlive: true,
			keepAliveMsecs: 60000 * 2,
			maxFreeSockets: 100000
		},
		timeout: 60000,
		session: this.tlsSession
	};

	if (url.match(/^https:/) && url.match(/rt[0-9]?\.bitrix24\./))
	{
		options.agentOptions.rejectUnauthorized = false;
	}

	var req = request(options, function (error, response, body) {

		history.status = response ? response.statusCode : -1;
		history.body = body;
		history.endDate = new Date();
		this.responses++;
		if (!error && (response.statusCode === 200 || response.statusCode === 304))
		{
			this.processRequest(response, body);
		}
		else
		{
			history.error = error;
			this.processError(error, response);
		}

		if (response && response.socket && typeof(response.socket.getSession) === "function")
		{
			this.tlsSession = response.socket.getSession();
		}

	}.bind(this));

	req.on("request", function() {
		history.onRequestDate = new Date();
		this.emit("poll");
	}.bind(this));
};

Client.prototype.poll = function(force)
{
	if (force === true)
	{
		this.request();
	}
	else
	{
		setTimeout(this.request.bind(this), 200);
	}
};

Client.prototype.wsocket = function()
{
	var url = this.url + (this.lastMessageId !== null ? "&mid=" + this.lastMessageId  : "");
	var ws = new WebSocket(url, { rejectUnauthorized: false });

	ws.on("open", function() {
		this.closed = false;
		this.emit("poll");
	}.bind(this));

	ws.on("close", function(code, reason) {
		this.closed = true;
		this.reason = code + "_" + reason;

		if (!this.finished)
		{
			this.reconnects++;
			this.wsocket();
		}

	}.bind(this));

	ws.on("error", function error(code, description) {
		console.log("websocket error: " + code + (description ? " " + description : ""));
		this.reconnects++;
		this.wsocket();
	}.bind(this));

	ws.on("message", function message(data, flags) {

		this.responses++;
		this.checkMessages(data);
		if (this.lastMessageCounter === this.maxCounter)
		{
			this.finished = true;
			ws.close(1000, "all done." + this.lastMessageCounter);
			this.emit("end");
		}

	}.bind(this));
};

Client.prototype.processRequest = function(response, body)
{
	if (response.statusCode !== 304)
	{
		this.checkMessages(body);
	}
	else
	{
		var lastMessageId = response.headers["last-message-id"];
		if (lastMessageId && lastMessageId.length > 0)
		{
			this.lastMessageId = lastMessageId;
		}
		else if (this.lastMessageId === null)
		{
			this.lastMessageId = "00000000000000000000000000";
		}
	}

	if (this.lastMessageCounter === this.maxCounter)
	{
		this.finished = true;
		this.emit("end");
	}
	else
	{
		this.poll();
	}
};

Client.prototype.processError = function(error, response)
{
	console.log("Polling Error:", error, (response && response.statusCode ? response.statusCode : ""), this.url);

	this.poll();
};

Client.prototype.checkMessages = function(body)
{
	var messages = body.match(/#!NGINXNMS!#(.*?)#!NGINXNME!#/gm);
	if (messages === null)
	{
		console.log("Error: empty response", body);
		return;
	}

	for (var i = 0; i < messages.length; i++)
	{
		var message = (new Function("return " + messages[i].substring(12, messages[i].length - 12)))();

		if (this.lastMessageId < message.mid)
		{
			this.lastMessageId = message.mid;
		}
		else
		{
			console.log("Error: Wrong message Id:", this.lastMessageId, ">=", message.mid);
		}

		var counter = this.checkMessageText(parseInt(message.text));
		if (counter !== null)
		{
			this.lastMessageCounter = counter;
		}
	}

	//console.log("message", this.lastMessageCounter);
};

Client.prototype.checkMessageText = function(counter)
{
	if (isNaN(counter))
	{
		console.log("Error: wrong response", counter);
	}
	else if (this.lastMessageCounter === 0 || counter == this.lastMessageCounter + 1)
	{
		return counter;
	}
	else
	{
		if (this.lastMessageCounter < counter)
		{
			console.log("Last message counter %s less than %s.", this.lastMessageCounter, counter, this.url);
			return counter;
		}

		console.log("Error: ", counter, " expected ", this.lastMessageCounter + 1);
	}

	return null;
};