class Statistics
{
	constructor()
	{
		this.messages = {};
		this.requests = {};

		this.websockets = 0;
		this.pollings = 0;

		this.messageTypePattern = /^[a-zA-Z0-9_*-]{1,32}$/;
		this.todayEndDate = Statistics.getTodayEndDate();
	}

	static getTodayEndDate()
	{
		const date = new Date();
		date.setHours(0, 0, 0, 0);
		date.setDate(date.getDate() + 1);

		return date;
	}

	tryResetCounters()
	{
		if (Date.now() >= this.todayEndDate)
		{
			this.todayEndDate = Statistics.getTodayEndDate();
			this.messages = {};
			this.requests = {};
		}
	}

	incrementMessage(type)
	{
		this.tryResetCounters();

		type = this.messageTypePattern.test(type) ? type : "unknown";
		this.messages[type] = (this.messages[type] || 0) + 1;
	}

	incrementRequest(command)
	{
		this.tryResetCounters();

		this.requests[command] = (this.requests[command] || 0) + 1;
	}

	getDailyStats()
	{
		this.tryResetCounters();

		return {
			requests: this.requests,
			messages: this.messages
		};
	}

	incrementConnection(connection)
	{
		connection.isWebsocket() ? this.websockets++ : this.pollings++;
	}

	decrementConnection(connection)
	{
		connection.isWebsocket() ? this.websockets-- : this.pollings--;
	}

	getWebsocketCount()
	{
		return this.websockets;
	}

	getPollingCount()
	{
		return this.pollings;
	}
}

module.exports = Statistics;
