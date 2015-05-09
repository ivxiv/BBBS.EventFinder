<?php
/*
arcgis_geocode.php
Wednesday September 10, 2014 5:02pm Stefan S.

provides interface to ESRI ArcGIS geocoding functionality

See:
https://developers.arcgis.com/rest/geocode/api-reference/geocoding-geocode-addresses.htm
e.g.: //http://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/geocodeAddresses?addresses={"records":[{"attributes":{"OBJECTID":1,"SingleLine":"380 New York St., Redlands, CA, 92373",}},{"attributes":{"OBJECTID":2,"SingleLine":"1 World Way, Los Angeles, CA, 90045",}}]}&sourceCountry=USA&token=<YOUR TOKEN>&f=pjson

*/

/* ---------- includes */

require_once "universal/universal.php";

require_once "perform_http_get.php";
require_once "secrets.php";

require_once "arcgis_api_key_request.php";
require_once "bbbs_event.php";
require_once "sql_database_interface.php";

/* ---------- constants */

define("ADDRESS_FOUND_JSON_KEY",								"address_found");

// if 'TRUE', low-scoring geocode results will be cached to the database
// this might be desired, for instance to prevent re-querying poor addresses
define("CACHE_LOW_SCORE_GEOCODE_RESULTS",						TRUE);

// the following are the result keys returned from an ArcGIS batch geocode query
// json_data.spatialReference[]
define("k_key_json_key_spatial_reference",						"spatialReference");
// json_data.spatialReference[wkid]
define("k_key_json_key_spatial_reference_wkid",					"wkid");
// json_data.locations[]
define("k_json_key_locations",									"locations");
// json_data.locations[][attributes]
define("k_json_key_attributes",									"attributes");
// json_data.locations[][attributes][ResultID]
define("k_json_key_result_id",									"ResultID");
// json_data.locations[][attributes][DisplayX]
define("k_json_key_display_x",									"DisplayX");
define("k_json_key_display_y",									"DisplayY");
// json_data.locations[][location]
define("k_json_key_location",									"location");
// json_data.locations[][score]
define("k_json_key_score",										"score");
// json_data.locations[][location][x]
define("k_json_key_x",											"x");
// json_data.locations[][location][y]
define("k_json_key_y",											"y");

define("k_geocode_score_acceptance_threshold",					80);

// ArcGIS map defaults to web mercator projection, which is not what batch geocoding returns by default!!!
// see: http://gis.stackexchange.com/questions/9442/arcgis-coordinate-system
define("k_web_mercator_projection_spatial_reference",			102100);

define("k_trim_whitespace_and_comma",							" \t\n\r\0\x0B,");

/* ---------- classes */

// this is a utility class which gets serialized to JSON for sending to the ArcGIS REST API geocodeAddresses
// ref: http://resources.arcgis.com/en/help/arcgis-rest-api/index.html#//02r300000003000000
// addresses={"records":[{"attributes":{"OBJECTID":1,"Address":"380 New York St","Neighborhood":"","City":"Redlands","Subregion":"","Region":"CA"}},{"attributes":{"OBJECTID":2,"Address":"1 World Way","Neighborhood":"","City":"Los Angeles","Subregion":"","Region":"CA"}}]}
class c_arcgis_address_attributes
{
	/* ---------- members */
	
	public $OBJECTID= NULL;
	public $SingleLine= "";
	
	/* ---------- methods */
	
	function __construct($object_id, $single_line)
	{
		// perform some fixup on input address, to improve our chances of success
		// if it starts with a number, it's probably OK; otherwise, attempt some repair
		$use_address= trim($single_line);
		$characters= str_split($use_address);
		for ($offset= 0; ($offset < count($characters)) && (!is_numeric($characters[$offset])); $offset+= 1)
		{
			//
		}
		if ((0 < $offset) && ($offset < count($characters)))
		{
			$use_address= substr($use_address, $offset);
			debug::log("repaired single line address [{$single_line}] => [{$use_address}]");
		}
		
		$this->OBJECTID= $object_id;
		$this->SingleLine= $use_address;
		
		return;
	}
}

// ArcGIS address input record
// ref: http://resources.arcgis.com/en/help/arcgis-rest-api/index.html#//02r300000003000000
// addresses={"records":[{"attributes":{"OBJECTID":1,"Address":"380 New York St","Neighborhood":"","City":"Redlands","Subregion":"","Region":"CA"}},{"attributes":{"OBJECTID":2,"Address":"1 World Way","Neighborhood":"","City":"Los Angeles","Subregion":"","Region":"CA"}}]}
class c_arcgis_address_input_record
{
	/* ---------- members */
	
	// an instance of c_arcgis_address_attributes
	public $attributes= NULL;
	
	/* ---------- methods */
	
	function __construct($object_id, $single_line_address)
	{
		$this->attributes= new c_arcgis_address_attributes($object_id, $single_line_address);
		
		return;
	}
}


/* ---------- functions */

// returns the input events array with any geocoding results that could be obtained via ArcGIS batch geocoding
// $bbbs_events_array is an array of PHP 'c_bbbs_event' objects
function arcgis_geocode_bbbs_event_addresses($bbbs_events_array)
{
	$sql_connection= NULL;
	$arcgis_api_token= NULL;
	$batch_addresses= array();
	$cached_address_count= 0;
	
	if (!is_null($bbbs_events_array) && (0 < count($bbbs_events_array)))
	{
		debug::log("parsing " . count($bbbs_events_array) . " events...");
		
		// obtain ArcGIS API token
		$arcgis_token_json= generate_arcgis_api_token();
		
		if (!is_null($arcgis_token_json))
		{
			$json_object= json_decode($arcgis_token_json, true);
			
			if (!is_null($json_object))
			{
				if (isset($json_object[JSON_KEY_SUCCESS]) &&
					TRUE === $json_object[JSON_KEY_SUCCESS] &&
					isset($json_object[ARCGIS_API_ACCESS_TOKEN_KEY]))
				{
					$arcgis_api_token= $json_object[ARCGIS_API_ACCESS_TOKEN_KEY];
				}
				else
				{
					debug::log("arcgis_geocode_addresses() failed to decode JSON for ArcGIS API token!");
				}
			}
		} // ArcGIS API token
		
		// any previously obtained geocoding results are tracked here, so as to avoid attempts at duplicate entries into the db (which would fail anyway)
		$already_cached_addresses= array();
		
		// assemble input for batch geocode operation
		if (!is_null($arcgis_api_token))
		{
			$sql_connection= initialize_database_connection();
			
			if (!is_null($sql_connection))
			{
				$event_index= 0;
				
				foreach ($bbbs_events_array as $input_event)
				{
					if (is_array($input_event))
					{
						debug::error("arcgis_geocode_addresses(): input_event [" . $event_index . "] was an array, not a c_bbbs_event! input_event= " . print_r($input_event, TRUE));
						break;
					}
					
					if ("c_bbbs_event" != get_class($input_event))
					{
						debug::error("arcgis_geocode_addresses(): input_event [" . $event_index . "] not a c_bbbs_event! input_event= " . print_r($input_event, TRUE));
						break;
					}
					
					// get the input event data (we're about to attempt to retrieve ArcGIS data for it)
					$input_event_data= $input_event->get_event_data();
					
					// $input_event[c_bbbs_event::k_key_arcgis_data] is expected to be an array itself
					if (!is_array($input_event_data[c_bbbs_event::k_key_arcgis_data]))
					{
						debug::error("arcgis_geocode_addresses(): input_event_data[". $event_index . "][" . c_bbbs_event::k_key_arcgis_data . "] needs to be an array, but is not!");
						break;
					}
					
					$event_location_index= 0;
					
					foreach ($input_event_data[c_bbbs_event::k_key_arcgis_data] as $address_entry)
					{
						$single_line_address= $address_entry[c_bbbs_event::k_key_arcgis_single_line_address];
						
						// the absolute index into the events array is used as the object identifier for now
						//##stefan $NOTE might want to use a Google Calendar event id here?
						// for now, we assume there could be up to c_bbbs_event::k_maximum_locations_per_event locations per event
						$location_object_index= (int)(c_bbbs_event::k_maximum_locations_per_event * $event_index + $event_location_index++);
						
						if (c_bbbs_event::k_maximum_locations_per_event <= $event_location_index)
						{
							debug::log("arcgis_geocode_addresses():address_geocode_data_try_and_get(): found an event with " . c_bbbs_event::k_maximum_locations_per_event . "+ locations!");
							break;
						}
						
						// do we already have ArcGIS positional data cached for this address?
						// result JSON: { "success": <boolean> [, "address_found": boolean] }
						$cached_arcgis_data_json= address_geocode_data_try_and_get($sql_connection, $single_line_address);
						$cached_arcgis_data= json_decode($cached_arcgis_data_json, TRUE);
						
						if (!is_null($cached_arcgis_data))
						{
							if (isset($cached_arcgis_data[JSON_KEY_SUCCESS]) &&
								(TRUE === $cached_arcgis_data[JSON_KEY_SUCCESS]))
							{
								if (isset($cached_arcgis_data[ADDRESS_FOUND_JSON_KEY]) &&
									TRUE === $cached_arcgis_data[ADDRESS_FOUND_JSON_KEY] &&
									isset($cached_arcgis_data[k_db_row_address]) &&
									isset($cached_arcgis_data[k_db_row_address_hash]) &&
									isset($cached_arcgis_data[k_db_row_geocode_score]) &&
									isset($cached_arcgis_data[k_db_row_spatial_reference]) &&
									isset($cached_arcgis_data[k_db_row_x]) &&
									isset($cached_arcgis_data[k_db_row_y]) &&
									isset($cached_arcgis_data[k_db_row_display_x]) &&
									isset($cached_arcgis_data[k_db_row_display_y]) &&
									!empty($cached_arcgis_data[k_db_row_address]) &&
									!empty($cached_arcgis_data[k_db_row_address_hash]))
									/* NOTE some of these may be '0' for 'poor addresses', so this sort of checking is too agressive
									&& !empty($cached_arcgis_data[k_db_row_geocode_score]))*/
								{
									// we haved cached geocoding data for this entry; attach it to this event record
									//###stefan $NOTE this uses the cached result regardless of the score value which was previously saved
									//debug::log("found cached address data for [" . $single_line_address . "] : " . $cached_arcgis_data_json);
									$input_event->add_arcgis_geocoding_data(
										$event_location_index,
										$single_line_address,
										$cached_arcgis_data[k_db_row_geocode_score],
										$cached_arcgis_data[k_db_row_spatial_reference],
										$cached_arcgis_data[k_db_row_x],
										$cached_arcgis_data[k_db_row_y],
										$cached_arcgis_data[k_db_row_display_x],
										$cached_arcgis_data[k_db_row_display_y]);
									
									// remember the fact that we already have this address data in the db, to avoid duplicate attempts to cache it in the same program run
									$already_cached_addresses[]= $single_line_address;
									$cached_address_count+= 1;
									//debug::log("Found {$cached_address_count} cached addresses...");
								}
								else if (isset($cached_arcgis_data[ADDRESS_FOUND_JSON_KEY]) &&
									(FALSE === $cached_arcgis_data[ADDRESS_FOUND_JSON_KEY]))
								{
									// no cached geocoding data for this address; add this entry to our batch geocoding request input
									//debug::log("No geocode data for [" . $single_line_address . "]");
									$input_record= new c_arcgis_address_input_record($location_object_index, $single_line_address);
									
									$batch_addresses[]= $input_record;
									//debug::log("Need to geocode " . count($batch_addresses) . " addresses...");
								}
								else
								{
									debug::error("arcgis_geocode_addresses():address_geocode_data_try_and_get() failed to query db on input address [{$single_line_address}]!");
								}
							}
							else
							{
								debug::error("arcgis_geocode_bbbs_event_addresses(): failed to query database!");
							}
						}
						else
						{
							debug::error("arcgis_geocode_bbbs_event_addresses(): failed to decode ArcGIS JSON [{$cached_arcgis_data_json}]!");
						}
					} // foreach ($input_event_data[c_bbbs_event::k_key_arcgis_data] as $address_entry)
					$event_index+= 1;
				} // foreach ($bbbs_events_array as $input_event)
			}
			else
			{
				debug::error("arcgis_geocode_bbbs_event_addresses(): failed to connect to db for cached geocoding data lookup!");
			}
		}
		else
		{
			debug::error("arcgis_geocode_bbbs_event_addresses(): failed to retrieve an ArcGIS API token!");
		}
		
		// perform batch geocoding call
		if (0 < count($batch_addresses))
		{
			$batch_geocode_response= NULL;
			$batch_geocode_response_json= "";
			
			//###stefan $TODO perform some fixup on singleline address that don't start w/ street number for ArcGIS geocoding 
			debug::log("arcgis_geocode_bbbs_event_addresses(): batch geocoding " . count($batch_addresses) . " addresses...");
			
			//addresses={"records":[{"attributes":{"OBJECTID":1,"Address":"380 New York St","Neighborhood":"","City":"Redlands","Subregion":"","Region":"CA"}},{"attributes":{"OBJECTID":2,"Address":"1 World Way","Neighborhood":"","City":"Los Angeles","Subregion":"","Region":"CA"}}]}
			$batch_addresses_json= "{\"records\":" . json_encode($batch_addresses) . "}";
			$batch_geocode_url= "http://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/geocodeAddresses";
			// NOTE: forcing web mercator projection spacial reference!
			$query_kvp= array(
				"addresses"=> $batch_addresses_json,
				"sourceCountry"=> "USA",
				"token"=> $arcgis_api_token,
				"outSR"=> k_web_mercator_projection_spatial_reference,
				"f"=> "json"
			);
			$batch_geocode_response_json= perform_http_get_json($batch_geocode_url, $query_kvp, TRUE);
			if (!is_null($batch_geocode_response_json))
			{
				$batch_geocode_response= json_decode($batch_geocode_response_json, TRUE);
			}
			
			if (!is_null($batch_geocode_response) &&
				isset($batch_geocode_response[k_key_json_key_spatial_reference]) &&
				isset($batch_geocode_response[k_json_key_locations]) &&
				is_array($batch_geocode_response[k_json_key_locations]))
			{
				// store the spatial reference
				$spatial_reference= $batch_geocode_response[k_key_json_key_spatial_reference][k_key_json_key_spatial_reference_wkid];
				
				// iterate through results and act accordingly
				foreach ($batch_geocode_response[k_json_key_locations] as $location)
				{
					$location_point= (isset($location[k_json_key_location]) && is_array($location[k_json_key_location])) ? $location[k_json_key_location] : NULL;
					$location_attributes= isset($location[k_json_key_attributes]) ? $location[k_json_key_attributes] : NULL;
					$single_line_address= "";
					$location_score= 0.0;
					$location_x= 0.0;
					$location_y= 0.0;
					$display_x= 0.0;
					$display_y= 0.0;
					
					if (is_null($location_attributes) || !isset($location_attributes[k_json_key_result_id]))
					{
						// if we didn't get back ResultID, skip this entry entirely
						continue;
					}
					
					// ResultID is passed in as ((event_index * c_bbbs_event::k_maximum_locations_per_event) + location_index)
					$result_id= (int)$location_attributes[k_json_key_result_id];
					$event_location_index= (int)($result_id % (int)c_bbbs_event::k_maximum_locations_per_event);
					$event_index= (int)(($result_id - $event_location_index) / (int)c_bbbs_event::k_maximum_locations_per_event);
					$event_data_array= $bbbs_events_array[$event_index]->get_event_data();
					$single_line_address= $event_data_array[c_bbbs_event::k_key_arcgis_data][$event_location_index][c_bbbs_event::k_key_arcgis_single_line_address];
					
					if (is_null($location_point) ||
						!isset($location[k_json_key_score]) ||
						!isset($location_attributes[k_json_key_display_x]) ||
						!isset($location_attributes[k_json_key_display_y]))
					{
						// don't bother logging this, it happens regularly for events that couldn't be geocoded
						//debug::warning("arcgis_geocode_bbbs_event_addresses(): batch geocoding returned a location with missing data! [" . print_r($location, TRUE) . "]");
						// we will go ahead and cache this entry to the database so that we don't bother trying to geocode it again in the future
					}
					else
					{
						$location_score= $location[k_json_key_score];
						$location_x= $location_point[k_json_key_x];
						$location_y= $location_point[k_json_key_y];
						$display_x= (double)$location_attributes[k_json_key_display_x];
						$display_y= (double)$location_attributes[k_json_key_display_y];
					}
					
					// save off whatever we found
					$bbbs_events_array[$event_index]->add_arcgis_geocoding_data($event_location_index, $single_line_address, $location_score, $spatial_reference, $location_x, $location_y, $display_x, $display_y);
					
					if ((k_geocode_score_acceptance_threshold <= $location_score) || CACHE_LOW_SCORE_GEOCODE_RESULTS)
					{
						// update database with any addresses we didn't previously have on record
						if (!in_array($single_line_address, $already_cached_addresses))
						{
							// save this geocode result out to the database
							$db_update_result_json= address_set_geocode_data($sql_connection, $single_line_address, $location_score, $spatial_reference, $location_x, $location_y, $display_x, $display_y);
							
							$db_update_result= json_decode($db_update_result_json, TRUE);
							
							if (!is_null($db_update_result) &&
								isset($db_update_result[JSON_KEY_SUCCESS]) &&
								TRUE === $db_update_result[JSON_KEY_SUCCESS])
							{
								// db update successful! remember that we already cached this address, to avoid duplicate attempts in the same run
								$already_cached_addresses[]= $single_line_address;
							}
							else
							{
								//debug::log(print_r($already_cached_addresses, TRUE));
								debug::error("arcgis_geocode_bbbs_event_addresses(): failed to update db with geocode data for event[" . $event_index . "].address [" . $location_index . "] <" .
									$single_line_address . ">! db result JSON= " . $db_update_result_json);
							}
						}
						else
						{
							// this address has already been cached to the db
							//debug::log("we've already cached address [" . $single_line_address . "] to the database");
						}
					}
					else
					{
						debug::log("arcgis_geocode_bbbs_event_addresses(): disregarding batch geocoding results for id= [" .
							$location_index . "] : score [" . $location_score . "] is below threshold value [" .
							k_geocode_score_acceptance_threshold . "].");
					}
				} // foreach(location)
			}
			else
			{
				debug::error("arcgis_geocode_bbbs_event_addresses(): batch geocoding returned an unexpected response! JSON= [" . $batch_geocode_response_json . "] => [" . print_r($batch_geocode_response, TRUE) . "]");
			}
		}
		else if (($cached_address_count == count($batch_addresses)) || (0 == count($batch_addresses)))
		{
			// this means that every input event address had already been cached, or there is nothing to check - so, nothing more needed
			//debug::log("arcgis_geocode_bbbs_event_addresses(): all addresses querried were previously cached, nothing to do (yay!)");
		}
		else
		{
			debug::warning("arcgis_geocode_addresses() failed to generate batch address request input!");
		}
	}
	else
	{
		debug::log("arcgis_geocode_addresses() called with no addresses to query!");
	}
	
	// close db connection
	if (!is_null($sql_connection))
	{
		dispose_database_connection($sql_connection);
	}
	
	// return the input array, now with any discovered geocoding results added to it
	
	return $bbbs_events_array;
}

?>
