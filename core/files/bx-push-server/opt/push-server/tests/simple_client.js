//Keep Alive Agent

//http.request method

//var http = require("http");
//var url = require("url");
//
//var keepAliveAgent = new http.Agent({
//	keepAlive: true,
//	keepAliveMsecs: 60000 * 2,
//	maxFreeSockets: 100000
//});
//
//var argv = process.argv.slice(2);
//if (argv.length > 0)
//{
//	var options = url.parse(argv[0]);
//	options.agent = keepAliveAgent;
//	makeRequest(options);
//}
//
//function makeRequest(options) {
//	var req = http.get(options, function (res) {
//
//		res.on("data", function (chunk) {
//			console.log('BODY1: %d', chunk.length);
//		});
//
//		res.on("end", function () {
//			setTimeout(function () {
//				makeRequest(options);
//			}, 200);
//		});
//	});
//
//	req.on("error", function (e) {
//		console.log("Got error: " + e.message);
//	});
//}
//=================================================


//http.request + npm/agentkeepalive method
//var http = require("http");
//var url = require("url");
//var Agent = require("agentkeepalive");
//
//var keepAliveAgent = new Agent({
//	keepAlive: true,
//	keepAliveMsecs: 60000 * 2,
//	maxFreeSockets: 100000
//});
//
//var argv = process.argv.slice(2);
//if (argv.length > 0)
//{
//	var options = url.parse(argv[0]);
//	options.agent = keepAliveAgent;
//	makeRequest(options);
//}
//
//function makeRequest(options) {
//	var req = http.get(options, function (res) {
//
//		res.on("data", function (chunk) {
//			console.log('BODY1: %d', chunk.length);
//		});
//
//		res.on("end", function () {
//			process.nextTick(function () {
//				makeRequest(options);
//			});
//		});
//	});
//
//	req.on("error", function (e) {
//		console.log("Got error: " + e.message);
//	});
//}
//=======================================================

// npm/request method
//var http = require("http");
//var url = require("url");
//
//var request = require("request");
//request = request.defaults({
//	agentClass: http.Agent,
//	agentOptions: {
//		keepAlive: true,
//		keepAliveMsecs: 60000 * 2,
//		maxFreeSockets: 100000
//	},
//	timeout: 60000
//});
//
//var argv = process.argv.slice(2);
//if (argv.length > 0)
//{
//	var options = {
//		uri : url.parse(argv[0])
//	};
//	makeRequest(options);
//}
//
//function makeRequest(options)
//{
//	request(options, function (error, response, body) {
//
//		if (!error)
//		{
//			console.log("Got Response:", response.statusCode);
//		}
//		else
//		{
//			console.log("Error:", error);
//		}
//
//		setTimeout(function() {
//			makeRequest(options);
//		}, 200);
//	});
//}
//=================================================

//Websocket
//var url = require("url");
//var WebSocket = require("ws");
//
//var argv = process.argv.slice(2);
//if (argv.length > 0)
//{
//	makeRequest(argv[0]);
//}
//
//function makeRequest(url)
//{
//	console.log(url);
//	var ws = new WebSocket(url, {});
//
//	ws.on("open", function() {
//		console.log("open", url);
//	});
//
//	ws.on("close", function(code, reason) {
//
//		console.log("closed", code, reason);
//		makeRequest(url);
//	});
//
//	ws.on("error", function error(code, description) {
//		console.log("error: " + code + (description ? " " + description : ""));
//		makeRequest(url);
//	});
//
//	ws.on("message", function message(data, flags) {
//		console.log("message", data, flags);
//	});
//}



var Client = require("./client");
var wsClient = new Client("http://127.0.0.1:1337/sub/?CHANNEL_ID=00000000000000000000100000000000", 10);
wsClient.wsocket();
wsClient.on("poll", function() {
	console.log("open");
});

setInterval(function() {
	console.log(wsClient);
}, 5000);




