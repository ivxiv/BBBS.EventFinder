<?php
/*
universal.php
Monday July 14, 2014 7:47pm Stefan S.

include common functionality

*/

/* ---------- constants */

define("NONE",									(int)(-1));

// all network calls returning a JSON structure will include
// a boolean 'success' parameter
define("JSON_KEY_SUCCESS",						"success");

// $DEPLOYMENT - settings to indicate deployment location
// local system deployment environment (ie, testing on the localhost)
define("LOCAL_DEVELOPMENT_DEPLOYMENT",			TRUE );
// live deployment
define("LIVE_DEPLOYMENT",						(FALSE === LOCAL_DEVELOPMENT_DEPLOYMENT));

/* ---------- includes */

require_once "debug.php";

?>
