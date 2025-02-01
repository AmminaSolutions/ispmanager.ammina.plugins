const { createLogger, format, transports } = require('winston');
const DailyRotateFile = require("winston-daily-rotate-file");
const config = require("../../config");
const util = require("util");

let allowedIPs = false;
let trustProxy = false;

if (config.debug)
{
	if (Array.isArray(config.debug.ip))
	{
		allowedIPs = config.debug.ip;
	}

	trustProxy = config.debug.trustProxy === true;
}

const myFormat = format.printf(info => {
	return info.timestamp + " " + (info.message !== undefined ? info.message : "");
});

const infoLogger = new createLogger({
	format: format.combine(
		format.timestamp({ format: timestamp }),
		format.splat(),
		myFormat,
	),
	transports: [
		new transports.Console({
			name: "console",
			level: "info",
			handleExceptions: true,
		}),
		new transports.File({
			name: "info-log",
			level: "info",
			maxsize: 1024 * 1024 * 10,
			dirname: config.debug.folderName,
			filename: 'info.log',
		})
	],
	level: 'info',
});

const debugLogger = new createLogger({
	format: format.combine(
		format.timestamp({ format: timestamp }),
		format.splat(),
		myFormat,
	),
	transports: [
		new DailyRotateFile({
			name: "debug-log",
			level: "debug",
			maxSize: 1024 * 1024 * 10,
			dirname: config.debug.folderName,
			filename: 'debug.%DATE%.log',
			datePattern: "YYYY-MM-DD",
		})
	],
	level: 'debug',
});

const errorLogger = new createLogger({
	format: format.combine(
		format.timestamp({ format: timestamp }),
		format.splat(),
		myFormat,
	),
	transports: [
		new DailyRotateFile({
			name: "error-log",
			level: "error",
			maxSize: 1024 * 1024 * 10,
			dirname: config.debug.folderName,
			filename: 'error.%DATE%.log',
			datePattern: "YYYY-MM-DD",
			handleExceptions: true,
		}),
	],
	level: 'error',
});

const systemErrorLogger = new createLogger({
	format: format.combine(
		format.timestamp({ format: timestamp }),
		format.splat(),
		myFormat,
	),
	transports: [
		new DailyRotateFile({
			name: "system-error-log",
			level: "error",
			maxSize: 1024 * 1024 * 10,
			dirname: config.debug.folderName,
			filename: 'system-error.%DATE%.log',
			datePattern: "YYYY-MM-DD",
			handleExceptions: true,
		}),
	],
	level: 'error',
});

const makeFormatTemplate = (args) => {
	return args.reduce((format, argument) => {
		if (typeof(argument) === 'string')
		{
			format += '%s';
		}
		else if (typeof(argument) === 'number')
		{
			format += '%d';
		}
		else
		{
			format += '%j';
		}

		return format + ' ';

	}, '').trim();

};

const logger = {

	info: function(...args)
	{
		infoLogger.info(...args);
	},

	debug: function(...args)
	{
		debugLogger.debug(makeFormatTemplate(args), ...args);
	},

	error: function(...args)
	{
		errorLogger.error(makeFormatTemplate(args), ...args);
	},

	/**
	 *
	 * @param connection
	 * @param args
	 */
	errorConnection: function(connection, ...args)
	{
		if (!connection.isTestConnection())
		{
			args.push(connection.getIp());
			errorLogger.error(makeFormatTemplate(args), ...args);
		}
	},

	systemError: function(...args)
	{
		systemErrorLogger.error(makeFormatTemplate(args), ...args);
	},

	initSocket: function(socket)
	{
		socket.bxDebugStart = new Date();
	},

	initTLSSocket: function(tlsSocket)
	{
		if (!tlsSocket || !tlsSocket._parent || !tlsSocket._parent.bxDebugStart)
		{
			return;
		}

		tlsSocket.bxDebugStart = tlsSocket._parent.bxDebugStart;
		tlsSocket.bxDebugStartTLS = new Date();
	},

	/**
	 *
	 * @param request
	 * @param response
	 */
	debugHttpRequest: function(request, response)
	{
		if (!request || !request.socket || !request.socket.bxDebugStart)
		{
			return;
		}

		const socket = request.socket;
		const forwarded = request.headers["x-forwarded-for"];
		const ipAddress = trustProxy && forwarded ? forwarded : socket.remoteAddress;

		if (allowedIPs === false || !isValidIp(ipAddress, allowedIPs))
		{
			return;
		}

		const startTime = socket.bxDebugStart;
		const id = getUniqueId();
		socket.bxDebugId = id;
		socket.bxIpAddress = ipAddress;

		debugLogger.debug('%s %s %s %s', id, "[TCP-CONNECTION]", formatDate(startTime), ipAddress);
		if (socket.bxDebugStartTLS)
		{
			debugLogger.debug('%s %s %s %s', id, "[TLS-CONNECTION]", formatDate(socket.bxDebugStartTLS), ipAddress);
		}

		debugLogger.debug(
			'%s %s %s %s',
			id,
			"[" + request.method + (request.upgrade ? "-UPGRADE" : "") + "]",
			request.url,
			ipAddress
		);

		request.on("close", function() {
			debugLogger.debug('%s %s %s %s', id, "[CLOSED-BY-CLIENT]", (Date.now() - startTime) + "ms", ipAddress);
		});

		if (response)
		{
			response.on("close", function() {
				debugLogger.debug(
					'%s %s %s %s %s',
					id,
					"[CLOSED]",
					(Date.now() - startTime) + "ms",
					this.statusCode,
					ipAddress
				);
			});

			response.on("finish", function() {
				debugLogger.debug(
					'%s %s %s %s %s',
					id,
					"[FINISHED]",
					(Date.now() - startTime) + "ms",
					this.statusCode,
					ipAddress
				);
			});
		}
	},

	debugWebsocket: function(request, socket)
	{
		if (!request || !request.socket || !request.socket.bxDebugId)
		{
			return;
		}

		const id = request.socket.bxDebugId;
		const startTime = request.socket.bxDebugStart;

		debugLogger.debug(
			'%s %s %s %s',
			id,
			"[WS-CONNECTION]",
			request.url,
			request.socket.bxIpAddress
		);

		socket.on("close", (code, message) => {
			debugLogger.debug(
				'%s %s %s %s %s %s',
				id,
				"[WS-CLOSED]",
				code,
				message,
				(Date.now() - startTime) + "ms",
				request.socket.bxIpAddress
			);
		});
	},

	profileStart: function(connection)
	{
		if (!connection.isDebugMode())
		{
			return null;
		}

		return new Date().getTime();
	},

	profileEnd: function(connection, startDate, ...args)
	{
		if (startDate === null || !connection.isDebugMode())
		{
			return;
		}

		args.unshift(connection.getSocket().bxDebugId);
		args.push((new Date().getTime() - startDate) + "ms", connection.getSocket().bxIpAddress);

		debugLogger.debug(makeFormatTemplate(args), ...args);
	}
};

module.exports = logger;

function isValidIp(ip, allowed)
{
	if (!util.isString(ip))
	{
		return false;
	}

	for (let i = 0, len = allowed.length; i < len; i++)
	{
		if (ip.indexOf(allowed[i]) !== -1)
		{
			return true;
		}
	}

	return false;
}

function timestamp()
{
	return formatDate(new Date());
}

function padding(number)
{
	if (number < 10)
	{
		return "0" + number;
	}

	return number;
}

function formatDate(date)
{
	return date.getFullYear() +
		"-" + padding(date.getMonth() + 1) +
		"-" + padding(date.getDate()) +
		" " + padding(date.getHours()) +
		":" + padding(date.getMinutes()) +
		":" + padding(date.getSeconds()) +
		"." + (date.getMilliseconds() / 1000).toFixed(3).slice(2, 5);
}

let requestId = 0;
function getUniqueId()
{
	return process.pid + "T" + (++requestId).toString().padStart(8, "0");
}