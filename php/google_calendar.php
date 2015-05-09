<?php
/*
google_calendar.php
Monday July 14, 2014 7:47pm Stefan S.

Google Calendar integration utilities for BBBS Events app

NOTES:

returns a JSON object as follows:
{
	"success":boolean,
	"results":[] Array of bbbs_event objects
}

*/

/* ---------- includes */

require_once "universal/universal.php";

require_once "arcgis_geocode.php";
require_once "event_source.php";
require_once "bbbs_event.php";
require_once "perform_http_get.php";
require_once "secrets.php";

/* ---------- classes */

// Google Calendar
class c_google_calendar extends c_event_source
{
	/* ---------- constants */
	
	// URI for Google Calendar API
	const k_google_calendar_uri= "https://www.googleapis.com/calendar/v3/calendars/";
	// Google Developer API key for Google Calendar API access
	const k_google_calendar_api_key= GOOGLE_CALENDAR_API_KEY;
	
	const k_google_calendar_query_time_start= "timeMin";
	const k_google_calendar_query_time_end= "timeMax";
	
	const k_key_location= "location"; // string
	const k_key_start_time= "start"; // string
		const k_key_start_date_time= "dateTime"; // DateTime
	const k_key_end_time= "end"; // string
		const k_key_end_date_time= "dateTime"; // string
	const k_key_organizer= "creator"; // string
		const k_key_organizer_name= "displayName"; // string
		const k_key_organizer_email= "email"; // string
	const k_key_time_zone= "timeZone";
	const k_key_description= "description"; // string
	const k_key_title= "summary"; // string
	const k_key_url= "htmlLink"; // string
	
	/* ---------- members */
	
	private $m_calendar_id= "";
	
	/* ---------- public methods */
	
	// constructor
	function __construct($calendar_id)
	{
		$this->m_calendar_id= $calendar_id;
		
		return;
	}
	
	/* ---------- c_event_source methods */
	
	// get_events()
	// $start_date_time: optional input start date / time, or NULL
	// $end_date_time: optional inout end date / time, or NULL
	// returns: array of bbbs_event objects, or NULL
	public function get_events($start_date_time, $end_date_time)
	{
		$bbbs_events_array= NULL;
		
		$google_events_list_json= $this->try_and_get_events_list_json($this->m_calendar_id, $start_date_time, $end_date_time);
		
		if (!is_null($google_events_list_json))
		{
			$google_events_array= $this->parse_google_calendar_events_list_json($google_events_list_json);
			
			if (!is_null($google_events_array))
			{
				$bbbs_events_array= $this->google_calendar_events_to_bbbs_events($google_events_array);
				if (!is_null($bbbs_events_array))
				{
					$google_events_count= count($google_events_array);
					$bbbs_events_count= count($bbbs_events_array);
				}
				else
				{
					debug::warning("failed to convert Google calendar events to BBBS events");
				}
			}
			else
			{
				debug::warning("failed to parse Google events list JSON: " . $google_events_list_json);
			}
		}
		
		return $bbbs_events_array;
	}
	
	/* ---------- private methods */
	
	// returns calendar events query results in JSON format (or NULL on failure)
	// for the input Google Calendar ID
	// ref: https://developers.google.com/google-apps/calendar/v3/reference/events/list
	private function try_and_get_events_list_json($calendar_id, $start_time, $end_time)
	{
		$body_result= NULL;
		$date_time_zone= new DateTimeZone("UTC");
		$start_date_time= isset($start_time) ? new DateTime($start_time, $date_time_zone) : new DateTime("now", $date_time_zone);
		$end_date_time= isset($end_time) ? DateTime($end_time, $date_time_zone) : new DateTime($start_time . " +1 day", $date_time_zone);
		$query_kvp= array(
			"key" => self::k_google_calendar_api_key,
			self::k_google_calendar_query_time_start => $start_date_time->format(DateTime::RFC3339),
			self::k_google_calendar_query_time_end => $end_date_time->format(DateTime::RFC3339)
		);
		$url= (self::k_google_calendar_uri . urlencode($calendar_id) . "/events");
		$json_result= perform_http_get_json($url, $query_kvp, TRUE);
		if (!is_null($json_result))
		{
			$body_result= $json_result;
		}
		else
		{
			debug::warning("failed to retrieve Google Calendar events JSON via HTTP for calendar [" . $calendar_id . "]");
		}
		
		return $body_result;
	}
	
	// parse a Google calendar events list JSON blob into a php associative array
	// returns an associative array representation of the Google events list, or an empty array on failure
	private function parse_google_calendar_events_list_json($google_events_list_json)
	{
		$result= array();
		$json_object= json_decode($google_events_list_json, TRUE);
		//debug::log("PARSING: " . $google_events_list_json);
		if (!is_null($json_object))
		{
			//debug::log("PARSED: " . print_r($json_object, TRUE));
			// Google calendar events list is the "items"[] array
			$result= $json_object["items"];
		}
		else
		{
			debug::warning("json_decode() failed for calendar results!");
		}
		
		return $result;
	}
	
	// takes a *decoded* $google_event JSON object as input (ie, the $google_event is itself already an associative array)
	// returns a c_bbbs_event object
	private function google_event_to_bbbs_event($google_event)
	{
		$bbbs_event= new c_bbbs_event();
		
		$bbbs_event->initialize_from_google_calendar_event($google_event);
		
		return $bbbs_event;
	}
	
	// convert an array of Google calendar events to an array of BBBS events
	private function google_calendar_events_to_bbbs_events($google_events_array)
	{
		$bbbs_events= array();
		
		if (!is_null($google_events_array))
		{
			foreach ($google_events_array as $google_event)
			{
				// convert to BBBS event and append to the array
				$bbbs_event= $this->google_event_to_bbbs_event($google_event);
				//debug::log("found event: " . print_r($bbbs_event, TRUE));
				$bbbs_events[]= $bbbs_event;
			}
			// break the reference with the last element
			unset($bbbs_event);
		}
		
		return $bbbs_events;
	}
}

/* ---------- main */

$calendar_id= "unknown";
$bbbs_events= NULL;

if (isset($_SERVER["REQUEST_METHOD"]))
{
	$http_method= $_SERVER["REQUEST_METHOD"];
	
	switch ($http_method)
	{
		case "GET":
		{
			$calendar_id= isset($_GET["calendar"]) ? strtolower($_GET["calendar"]) : "fallback_calendar@gmail.com";
			$start_date= isset($_GET["start_date"]) ? $_GET["start_date"] : NULL;
			//###stefan $NOTE: always passing NULL here, which will cause a look-ahead of 1 day. Should clean this up!
			$end_date= NULL; //isset($_GET["end_date"]) ? $_GET["end_date"] : NULL;
			$gcal= new c_google_calendar($calendar_id);
			// retrieve BBBS Events objects
			$bbbs_events= $gcal->get_events($start_date, $end_date);
			
			if (!is_null($bbbs_events))
			{
				$event_index= 0;
				foreach ($bbbs_events as $input_event)
				{
					if (is_array($input_event))
					{
						debug::error("WHOA: input_event [" . $event_index . "] was an array, not a c_bbbs_event! input_event= " . print_r($input_event, TRUE));
					}
					else if ("c_bbbs_event" != get_class($input_event))
					{
						debug::error("WHOA: input_event [" . $event_index . "] not a c_bbbs_event! input_event= " . print_r($input_event, TRUE));
					}
					$event_index++;
				}
			}
			break;
		}
		case "POST":
		{
			break;
		}
		default:
		{
			break;
		}
	}
	
	header("Content-type: application/json");
}

if (isset($bbbs_events))
{
	if (0 < count($bbbs_events))
	{
		$bbbs_events= arcgis_geocode_bbbs_event_addresses($bbbs_events);
		if (isset($bbbs_events))
		{
			$results_array= array();
			
			// extract event details from the objects in the array, then encode into JSON
			foreach ($bbbs_events as $event)
			{
				$results_array[]= $event->get_event_data();
			}
			
			$event_results= json_encode($results_array, JSON_UNESCAPED_SLASHES);
			//debug::log("###returning event_results= " . $event_results);
			echo("{\"results\":" . $event_results . ",\"success\":true}");
		}
		else
		{
			debug::warning("Failed to get calendar events from [" . $calendar_id . "]!");
			echo("{\"success\":false}");
		}
	}
	else
	{
		debug::log("Calendar [" . $calendar_id . "] had no events in the given date range.");
		echo("{\"success\":false}");
	}
}
else
{
	debug::warning("Failed to get calendar events from [" . $calendar_id . "]!");
	echo("{\"success\":false}");
}

?>
