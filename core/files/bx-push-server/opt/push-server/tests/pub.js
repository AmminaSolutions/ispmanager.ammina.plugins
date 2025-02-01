var http = require("http");
var request = require("request");
request = request.defaults({
	agentOptions: {
		keepAlive: true,
		keepAliveMsecs: 60000 * 2,
		maxFreeSockets: 100000,
		rejectUnauthorized: false
	},
	timeout: 60000
});

var postMessages = 0;
var startTime = 0;

function Channel(id, options)
{
	this.options = options || {};
	this.id = id;
	this.data = 1;
	this.maxValue = this.options.messages;
}

Channel.prototype.publish = function(callback)
{
	if (this.data > this.maxValue)
	{
		callback(true);
		return;
	}

	request(
		{
			method: "POST",
			uri: this.options.serverUrl + "?CHANNEL_ID=" + this.id,
			body: this.data.toString(),
			headers: {
				"message-expiry": 3600 * 24
			}
		},
		function (error, response, body) {

			if ((postMessages % 500) === 0)
			{
				console.log("pub", postMessages, new Date());
			}

			if (error)
			{
				console.error("Publishing failed:", postMessages, error);
			}
			else if (response.statusCode !== 200)
			{
				console.error("Publishing failed:", postMessages, response.statusCode);
			}
			else
			{
				postMessages++;
			}

			callback();

		}.bind(this));

	this.data++;
};

function Generator(options)
{
	this.options = options;
	this.channels = [];

	for (var channelId = this.options.privateKeys.from; channelId < this.options.privateKeys.to; channelId++)
	{
		this.channels.push(new Channel("00000000000000000000" + channelId, this.options));
	}
}

Generator.prototype.run = function()
{
	var len = this.channels.length;
	if (len < 1)
	{
		var time = new Date().getTime() - startTime;
		console.log("pub", postMessages, new Date());
		console.log("Message generation was completed. %s in %s seconds ", this.options.channels * this.options.messages, Math.ceil(time / 1000));
		process.send({ command: "end" });
		return;
	}

	var index = Math.floor(Math.random() * (len - 1));
	var channel = this.channels[index];

	channel.publish(function(completed) {
		if (completed === true)
		{
			this.channels.splice(index, 1);
		}

		this.run();
		//setTimeout(function() { this.run(); }.bind(this), 20);

	}.bind(this));

};

process.once("message", function(message) {
	if (message.command === "pub")
	{
		var generator = new Generator(message.options);

		startTime = new Date().getTime();
		console.log("Starting publishing...", new Date());
		generator.run();
	}
});

