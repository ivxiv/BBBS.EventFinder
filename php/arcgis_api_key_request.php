<?php
/*
arcgis_api_key_request.php
Sunday August 31, 2014 2:10pm Stefan S.

provides access token for calling into ESRI ArcGIS geocoding functionality

See:
https://developers.arcgis.com/rest/geocode/api-reference/geocoding-authenticate-a-request.htm

NOTES:

returns a JSON object as follows:
{
	"success":boolean,
	"access_token":string,
	"expires_in":int
}

*/

/* ---------- includes */

require_once "universal/universal.php";

require_once "perform_http_get.php";
require_once "secrets.php";

/* ---------- constants */

define("ARCGIS_API_ACCESS_TOKEN_KEY",		"access_token");

/* ---------- functions */

// returns ArcGIS API token in JSON format
// https://www.arcgis.com/sharing/oauth2/token?client_id=<YOUR CLIENT ID>&grant_type=client_credentials&client_secret=<YOUR CLIENT SECRET>&f=json
function generate_arcgis_api_token()
{
	$body_result= NULL;
	$query_kvp= array(
		"client_id" => BBBS_ARCGIS_API_CLIENT_ID,
		"grant_type" => "client_credentials",
		"client_secret" => BBBS_ARCGIS_API_CLIENT_SECRET,
		"f" => "json"
	);
	$json_result= perform_http_get_json("https://www.arcgis.com/sharing/oauth2/token", $query_kvp, TRUE);
	
	if (!is_null($json_result))
	{
		$body_result= str_ireplace(ARCGIS_API_ACCESS_TOKEN_KEY, "success\":true,\"access_token", $json_result);
	}
	else
	{
		$body_result= "{\"success\":false}";
	}
	
	return $body_result;
}

?>
