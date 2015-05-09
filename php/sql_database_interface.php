<?php
/*
sql_database_interface.php
Tuesday September 09, 2014 10:48am Stefan S.

interface to the SQL database on the backend
see: create_address_data_table.sql for schema

*/

/* ---------- includes */

require_once "universal/universal.php";

require_once "bbbs_event.php";

/* ---------- constants */

define("MYSQL_DB_ADDRESS_TABLE",			"address_data");

define("k_db_row_id",						"id");
define("k_db_row_address",					"address");
define("k_db_row_address_hash",				"address_hash");
define("k_db_row_geocode_score",			"geocode_score");
define("k_db_row_spatial_reference",		"spatial_reference");
define("k_db_row_x",						"x");
define("k_db_row_y",						"y");
define("k_db_row_display_x",				"display_x");
define("k_db_row_display_y",				"display_y");

/* ---------- functions */

function initialize_database_connection()
{
	$sql_connection= mysql_connect(MYSQL_DB_HOST, MYSQL_DB_USERNAME, MYSQL_DB_PASSWORD);
	
	if (!is_null($sql_connection) && (FALSE != $sql_connection))
	{
		if (mysql_select_db(MYSQL_DB_DATABASE_NAME))
		{
			// success!
		}
		else
		{
			debug::error("initialize_database_connection():mysql_select_db(" . MYSQL_DB_DATABASE_NAME . ") failed with: " . mysql_error());
			mysql_close($sql_connection);
			$sql_connection= NULL;
		}
	}
	else
	{
		debug::error("initialize_database_connection():mysql_connect(" .
			MYSQL_DB_HOST . ", " .
			MYSQL_DB_USERNAME . ", " .
			encode_password(MYSQL_DB_PASSWORD) . ") failed with: " . mysql_error());
		$sql_connection= NULL;
	}
	
	return $sql_connection;
}

function dispose_database_connection($sql_connection)
{
	if (!is_null($sql_connection))
	{
		mysql_close($sql_connection);
	}
	
	return;
}

// query an address for geocode data
// result JSON: { "success": <boolean> [, "address_not_found": true] }
function address_geocode_data_try_and_get($sql_connection, $address_string)
{
	$address_hash= c_bbbs_event::compute_address_hash($address_string);
	$sql_query= "SELECT * FROM " . MYSQL_DB_ADDRESS_TABLE ." WHERE address_hash=\"" . $address_hash . "\"";
	$result_json= "{\"success\":false}";
	$sql_row= NULL;
	
	$sql_result= mysql_query($sql_query, $sql_connection);
	if (!is_null($sql_result))
	{
		$sql_row= mysql_fetch_array($sql_result);
		if (!is_null($sql_row))
		{
			$result_json= sql_address_row_to_json($sql_row);
		}
		else
		{
			debug::log("address_geocode_data_try_and_get():mysql_fetch_array() failed with: <" . mysql_error() . "> (address not found)");
			$result_json= "{\"success\":false,\"address_found\":false}";
		}
	}
	else
	{
		debug::log("address_geocode_data_try_and_get():mysql_query() failed with: <" . mysql_error() . "> (address not found)");
		$result_json= "{\"success\":false,\"address_found\":false}";
	}
	
	return $result_json;
}

// update an address with geocode data
// result JSON: { "success": <boolean> [,"id": <int>] }
function address_set_geocode_data($sql_connection, $address_string, $geocode_score, $spatial_reference, $x, $y, $display_x, $display_y)
{
	$address_hash= c_bbbs_event::compute_address_hash($address_string);
	$sql_query= "INSERT INTO " . MYSQL_DB_ADDRESS_TABLE ." (" .
		k_db_row_address . ", " .
		k_db_row_address_hash . ", " .
		k_db_row_geocode_score . ", " .
		k_db_row_spatial_reference . ", " .
		k_db_row_x . ", " .
		k_db_row_y . ", " .
		k_db_row_display_x . ", " .
		k_db_row_display_y . ") VALUES (\"" .
		$address_string . "\", \"" .
		$address_hash . "\", \"" .
		$geocode_score . "\", \"" .
		$spatial_reference . "\", \"" .
		$x . "\", \"" .
		$y . "\", \"" .
		$display_x . "\", \"" .
		$display_y .
		"\")";
	$result_json= "{\"success\":false}";
	$entry_id= NONE;
	
	//debug::log("executing SQL: {$sql_query}");
	$sql_result= mysql_query($sql_query);
	if (!is_null($sql_result))
	{
		$entry_id= mysql_insert_id();
		if (NONE != $entry_id)
		{
			$result_json= "{\"success\":true,\"id\":" . $entry_id . "}";
			//debug::log("SQL result (" . mysql_error() . ") : {$result_json}");
		}
		else
		{
			debug::error("address_set_geocode_data():mysql_insert_id() returned unexpected value 'NONE'! " . mysql_error());
		}
	}
	else
	{
		debug::error("address_set_geocode_data():mysql_query() failed with: " . mysql_error());
	}
	
	return $result_json;
}

// convert a SQL address table row to JSON
function sql_address_row_to_json($sql_row)
{
	$result= "";
	
	if (isset($sql_row[k_db_row_id]) &&
		isset($sql_row[k_db_row_address]) &&
		isset($sql_row[k_db_row_address_hash]) &&
		isset($sql_row[k_db_row_geocode_score]) &&
		isset($sql_row[k_db_row_spatial_reference]) &&
		isset($sql_row[k_db_row_x]) &&
		isset($sql_row[k_db_row_y]) &&
		isset($sql_row[k_db_row_display_x]) &&
		isset($sql_row[k_db_row_display_y]) &&
		!empty($sql_row[k_db_row_id]) &&
		!empty($sql_row[k_db_row_address]) &&
		!empty($sql_row[k_db_row_address_hash])
		/* NOTE this is too agressive, these may be 0 for 'poor' addresses
		&& !empty($sql_row[k_db_row_geocode_score])*/
		)
	{
		$result=
			"{" .
				"\"success\":true,\"address_found\":true," .
				"\"" . k_db_row_id . "\":" . $sql_row[k_db_row_id] . "," .
				"\"" . k_db_row_address . "\":\"" . $sql_row[k_db_row_address] . "\"," .
				"\"" . k_db_row_address_hash . "\":\"" . $sql_row[k_db_row_address_hash] . "\"," .
				"\"" . k_db_row_geocode_score . "\":" . $sql_row[k_db_row_geocode_score] . "," .
				"\"" . k_db_row_spatial_reference . "\":\"" . $sql_row[k_db_row_spatial_reference] . "\"," .
				"\"" . k_db_row_x . "\":" . $sql_row[k_db_row_x] . "," .
				"\"" . k_db_row_y . "\":" . $sql_row[k_db_row_y] . "," .
				"\"" . k_db_row_display_x . "\":" . $sql_row[k_db_row_display_x] . "," .
				"\"" . k_db_row_display_y . "\":" . $sql_row[k_db_row_display_y] .
			"}";
	}
	else
	{
		$result= "{\"success\":true,\"address_found\":false}";
	}
	
	return $result;
}

function encode_password($password_string)
{
	$result= "";
	$length= strlen($password_string);
	
	while (0 < $length)
	{
		$result.= "*";
		$length-= 1;
	}
	
	return $result;
}

?>
