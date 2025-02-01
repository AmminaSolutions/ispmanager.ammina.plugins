const Connection = require("./connection");
const { ResponseBatch } = require("../models");

class HttpRequest extends Connection
{
	constructor(request, response, verifySignature = true, license = null)
	{
		super(request, verifySignature, license);

		response.setTimeout(1000 * 20);

		this.response = response;
		this.type = "httprequest";
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

			if (response.json)
			{
				headers["Content-Type"] = "application/json";
			}
			else
			{
				headers["Content-Type"] = "text/plain";
			}
		}

		this.response.writeHead(200, headers);
		this.response.end(data, () => {
			this.debugDispatch(responseBatch, data);
		});

		this.active = false;
	}

	/**
	 *
	 * @param {number} status
	 * @param {string} [reason]
	 */
	close(status, reason)
	{
		this.response.writeHead(Connection.getHttpStatus(status), {
			"Content-Type": "text/plain",
			"Access-Control-Allow-Origin": "*"
		});

		this.response.end(Connection.getStatusText(status, reason));
	}
}

module.exports = HttpRequest;