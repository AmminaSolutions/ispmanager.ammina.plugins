const Connection = require("./connection");
const { ResponseBatch } = require("../models");
const WSWebSocket = require("ws");
const app = require("../application");

class WebSocket extends Connection
{
	constructor(request, websocket, license = null)
	{
		super(request, true, license);

		this.websocket = websocket;
		this.type = "websocket";

		this.cacheTimeoutId = null;
		this.responseBatch = null;
		this.sendBuffer = this.sendBuffer.bind(this);

		this.pingInterval = setInterval(this.ping.bind(this), 120000);
		this.websocket.on("pong", this.handlePong.bind(this));
		this.websocket.on("close", this.handleClose.bind(this));

		if (this.isBinaryMode())
		{
			this.websocket.on("message", this.handleMessage.bind(this));
		}

		this.websocket.on("error", this.handleError.bind(this));
	}

	/**
	 *
	 * @param {Response} response
	 */
	send(response)
	{
		if (this.bufferResponse(response) || !this.isActive())
		{
			return;
		}

		const batch = new ResponseBatch();
		batch.responses.push(response);
		this.dispatch(batch);

		this.cacheTimeoutId = setTimeout(this.sendBuffer, 400);
	}

	/**
	 *
	 * @param {number} status
	 * @param {string} [reason]
	 */
	close(status, reason)
	{
		this.active = false;
		status = status < 1000 ? 1000 : status;
		this.websocket.close(status, Connection.getStatusText(status, reason));
	}

	/**
	 *
	 * @param {Response} response
	 * @return {boolean}
	 */
	bufferResponse(response)
	{
		if (!this.cacheTimeoutId)
		{
			return false;
		}

		if (this.responseBatch === null)
		{
			this.responseBatch = new ResponseBatch();
		}

		this.responseBatch.responses.push(response);

		return true;
	}

	sendBuffer()
	{
		clearInterval(this.cacheTimeoutId);
		this.cacheTimeoutId = null;

		if (this.responseBatch !== null && this.isActive())
		{
			this.dispatch(this.responseBatch);
		}

		this.responseBatch = null;
	}

	/**
	 *
	 * @param {ResponseBatch} responseBatch
	 */
	dispatch(responseBatch)
	{
		let data = null;
		if (this.isBinaryMode())
		{
			data = ResponseBatch.encode(responseBatch).finish();
		}
		else
		{
			data = Connection.convertResponseBatch(responseBatch);
		}

		if (data !== null && data !== "")
		{
			this.websocket.send(
				data,
				() => {
					this.debugDispatch(responseBatch, data);
				}
			);
		}
	}

	isActive()
	{
		return super.isActive() && this.websocket.readyState === WSWebSocket.OPEN;
	}

	handleClose()
	{
		clearTimeout(this.pingInterval);
		this.active = false;
		this.emit("close");
	}

	ping()
	{
		if (this.active === false)
		{
			return this.websocket.terminate();
		}

		this.active = false;
		this.websocket.ping("ws ping", false, () => {});
	}

	handlePong()
	{
		this.active = true;
		this.emit("pong");
	}

	handleMessage(data)
	{
		app.processClientRequest(data, this, false);
	}

	handleError(error, errorCode)
	{
		//logger.error("Websocket Error:", error, errorCode);
	}
}

module.exports = WebSocket;