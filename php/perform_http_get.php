<?php
/*
perform_http_get.php
Monday February 15, 2015 2:34pm Stefan S.

*/

/* ---------- includes */

require_once "universal/universal.php";

/* ---------- functions */

// returns JSON on success, NULL on failure
function perform_http_get_json($base_url, $query_kvp, $encode_parameters)
{
	$json_result= NULL;
	$url= $base_url . http_query_paramters_to_string($query_kvp, $encode_parameters);
	
	$curl_handle= curl_init();
	curl_setopt($curl_handle, CURLOPT_URL, $url);
	//curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 0); // 0 means "use default", which is 300 seconds
	curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, TRUE); // TRUE means return actual result of the successful operation (failure still returns 'FALSE')
	//debug::log("Loading URL via PHP CURL: " . $url);
	$query_result= curl_exec($curl_handle);
	if (FALSE !== $query_result)
	{
		$json_result= $query_result;
	}
	else
	{
		debug::warning("error [" . curl_error($curl_handle) . "] while loading URL [" . $url . "]");
	}
	curl_close($curl_handle);
	
	return $json_result;
}

function http_query_paramters_to_string($query_kvp, $encode_parameters)
{
	$result= "";
	
	if (!is_null($query_kvp) && is_array($query_kvp))
	{
		$result.= "?";
		$initial_kvp= TRUE;
		
		foreach ($query_kvp as $key => $value)
		{
			if (FALSE === $initial_kvp)
			{
				$result.= "&";
			}
			$result.= ($key . "=" . ((TRUE === $encode_parameters) ? urlencode($value) : $value));
			$initial_kvp= FALSE;
		}
	}
	
	return $result;
}

?>
