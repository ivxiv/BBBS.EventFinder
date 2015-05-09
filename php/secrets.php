<?php
/*
secrets.php
Saturday August 31, 2014 11:01am Stefan S.

*/

/* ---------- includes */

require_once "universal/universal.php";

/* ---------- constants */

define("GOOGLE_CALENDAR_API_KEY",					"your_google_api_key_goes_here");

// ArcGIS API keys
// See: https://developers.arcgis.com/rest/geocode/api-reference/geocoding-authenticate-a-request.htm
define("BBBS_ARCGIS_API_CLIENT_ID",					"your_arcgis_api_client_id_goes_here");
define("BBBS_ARCGIS_API_CLIENT_SECRET",				"your_arcgis_api_client_secret_goes_here");

// SQL database settings - vary depending on hosting environment
if (TRUE === LOCAL_DEVELOPMENT_DEPLOYMENT)
{
	// database hostname:port
	define("MYSQL_DB_HOST",								"dev_db_host");
	// database username
	define("MYSQL_DB_USERNAME",							"dev_db_username_goes_here");
	// database password
	define("MYSQL_DB_PASSWORD",							"dev_db_user_password_goes_here");
	// database name
	define("MYSQL_DB_DATABASE_NAME",					"dev_database_name_goes_here");
}
else if (TRUE === LIVE_DEPLOYMENT)
{
	// database hostname:port
	define("MYSQL_DB_HOST",								"live_db_host");
	// database username
	define("MYSQL_DB_USERNAME",							"live_db_username_goes_here");
	// database password
	define("MYSQL_DB_PASSWORD",							"live_db_user_password_goes_here");
	// database name
	define("MYSQL_DB_DATABASE_NAME",					"live_database_name_goes_here");
}

?>
