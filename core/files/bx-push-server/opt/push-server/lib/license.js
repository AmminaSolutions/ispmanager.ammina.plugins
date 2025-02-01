const MySql = require("./storages/mysql");
const superAgent = require("superagent");
const crypto = require("crypto");
const config = require("../config");
const logger = require("./debug");
const { RegisterRequest, Notification, License } = require("./models");
let application = null;

const cache = new Map();
const cacheTtl = 3600 * 24 * 1000;
const dateTimePattern = /^(20[0-9]{2})-([0-9]{2})-([0-9]{2})$/;

//Let's extend License class with static methods.

/**
 *
 * @param {string} clientId
 * @param {string} host
 * @param useCache
 * @return Promise<null|License>
 */
License.get = function(clientId, host, useCache = true)
{
	if (typeof(clientId) !== "string" || clientId.length < 32)
	{
		return Promise.resolve(null);
	}

	if (typeof(host) !== "string" || host.length > 32)
	{
		return Promise.resolve(null);
	}

	let license = undefined;
	if (useCache)
	{
		const clientHosts = cache.get(clientId);
		const cacheRecord = clientHosts && clientHosts.get(host);
		if (typeof(cacheRecord) === 'object' && cacheRecord.expiry > Date.now())
		{
			license = cacheRecord.license;
		}
	}

	if (license !== undefined)
	{
		return Promise.resolve(license);
	}

	return new Promise(resolve => {
		MySql.getDatabase().then(db => {
			if (db === null)
			{
				resolve(null);
				return;
			}

			db.query("SELECT * FROM licenses WHERE clientId = ? AND host = ?", [clientId, host], (error, results) => {
				if (error)
				{
					logger.systemError(error);
					resolve(null);
				}
				else
				{
					const license = results && results[0] ? License.create(results[0]) : null;
					if (license !== null)
					{
						let clientHosts = cache.get(clientId);
						if (!clientHosts)
						{
							clientHosts = new Map();
							cache.set(clientId, clientHosts);
						}

						clientHosts.set(
							host,
							{
								license,
								expiry: Date.now() + cacheTtl
							}
						);
					}

					resolve(license);
				}
			});
		});

	});
};

/**
 *
 * @param {IncomingMessage} request
 * @returns {Promise<null|License>}
 */
License.getByRequest = function(request)
{
	const searchQuery = request.url.split('?');
	const urlSearchParams = new URLSearchParams(typeof(searchQuery[1]) === "string" ? searchQuery[1] : "");
	const clientId = urlSearchParams.get("clientId");

	const url = new URL(request.url, `https://${request.headers.host}`);
	const host = url.hostname;

	return License.get(clientId, host);
}

/**
 *
 * @param {RegisterRequest} registerRequest
 * @param {function(Error, License)} callback
 */
License.register = function(registerRequest, callback)
{
	superAgent
		.post(config.licenseServer)
		.send(registerRequest.verificationQuery)
		.retry(2)
		.timeout({
			response: 5000,
			deadline: 10000
		})
		.then(response => {

			const body = response.body || {};
			const result = body.result && typeof(body.result) === "object" ? body.result : null;

			if (body.status === "ok" && result)
			{
				const license = License.create({
					clientId: result["LICENSE_KEY_HEAD"],
					active: result["ACTIVE"],
					dateTo: result["DATE_TO"],
					siteUrl: result["SITE_URL"],
					host: registerRequest.host,
					verificationQuery: registerRequest.verificationQuery
				});

				License.save(license, callback);
			}
			else
			{
				const error = body.status === "error" && body.text ? body.text : "Unknown Error";

				callback(new Error(error), null);
			}

		})
		.catch(error => {
			callback(error, null);
		})
	;
};

/**
 *
 * @param {string} clientId
 * @param host
 * @param {function(Error, License)} callback
 */
License.unregister = function(clientId, host, callback)
{
	if (typeof(clientId) !== "string" || !clientId.match(/[a-z0-9]{32}/))
	{
		callback(new Error("Wrong Client Id."), null);
		return;
	}

	License.refresh(clientId);

	License.get(clientId, host, false).then(async (license) => {
		if (license === null)
		{
			callback(new Error("Client not found."), null);
			return;
		}

		const db = await MySql.getDatabase();
		if (!db)
		{
			callback(new Error("Couldn't connect to the database."), null);
			return;
		}

		db.query(`DELETE FROM licenses WHERE clientId = ? AND host = ?`, [clientId, host], (error) => {
			if (error)
			{
				callback(error, null);
				return;
			}

			if (application === null)
			{
				application = require("./application");
			}

			application.getAdapter().postIpcNotification(Notification.create({
				ipcLicenses: {
					licenses: [{
						license,
						action: "unregister"
					}]
				}
			}));

			callback(null, license);
		});
	});
};

/**
 *
 * @param {License} license
 * @param {function(Error, License)} callback
 */
License.save = function(license, callback)
{
	license = license && typeof(license) === "object" ? license : {};

	const clientId = typeof (license.clientId) === "string" ? license.clientId : "";
	if (!clientId.match(/[a-z0-9]{32}/))
	{
		callback(new Error("Wrong Client ID."), null);
		return;
	}

	const host = typeof(license.host) === "string" ? license.host : "";
	if (host.length < 2 || host.length > 32)
	{
		callback(new Error("Wrong hostname."), null);
		return;
	}

	const securityKey = this.getSecurityKey(clientId, host);

	let dateTo = typeof(license.dateTo) === "string" ? license.dateTo : "";
	const result = dateTimePattern.exec(dateTo);
	if (result === null)
	{
		callback(new Error("Wrong expiry date."), null);
		return;
	}

	dateTo = (new Date(dateTo)).getTime();

	const siteUrl = typeof(license.siteUrl) === "string" ? license.siteUrl.substr(0, 100) : "";

	const verificationQuery = typeof(license.verificationQuery) === "string" ? license.verificationQuery : "";
	if (verificationQuery.length < 64 || verificationQuery.length > 200)
	{
		callback(new Error("Wrong Verification Query."), null);
		return;
	}

	const sql = `
		INSERT INTO licenses (clientId, host, securityKey, dateTo, siteUrl, verificationQuery)
		VALUES (?, ?, ?, ?, ?, ?)
		ON DUPLICATE KEY UPDATE
			dateTo = values(dateTo),
			siteUrl = values(siteUrl),
			verificationQuery = values(verificationQuery)
	`;

	MySql.getDatabase().then((db) => {
		if (!db)
		{
			callback(new Error("Couldn't connect to the database."), null);
			return;
		}

		db.query(
			sql,
			[clientId, host, securityKey, dateTo, siteUrl, verificationQuery],
			(error) => {
				if (error)
				{
					callback(error, null);
				}
				else
				{
					License.refresh(clientId);

					License.get(clientId, host).then(license => {
						if (application === null)
						{
							application = require("./application");
						}

						application.getAdapter().postIpcNotification(Notification.create({
							ipcLicenses: {
								licenses: [{
									license,
									action: "register"
								}]
							}
						}));

						callback(null, license);
					});
				}
			}
		);
	});
};

License.getSecurityKey = function(clientId, host)
{
	let globalSecurityKey = null;
	if (
		config.security &&
		typeof (config.security.key) === "string" &&
		config.security.key.length >= 32 &&
		config.security.key.length <= 512
	)
	{
		globalSecurityKey = config.security.key;
	}

	const data = `push${clientId}${host}server`;

	let securityKey =
		globalSecurityKey === null
			? crypto.randomBytes(64).toString("hex")
			: crypto.createHmac("sha256", globalSecurityKey).update(data).digest("hex")
	;

	// Test Client Id
	if (clientId === "fd818684484258a5c6f0442a070661d6" && globalSecurityKey !== null)
	{
		securityKey = globalSecurityKey;
	}

	return securityKey;
}

/**
 *
 * @param {License} license
 */
License.isValid = function(license)
{
	if (license && license.clientId)
	{
		if (!Number.isInteger(license.dateTo))
		{
			return false;
		}

		const delta = license.dateTo - Date.now();
		if (delta > 0)
		{
			return true;
		}
		else
		{
			const oneDay = 3600 * 24 * 1000;
			const twoWeeks = 3600 * 24 * 14 * 1000;

			if (Number.isInteger(license.lastCheck) && (Date.now() - license.lastCheck) > oneDay)
			{
				void License.sync(license.clientId, license.host);
			}

			if (delta > -twoWeeks)
			{
				return true;
			}
		}
	}

	return false;
};

const syncLicenses = new Set();

License.sync = async function(clientId, host)
{
	if (syncLicenses.has(clientId))
	{
		return;
	}

	syncLicenses.add(clientId);

	const license = await License.get(clientId, host, false);
	const oneDay = 3600 * 24 * 1000;

	if (license === null || (Date.now() - license.lastCheck) < oneDay)
	{
		syncLicenses.delete(clientId);
		return;
	}

	const db = await MySql.getDatabase();
	if (!db)
	{
		syncLicenses.delete(clientId);
		return;
	}

	db.query(
		'UPDATE licenses SET lastCheck = ? WHERE clientId = ?',
		[Date.now(), clientId],
		error => {
			syncLicenses.delete(clientId);

			if (error)
			{
				logger.systemError("License refresh failed.", error);
				return;
			}

			license.lastCheck = Date.now();

			const registerRequest = RegisterRequest.create({
				verificationQuery: license.verificationQuery,
				host: host
			});

			License.register(registerRequest, (error, /*License*/license) => {
				if (error)
				{
					logger.systemError("License refresh failed.", error);
				}
			});
		}
	);
};

License.refresh = function(clientId)
{
	cache.delete(clientId);
	syncLicenses.delete(clientId);
};

module.exports = License;