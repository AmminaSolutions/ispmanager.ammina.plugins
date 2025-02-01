/* eslint-disable no-unused-vars */
/**
 * @interface
 * @abstract
 */
class Storage
{
	/**
	 *
	 * @param {Application} application
	 */
	constructor(application)
	{
	}

	/**
	 * @abstract
	 * @param {IncomingMessage} incomingMessage
	 * @param {function(Error, OutgoingMessage)} callback
	 */
	set(incomingMessage, callback)
	{
		throw new Error("The method is not implemented");
	}

	/**
	 * @abstract
	 * @param {Receiver[]} receivers
	 * @param {string} since
	 * @param {function(Error, OutgoingMessage[])} callback
	 */
	get(receivers, since, callback)
	{
		throw new Error("The method is not implemented");
	}

	/**
	 *
	 * @param {Receiver[]} receivers
	 * @param {function(Error, OutgoingMessage)} callback
	 */
	getLastMessage(receivers, callback)
	{
		throw new Error("The method is not implemented");
	}
}

module.exports = Storage;