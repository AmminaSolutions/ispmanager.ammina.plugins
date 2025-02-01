class Channel
{
	/**
	 *
	 * @param {Buffer} privateId
	 * @param {?Buffer} publicId
	 */
	constructor(privateId, publicId)
	{
		/**
		 *
		 * @type {Buffer}
		 */
		this.privateId = privateId;

		/**
		 *
		 * @type {?Buffer}
		 */
		this.publicId = Buffer.isBuffer(publicId) ? publicId : null;

		/**
		 *
		 * @type {?String}
		 */
		this.hexPrivateId = null;
		this.hexPublicId = null;
	}

	getPrivateId()
	{
		return this.privateId;
	}

	getHexPrivateId()
	{
		if (this.hexPrivateId === null)
		{
			this.hexPrivateId = this.privateId.toString("hex");
		}

		return this.hexPrivateId;
	}

	getPublicId()
	{
		return this.publicId;
	}

	getHexPublicId()
	{
		if (this.hexPublicId === null)
		{
			this.hexPublicId = this.publicId.toString("hex");
		}

		return this.hexPublicId;
	}

	static isValid(id)
	{
		return Buffer.isBuffer(id) && id.length === 16;
	}
}

module.exports = Channel;