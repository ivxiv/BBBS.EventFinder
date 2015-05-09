<?php
/*
debug.php
Monday July 14, 2014 7:47pm Stefan S.

debugging utilities
*/

/* ---------- constants */

// set to TRUE to enable various debugging functionality

define("DEBUG",								TRUE);

define("DEBUG_LOGGING",						DEBUG);

define("ERROR_INCLUDES_CALLSTACK",			TRUE);
define("WARNING_INCLUDES_CALLSTACK",		FALSE);
define("LOG_INCLUDES_CALLSTACK",			FALSE);

define("ERROR_MESSAGE_TO_SYSLOG",			TRUE);
define("WARNING_MESSAGE_TO_SYSLOG",			TRUE);
define("LOG_MESSAGE_TO_SYSLOG",				LOCAL_DEVELOPMENT_DEPLOYMENT);

define("ERROR_MESSAGE_TO_STDOUT",			FALSE);
define("WARNING_MESSAGE_TO_STDOUT",			FALSE);
define("LOG_MESSAGE_TO_STDOUT",				FALSE);

// formatted to work as JSON object fields
define("ERROR_PREFIX",						"ERROR: ");
define("WARNING_PREFIX",					"WARNING: ");
define("LOG_PREFIX",						"LOG: ");

define("ERROR_POSTFIX",						"");
define("WARNING_POSTFIX",					"");
define("LOG_POSTFIX",						"");

define("NEWLINE",							PHP_EOL);

// debug class
class debug
{
	/* ---------- members */
	
	private static $g_initialized= FALSE;
	
	/* ---------- methods */
	
	static function initialize()
	{
		if (FALSE == self::$g_initialized)
		{
			error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
			// setup for syslog
			//openlog("bbbs_events", LOG_NDELAY, LOG_USER);
			self::$g_initialized= TRUE;
			//error_log("logging initialized!", 0);
		}
		
		return;
	}

	// generate an error message
	// error messages should indicate something potentially damaging / unrecoverable has occurred
	static function error($message)
	{
		if (TRUE == DEBUG_LOGGING)
		{
			self::initialize();
			
			$error_message= ERROR_PREFIX . $message;
			if (TRUE == ERROR_INCLUDES_CALLSTACK)
			{
				$skip_frames= 1;
				$error_message= $error_message . NEWLINE . debug::generate_backtrace($skip_frames);
			}
			$error_message.= ERROR_POSTFIX;
			
			if (TRUE == ERROR_MESSAGE_TO_SYSLOG)
			{
				error_log($error_message, 0);
			}
			
			if (TRUE == ERROR_MESSAGE_TO_STDOUT)
			{
				echo($error_message . NEWLINE);
			}
		}
		
		return;
	}

	// generate an warning message
	// warning messages should indicate something problematic, but recoverable, has occurred
	static function warning($message)
	{
		if (TRUE == DEBUG_LOGGING)
		{
			self::initialize();
			
			$warning_message= WARNING_PREFIX . $message;
			if (TRUE == WARNING_INCLUDES_CALLSTACK)
			{
				$skip_frames= 1;
				$warning_message= $warning_message . NEWLINE . debug::generate_backtrace($skip_frames);
			}
			$warning_message.= WARNING_POSTFIX;
			
			if (TRUE == WARNING_MESSAGE_TO_SYSLOG)
			{
				error_log($warning_message, 0);
			}
			
			if (TRUE == WARNING_MESSAGE_TO_STDOUT)
			{
				echo($warning_message . NEWLINE);
			}
		}
		
		return;
	}

	// generate an log message
	// log messages are intended for general informational reporting
	static function log($message)
	{
		if (TRUE == DEBUG_LOGGING)
		{
			self::initialize();
			
			$log_message= LOG_PREFIX . $message;
			if (TRUE == LOG_INCLUDES_CALLSTACK)
			{
				$skip_frames= 1;
				$log_message= $log_message . NEWLINE . debug::generate_backtrace($skip_frames);
			}
			$log_message.= LOG_POSTFIX;
			
			if (TRUE == LOG_MESSAGE_TO_SYSLOG)
			{
				error_log($log_message, 0);
			}
			
			if (TRUE == LOG_MESSAGE_TO_STDOUT)
			{
				echo($log_message . NEWLINE);
			}
		}
		
		return;
	}

	// generates a text string representation of a backtrace
	// $skip_frame_count: number of stack frames to ignore
	static function generate_backtrace($skip_frame_count)
	{
		$backtrace_string= "";
		$backtrace= debug_backtrace();
		
		$skip_frame_count= max(0, $skip_frame_count);
		$frame_count= count($backtrace);
		
		for ($frame_index= $skip_frame_count; $frame_index<$frame_count; $frame_index++)
		{
			$file_name= $backtrace[$frame_index]["file"];
			$line_number= $backtrace[$frame_index]["line"];
			$method_name= $backtrace[$frame_index]["function"];
			$arguments= $backtrace[$frame_index]["args"];
			$arguments_string= "";
			
			foreach ($arguments as $arg)
			{
				$separator= empty($arguments_string) ? "" : ", ";
				$arguments_string.= (gettype($arg) . $separator);
			}
			
			$frame_details= $method_name . "(" . $arguments_string . ") called at [" . $file_name . ":" . $line_number . "]";
			$backtrace_string.= ($frame_details . NEWLINE);
		}
		
		return $backtrace_string;
	}
}

?>
