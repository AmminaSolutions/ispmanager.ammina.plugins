const mysql = require("mysql");
const config = require("../../config");
const logger = require("../debug");

let dbConnection;

class MySql
{
	static getDatabase()
	{
		if (dbConnection)
		{
			return Promise.resolve(dbConnection);
		}

		return new Promise((resolve) => {
			const connectionConfig = typeof(config.licenseStorage) === 'object' ? config.licenseStorage : {};
			dbConnection = mysql.createConnection(connectionConfig);
			dbConnection.connect(error => {
				if (error)
				{
					logger.systemError(error);
					dbConnection = null;
				}
				else
				{
					dbConnection.query(MySql.getSchema());
				}

				resolve(dbConnection);
			});

			dbConnection.on('error', error => {
				logger.systemError(error);
				dbConnection = null;
			});
		});
	}

	static getSchema()
	{
		return ` 
			CREATE TABLE IF NOT EXISTS licenses
			(
				clientId varchar(32) not null,
			    host varchar(32) not null,
				securityKey varchar(512) not null,
				securityAlgo varchar(10),
				dateTo bigint not null,
				siteUrl varchar(100),
				verificationQuery varchar(200),
				lastCheck bigint default 0,
				UNIQUE INDEX UX_LICENCES_CLIENT_ID(clientId, host)
			);
		`;
	}
}

module.exports = MySql;