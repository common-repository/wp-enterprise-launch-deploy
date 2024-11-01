#!/usr/bin/php -q
<?php


// Prevent this from running in a browser
if(!php_sapi_name() == 'cli'){
	echo "WELD Error: This CANNOT be executed from within the browser";
	exit;
}
ini_set('memory_limit', '-1');


//setup global $_SERVER variables to keep WP from trying to redirect
$_SERVER = array(
  "REQUEST_URI" => "/",
  "REQUEST_METHOD" => "GET"
);

//require the WP bootstrap
require_once(__DIR__.'/../../../../wp-load.php');

 // Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

echo "\n\n\n**********Starting the WELD deploy processor**********\n";
echo "\nINFO: Loading required libraries";

// Include the WELD cron classes
require_once(WELD_PLUGIN_DIR."/weld-cron/WELD_cron_log.php");
require_once(WELD_PLUGIN_DIR."/weld-cron/WELD_cron_processor.php");
require_once(WELD_PLUGIN_DIR."/weld-cron/WELD_cron_database.php");
require_once(WELD_PLUGIN_DIR."/weld-cron/WELD_mail.php");
require_once(WELD_PLUGIN_DIR."/weld-cron/ftp-bulk-transfer.php");






// Check Status

	// Get status settings
	$systemStatus = get_option('weld_status');
	$systemStatusTime = get_option('weld_statusTime');
	$warningInterval = get_option('weld_warningInterval');
	$warningIntervalSent = get_option('weld_warningIntervalSent');

	if($systemStatusTime == 0){$systemStatusTime=time();}
	$now = time();
	$intervalCheck = $now - $systemStatusTime;
	
	echo "\nINFO: Checking System Status: ".$systemStatus;
	// If status is processing && now - statusTime > warning interval 
	if($systemStatus == "Processing"){
		echo "\nINFO: Status Interval: $intervalCheck, Next Alert: ".((($warningInterval * 60)+$warningIntervalSent)-$now);
		// Has warning interval been exceeded?
		if($intervalCheck > ($warningInterval * 60)){
			// Has a warning message been sent recently?
			if($now > (($warningInterval * 60)+$warningIntervalSent)){

				echo "\nINFO: Status interval exceeded.  Sending alert email.";
				// Send a warning
				$warning = new weld_mail();

				$blogName = get_bloginfo("name");

				$message = "The WELD deployment processor for: $blogName has been in the Processing state for an unusually long time.  \n\n Time: ".date('l jS \of F Y h:i:s A');

				$warning->adminMail($message);

				// Update the interval sent time
				update_option('weld_warningIntervalSent',$now);
			}
		}
		echo "\nWARNING: System is not ready. Exiting.\n\n";
		exit;
	}

	// If status is error, exit
	if($systemStatus == "Error"){
		echo "\nINFO: Status Interval: $intervalCheck, Next Alert: ".((($warningInterval * 60)+$warningIntervalSent)-$now);

		// Has warning interval been exceeded?
		if($intervalCheck > ($warningInterval * 60)){
			// Has a warning message been sent recently?
			if($now > (($warningInterval * 60)+$warningIntervalSent)){
				echo "\nINFO: Status interval exceeded.  Sending alert email.";
				// Send a warning
				$warning = new weld_mail();

				$blogName = get_bloginfo("name");

				$message = "The WELD deployment processor for: $blogName is in the Error state \n\n Time: ".date('l jS \of F Y h:i:s A');

				$warning->adminMail($message);
//
				// Update the interval sent time
				update_option('weld_warningIntervalSent',$now);
			}
		}
		echo "\nERROR: System is not ready. Exiting.\n\n";
		exit;
	}


	// If status is ready || false, proceed
	if($systemStatus != "Ready" && $systemStatus != false){
		exit;
	}

	// Check for Entries to Process
	echo "\nINFO: Starting Weld Processor Object";
	$processor = new weld_Processor();

	echo "\nINFO: Checking Process List";
	$pcount = $processor->checkProcessList();

	if($pcount == false){
		// No entries to process
		exit;
	}

	
	// If entry(s) found

	// Init Log Object
	global $log;
	echo "\nINFO: Starting Weld Log";
	$log = new weld_status();
	$log->initLog();

	$log->logEntry("INFO: Starting Deployments. $pcount tasks found to process.","Processing",null);


	// Start the processor
	$success = $processor->processNextEntry();


	if($success == true){
		$log->logEntry("INFO: Finished processing entries to deploy.","Processing",null);
	}else{
		$log->error("Processor has Failed. Exiting.");
	}

	update_option('weld_remote_status_override','None');

	// Clean Log Folder of Files older than LodDuration days
	$log->logEntry("INFO: Cleaning Log Files","Processing",null);
	$log->cleanLogFolder();


	// Update Status
	$log->logEntry("INFO: Processor Finished","Ready",null);
	$log->updateStatus("Ready",null);
	

		
