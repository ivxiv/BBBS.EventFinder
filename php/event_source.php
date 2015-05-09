<?php
/*
event_source.php
Monday July 14, 2014 7:47pm Stefan S.

event source interface class
implementation-dependent event sources should derive from this class

*/

/* ---------- classes */

// c_event_source
class c_event_source
{
	/* ---------- methods */
	
	// get_events()
	// $start_date_time: optional input start date / time, or NULL
	// $end_date_time: optional inout end date / time, or NULL
	// returns: array of bbbs_event objects, or NULL
	public function get_events($start_date_time, $end_date_time)
	{
		return NULL;
	}
}

?>
