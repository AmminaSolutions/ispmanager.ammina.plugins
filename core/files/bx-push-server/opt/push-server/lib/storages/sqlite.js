const SqliteDatabase = require("better-sqlite3");
const config = require("../../config");
const logger = require("../debug");

let dbConnection;

class Sqlite
{
	/**
	 *
	 * @returns {SqliteDatabase|null}
	 */
	static getDatabase()
	{
		if (dbConnection)
		{
			return dbConnection;
		}

		try
		{
			dbConnection = new SqliteDatabase(config.dataDir + "/push-server.db");
			dbConnection.exec(Sqlite.getSchema());
		}
		catch (error)
		{
			logger.systemError(error);
			dbConnection = null;
		}

		return dbConnection;
	}

	static getSchema()
	{
		return ` 
			CREATE TABLE IF NOT EXISTS licenses
			(
				id integer not null primary key,
				clientId varchar(32) not null,
				securityKey varchar(512) not null,
				securityAlgo varchar(10),
				dateTo integer not null,
				siteUrl varchar(100),
				verificationQuery varchar(200),
				lastCheck integer default 0
			);

			CREATE UNIQUE INDEX IF NOT EXISTS UX_LICENCES_CLIENT_ID ON licenses (clientId);
		`;
	}
}

module.exports = Sqlite;