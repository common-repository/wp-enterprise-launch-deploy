<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


function weld_load_server_array($input){
    if($input == "" || $input == null){
     return $input;   
    }
	// Only decrypt if user is logged into admin OR if the cron CLI task is running
	if(php_sapi_name() == 'cli' || is_admin()){

		$key = weld_get_key();
		$cipher = MCRYPT_RIJNDAEL_256;
		$mode = MCRYPT_MODE_CBC;

		$output =  mcrypt_decrypt(
		    $cipher,
		    substr(md5($key),0,mcrypt_get_key_size($cipher, $mode)),
		    base64_decode($input),
		    $mode,
		    substr(md5($key),0,mcrypt_get_block_size($cipher, $mode))
		   );

		$output = rtrim($output);
//echo $output;
		// Remove passwords from arrays
		
		$server_array=json_decode($output,true);

		// Protect Passwords in Admin

		if(is_admin()){
			foreach($server_array as $key => $serverGroup){
				if($serverGroup['serverPassword']!=""){
					$server_array[$key]['serverPasswordB']=$server_array[$key]['serverPassword'];		
					$server_array[$key]['serverPassword']="PasswordHasBeenSet";
				}
				if($serverGroup['databasePassword']!=""){
					$server_array[$key]['databasePassword']="PasswordHasBeenSet";
				}
			}
		}

		$output = json_encode($server_array);
		

	}

	return $output;
}// end weld_load_server_array



function weld_process_list_save_and_validate($input){


	$processArray = json_decode(rtrim($input),true);

	$hashArray = array();

	foreach($processArray as $key => $process){

		if(intval($process['hashID']) == 0 || in_array($process['hashID'],$hashArray)){
			// Remove duplicates
			unset($processArray[$key]);
		}else{

			$hashArray[] = $process['hashID'];
			$processArray[$key]['hashID'] = intval($process['hashID']);
			$processArray[$key]['status'] = preg_replace("|[^A-Za-z]|","",$processArray[$key]['status']);
			if($processArray[$key]['queuedTime'] == ""){
				$processArray[$key]['queuedTime'] = time();
			}else{
				$processArray[$key]['queuedTime'] = intval($processArray[$key]['queuedTime']);
			}
			if($processArray[$key]['lastUpdated'] == ""){
				$processArray[$key]['lastUpdated'] = time();
			}else{
				$processArray[$key]['lastUpdated'] = intval($processArray[$key]['lastUpdated']);
			}
		}
	}

	$output = json_encode($processArray);

	return $output;
}// end weld_process_list_save_and_validate



function weld_server_array_save_and_validate($input){

	$key = weld_get_key();
	$cipher = MCRYPT_RIJNDAEL_256;
	$mode = MCRYPT_MODE_CBC;

	// Load the previous settings
	$previousSettings = get_option('weld_server_array');

	$previousSettings =  mcrypt_decrypt(
		    $cipher,
		    substr(md5($key),0,mcrypt_get_key_size($cipher, $mode)),
		    base64_decode($previousSettings),
		    $mode,
		    substr(md5($key),0,mcrypt_get_block_size($cipher, $mode))
		   );

	$previousSettings = rtrim($previousSettings);
	$previousSettingsARR = json_decode($previousSettings,true);


	// Load the proposed settings
	$newSettings = json_decode($input,true);


	foreach($newSettings as $key => $serverGroup){
		// Enforce hashid as int
		$newSettings[$key]['hashID']=intval($serverGroup['hashID']);

		// Check to see if the password is unchanged. If so, then retrieve from DB and integrate into new settings
		if($serverGroup['serverPassword']=="PasswordHasBeenSet"){
			foreach($previousSettingsARR as $oldKey => $oldServerGroup){
				if($oldServerGroup['hashID']==$serverGroup['hashID']){
					$newSettings[$key]['serverPassword'] = $oldServerGroup['serverPassword'];
				}
			}
		}

		if($serverGroup['databasePassword']=="PasswordHasBeenSet"){

			foreach($previousSettingsARR as $oldKey => $oldServerGroup){

				if($oldServerGroup['hashID']==$serverGroup['hashID']){

					$newSettings[$key]['databasePassword'] = $oldServerGroup['databasePassword'];
				}
			}
		}

		foreach($serverGroup as $setKey => $settingDatum){
			if($settingDatum === "true"){
				$newSettings[$key][$setKey] = true;
			}
			if($settingDatum === "false"){
				$newSettings[$key][$setKey] = false;
			}
		}


		// Validate and clean settings
		$newSettings[$key]['serverLoadBalancerFile']=str_replace(array(':','*','?','"','<','>','|'),'',$newSettings[$key]['serverLoadBalancerFile']);
		$newSettings[$key]['serverTargetDir']=str_replace(array(':','*','?','"','<','>','|'),'',$newSettings[$key]['serverTargetDir']);
		if($newSettings[$key]['serverPort']!=""){$newSettings[$key]['serverPort'] = intval($newSettings[$key]['serverPort']);}
		if($newSettings[$key]['databasePort']!=""){$newSettings[$key]['databasePort'] = intval($newSettings[$key]['databasePort']);}
		if($newSettings[$key]['databaseSSL']!=true && $newSettings[$key]['databaseSSL']!=false){$newSettings[$key]['databaseSSL'] = false;}
		
		if($newSettings[$key]['databaseSSLclientCert']!=""){
			if(!is_file($newSettings[$key]['databaseSSLca'])){
				$newSettings[$key]['databaseSSLca'] = "";	
			}else{
				$newSettings[$key]['databaseSSLca'] = trim($newSettings[$key]['databaseSSLca']);
			}
		}
		if($newSettings[$key]['databaseSSLclientKey']!=""){
			if(!is_file($newSettings[$key]['databaseSSLca'])){
				$newSettings[$key]['databaseSSLca'] = "";	
			}else{
				$newSettings[$key]['databaseSSLca'] = trim($newSettings[$key]['databaseSSLca']);
			}
		}

		$validTransferTypes = array("ftp","ssh");

		if(!in_array($newSettings[$key]['serverTransferType'],$validTransferTypes)){$newSettings[$key]['serverTransferType'] = "ssh";}

		$ValidIpAddressRegex = "^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$";
		$ValidHostnameRegex = "^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$";
		foreach($newSettings[$key]['serverAddr'] as $saKey => $serverAddr){
			if(filter_var($serverAddr,FILTER_VALIDATE_IP)){
				$newSettings[$key]['serverAddr'][$saKey]=$serverAddr;
			}else{
				$url = parse_url($serverAddr);

				if(!isset($url['scheme'])){$url = parse_url("http://".$serverAddr);}
				if($url['host']!=""){
					$newSettings[$key]['serverAddr'][$saKey]=$url['host'];
				}else{
					unset($newSettings[$key]['serverAddr'][$saKey]);
				}
			}
		}

		foreach($newSettings[$key]['databaseAddr'] as $saKey => $serverAddr){
			if(filter_var($serverAddr,FILTER_VALIDATE_IP)){
				$newSettings[$key]['databaseAddr'][$saKey]=$serverAddr;
			}else{
				$url = parse_url($serverAddr);

				if(!isset($url['scheme'])){$url = parse_url("http://".$serverAddr);}
				if($url['host']!=""){
					$newSettings[$key]['databaseAddr'][$saKey]=$url['host'];
				}else{
					unset($newSettings[$key]['databaseAddr'][$saKey]);
				}
			}
		}
		

		if(!filter_var($newSettings[$key]['siteURL'],FILTER_VALIDATE_URL)){
			$newSettings[$key]['siteURL'] = null;
		}

		if($newSettings[$key]['excludeAdmin']!=true && $newSettings[$key]['excludeAdmin']!=false){$newSettings[$key]['excludeAdmin'] = false;}

		if($newSettings[$key]['databaseServersDifferent']!=true && $newSettings[$key]['databaseServersDifferent']!=false){$newSettings[$key]['databaseServersDifferent'] = false;}//databaseSSL
		


	}

	$input = json_encode($newSettings);

	$input = rtrim($input);	

	$key = weld_get_key();
	$cipher = MCRYPT_RIJNDAEL_256;
	$mode = MCRYPT_MODE_CBC;

	$output = base64_encode(mcrypt_encrypt(
		$cipher,
		substr(md5($key),0,mcrypt_get_key_size($cipher, $mode)),
		$input,
		$mode,
		substr(md5($key),0,mcrypt_get_block_size($cipher, $mode))
	   ));

	return $output;
}// weld_server_array_save_and_validate


/*  */
function WELD_admin_inject(){
	//page=weld-settings&tab=weld_logs&L=
	if(isset($_REQUEST['page']) && isset($_REQUEST['tab']) && isset($_REQUEST['L'])) {
		if($_REQUEST['page'] == 'weld-settings' && $_REQUEST['tab']='weld_logs'){
			$logInput = $_REQUEST['L'];

			$potato = "/^[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}$/";
			$match = preg_match($potato,$_REQUEST['L']);
			if($_REQUEST['L'] == "lda"){
				$match = 1;
			}
			if($match > 0 && is_admin()){
				require_once(WELD_PLUGIN_DIR."/admin/WELD-LogLoad.php");
			}


		}
	}
}


function weld_render_system_status(){

	$systemStatus = get_option('weld_status');
	$systemStatusTime = get_option('weld_statusTime');
	$formattedStatusTime = date('l jS \of F Y h:i:s A', $systemStatusTime);

		if($systemStatus == "Ready") {
				echo PHP_EOL."<img src=\"".WELD_PLUGIN_URL."/admin/green.png\" alt=\"Ready\" class=\"weld-status-stoplight\" />".PHP_EOL;
				echo PHP_EOL."<h2>Current System Status</h2>".PHP_EOL;
				echo PHP_EOL."<p>System is ready to run a deployment</p>".PHP_EOL;
		} elseif ($systemStatus == "Error") {
				echo PHP_EOL."<img src=\"".WELD_PLUGIN_URL."/admin/red.png\" alt=\"Ready\" class=\"weld-status-stoplight\" />".PHP_EOL;
				echo PHP_EOL."<h2>Current System Status</h2>".PHP_EOL;
				echo PHP_EOL."<p>A serious error has occured during deployment. Please check the logs and remedy. All deployments are halted until the error has been corrected.</p>".PHP_EOL;
		} elseif ($systemStatus == "Processing") {
				echo PHP_EOL."<img src=\"".WELD_PLUGIN_URL."/admin/yellow.png\" alt=\"Ready\" class=\"weld-status-stoplight\" />".PHP_EOL;
				echo PHP_EOL."<h2>Current System Status</h2>".PHP_EOL;
				echo PHP_EOL."<p>The system is processing a deployment</p>".PHP_EOL;

		}
			echo "<p>Last system status change: <strong>$formattedStatusTime</strong>	Current System Time: <strong>".date('l jS \of F Y h:i:s A')."</strong></p>";
}// weld_render_system_status
