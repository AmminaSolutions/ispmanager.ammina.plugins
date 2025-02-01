const Connection = require("./connection");
const logger = require("../debug");
const { ResponseBatch } = require("../models");
const app = require("../application");

class Polling extends Connection
{
	constructor(request, response, license = null)
	{
		super(request, true, license);

		this.response = response;
		this.response.setTimeout(40 * 1000, this.handleTimeout.bind(this));
		this.response.on("close", this.finalize.bind(this));

		this.type = "polling";
	}

	/**
	 *
	 * @param {Response} response
	 */
	send(response)
	{
		if (!this.isActive())
		{
			return;
		}

		const headers = {
			"Access-Control-Allow-Origin": "*",
		};

		let data = null;
		const responseBatch = new ResponseBatch();
		responseBatch.responses.push(response);

		if (this.isBinaryMode())
		{
			data = ResponseBatch.encode(responseBatch).finish();
			headers["Content-Type"] = "application/octet-stream";
		}
		else
		{
			data = Connection.convertResponse(response);
			headers["Content-Type"] = "text/plain";
		}

		this.response.writeHead(200, headers);
		this.response.end(data, () => {
			this.debugDispatch(responseBatch, data);
		});

		this.finalize();
	}

	/**
	 *
	 * @param {number} status
	 * @param {string} [reason]
	 */
	close(status, reason)
	{
		this.closeWithHeaders(status, reason);
	}

	/**
	 *
	 * @param {number} status
	 * @param {?string} [reason]
	 * @param {object} [headers]
	 */
	closeWithHeaders(status, reason, headers)
	{
		let defaultHeaders = {
			"Content-Type": "text/plain",
			"Access-Control-Allow-Origin": "*",
			"Access-Control-Expose-Headers": "Last-Message-Id"
		};

		if (headers)
		{
			defaultHeaders = Object.assign(defaultHeaders, headers);
		}

		this.response.writeHead(Connection.getHttpStatus(status), defaultHeaders);
		this.response.end(Connection.getStatusText(status, reason));

		this.finalize();
	}

	finalize()
	{
		this.active = false;
		this.emit("close");
	}

	handleTimeout()
	{
		if (this.getMid() !== null)
		{
			this.closeWithHeaders(304, null, {
				"Last-Message-Id": this.getMid().toString("hex"),
				"Expires": "Thu, 01 Jan 1973 11:11:01 GMT"
			});
		}
		else
		{
			const startDate = logger.profileStart(this);
			app.getStorage().getLastMessage(this.getReceivers(), (error, message) => {

				logger.profileEnd(this, startDate, "[MESSAGE-LAST]", message !== null ? message.id : "none");

				this.closeWithHeaders(304, null, {
					"Last-Message-Id": message !== null ? message.id.toString("hex") : "",
					"Expires": "Thu, 01 Jan 1973 11:11:01 GMT"
				});

			});
		}
	}
}

module.exports = Polling;
