var http = require("http");
var cluster = require("cluster");
var numCPUs = require("os").cpus().length;

if (cluster.isMaster)
{
	// Fork workers.
	for (var i = 0; i < numCPUs; i++) {
		cluster.fork();
	}

	cluster.on("exit", function(worker, code, signal) {
		console.log("worker " + worker.process.pid + " died");
	});
}
else
{
	var requestId = 0;
	var connections = {};
	var server = http.createServer();

	server.on("connection", function(socket) {
		socket.setNoDelay(true);
	});

	server.on("request", function(req, res) {

		if (req.method === "OPTIONS")
		{
			res.writeHead(200, {
				"Content-Type": "text/plain",
				"Access-Control-Allow-Origin": "*",
				"Access-Control-Allow-Methods": "POST, GET, DELETE, OPTIONS",
				"Access-Control-Allow-Headers": "If-Modified-Since, If-None-Match"
			});
			res.end();
			return;
		}

		requestId++;

		var connection = { req: req, res: res, id: requestId };
		connections[requestId] = connection;

		res.setTimeout(40 * 1000, function() {

			delete connections[connection.id];

			res.writeHead(304, {
				"Content-Type": "text/plain",
				"Access-Control-Allow-Origin": "*",
				"Access-Control-Allow-Headers": "If-Modified-Since,If-None-Match,Etag,Event-Id,Event-Type,Last-Event-Id",
				"Last-modified": new Date().toUTCString(),
				"Etag": "0"
			});
			res.end();

		});

		res.on("close", function() {
			delete connections[connection.id];
		});

	});

	server.listen(1337);

	server.on("listening", function() {
		console.log("Server running at http://127.0.0.1:%s", 1337);
	});

	process.stdin.on("data", function (data) {
		data = (data + "").trim().toLowerCase();

		switch(data)
		{
			case "c":
				console.log("Request Id", requestId);
				break;
			case "cc":
				server.getConnections(function(error, count) {
					console.log("Number of connections:", count);
				});
				break;
		}
	});
}