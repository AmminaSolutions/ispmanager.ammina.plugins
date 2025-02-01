var Client = require("./client");
var util = require("util");
var argv = require("minimist")(process.argv.slice(2));
var crypto = require("crypto");
var config = require("../config");

var tests = {
	"1": {
		channels: 20000,
		clients: 1,
		messages: 10
	},
	"2": {
		channels: 200,
		clients: 100,
		messages: 200
	},
	"3": {
		channels: 4,
		clients: 200,
		messages: 15000
	},
	"4": {
		channels: 4,
		clients: 1000,
		messages: 1000
	},
	"5": {
		channels: 10,
		clients: 2000,
		messages: 5000
	},
	"6": {
		channels: 400,
		clients: 50,
		messages: 5000
	}
};

var test = argv.test && tests[argv.test] ? tests[argv.test] : tests[1];
test.channels = argv.channels > 0 ? argv.channels : test.channels;
test.clients = argv.clients > 0 ? argv.clients : test.clients;
test.messages = argv.messages > 0 ? argv.messages : test.messages;

var privateId = argv.random ? getRandomInt(200000000000, 900000000000) : 100000000000;
privateId = privateId + (argv.offset || 0);

var sharedId =  900000000000;
var lastPrivateId = privateId;

var clients = [];
var activeClients = 0;
var pollingCLients = 0;
var websocketClients = 0;

var subUrl = argv.subUrl || null;
var pubUrl = argv.pubUrl || null;
var onlyPublish = pubUrl && !subUrl;

if (onlyPublish)
{
	publishMessages();
}
else if (subUrl)
{
	createConnections();
}
else
{
	console.log("You have to set --subUrl or --pubUrl params.");
	process.exit();
}

console.log("Channels:", test.channels, "Clients:", test.clients, "Messages:", test.messages);
console.log("Polling clients: %s  Websocket clients: %s", pollingCLients, websocketClients);
console.log("All Messages:", test.channels * test.messages);
console.log("Time:", new Date());
console.log("Sub server:", subUrl || "None");
console.log("Pub Server:", pubUrl || "None");
if (subUrl)
{
	console.log("Waiting for establishing connections...");
}

function createConnections()
{
	var padding = new Array(21).join("0");

	for (var channel = 0; channel < test.channels; channel++)
	{
		for (var clientId = 0; clientId < test.clients; clientId++)
		{
			var channelId = signChannel(padding + lastPrivateId) + "/" + signChannel(padding + sharedId);
			var client = new Client(subUrl + "?CHANNEL_ID=" + channelId, test.messages);

			client.once("end", onClientEnd);
			client.once("poll", onClientPoll);

			if (argv.websocket)
			{
				websocketClients++;
				client.wsocket(true);
			}
			else if (argv.polling)
			{
				pollingCLients++;
				client.poll(true);
			}
			else
			{
				if ((clients.length % 2) === 0)
				{
					pollingCLients++;
					client.poll(true);
				}
				else
				{
					websocketClients++;
					client.wsocket(true);
				}
			}
			clients.push(client);
		}

		lastPrivateId++;
	}
}

function signChannel(channelId)
{
	if (!config.security || !config.security.key || argv["skip-signature"])
	{
		return channelId;
	}

	var hmac = crypto.createHmac("sha1", config.security.key);
	hmac.update(channelId);
	return channelId + "." + hmac.digest("hex");
}

function onClientEnd()
{
	activeClients--;
	if (activeClients === 0)
	{
		checkClients();
	}
}

function onClientPoll()
{
	activeClients++;
	if (activeClients === clients.length)
	{
		if (pubUrl)
		{
			publishMessages();
		}
		else
		{
			console.log("Message publishing was skipped. Use 'ch' command to check clients.");
		}
	}
}

function publishMessages()
{
	var pubService = require("child_process").fork(__dirname + "/pub.js");
	pubService.send({
		command: "pub",
		options: {
			channels: test.channels,
			messages: test.messages,
			clients: test.clients,
			privateKeys: {
				from: privateId,
				to: privateId + test.channels
			},
			serverUrl: pubUrl
		}
	});

	pubService.on("message", function(message) {
		if (message.command === "end")
		{
			console.log("Waiting for closing long polling request...");
			if (onlyPublish)
			{
				process.exit();
			}
			else
			{
				console.log("Active Clients:", activeClients);
				setInterval(function() {
					console.log("Active Clients:", activeClients);
				}, 5000);
			}
		}
	});
}

function checkClients()
{
	console.log("Checking results...");

	var all = clients.length;
	var wrong = 0;

	for (var i = 0; i < all; i++)
	{
		var client = clients[i];
		if (client.lastMessageCounter !== test.messages)
		{
			wrong++;
			console.log("Error: last message counter is", client.lastMessageCounter, "expected", test.messages, client.url);
		}
	}

	console.log("Checking completed. All clients: %s Wrong clients: %s", all, wrong);
	process.exit(wrong);
}

function getRandomInt(min, max)
{
	return Math.floor(Math.random() * (max - min + 1)) + min
}

process.on("uncaughtException", function(error) {
    console.log("uncaughtException", error);
});

process.stdin.on("data", function (data) {
	data = (data + "").trim().toLowerCase();

	switch(data)
	{
		case "aa":
			console.log("Active Clients", activeClients);
			break;
		case "ch":
			checkClients();
			break;
		case "cc":
			console.log(util.inspect(clients, { colors: true, depth: 2 }));
			break;
		case "wr":
			for (var i = 0; i < clients.length; i++)
			{
				if (clients[i].finished === false)
				{
					console.log(clients[i], { colors: true, depth: 2 });
				}
			}
			break;
	}

});

process.on("exit", function(code) {
	console.log("About to exit with code:", code);
});