<?php
/*
bbbs_event.php
Friday July 18, 2014 8:45pm Stefan S.

Wrapper class for BBBS event object

NOTES:

Google Event representation is documented here:
https://developers.google.com/google-apps/calendar/v3/reference/events
*/

/* ---------- includes */

require_once "universal/universal.php";

// Google Event class
require_once "google_calendar.php";

/* ---------- constants */

define("PARSE_GCAL_EVENT_DESCRIPTION",		TRUE);

/* ---------- classes */

// BBBS Event
class c_bbbs_event
{
	/* ---------- constants */
	
	const k_key_age_min= "age_min"; // int
	const k_key_age_max= "age_max"; // int
	const k_key_category= "category"; // string
	const k_key_start_time= "date_begin"; // string
	const k_key_end_time= "date_end"; // string
	const k_key_all_day_event= "all_day"; // boolean
	const k_key_organizer_name= "organizer_name"; // string
	const k_key_organizer_email= "organizer_email"; // string
	const k_key_description= "description"; // string
	const k_key_price_range= "pricerange"; // int
	const k_key_title= "title"; // string
	const k_key_url= "url"; // string
	const k_key_arcgis_data= "arcgis_locations"; // an array of geocoded locations for the event
		// ArcGIS geocoding data will be attached here if available
		const k_maximum_locations_per_event= 100; // assume there could be up to this many locations per an event
		const k_key_arcgis_single_line_address= "single_line_address";
		const k_key_arcgis_score= "score";
		const k_key_arcgis_spatial_reference= "spatial_reference";
		const k_key_arcgis_x= "x";
		const k_key_arcgis_y= "y";
		const k_key_arcgis_display_x= "display_x";
		const k_key_arcgis_display_y= "display_y";
	
	// additional event tags may be stored in the description field
	const k_gcal_desc_key_address= "[address:"; // b/c ArcGIS is much more picky about single-line address entries than Google Maps
	const k_gcal_desc_key_age= "[age:";
	const k_gcal_desc_key_cost= "[cost:";
	const k_gcal_desc_key_category= "[category:";
	const k_gcal_desc_key_url= "http";
	
	/* ---------- members */
	
	private $m_data= array();
	
	/* ---------- methods */
	
	function __construct()
	{
		// initialize $m_data
		$this->m_data[self::k_key_age_min]= 0;
		$this->m_data[self::k_key_age_max]= 99;
		$this->m_data[self::k_key_category]= "";
		$this->m_data[self::k_key_start_time]= 0;
		$this->m_data[self::k_key_end_time]= 0;
		$this->m_data[self::k_key_all_day_event]= FALSE;
		$this->m_data[self::k_key_organizer_name]= "";
		$this->m_data[self::k_key_organizer_email]= "";
		$this->m_data[self::k_key_description]= "";
		$this->m_data[self::k_key_price_range]= "";
		$this->m_data[self::k_key_title]= "";
		$this->m_data[self::k_key_url]= "";
		$this->m_data[self::k_key_arcgis_data]= array();
		
		return;
	}
	
	// return a copy of the event data
	public function get_event_data()
	{
		$copy = array();
		$copy= $this->m_data;
		
		return $copy;
	}
	
	// consumes a decoded Google event JSON object as input (ie, the Google event is itself already an associative array)
	// and initializes the BBBS event
	public function initialize_from_google_calendar_event($google_calendar_event)
	{
		if (isset($google_calendar_event[c_google_calendar::k_key_location]))
		{
			$address_data= array(
				self::k_key_arcgis_single_line_address=> $google_calendar_event[c_google_calendar::k_key_location],
				self::k_key_arcgis_score=> 0,
				self::k_key_arcgis_spatial_reference=> 0,
				self::k_key_arcgis_x=> 0,
				self::k_key_arcgis_y=> 0,
				self::k_key_arcgis_display_x=> 0,
				self::k_key_arcgis_display_y=> 0
			);
			$this->m_data[self::k_key_arcgis_data][0]= $address_data;
		}
		if (isset($google_calendar_event[c_google_calendar::k_key_start_time]) &&
			isset($google_calendar_event[c_google_calendar::k_key_start_time][c_google_calendar::k_key_start_date_time]))
		{
			$start_time_zone= isset($google_calendar_event[c_google_calendar::k_key_start_time][c_google_calendar::k_key_time_zone]) ?
				new DateTimeZone($google_calendar_event[c_google_calendar::k_key_start_time][c_google_calendar::k_key_time_zone]) :
				new DateTimeZone("UTC");
			$this->m_data[self::k_key_start_time]= new DateTime(
				$google_calendar_event[c_google_calendar::k_key_start_time][c_google_calendar::k_key_start_date_time],
				$start_time_zone);
		}
		if (isset($google_calendar_event[c_google_calendar::k_key_end_time]) &&
			isset($google_calendar_event[c_google_calendar::k_key_end_time][c_google_calendar::k_key_end_date_time]))
		{
			$end_time_zone= isset($google_calendar_event[c_google_calendar::k_key_end_time][c_google_calendar::k_key_time_zone]) ?
				new DateTimeZone($google_calendar_event[c_google_calendar::k_key_end_time][c_google_calendar::k_key_time_zone]) :
				new DateTimeZone("UTC");
			$this->m_data[self::k_key_end_time]= new DateTime(
				$google_calendar_event[c_google_calendar::k_key_end_time][c_google_calendar::k_key_end_date_time],
				$end_time_zone);
		}
		if (isset($google_calendar_event[c_google_calendar::k_key_organizer]))
		{
			if (isset($google_calendar_event[c_google_calendar::k_key_organizer][c_google_calendar::k_key_organizer_name]))
			{
				$this->m_data[self::k_key_organizer_name]= $google_calendar_event[c_google_calendar::k_key_organizer][c_google_calendar::k_key_organizer_name];
			}
			if (isset($google_calendar_event[c_google_calendar::k_key_organizer][c_google_calendar::k_key_organizer_email]))
			{
				$this->m_data[self::k_key_organizer_email]= $google_calendar_event[c_google_calendar::k_key_organizer][c_google_calendar::k_key_organizer_email];
			}
		}
		
		if (isset($google_calendar_event[c_google_calendar::k_key_title]))
		{
			$this->m_data[self::k_key_title]= $google_calendar_event[c_google_calendar::k_key_title];
		}
		if (isset($google_calendar_event[c_google_calendar::k_key_url]))
		{
			$this->m_data[self::k_key_url]= $google_calendar_event[c_google_calendar::k_key_url];
		}
		
		// handle description last, since parsing this field may (re)set other event parameters
		if (isset($google_calendar_event[c_google_calendar::k_key_description]))
		{
			if (PARSE_GCAL_EVENT_DESCRIPTION)
			{
				// attempt to extract additional event details from the provided event description field
				$this->parse_google_calendar_event_description_field_parameters($google_calendar_event[c_google_calendar::k_key_description]);
			}
			else
			{
				$this->m_data[self::k_key_description]= $google_calendar_event[c_google_calendar::k_key_description];
			}
		}
		
		return;
	}
	
	// add ArcGIS geocoding data to an object (assumed to be an associative array)
	public function add_arcgis_geocoding_data($address_index, $single_line_address, $score, $spatial_reference, $x, $y, $display_x, $display_y)
	{
		$this->m_data[self::k_key_arcgis_data][$address_index]= array(
			self::k_key_arcgis_single_line_address=> $single_line_address,
			self::k_key_arcgis_score=> $score,
			self::k_key_arcgis_spatial_reference=> $spatial_reference,
			self::k_key_arcgis_x=> $x,
			self::k_key_arcgis_y=> $y,
			self::k_key_arcgis_display_x=> $display_x,
			self::k_key_arcgis_display_y=> $display_y
		);
		
		return;
	}
	
	// attempts to extract additional event details from the provided text
	private function parse_google_calendar_event_description_field_parameters($parse_text)
	{
		$address_text_offset= stripos($parse_text, self::k_gcal_desc_key_address);
		$age_text_offset= stripos($parse_text, self::k_gcal_desc_key_age);
		$cost_text_offset= stripos($parse_text, self::k_gcal_desc_key_cost);
		$category_text_offset= stripos($parse_text, self::k_gcal_desc_key_category);
		$url_text_offset= stripos($parse_text, self::k_gcal_desc_key_url);
		$address_index= 0;
		
		// look for custom address
		$address_parse_text= $parse_text;
		if (FALSE !== $address_text_offset)
		{
			$next_address_text_offset= $address_text_offset;
			
			while (FALSE !== $next_address_text_offset)
			{
				// an event instance may have multiple locations (e.g. for Match Discount Partners)
				// examples:
				// [address: 123 4th Street, Austin, TX]
				$address_text= substr($address_parse_text, $next_address_text_offset + strlen(self::k_gcal_desc_key_address));
				$tokens= "]";
				$use_address= strtok($address_text, $tokens);
				if (!empty($use_address))
				{
					$use_address= trim($use_address);
					$address_data= array(
						self::k_key_arcgis_single_line_address=> $use_address,
						self::k_key_arcgis_score=> 0,
						self::k_key_arcgis_spatial_reference=> 0,
						self::k_key_arcgis_x=> 0,
						self::k_key_arcgis_y=> 0,
						self::k_key_arcgis_display_x=> 0,
						self::k_key_arcgis_display_y=> 0
					);
					$this->m_data[self::k_key_arcgis_data][$address_index++]= $address_data;
				}
				// look for additional location
				$address_parse_text= $address_text;
				$next_address_text_offset= stripos($address_parse_text, self::k_gcal_desc_key_address);
			}
		}
		else
		{
			$address_text_offset= 0;
		}
		
		// look for age range
		if (FALSE !== $age_text_offset)
		{
			// examples:
			// [age:9+]
			// [age:9-99]
			$age_text= substr($parse_text, $age_text_offset + strlen(self::k_gcal_desc_key_age));
			$tokens= "+-]";
			$age_start= strtok($age_text, $tokens);
			$age_end= 99;
			
			if (!empty($age_start))
			{
				$age_start= trim($age_start);
				$age_start= min(max(0, $age_start), 99);
				$age_end= strtok($tokens);
				$age_end= empty($age_end) ? 99 : min(max(0, $age_end), 99);
			}
			else
			{
				$age_start= 0;
			}
			
			$this->m_data[self::k_key_age_min]= $age_start;
			$this->m_data[self::k_key_age_max]= $age_end;
		}
		else
		{
			$age_text_offset= 0;
		}
		
		// look for cost information
		if (FALSE !== $cost_text_offset)
		{
			// examples:
			// [cost:]
			// [cost:$]
			// [cost:$$]
			$cost_text= substr($parse_text, $cost_text_offset + strlen(self::k_gcal_desc_key_cost));
			$tokens= "]";
			$cost_tokens= strtok($cost_text, $tokens);
			
			if (!empty($cost_tokens))
			{
				$cost_tokens= trim($cost_tokens);
				$cost_tokens_length= strlen($cost_tokens);
				
				for ($char_index= 0; $char_index < $cost_tokens_length; $char_index++)
				{
					if ('$' == $cost_tokens[$char_index])
					{
						$this->m_data[self::k_key_price_range].= '$';
					}
				}
			}
		}
		else
		{
			$cost_text_offset= 0;
		}
		
		// look for event categorization
		if (FALSE !== $category_text_offset)
		{
			// examples:
			// [category:educational]
			$category_text= substr($parse_text, $category_text_offset + strlen(self::k_gcal_desc_key_category));
			$tokens= "]";
			$category= strtok($category_text, $tokens);
			if (!empty($category))
			{
				$category= trim($category);
				$this->m_data[self::k_key_category]= $category;
			}
		}
		else
		{
			$category_text_offset= 0;
		}
		
		// look for an event URL
		if (FALSE !== $url_text_offset)
		{
			// examples:
			// http://www.bigmentoring.org
			// https://www.github.com
			$url_text= substr($parse_text, $url_text_offset);
			$tokens= " \t\n\r\0\x0B"; // chop at first whitepace (same tokens as trim() uses)
			$url_text= strtok($url_text, $tokens);
			if (!empty($url_text))
			{
				$url_text= trim($url_text);
				$this->m_data[self::k_key_url]= $url_text;
			}
		}
		else
		{
			$url_text_offset= 0;
		}
		
		// remove any special tags from the description field now that we've parsed them
		// NOTE: this assumes that special tags occur at the end of the description field
		// ###stefan $TODO this should probably be done on the client
		$first_tag_offset= strlen($parse_text);
		$first_tag_offset= (0 < $address_text_offset) ? min($first_tag_offset, $address_text_offset) : $first_tag_offset;
		$first_tag_offset= (0 < $age_text_offset) ? min($first_tag_offset, $age_text_offset) : $first_tag_offset;
		$first_tag_offset= (0 < $cost_text_offset) ? min($first_tag_offset, $cost_text_offset) : $first_tag_offset;
		$first_tag_offset= (0 < $category_text_offset) ? min($first_tag_offset, $category_text_offset) : $first_tag_offset;
		$description_text= (0 < $first_tag_offset) ? substr($parse_text, 0, $first_tag_offset) : $parse_text;
		// finally, add back in formatted cost / category information if available
		$description_text.= "<br />";
		if (!empty($this->m_data[self::k_key_price_range]))
		{
			$description_text.= ("<br />Cost: " . $this->m_data[self::k_key_price_range]);
		}
		if (!empty($this->m_data[self::k_key_category]))
		{
			$description_text.= ("<br />Category: " . $this->m_data[self::k_key_category]);
		}
		$this->m_data[self::k_key_description]= $description_text;
		
		return;
	}
	
	/* ---------- static methods */
	
	// 'cleanse' an address string
	static function cleanse_address_string($address_string)
	{
		return preg_replace("/[^A-Za-z0-9#]/", "", $address_string);
	}

	// compute a hash (key) from an address
	static function compute_address_hash($address_string)
	{
		// strip out anything which is not: [alphanumeric, '#']
		$cleansed_address_string= self::cleanse_address_string($address_string);
		// compute the MD5 hash of this string
		$result= md5($cleansed_address_string);
		
		return $result;
	}
}

?>
