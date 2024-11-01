<?php

// Prevent this from running in a browser
if(!php_sapi_name() == 'cli'){
	echo "WELD Error: This CANNOT be executed from within the browser";
	exit;
}

if ( ! defined( 'ABSPATH' ) ) exit;

class weld_mail {

	private $adminEmails = null;
	public $logFolder = null;

	function __construct() {
		if($adminEmails == null){
			$args = array('role'=>'administrator');
			$adminUsers = get_users($args);

			foreach($adminUsers as $adminUser){
				$this->adminEmails[]=$adminUser->user_email;
			}
		}
	} // end __construct

	function adminMail($message,$logfile){

		if($message == ""){
			return false;
		}

		if($logfile != ""){

			$this->logFolder = get_option('weld_logFolder');
// Remove any invalid characters from string
			$this->logFolder = str_replace(array(':','*','?','"','<','>','|'),'',$this->logFolder);


			while($this->logFolder != str_replace(array('//','\\'),'/',$this->logFolder)){
				$this->logFolder = str_replace(array('//','\\'),'/',$this->logFolder);
			}


			// If log folder not set
			if($this->logFolder == "" || $this->logFolder == null){
				// Create folder in default location
				$this->logFolder = ABSPATH ."/wp-content/weld-logs/";
			}else{
				$this->logFolder = ABSPATH . $this->logFolder;
			}

			$testfilename = str_replace($this->logFolder,"",$logfile);
			$testfilename = str_replace(".weld","",$testfilename);
			$testfilename  = str_replace("//","/",$this->logFolder."/".$testfilename.".weld");

			if(file_exists($testfilename)){
				$logfile = $testfilename;
			}else{
				$logfile = null;
			}

		}

		$subject = "WELD Deploy Error";
		$headers = 'From: WP Enterprise Launch Deploy <weld@'.$_SERVER['SERVER_NAME'].'>' . "\r\n";

		$return = wp_mail(implode(", ",$this->adminEmails), $subject, $message, $headers, $logfile );

		if($return == false){
			global $log;
			$log->logEntry("Error: Alert email failed to send.","Error",null);
		}
		return $return;

	}// end adminMail






}
