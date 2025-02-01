/* eslint-env mocha */

"use strict";

const { Request, RequestBatch, ResponseBatch, IncomingMessage, Receiver, ChannelId } = require("../../lib/models");
const Client = require("./client");
const Chat = require("./chat");
const Signature = require("../../lib/signature");
const config = require("../../config");
const argv = require("minimist")(process.argv.slice(2));
const crypto = require("crypto");

const assert = require("assert");
const baseRequest = require("request");
const request = baseRequest.defaults({ timeout: 3000 });

const urls = config.test ? config.test : {};

const subUrl = urls.subUrl || argv.subUrl || "http://localhost:1337/sub/";
const pubUrl = urls.pubUrl || argv.pubUrl || "http://localhost:1337/pub/";
const restUrl = urls.restUrl || argv.restUrl || "http://localhost:1337/rest/";
const statsUrl = urls.statsUrl || argv.statsUrl || "http://localhost:1337/server-stat/";
const registerUrl = urls.registerUrl || argv.registerUrl || "http://localhost:1337/register-client/";
const systemctlUrl = urls.systemctlUrl || argv.systemctlUrl || "http://localhost:1337/systemctl/";

let trustClients = config.trustClients;
let cloudMode = config.cloudMode;
if (argv.trustClients === "true" || argv.cloudMode === "false")
{
	trustClients = true;
	cloudMode = false;
}
else if (argv.trustClients === "false" || argv.cloudMode === "true")
{
	trustClients = false;
	cloudMode = true;
}

const clientId = trustClients ? null : "fd818684484258a5c6f0442a070661d6";
let securityKey = config.security && config.security.key ? config.security.key : crypto.randomBytes(64).toString("hex");
const testConnectionKey = config.debug && config.debug.testConnectionKey ? config.debug.testConnectionKey : "";

function get(...args)
{
	if (typeof(args[0]) === "string")
	{
		args[0] += (args[0].slice(-1) === "/" ? "?" : "") + "&testKey=" + testConnectionKey;
	}
	else if (typeof(args[0]) === "object" && typeof(args[0].uri) === "string")
	{
		args[0].uri += (args[0].uri.slice(-1) === "/" ? "?" : "") + "&testKey=" + testConnectionKey;
	}

	return request.get(...args);
}

function post(...args)
{
	if (typeof(args[0]) === "string")
	{
		args[0] += (args[0].slice(-1) === "/" ? "?" : "") + "&testKey=" + testConnectionKey;
	}
	else if (typeof(args[0]) === "object" && typeof(args[0].uri) === "string")
	{
		args[0].uri += (args[0].uri.slice(-1) === "/" ? "?" : "") + "&testKey=" + testConnectionKey;
	}

	return request.post(...args);
}

describe("Client Registration", function() {

	if (trustClients === true)
	{
		return;
	}

	this.timeout(10000);

	it("registers a new client", function(done) {
		const verificationQuery = encodeURIComponent("BX_HASH=0da651c6007c803893cc3622f4565a1c&BX_LICENCE=fd818684484258a5c6f0442a070661d6&BX_TYPE=CP");

		post(
			registerUrl,
			{
				body: `verificationQuery=${verificationQuery}`,
				timeout: 5000
			},
			function(error, response, body) {

				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 200);

					const result = JSON.parse(body);
					assert.equal(result.status, "success", result.error);
					assert.equal(result.securityKey, securityKey);

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

	it("tries to register a client with a wrong signature (WRONG_SIGN)", function(done) {

		const verificationQuery = encodeURIComponent("BX_HASH=1da651c6007c803893cc3622f4565a1c&BX_LICENCE=fd818684484258a5c6f0442a070661d6&BX_TYPE=CP");

		post(
			registerUrl,
			{
				body: `verificationQuery=${verificationQuery}`,
				timeout: 5000
			},
			function(error, response, body) {

				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 200);

					const result = JSON.parse(body);
					assert.equal(result.status, "error");
					assert.equal(result.error, "WRONG_SIGN");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

	it("tries to register a client without Client ID (WRONG_REQUEST)", function(done) {

		const verificationQuery = encodeURIComponent("BX_HASH=1da651c6007c803893cc3622f4565a1c&BX_TYPE=CP");

		post(
			registerUrl,
			{
				body: `verificationQuery=${verificationQuery}`,
				timeout: 5000
			},
			function(error, response, body) {

				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 200);

					const result = JSON.parse(body);
					assert.equal(result.status, "error");
					assert.equal(result.error, "WRONG_REQUEST");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

	it("tries to register an inactive client (LICENSE_NOT_ACTIVE)", function(done) {

		const verificationQuery = encodeURIComponent("BX_HASH=3b7ace38eafad6a088b995c37b4985e0&BX_LICENCE=5902c0119a367254b6703cce50af5ab5&BX_TYPE=CP");

		post(
			registerUrl,
			{
				body: `verificationQuery=${verificationQuery}`,
				timeout: 5000
			},
			function(error, response, body) {

				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 200);

					const result = JSON.parse(body);
					assert.equal(result.status, "error");
					assert.equal(result.error, "LICENSE_NOT_ACTIVE");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

	it("tries to register a demo client (LICENSE_DEMO)", function(done) {

		const verificationQuery = encodeURIComponent("BX_HASH=35aef9cac7bbc7910c76a8de5ca7890b&BX_LICENCE=feeeb150e924285d6dbf9de668d240cd&BX_TYPE=CP");

		post(
			registerUrl,
			{
				body: `verificationQuery=${verificationQuery}`,
				timeout: 5000
			},
			function(error, response, body) {

				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 200);

					const result = JSON.parse(body);
					assert.equal(result.status, "error");
					assert.equal(result.error, "LICENSE_DEMO");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

	it("tries to register a non-CP client (LICENSE_NOT_FOUND)", function(done) {

		const verificationQuery = encodeURIComponent("BX_HASH=01e8f3b66d982aac908fa306612dedb7&BX_LICENCE=9a9a06d9feff512b2bd78033c8fc5397&BX_TYPE=CP");

		post(
			registerUrl,
			{
				body: `verificationQuery=${verificationQuery}`,
				timout: 5000
			},
			function(error, response, body) {

				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 200);

					const result = JSON.parse(body);
					assert.equal(result.status, "error");
					assert.equal(result.error, "LICENSE_NOT_FOUND");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

	it("tries to make an empty POST request", function(done) {

		post(
			registerUrl,
			{
				body: ""
			},
			function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4022: Empty request.");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

	it("tries to make a POST request without verificationQuery", function(done) {

		post(
			registerUrl,
			{
				body: "verificationQuery="
			},
			function(error, response, body) {

				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4024: A verification query is required.");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

});

describe("Message Exchange", function() {

	this.timeout(6000);

	describe("Websocket", function() {

		let chat = null;
		let client = null;
		let hookError = null;

		beforeEach(function(done) {

			chat = new Chat(5);
			chat.connect();
			chat.once("connection", done);
			chat.once("onerror", function(error) {
				//done(error); - skip all the tests
				hookError = error; // manual failing
				done(); // doesn't skip the tests
			});
		});

		afterEach(function() {
			chat.disconnect();
			chat = null;

			if (client)
			{
				client.disconnect();
			}
		});

		it("sends one-to-one messages", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			chat.setMaxMessages(5 * 5);
			chat.getClients().forEach((sender, index) => {
				chat.getClients().forEach((receiver) => {
					const request = createMessageRequest(createMessage(index + 1, receiver.getPublicId()));
					sender.send(request);
				});
			});

			chat.once("ready", () => {
				try
				{
					verifyChatResult(chat, "12345");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("sends one-to-one messages (delayed)", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			chat.setMaxMessages(5 * 5);
			chat.getClients().forEach((sender, index) => {
				chat.getClients().forEach((receiver) => {

					const request = createMessageRequest(createMessage(index + 1, receiver.getPublicId()));
					setTimeout(() => {
						sender.send(request);
					}, index * 200);

				});
			});

			chat.once("ready", () => {
				try
				{
					verifyChatResult(chat, "12345");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("sends one message with many public channels", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			chat.setMaxMessages(5);
			const channels = chat.getClients().map(client => client.getPublicId());
			chat.getClients()[0].send(createMessageRequest(createMessage("12345", channels)));

			chat.once("ready", () => {
				try
				{
					verifyChatResult(chat, "12345");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("sends one request with all messages", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			chat.setMaxMessages(5 * 5);
			chat.getClients().forEach((sender, index) => {

				const messages = [];

				chat.getClients().forEach((receiver) => {
					messages.push(createMessage(index + 1, receiver.getPublicId()));
				});

				sender.send(createMessageRequest(messages));
			});

			chat.once("ready", () => {
				try
				{
					verifyChatResult(chat, "12345");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

	});

	describe("POST /pub/", function() {

		let chat = null;
		let hookError = null;

		beforeEach(function(done) {
			chat = new Chat(15);
			chat.connect();
			chat.once("connection", done);
			chat.once("onerror", function(error) {
				//done(error); - skip all the tests
				hookError = error; // manual failing
				done(); // doesn't skip the tests
			});
		});

		afterEach(function() {
			chat.disconnect();
			chat = null;
		});

		it("sends one-to-one messages (plain text)", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			chat.setMaxMessages(15 * 15);

			let isDone = false;

			chat.getClients().forEach((sender, index) => {
				chat.getClients().forEach((receiver) => {

					const body = (index + 1).toString();
					const signature = Signature.getDigest(body, securityKey).toString("hex");

					post(
						pubUrl + "?CHANNEL_ID=" + receiver.getPrivateId() +
							(trustClients ? "" : "&clientId=" + clientId + "&signature=" + signature),
						{ body },
						function (error, response, body) {
							if (isDone)
							{
								return;
							}

							if (error)
							{
								isDone = true;
								done(error);
							}
						}
					);
				});
			});

			chat.once("ready", () => {
				try
				{
					verifyChatResult(chat, "123456789101112131415");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("sends one message to many channels (plain text)", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			chat.setMaxMessages(15);

			const channels = chat.getClients().map(client => client.getPrivateId());

			const body = "message";
			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(
				pubUrl +
					"?CHANNEL_ID=" + channels.join("/") +
					(trustClients ? "" : "&clientId=" + clientId + "&signature=" + signature),
				{ body: "message"},
				function (error, response, body) {
					if (error)
					{
						done(error);
					}
				}
			);

			chat.once("ready", () => {

				try
				{
					verifyChatResult(chat, "message");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("sends one-to-one messages (binary)", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			chat.setMaxMessages(15 * 15);

			let isDone = false;
			chat.getClients().forEach((sender, index) => {
				chat.getClients().forEach((receiver) => {

					const batch = createBatch(
						createMessageRequest(createMessage(index + 1, receiver.getPublicId()))
					);

					const body = RequestBatch.encode(batch).finish();
					const signature = Signature.getDigest(body, securityKey).toString("hex");

					setTimeout(() => {
						post(
							pubUrl +
								"?CHANNEL_ID=" + sender.getPrivateId() + "&binaryMode=true" +
								(trustClients ? "" : "&clientId=" + clientId + "&signature=" + signature),
							{ body },
							function (error, response, body) {
								if (isDone)
								{
									return;
								}

								if (error)
								{
									isDone = true;
									done(error);
								}
							}
						);
					}, index * 100);

				});
			});

			chat.once("ready", () => {
				try
				{
					verifyChatResult(chat, "123456789101112131415");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("sends one message to many channels (binary)", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			chat.setMaxMessages(15);

			const channels = chat.getClients().map(client => client.getPrivateId());
			const batch = createBatch(createMessageRequest(createMessage("message", null, channels)));

			const body = RequestBatch.encode(batch).finish();
			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(
				pubUrl +
					"?binaryMode=true" +
					(trustClients ? "" : "&clientId=" + clientId + "&signature=" + signature),
				{ body },
				function (error, response, body) {
					if (error)
					{
						done(error);
					}
				}
			);

			chat.once("ready", () => {
				try
				{
					verifyChatResult(chat, "message");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});
	});

	describe("REST", function() {

		let chat = null;
		let hookError = null;

		beforeEach(function(done) {
			chat = new Chat(9);
			chat.connect();
			chat.once("connection", done);
			chat.once("onerror", function(error) {
				//done(error); - skip all the tests
				hookError = error; // manual failing
				done(); // doesn't skip the tests
			});
		});

		afterEach(function() {
			chat.disconnect();
			chat = null;
		});

		it("sends one-to-one messages", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			chat.setMaxMessages(9 * 9);
			let isDone = false;

			chat.getClients().forEach((sender, senderIndex) => {
				chat.getClients().forEach((receiver, receiverIndex) => {

					const batch = createBatch(
						createMessageRequest(createMessage(senderIndex + 1, receiver.getPublicId()))
					);

					setTimeout(() => {
						post(
							restUrl +
								"?CHANNEL_ID=" + sender.getChannelId() + "." + sender.getSignature() +
								(trustClients ? "" : "&clientId=" + clientId),
							{
								body: RequestBatch.encode(batch).finish()
							},
							function (error, response, body) {

								if (isDone)
								{
									return;
								}

								if (error)
								{
									isDone = true;
									done(error);
								}
							}
						);
					}, senderIndex * 100);

				});
			});

			chat.once("ready", () => {
				try
				{
					verifyChatResult(chat, "123456789");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});

		});

		it("sends one message to many channels (binary)", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			chat.setMaxMessages(9);

			const channels = chat.getClients().map(client => client.getPublicId());
			const batch = createBatch(createMessageRequest(createMessage("rest message", channels)));

			const sender = chat.getClients()[0];

			post(
				restUrl +
						"?CHANNEL_ID=" + sender.getChannelId() + "." + sender.getSignature() +
						(trustClients ? "" : "&clientId=" + clientId),
				{ body: RequestBatch.encode(batch).finish() },
				function (error, response, body) {
					if (error)
					{
						done(error);
					}
				}
			);

			chat.once("ready", () => {
				try
				{
					verifyChatResult(chat, "rest message");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});
	});
});

describe("Channel Stats", function() {

	this.timeout(6000);

	let chat = null;
	let hookError = null;

	beforeEach(function(done) {

		chat = new Chat(5);
		chat.connect();
		chat.once("connection", done);

		//setTimeout(done, 2500);
		chat.once("onerror", function(error) {
			//done(error); - skip all the tests
			hookError = error; // manual failing
			done(); // doesn't skip the tests
		});
	});

	afterEach(function() {
		chat.disconnect();
		chat = null;
	});

	describe("Websocket", function() {

		it("gets online public channel stats", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			chat.setMaxMessages(5);
			const publicIds = chat.getClients().map(client => client.getHexPublicId());

			chat.getClients().forEach(client => {
				client.send(createChannelStatsRequest(
					publicIds
				));
			});

			chat.once("ready", () => {
				try
				{
					chat.getClients().forEach(client => {
						client.responses.forEach(repsonse => {
							const ids = [];
							repsonse.channelStats.channels.forEach(channelStat => {
								ids.push(channelStat.id);
								assert.ok(channelStat.isPrivate === false);
								assert.ok(channelStat.isOnline);
							});
							assert.deepEqual(ids, publicIds);
						});
					});
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});

			//setTimeout(done, 2500);
		});

		it("gets online and offline public channels", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			chat.setMaxMessages(5);

			const onlinePublicIds = chat.getClients().map(client => client.getHexPublicId());
			const offlineChat = new Chat(5, 100);
			const offlinePublicIds = offlineChat.getClients().map(client => client.getHexPublicId());

			const publicIds = onlinePublicIds.concat(offlinePublicIds);

			chat.getClients().forEach(client => {
				client.send(createChannelStatsRequest(
					publicIds
				));
			});

			chat.once("ready", () => {
				try
				{
					chat.getClients().forEach(client => {
						client.responses.forEach(repsonse => {
							const online = [];
							const offline = [];
							repsonse.channelStats.channels.forEach(channelStat => {
								channelStat.isOnline ? online.push(channelStat.id) : offline.push(channelStat.id);
								assert.ok(channelStat.isPrivate === false);
							});
							assert.deepEqual(online, onlinePublicIds);
							assert.deepEqual(offline, offlinePublicIds);
						});
					});
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

	});

	describe("POST /pub/ (binary)", function() {
		it("gets channel stats (private/public, online/offline)", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			const onlinePublicIds = chat.getClients().map(client => client.getHexPublicId());
			const onlinePrivateIds = chat.getClients().map(client => client.getHexPrivateId());

			const offlineChat = new Chat(5, 100);
			const offlinePublicIds = offlineChat.getClients().map(client => client.getHexPublicId());
			const offlinePrivateIds = offlineChat.getClients().map(client => client.getHexPrivateId());

			const publicIds = onlinePublicIds.concat(offlinePublicIds);
			const privateIds = onlinePrivateIds.concat(offlinePrivateIds);

			const requestIds = privateIds.concat(publicIds);

			const body = RequestBatch.encode(createBatch(createChannelStatsRequest(publicIds, privateIds))).finish();
			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(
				pubUrl +
					"?binaryMode=true" +
					(trustClients ? "" : "&clientId=" + clientId + "&signature=" + signature)
				,
				{
					body,
					encoding: null //If null, the body is returned as a Buffer
				},
				function(error, response, body) {
					try
					{
						assert.equal(error, null);
						assert.equal(response.statusCode, 200);

						const responseBatch = ResponseBatch.decode(new Uint8Array(body));
						const channels = responseBatch.responses[0].channelStats.channels;

						const result = {
							ids: [],
							onlinePublicIds: [],
							onlinePrivateIds: [],
							offlinePublicIds: [],
							offlinePrivateIds: []
						};

						channels.forEach(channel => {
							result.ids.push(channel.id);

							if (channel.isPrivate)
							{
								channel.isOnline
									? result.onlinePrivateIds.push(channel.id)
									: result.offlinePrivateIds.push(channel.id)
								;
							}
							else
							{
								channel.isOnline
									? result.onlinePublicIds.push(channel.id)
									: result.offlinePublicIds.push(channel.id)
								;
							}
						});

						assert.deepEqual(result.ids, requestIds);
						assert.deepEqual(result.onlinePublicIds, onlinePublicIds);
						assert.deepEqual(result.onlinePrivateIds, onlinePrivateIds);
						assert.deepEqual(result.offlinePublicIds, offlinePublicIds);
						assert.deepEqual(result.offlinePrivateIds, offlinePrivateIds);

						done();
					}
					catch(exception)
					{
						done(exception);
					}
				}
			);
		});
	});

	describe("GET /pub/ (plain text)", function() {

		it("gets private channel stats", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			const privateIds = chat.getClients().map(client => client.getPrivateId());

			const channels = privateIds.join("/");
			const signature = Signature.getDigest(channels, securityKey).toString("hex");

			const url =
				pubUrl + "?CHANNEL_ID=" + channels +
				(trustClients ? "" : "&clientId=" + clientId + "&signature=" + signature)
				//+ "/" + offlineClient.getPrivateId() //push-server skips offline channels
				//+ "/" + chat.getClients()[0].getPublicId() //push-server skips public channels
			;
			get(url, function(error, response, body) {

				try
				{
					assert.ok(error === null);
					assert.equal(response.statusCode, 200);

					const result = JSON.parse(body);
					const ids = result.infos.map(channelStat => channelStat.channel);

					assert.deepEqual(ids, privateIds);
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});
	});

	describe("REST", function() {
		it("gets public channel stats", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			const publicIds = chat.getClients().map(client => client.getHexPublicId());
			const batch = createBatch(createChannelStatsRequest(publicIds));
			const client = chat.getClients()[0];

			const body = RequestBatch.encode(batch).finish();
			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(
				restUrl +
					"?CHANNEL_ID=" + client.getChannelId() + "." + client.getSignature() +
					(trustClients ? "" : "&clientId=" + clientId + "&signature=" + signature),
				{
					body,
					encoding: null //If null, the body is returned as a Buffer
				},
				function(error, response, body) {

					try
					{
						assert.ok(error === null);
						assert.equal(response.statusCode, 200);

						const responseBatch = ResponseBatch.decode(new Uint8Array(body));
						const ids = [];
						responseBatch.responses[0].channelStats.channels.forEach(channelStat => {
							ids.push(channelStat.id);
							assert.ok(channelStat.isPrivate === false);
							assert.ok(channelStat.isOnline);
						});

						assert.deepEqual(ids, publicIds);

						done();
					}
					catch(exception)
					{
						done(exception);
					}
				}
			);
		});
	});
});

describe("Server Stats", function() {

	this.timeout(6000);

	it("gets stats via GET /server-stat/", function(done) {
		get(statsUrl, (error, response, body) => {
			try
			{
				assert.equal(error, null);
				assert.equal(response.statusCode, 200);
				verifyServerStats(JSON.parse(body));
				done();
			}
			catch (e)
			{
				done(e);
			}
		});
	});

	it("gets stats via POST /pub/ (binary)", function(done) {
		const body = RequestBatch.encode(createBatch(createServerStatsRequest())).finish();
		const signature = Signature.getDigest(body, securityKey).toString("hex");

		post(
			pubUrl + "?binaryMode=true" +
			(trustClients ? "" : "&clientId=" + clientId + "&signature=" + signature),
			{
				body,
				encoding: null //If null, the body is returned as a Buffer
			},
			function(error, response, body) {

				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 200);

					const responseBatch = ResponseBatch.decode(new Uint8Array(body));
					const stats = JSON.parse(responseBatch.responses[0].serverStats.json);
					verifyServerStats(stats);

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

	it("gets stats via Websocket (not allowed)", function(done) {
		const client = new Client(1);
		client.connect();

		client.once("connection", () => {
			client.send(createServerStatsRequest());
		});

		let isDone = false;
		client.once("error", (error) => {

			if (!isDone)
			{
				isDone = true;
				done(error);
			}
		});

		client.once("close", (code, reason) => {

			if (isDone)
			{
				return;
			}

			isDone = true;

			try
			{
				assert.equal(code, 4014);
				assert.equal(reason, "4014: Request command is not allowed.");
				client.disconnect();
				done();
			}
			catch(exception)
			{
				done(exception);
			}
		});
	});

	it("gets stats via REST (not allowed)", function(done) {
		const client = new Client(10);
		const body = RequestBatch.encode(createBatch(createServerStatsRequest())).finish();
		post(
			restUrl +
				"?CHANNEL_ID=" + client.getChannelId() + "." + client.getSignature() +
				(trustClients ? "" : "&clientId=" + clientId)
			,
			{ body },
			function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(body, "4014: Request command is not allowed.");
					assert.equal(response.statusCode, 400);
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

});

describe("Long Polling Emulation", function() {

	this.timeout(6000);

	[
		{ binaryRequest: true, binaryResponse: true },
		{ binaryRequest: false, binaryResponse: false },
		{ binaryRequest: true, binaryResponse: false },
		{ binaryRequest: false, binaryResponse: true }
	].forEach(({binaryRequest, binaryResponse}, index) => {

		const requestType = binaryRequest ? "binary" : "text";
		const responseType = binaryResponse ? "binary" : "text";

		it(`sends ${requestType} requests, gets ${responseType} responses` , function(done) {
			this.timeout(6000);

			const client = new Client(600 + index);
			const url =
					subUrl + "?CHANNEL_ID=" + client.getChannelId() + "." + client.getSignature() +
					(binaryResponse ? "&binaryMode=true" : "") +
					(trustClients ? "" : "&clientId=" + clientId)
			;

			const expected = "1234567890";
			let result = "";
			let lastMessageId = null;

			function connect()
			{
				get(
					url + (lastMessageId ? "&mid=" + lastMessageId : ""),
					{ encoding: null },
					function(error, response, body) {

						try
						{
							assert.equal(error, null);
							assert.equal(response.statusCode, 200);

							let messages = [];
							if (binaryResponse)
							{
								const responseBatch = ResponseBatch.decode(new Uint8Array(body));
								responseBatch.responses.forEach(response => {
									messages = messages.concat(response.outgoingMessages.messages);
								});
							}
							else
							{
								messages = getMessagesFromText(body.toString());
							}

							let finished = false;
							messages.forEach(message => {
								lastMessageId = binaryResponse ? Buffer.from(message.id).toString("hex") : message.mid;
								const messageBody = binaryResponse ? message.body : message.text.toString();

								result += messageBody;

								if (messageBody === expected[expected.length - 1])
								{
									finished = true;
								}
							});

							if (finished)
							{
								assert.equal(result, expected);
								done();
							}
							else
							{
								setTimeout(() => connect(), 50);
							}
						}
						catch(exception)
						{
							done(exception);
						}
					}
				);
			}

			connect();

			let timeout = 0;
			expected.split("").forEach((body) => {

				timeout += 200;

				setTimeout(() => {

					if (binaryRequest)
					{
						const batch = createBatch(
							createMessageRequest(createMessage(body, null, client.getPrivateId()))
						);

						body = RequestBatch.encode(batch).finish();
					}

					const signature = Signature.getDigest(body, securityKey).toString("hex");

					post(
						pubUrl + (binaryRequest ? "?binaryMode=true" : "?CHANNEL_ID=" + client.getPrivateId()) +
						(trustClients ? "" : "&clientId=" + clientId + "&signature=" + signature),
						{
							body,
							headers: {
								"message-expiry": 3600 * 24,
								"x-forwarded-for": "92.50.195.150"
							}
						},
						(error, response) => {

							try
							{
								assert.equal(error, null);
								assert.equal(response.statusCode, 200);
							}
							catch(exception)
							{
								done(exception);
							}
						}
					);
				}, timeout);
			});
		});

	});
});

describe("Data Validation", function() {

	this.timeout(6000);

	let limits = {};
	let serverStats = {};

	before(function(done) {

		const body = RequestBatch.encode(createBatch(createServerStatsRequest())).finish();
		const signature = Signature.getDigest(body, securityKey).toString("hex");
		post(
			pubUrl +
				"?binaryMode=true" +
				(trustClients ? "" : "&clientId=" + clientId + "&signature=" + signature)
			,
			{
				body,
				encoding: null //If null, the body is returned as a Buffer
			},
			(error, response, body) => {

				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 200);

					const responseBatch = ResponseBatch.decode(new Uint8Array(body));
					const stats = JSON.parse(responseBatch.responses[0].serverStats.json);
					serverStats = stats[0];
					limits = stats[0].limits;

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);

	});

	it("sends wrong binary data", function(done) {

		const client = new Client(1000);
		client.connect();

		client.once("connection", () => {
			client.getWebsocket().send(Buffer.from([1,2,3,4,5]));
		});

		let isDone = false;
		client.once("error", (error) => {

			if (!isDone)
			{
				isDone = true;
				done(error);
			}
		});

		client.once("close", (code, reason) => {

			if (isDone)
			{
				return;
			}

			isDone = true;

			try
			{
				assert.equal(code, 4013);
				assert.equal(reason, "4013: Wrong Request Data.");
				client.disconnect();
				done();
			}
			catch(exception)
			{
				done(exception);
			}
		});
	});

	it("sends a message more than 1MB (websocket)", function(done) {

		const client = new Client(1000);
		client.connect();

		let isDone = false;
		client.once("connection", () => {
			client.send(createMessageRequest(
				createMessage("1".repeat(config.limits.maxPayload), client.getPublicId())
			));
		});

		let disconnected = false;
		client.once("error", (code, description) => {
			if (!disconnected)
			{
				if (!isDone)
				{
					isDone = true;
					done();
				}

				client.disconnect();
			}

			disconnected = true;
		});

		client.on("message", message => {

			if (isDone)
			{
				return;
			}

			isDone = true;

			done(new Error('Message event cannot be emitted.'));
		});

		client.once("close", (code, reason) => {
			if (!disconnected)
			{
				if (!isDone)
				{
					isDone = true;
					done();
				}
				client.disconnect();
			}

			disconnected = true;
		});
	});

	it("sends a message more than 1MB (rest)", function(done) {

		const client = new Client(500);

		const batch = createBatch(
			createMessageRequest(createMessage("1".repeat(config.limits.maxPayload), client.getPublicId()))
		);

		post(
			restUrl + "?CHANNEL_ID=" + client.getChannelId() + "." + client.getSignature(),
			{
				body: RequestBatch.encode(batch).finish(),
				encoding: null //If null, the body is returned as a Buffer
			},
			function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 413);

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

	it("sends a REST request without a signature", function(done) {

		const client = new Client(500);

		const batch = createBatch(
			createMessageRequest(createMessage("test", client.getPublicId()))
		);

		post(
			restUrl + "?CHANNEL_ID=" + client.getChannelId(),
			{
				body: RequestBatch.encode(batch).finish()
			},
			function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4012: Public Channel Id is Required.");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

	it("sends a REST request without a public channel", function(done) {

		const client = new Client(500);

		const batch = createBatch(
			createMessageRequest(createMessage("test", client.getPublicId()))
		);

		post(
			restUrl + "?CHANNEL_ID=" +
			client.getPrivateId() + "." + Signature.getDigest(client.getPrivateId(), securityKey).toString("hex"),
			{
				body: RequestBatch.encode(batch).finish()
			},
			function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4012: Public Channel Id is Required.");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

	it("tries to subscribe a lot of clients to one channel", function(done) {

		if (serverStats.clusterMode === true)
		{
			done();
			return;
		}

		let overhead = 3;
		const maxConnPerChannel = limits.maxConnPerChannel;
		const clientCount = maxConnPerChannel + overhead;
		const clients = [];
		let closedConns = 0;

		let isDone = false;

		for (let i = 1; i <= clientCount; i++)
		{
			const client = new Client(50);

			const timeout = i <= overhead ? (i-1) * 50 : overhead * 70;

			setTimeout(() => {
				client.connect();
			}, timeout);

			clients.push(client);

			client.once("error", (error) => {

				if (!isDone)
				{
					isDone = true;
					done(error);
				}
			});

			client.on("close", (code, reason) => {

				if (isDone)
				{
					return;
				}

				try
				{
					assert.ok(i <= overhead);
					assert.equal(reason, "4029: Too many connections");
					assert.equal(code, 4029);

					closedConns++;

					if (overhead === closedConns)
					{
						isDone = true;
						done();
						clients.forEach(client => client.disconnect());
					}
				}
				catch(exception)
				{
					isDone = true;
					done(exception);
				}
			});
		}
	});

	it("sends a lot of messages in one request", function(done) {

		let isDone = false;
		const client = new Client(789);
		client.connect();

		client.once("error", (error) => {

			if (!isDone)
			{
				isDone = true;
				done(error);
			}
		});

		client.once("connection", () => {

			const messages = [];
			for (let i = 0; i <= limits.maxMessagesPerRequest; i++)
			{
				messages.push(createMessage(i, client.getPublicId()));
			}

			client.send(createMessageRequest(messages));
		});

		client.once("close", (code, reason) => {

			if (isDone)
			{
				return;
			}

			isDone = true;

			try
			{
				assert.equal(code, 4016);
				assert.equal(reason, "4016: Request exceeded the maximum number of messages.");
				client.disconnect();
				done();
			}
			catch(exception)
			{
				done(exception);
			}
		});
	});

	it("sends an empty channel stats request (WS)", function(done) {

		const client = new Client(444);
		client.connect();
		client.on("connection", () => {
			client.send(createChannelStatsRequest([]));
		});

		let isDone = false;
		client.once("error", (error) => {

			if (!isDone)
			{
				isDone = true;
				done(error);
			}
		});

		client.on("close", (code, reason) => {

			if (isDone)
			{
				return;
			}

			isDone = true;

			try
			{
				assert.equal(code, 4017);
				assert.equal(reason, "4017: No channels found.");
				client.disconnect();
				done();
			}
			catch(exception)
			{
				done(exception);
			}
		});

	});

	it("sends an empty channel stats request (HTTP)", function(done) {

		const signature = Signature.getDigest("", securityKey).toString("hex");
		get(
			pubUrl +
				"?CHANNEL_ID=" +
				(trustClients ? "" : "&clientId=" + clientId + "&signature=" + signature)
			,
			(error, response, body) => {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4017: No channels found.");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

	it("sends a stats request with a lot of channels (WS)", function(done) {

		const client = new Client(444);
		client.connect();

		let isDone = false;
		client.once("error", (error) => {

			if (!isDone)
			{
				isDone = true;
				done(error);
			}
		});

		client.on("connection", () => {

			const publicIds = [];

			for (var i = 0; i <= config.limits.maxChannelsPerRequest; i++)
			{
				const client = new Client(i);
				publicIds.push(client.getPublicId());
			}

			client.send(createChannelStatsRequest(publicIds));
		});

		client.on("close", (code, reason) => {

			if (isDone)
			{
				return;
			}

			isDone = true;

			try
			{
				assert.equal(code, 4018);
				assert.equal(reason, "4018: Request exceeded the maximum number of channels.");
				client.disconnect();
				done();
			}
			catch(exception)
			{
				done(exception);
			}
		});

	});

	it("sends a stats request with a lot of channels (HTTP)", function(done) {

		const publicIds = [];

		for (var i = 0; i <= config.limits.maxChannelsPerRequest; i++)
		{
			const client = new Client(i);
			publicIds.push(client.getPublicId());
		}

		const channels = publicIds.join("/");
		const signature = Signature.getDigest(channels, securityKey).toString("hex");

		get(
			pubUrl +
				"?CHANNEL_ID=" + channels +
				(trustClients ? "" : "&clientId=" + clientId + "&signature=" + signature)
			,
			(error, response, body) => {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4018: Request exceeded the maximum number of channels.");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);

	});

	it("gets stats for a private channel (REST)", function(done) {

		const client = new Client(1);
		const body = RequestBatch.encode(
			createBatch(createChannelStatsRequest(null, client.getPrivateId()))
		).finish();

		post(
			restUrl + "?CHANNEL_ID=" + client.getChannelId() + "." + client.getSignature()
			+ (trustClients ? "" : "&clientId=" + clientId),
			{
				body
			},
			(error, response, body) => {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4020: Private channel is not allowed.");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

	it("gets stats for a wrong private channel (trusted)", function(done) {

		const client = new Client(1);
		const statsRequest = createChannelStatsRequest(client.getPublicId());
		statsRequest.channelStats.channels[0].id = Buffer.from("wrong id");

		const body = RequestBatch.encode(createBatch(statsRequest)).finish();
		const signature = Signature.getDigest(body, securityKey).toString("hex");

		post(
			pubUrl +"?binaryMode=true" + (trustClients ? "" : "&clientId=" + clientId + "&signature=" + signature),
			{
				body
			},
			(error, response, body) => {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4019: Request has an invalid channel id.");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

	it("sends stats request with a wrong signature (REST)", function(done) {

		const client = new Client(1);
		const statsRequest = createChannelStatsRequest(client.getPublicId());
		statsRequest.channelStats.channels[0].signature = Buffer.from("wrong id");
		const body = RequestBatch.encode(createBatch(statsRequest)).finish();

		post(
			restUrl +
				"?CHANNEL_ID=" + client.getChannelId() + "." + client.getSignature() +
				(trustClients ? "" : "&clientId=" + clientId),
			{
				body
			},
			(error, response, body) => {

				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4021: Channel has an invalid signature.");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

	it("sends a message with an empty signature to the public channel", function(done) {
		const client = new Client(2);
		client.connect();

		client.once("connection", function() {
			const message = createMessage("message", client.getPublicId());
			delete message.receivers[0].signature;

			client.send(createMessageRequest([message]));
		});

		let isDone = false;
		client.once("error", (error) => {

			if (!isDone)
			{
				isDone = true;
				done(error);
			}
		});

		client.on("close", (code, reason) => {

			if (isDone)
			{
				return;
			}

			isDone = true;

			try
			{
				assert.equal(code, 4021);
				assert.equal(reason, "4021: Channel has an invalid signature.");
				client.disconnect();
				done();
			}
			catch(exception)
			{
				done(exception);
			}
		});

		client.on("message", message => {

			if (isDone)
			{
				return;
			}

			isDone = true;

			done(new Error('Message event cannot be emitted.'));
		});

	});

	it("sends a message with empty receivers", function(done) {
		const client = new Client(2);
		client.connect();

		client.once("connection", function() {
			const message = createMessage("message", client.getPublicId());
			message.receivers = [];
			client.send(createMessageRequest([message]));
		});

		let isDone = false;
		client.once("error", (error) => {

			if (!isDone)
			{
				isDone = true;
				done(error);
			}
		});

		client.on("close", (code, reason) => {

			if (isDone)
			{
				return;
			}

			isDone = true;

			try
			{
				assert.equal(code, 4017);
				assert.equal(reason, "4017: No channels found.");
				client.disconnect();
				done();
			}
			catch(exception)
			{
				done(exception);
			}
		});

		client.on("message", message => {

			if (isDone)
			{
				return;
			}

			isDone = true;

			done(new Error('Message event cannot be emitted.'));
		});

	});

	it("sends a message with a wrong signature to the public channel", function(done) {
		const client = new Client(2);
		client.connect();

		client.once("connection", function() {
			const message = createMessage("message", client.getPublicId());

			message.receivers[0].signature = Signature.getPublicDigest("wrong id", securityKey);

			client.send(createMessageRequest([message]));
		});

		let isDone = false;
		client.once("error", (error) => {

			if (!isDone)
			{
				isDone = true;
				done(error);
			}
		});

		client.on("close", (code, reason) => {

			if (isDone)
			{
				return;
			}

			isDone = true;

			try
			{
				assert.equal(code, 4021);
				assert.equal(reason, "4021: Channel has an invalid signature.");
				client.disconnect();
				done();
			}
			catch(exception)
			{
				done(exception);
			}
		});

		client.on("message", message => {

			if (isDone)
			{
				return;
			}

			isDone = true;

			done(new Error('Message event cannot be emitted.'));
		});

	});

	it("sends a message to the private channel", function(done) {
		const client = new Client(2);
		client.connect();

		client.once("connection", function() {
			const message = createMessage("message", null, client.getPrivateId());
			client.send(createMessageRequest([message]));
		});

		let isDone = false;
		client.once("error", (error) => {

			if (!isDone)
			{
				isDone = true;
				done(error);
			}
		});

		client.on("close", (code, reason) => {

			if (isDone)
			{
				return;
			}

			isDone = true;

			try
			{
				assert.equal(code, 4020);
				assert.equal(reason, "4020: Private channel is not allowed.");
				client.disconnect();
				done();
			}
			catch(exception)
			{
				done(exception);
			}
		});

		client.on("message", message => {

			if (isDone)
			{
				return;
			}

			isDone = true;

			done(new Error('Message event cannot be emitted.'));
		});

	});

	it("sends a message with a wrong private channel", function(done) {

		const batch = createBatch(
			createMessageRequest(createMessage("message", null, "wrong id"))
		);

		const body = RequestBatch.encode(batch).finish();
		const signature = Signature.getDigest(body, securityKey).toString("hex");

		post(
			pubUrl +
				"?binaryMode=true" +
				(trustClients ? "" : "&clientId=" + clientId + "&signature=" + signature)
			,
			{
				body,
				encoding: null //If null, the body is returned as a Buffer
			},
			function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4019: Request has an invalid channel id.");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);

	});

	it("tries to listen to a public channel", function(done) {

		const client = new Client(1);
		get(
			subUrl +
			"?CHANNEL_ID=" + client.getPublicId() + "." +
			Signature.getPublicDigest(client.getPublicId(), securityKey).toString("hex") +
			"&binaryMode=true&clientId=" + clientId,
			function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4010: Wrong Channel Id.");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			}
		);
	});

});

describe("Data Validation (trustClients=false)", function() {

	this.timeout(6000);

	if (trustClients)
	{
		return;
	}

	describe("Subscription", function() {

		it("tries to subscribe without a client id", function(done) {

			const client = new Client(1);

			let url = subUrl + "?CHANNEL_ID=" + client.getChannelId() + "." + client.getSignature();

			get(url, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4036: Wrong Client Id.");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("tries to subscribe without a client id (binary)", function(done) {

			const client = new Client(1);

			let url = subUrl + "?CHANNEL_ID=" + client.getChannelId() + "." + client.getSignature() + "&binaryMode=true";

			get(url, { encoding: null }, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body.toString(), "4036: Wrong Client Id.");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		const wrongClientId = "1" + clientId.substring(1);

		it("tries to subscribe with a wrong client Id", function(done) {

			const client = new Client(1);

			let url = subUrl +
				"?CHANNEL_ID=" + client.getChannelId() + "." + client.getSignature() +
				"&clientId=" + wrongClientId;

			get(url, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4036: Wrong Client Id.");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("tries to subscribe with a wrong client Id (ws)", function(done) {

			const client = new Client(1);

			client.url = subUrl +
				"?CHANNEL_ID=" + client.getChannelId() + "." + client.getSignature() +
				"&clientId=" + wrongClientId;

			client.connect();

			client.on('unexpected-response', (request, response) => {
				try
				{
					const queryData = [];
					response.on("data", (data) => {
						queryData.push(data);
					});

					response.on("end", () => {
						assert.equal(response.statusCode, 400);
						assert.equal(
							Buffer.concat(queryData).toString(),
							"4036: Wrong Client Id."
						);
						done();
					});
				}
				catch(exception)
				{
					done(exception);
				}
			});

			client.on("error", error => {
				done(error);
			});

		});

		it("tries to subscribe with a wrong client Id (binary)", function(done) {

			const client = new Client(1);

			let url = subUrl +
				"?CHANNEL_ID=" + client.getChannelId() + "." + client.getSignature() + "&binaryMode=true" +
				"&clientId=" + wrongClientId;

			get(url, { encoding: null }, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body.toString(), "4036: Wrong Client Id.");
					done();
				}
				catch(exception)
				{
					done(exception);
				}

			});
		});

		it("tries to subscribe with a wrong signature", function(done) {

			const client = new Client(1);
			const client2 = new Client(2);

			let url = subUrl +
				"?CHANNEL_ID=" + client.getChannelId() + "." + client2.getSignature() +
				"&clientId=" + clientId;

			get(url, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4010: Wrong Channel Id.");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("tries to subscribe with a wrong signature (binary)", function(done) {

			const client = new Client(1);
			const client2 = new Client(2);

			let url = subUrl +
				"?CHANNEL_ID=" + client.getChannelId() + "." + client2.getSignature() + "&binaryMode=true" +
				"&clientId=" + clientId;

			get(url, { encoding: null }, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body.toString(), "4010: Wrong Channel Id.");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

	});

	describe("Publication", function() {

		it("sends a message without a client Id", function(done) {

			const body = "message";
			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(
				pubUrl +
				"?clientId=" + "&signature=" + signature
				,
				{
					body,
				},
				function(error, response, body) {
					try
					{
						assert.equal(error, null);
						assert.equal(response.statusCode, 400);
						assert.equal(body, "4025: Client Id is required.");

						done();
					}
					catch(exception)
					{
						done(exception);
					}
				}
			);
		});

		it("sends a message without a client Id (binary)", function(done) {

			const client = new Client(10);

			const batch = createBatch(createMessageRequest(createMessage("message", null, client.getPrivateId())));
			const body = RequestBatch.encode(batch).finish();
			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(
				pubUrl +
				"?binaryMode=true" +
				"&clientId=" + "&signature=" + signature
				,
				{
					body,
					encoding: null //If null, the body is returned as a Buffer
				},
				function(error, response, body) {
					try
					{
						assert.equal(error, null);
						assert.equal(response.statusCode, 400);
						assert.equal(body.toString(), "4025: Client Id is required.");

						done();
					}
					catch(exception)
					{
						done(exception);
					}
				}
			);
		});

		const wrongClientId = "1" + clientId.substring(1);
		it("sends a message with a wrong client Id", function(done) {

			const body = "message";
			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(
				pubUrl + "?clientId=" + wrongClientId + "&signature=" + signature,
				{
					body,
				},
				function(error, response, body) {
					try
					{
						assert.equal(error, null);
						assert.equal(response.statusCode, 400);
						assert.equal(body, "4025: Client Id is required.");

						done();
					}
					catch(exception)
					{
						done(exception);
					}
				}
			);
		});

		it("sends a message with a wrong client Id (binary)", function(done) {

			const client = new Client(10);

			const batch = createBatch(createMessageRequest(createMessage("message", null, client.getPrivateId())));
			const body = RequestBatch.encode(batch).finish();
			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(
				pubUrl +
				"?binaryMode=true" +
				"&clientId=" + wrongClientId + "&signature=" + signature
				,
				{
					body,
					encoding: null //If null, the body is returned as a Buffer
				},
				function(error, response, body) {

					try
					{
						assert.equal(error, null);
						assert.equal(response.statusCode, 400);
						assert.equal(body.toString(), "4025: Client Id is required.");

						done();
					}
					catch(exception)
					{
						done(exception);
					}
				}
			);
		});

		it("sends a message with a wrong signature", function(done) {

			const body = "message";
			const signature = Signature.getDigest("wrong message", securityKey).toString("hex");

			post(
				pubUrl +
				"?clientId=" + clientId + "&signature=" + signature,
				{
					body,
				},
				function(error, response, body) {

					try
					{
						assert.equal(error, null);
						assert.equal(response.statusCode, 400);
						assert.equal(body, "4026: Wrong request signature.");

						done();
					}
					catch(exception)
					{
						done(exception);
					}
				}
			);
		});

		it("sends a message with a wrong signature (binary)", function(done) {

			const client = new Client(10);

			const batch = createBatch(createMessageRequest(createMessage("message", null, client.getPrivateId())));
			const body = RequestBatch.encode(batch).finish();
			const signature = Signature.getDigest("wrong message", securityKey).toString("hex");

			post(
				pubUrl +
				"?binaryMode=true" +
				"&clientId=" + clientId + "&signature=" + signature
				,
				{
					body,
					encoding: null //If null, the body is returned as a Buffer
				},
				function(error, response, body) {

					try
					{
						assert.equal(error, null);
						assert.equal(response.statusCode, 400);
						assert.equal(body.toString(), "4026: Wrong request signature.");

						done();
					}
					catch(exception)
					{
						done(exception);
					}
				}
			);
		});

	});

	describe("Channel Stats", function() {

		it("gets private channel stats without a client Id", function(done) {

			const chat = new Chat(5);
			const privateIds = chat.getClients().map(client => client.getPrivateId());

			const channels = privateIds.join("/");
			const signature = Signature.getDigest(channels, securityKey).toString("hex");

			const url =
				pubUrl + "?CHANNEL_ID=" + channels +
				"&clientId=" + "&signature=" + signature
			;

			get(url, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4025: Client Id is required.");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("gets private channel stats without a client Id (binary)", function(done) {

			const chat = new Chat(5);
			const publicIds = chat.getClients().map(client => client.getHexPublicId());
			const privateIds = chat.getClients().map(client => client.getHexPrivateId());

			const body = RequestBatch.encode(createBatch(createChannelStatsRequest(publicIds, privateIds))).finish();
			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(
				pubUrl +
				"?binaryMode=true" +
				"&clientId=" + "&signature=" + signature
				,
				{
					body,
					encoding: null //If null, the body is returned as a Buffer
				},
				function(error, response, body) {
					try
					{
						assert.equal(error, null);
						assert.equal(response.statusCode, 400);
						assert.equal(body.toString(), "4025: Client Id is required.");

						done();
					}
					catch(exception)
					{
						done(exception);
					}
				}
			);
		});

		const wrongClientId = "1" + clientId.substring(1);
		it("gets private channel stats with a wrong client Id", function(done) {

			const chat = new Chat(5);
			const privateIds = chat.getClients().map(client => client.getPrivateId());

			const channels = privateIds.join("/");
			const signature = Signature.getDigest(channels, securityKey).toString("hex");

			const url =
				pubUrl + "?CHANNEL_ID=" + channels +
				"&clientId=" + wrongClientId + "&signature=" + signature
			;

			get(url, function(error, response, body) {
				try
				{
					assert.ok(error === null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4025: Client Id is required.");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("gets private channel stats with a wrong client Id (binary)", function(done) {

			const chat = new Chat(5);
			const publicIds = chat.getClients().map(client => client.getHexPublicId());
			const privateIds = chat.getClients().map(client => client.getHexPrivateId());

			const body = RequestBatch.encode(createBatch(createChannelStatsRequest(publicIds, privateIds))).finish();
			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(
				pubUrl +
				"?binaryMode=true" +
				"&clientId=" + wrongClientId + "&signature=" + signature
				,
				{
					body,
					encoding: null //If null, the body is returned as a Buffer
				},
				function(error, response, body) {
					try
					{
						assert.ok(error === null);
						assert.equal(response.statusCode, 400);
						assert.equal(body.toString(), "4025: Client Id is required.");

						done();
					}
					catch(exception)
					{
						done(exception);
					}
				}
			);
		});

		it("gets private channel stats with a wrong signature", function(done) {

			const chat = new Chat(5);
			const privateIds = chat.getClients().map(client => client.getPrivateId());

			const channels = privateIds.join("/");
			const signature = Signature.getDigest(channels + "wrong", securityKey).toString("hex");

			const url =
				pubUrl + "?CHANNEL_ID=" + channels +
				"&clientId=" + clientId + "&signature=" + signature
			;

			get(url, function(error, response, body) {
				try
				{
					assert.ok(error === null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4026: Wrong request signature.");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("gets private channel stats with a wrong signature (binary)", function(done) {

			const chat = new Chat(5);
			const publicIds = chat.getClients().map(client => client.getHexPublicId());
			const privateIds = chat.getClients().map(client => client.getHexPrivateId());

			const body = RequestBatch.encode(createBatch(createChannelStatsRequest(publicIds, privateIds))).finish();
			const signature = Signature.getDigest("wrong", securityKey).toString("hex");

			post(
				pubUrl +
				"?binaryMode=true" +
				"&clientId=" + clientId + "&signature=" + signature
				,
				{
					body,
					encoding: null //If null, the body is returned as a Buffer
				},
				function(error, response, body) {
					try
					{
						assert.ok(error === null);
						assert.equal(response.statusCode, 400);
						assert.equal(body.toString(), "4026: Wrong request signature.");

						done();
					}
					catch(exception)
					{
						done(exception);
					}
				}
			);
		});

	});

});

describe("Last Messages", function() {

	this.timeout(6000);

	let client = null;
	let firstMessageId = null;
	let lastMessageId = null;
	let hookError = null;

	beforeEach(function(done) {

		client = new Client(123);
		client.connect();

		let isDone = false;

		client.once("connection", () => {

			["A", "B", "C", "D", "E", "F"].forEach((message, index) => {

				const toPrivateChannel = index % 2 === 0;

				setTimeout(() => {

					const batch = createBatch(createMessageRequest(
						createMessage(
							message,
							toPrivateChannel ? null : client.getPublicId(),
							toPrivateChannel ? client.getPrivateId() : null,
							120)
					));

					const body = RequestBatch.encode(batch).finish();
					const signature = Signature.getDigest(body, securityKey).toString("hex");

					post(
						pubUrl + "?binaryMode=true" +
							(trustClients ? "" : "&clientId=" + clientId + "&signature=" + signature),
						{ body },
						function (error, response, body) {
							if (isDone)
							{
								return;
							}

							if (error)
							{
								isDone = true;
								//done(error); - skip all the tests
								hookError = error; // manual failing
								done(); // doesn't skip the tests
							}
						}
					);
				}, index * 200);

			});

		});

		client.once("error", (error) => {
			if (!isDone)
			{
				isDone = true;
				//done(error); - skip all the tests
				hookError = error; // manual failing
				done(); // doesn't skip the tests
			}
		});

		client.on("message", message => {

			if (message.body === "A")
			{
				firstMessageId = Buffer.from(message.id).toString("hex");
			}

			if (message.body === "F")
			{
				lastMessageId = Buffer.from(message.id).toString("hex");
				client.disconnect();
				done();
			}
		});

	});

	afterEach(function() {
		firstMessageId = null;
		lastMessageId = null;
	});

	describe("Websocket", function() {
		it("gets last messages", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			let isDone = false;
			const client = new Client(123);
			client.connect("mid=" + firstMessageId);
			client.once("response", responses => {

				if (isDone)
				{
					return;
				}

				isDone = true;

				let result = "";
				responses.forEach(response => {
					response.outgoingMessages.messages.forEach((message) => {
						result += message.body;
					});
				});

				client.disconnect();

				try
				{
					assert.equal(result, "BCDEF");
					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});

			client.once("error", (error) => {

				if (!isDone)
				{
					isDone = true;
					done(error);
				}
			});

		});
	});

	describe("Long Polling", function() {

		it("gets last messages", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			let url =
				subUrl + "?CHANNEL_ID=" + client.getChannelId() + "." + client.getSignature() +
				(trustClients ? "" : "&clientId=" + clientId) +
				"&binaryMode=true&mid=" + firstMessageId
			;

			get(url, { encoding: null }, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 200);

					const responseBatch = ResponseBatch.decode(new Uint8Array(body));

					let result = "";
					responseBatch.responses.forEach(response => {
						response.outgoingMessages.messages.forEach((message) => {
							result += message.body;
						});
					});

					assert.equal(result, "BCDEF");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("gets last message on timeout", function(done) {

			if (hookError)
			{
				done(hookError);
				return;
			}

			this.timeout(43000);
			let url =
				subUrl + "?CHANNEL_ID=" + client.getChannelId() + "." + client.getSignature() +
				"&binaryMode=true" +
				(trustClients ? "" : "&clientId=" + clientId)
			;

			get(url, { encoding: null, timeout: 43000 }, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 304);
					assert.equal(body, "");
					assert.equal(response.headers["last-message-id"], lastMessageId);

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

	});
});

describe("System commands", function() {

	this.timeout(6000);

	if (trustClients === true)
	{
		return;
	}

	describe("Unregister", function() {

		it("makes an empty request", function(done) {

			const body = "";

			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(systemctlUrl + "?signature=" + signature , { body }, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4022: Empty request.");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("makes a request with a wrong signature", function(done) {

			const body = JSON.stringify({
				method: "unregister",
				params: { clientId: clientId }
			});

			const signature = Signature.getDigest("wrong", securityKey).toString("hex");

			post(systemctlUrl + "?signature=" + signature , { body }, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4026: Wrong request signature.");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("makes a request with a bad JSON", function(done) {

			const body = "wrong";

			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(systemctlUrl + "?signature=" + signature , { body }, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4032: Bad JSON request.");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("makes a request with a bad JSON (null)", function(done) {

			const body = "null";

			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(systemctlUrl + "?signature=" + signature , { body }, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4032: Bad JSON request.");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("makes a request with a wrong JSON RPC (empty method)", function(done) {

			const body = JSON.stringify({
				params: { clientId: clientId }
			});

			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(systemctlUrl + "?signature=" + signature , { body }, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4033: JSON object doesn't have 'method' property.");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("makes a request with a wrong JSON RPC (wrong params)", function(done) {

			const body = JSON.stringify({
				method: "unregister",
				params: "wrong"
			});

			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(systemctlUrl + "?signature=" + signature , { body }, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4034: JSON object doesn't have 'params' property.");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});


		it("makes a request with an unknown command", function(done) {

			const body = JSON.stringify({
				method: "wrong",
				params: { clientId: clientId }
			});

			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(systemctlUrl + "?signature=" + signature , { body }, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 400);
					assert.equal(body, "4035: Unknown command.");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("unregisters a client", function(done) {

			const host = new URL(systemctlUrl).hostname;
			const body = JSON.stringify({
				method: "unregister",
				params: { clientId: clientId, host  }
			});

			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(systemctlUrl + "?signature=" + signature , { body }, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 200);

					const result = JSON.parse(body);
					assert.equal(result.error, null);
					assert.equal(result.result.status, "success");

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("tries to unregister a removed client", function(done) {

			const body = JSON.stringify({
				method: "unregister",
				params: { clientId: clientId }
			});

			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(systemctlUrl + "?signature=" + signature , { body }, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 200);

					const result = JSON.parse(body);
					assert.equal(result.error, "Client not found.");
					assert.equal(result.result, null);

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

		it("tries to unregister a client by wrong id", function(done) {

			const body = JSON.stringify({
				method: "unregister",
				params: { clientId: "wrong" }
			});

			const signature = Signature.getDigest(body, securityKey).toString("hex");

			post(systemctlUrl + "?signature=" + signature , { body }, function(error, response, body) {
				try
				{
					assert.equal(error, null);
					assert.equal(response.statusCode, 200);

					const result = JSON.parse(body);
					assert.equal(result.error, "Wrong Client Id.");
					assert.equal(result.result, null);

					done();
				}
				catch(exception)
				{
					done(exception);
				}
			});
		});

	});
});
/**
 *
 * @param {string} body
 * @param {string|string[]} [publicId]
 * @param {string|string[]} [privateId]
 * @param {number} [expiry=60]
 * @return {IncomingMessage}
 */
function createMessage(body, publicId, privateId, expiry)
{
	const receivers = [];
	const pubChannels = Array.isArray(publicId) ? publicId : (publicId ? [publicId] : []);
	const channels = Array.isArray(privateId) ? privateId : (privateId ? [privateId] : []);

	channels.forEach(channel => {
		receivers.push(new Receiver({
			isPrivate: true,
			id: Buffer.from(channel, "hex")
		}));
	});

	pubChannels.forEach(channel => {
		receivers.push(new Receiver({
			isPrivate: false,
			id: Buffer.from(channel, "hex"),
			signature: Signature.getPublicDigest(channel, securityKey)
		}));
	});

	return IncomingMessage.create({
		receivers,
		body: body.toString(),
		expiry: expiry || 60,
		type: "unit_test"
	});
}

/**
 *
 * @param {IncomingMessage|IncomingMessage[]} messages
 * @return {Request}
 */
function createMessageRequest(messages)
{
	return Request.create({
		incomingMessages: {
			messages: Array.isArray(messages) ? messages : [messages]
		}
	});
}

/**
 *
 * @param {Request} request
 * @return {RequestBatch}
 */
function createBatch(request)
{
	const batch = new RequestBatch();
	batch.requests.push(request);

	return batch;
}

/**
 *
 * @param {Chat} chat
 * @param {string} expected
 */
function verifyChatResult(chat, expected)
{
	chat.getClients().forEach((client) => {

		const messages = [];
		client.responses.forEach((response) => {
			response.outgoingMessages.messages.forEach((message) => {
				messages.push(message);
			});
		});

		let result = "";
		messages.sort((a, b) => a.body - b.body).forEach(message => {
			result += message.body;
		});

		assert.equal(result, expected, "Wrong result: " + result + " expected: " + expected);
	});
}

function createChannelStatsRequest(publicId, privateId)
{
	const channels = [];
	const publicIds = Array.isArray(publicId) ? publicId : (publicId ? [publicId] : []);
	const privateIds = Array.isArray(privateId) ? privateId : (privateId ? [privateId] : []);

	privateIds.forEach(channel => {
		channels.push(new ChannelId({
			isPrivate: true,
			id: Buffer.isBuffer(channel) ? channel : Buffer.from(channel, "hex")
		}));
	});

	publicIds.forEach(channel => {
		channels.push(new ChannelId({
			isPrivate: false,
			id: Buffer.isBuffer(channel) ? channel : Buffer.from(channel, "hex"),
			signature: Signature.getPublicDigest(channel, securityKey)
		}));
	});

	return Request.create({
		channelStats: {
			channels
		}
	});
}

function createServerStatsRequest()
{
	return Request.create({
		serverStats: {}
	});
}

function verifyServerStats(serverStats)
{
	serverStats.forEach(processStats => {
		assert.ok(
			"pid" in processStats && "date" in processStats
		);
	});
}

function getMessagesFromText(text)
{
	if (typeof(text) !== "string" || text.length < 1)
	{
		return [];
	}

	var parts = text.match(/#!NGINXNMS!#(.*?)#!NGINXNME!#/gm);
	if (parts === null)
	{
		return [];
	}

	const messages = [];
	for (var i = 0; i < parts.length; i++)
	{
		const message = (new Function("return " + parts[i].substring(12, parts[i].length - 12)))();
		messages.push(message);
	}

	return messages;
}
