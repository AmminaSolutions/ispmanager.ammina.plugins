const http = require("http");
const https = require("https");
const fs = require("fs");
const util = require("util");
const path = require("path");

const app = require("./lib/application");

const config = require("./config");
const logger = require("./lib/debug");

const Routing = require("./lib/routing");
const License = require("./lib/license");

const WebSocket = require("ws");

config.servers.forEach(serverConfig => {
	const routing = new Routing(app, serverConfig);
	let server;
	if (serverConfig.ssl)
	{
		server = https.createServer({
			key: fs.readFileSync(path.resolve(__dirname, serverConfig.ssl.key)),
			cert: fs.readFileSync(path.resolve(__dirname, serverConfig.ssl.cert)),
			ciphers: serverConfig.ssl.ciphers,
			dhparam: serverConfig.ssl.dhparam,
			honorCipherOrder: serverConfig.ssl.honorCipherOrder
		});

		server.on("secureConnection", logger.initTLSSocket);
	}
	else
	{
		server = http.createServer();
	}

	server.listen({
		port: serverConfig.port,
		hostname: serverConfig.hostname,
		backlog: serverConfig.backlog,
		path: serverConfig.path,
	});

	server.on("connection", logger.initSocket);
	server.on("request", routing.processRequest.bind(routing));
	server.on("listening", () => {
		logger.info("%s listening at %s://%s:%s PID: %s",
			serverConfig.name,
			serverConfig.ssl ? "https" : "http",
			server.address().address,
			server.address().port,
			process.pid
		);
	});

	if (serverConfig.routes && serverConfig.routes.sub)
	{
		const wsServer = new WebSocket.Server({
			noServer: true,
			clientTracking: false,
			perMessageDeflate: false,
			disableHixie: true,
			path: serverConfig.routes.sub,
			maxPayload: config.limits.maxPayload
		});

		wsServer.on("connection", (websocket, request, license) => {
			logger.debugWebsocket(request, websocket);
			app.subscribeWebsocket(request, websocket, license);
		});

		server.on("upgrade", async (request, socket, head) => {
			let license;
			if (config.cloudMode)
			{
				license = await License.getByRequest(request);
				if (license === null)
				{
					socket.write(`HTTP/1.1 400 ${http.STATUS_CODES[400]}\r\n\r\n4036: Wrong Client Id.`);
					socket.destroy();
					return;
				}
			}

			logger.debugHttpRequest(request);

			wsServer.handleUpgrade(request, socket, head, (websocket) => {
				wsServer.emit("connection", websocket, request, license);
			});
		});
	}
});

//Debug
process.stdin.on("data", function (data) {
	data = (data + "").trim().toLowerCase();
	if (data === "cc")
	{
		console.log(util.inspect(app.adapter.connections, { colors: true, depth: 2 }));
	}
	else if (data === "pp")
	{
		console.log(util.inspect(app.adapter.pubChannels, { colors: true, depth: 2 }));
	}
	else if (data === "heap")
	{
		var heapdump = require("heapdump");
		var file = path.resolve(__dirname, "logs/" + Date.now() + ".heapsnapshot");
		heapdump.writeSnapshot(file, function(err) {
			if (err)
			{
				console.error(err);
			}
			else
			{
				console.error("Wrote snapshot: " + file);
			}
		});
	}
	else if (data === "memory")
	{
		var memory = process.memoryUsage();
		for (var key in memory)
		{
			console.log(key, (memory[key] / 1024 / 1024) + "Mb");
		}
	}
	else if (data === "gc" && typeof(gc) !== "undefined")
	{
		gc(); //requires --expose-gc parameter
	}

	console.log("PID", process.pid);

});
