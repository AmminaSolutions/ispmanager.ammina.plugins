const crypto = require("crypto");
const config = require("../config");
const License = require("./license");

const algorithm = config.security && config.security.algo ? config.security.algo : "sha1";
const securityKey = config.security && config.security.key ? config.security.key : crypto.randomBytes(64).toString("hex");

class Signature
{
	/**
	 *
	 * @param {Buffer|string} data
	 * @param {string} key
	 * @returns {Buffer}
	 */
	static getDigest(data, key)
	{
		const hmac = crypto.createHmac(algorithm, key);
		hmac.update(data);

		return hmac.digest();
	}

	/**
	 *
	 * @param {Buffer|string} data
	 * @param {string} key
	 * @returns {Buffer}
	 */
	static getPublicDigest(data, key)
	{
		return Signature.getDigest(
			Buffer.isBuffer(data) ? "public:" + data.toString("hex") : "public:" + data,
			key
		);
	}

	/**
	 *
	 * @param {string|Buffer} data
	 * @param {string|Buffer} signature
	 * @param {?License} [license=null]
	 * @returns {boolean}
	 */
	static isValid(data, signature, license = null)
	{
		if (typeof(signature) !== "string" && !Buffer.isBuffer(signature))
		{
			return false;
		}

		const key = license !== null && License.isValid(license) ? license.securityKey : securityKey;
		const digest = Signature.getDigest(data, key);

		if (Buffer.isBuffer(signature))
		{
			return digest.equals(signature);
		}
		else
		{
			return digest.toString("hex") === signature;
		}
	}
}

module.exports = Signature;