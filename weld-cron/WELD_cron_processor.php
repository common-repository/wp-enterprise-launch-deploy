<?php

// Prevent this from running in a browser
if(!php_sapi_name() == 'cli'){
	echo "WELD ERROR: This CANNOT be executed from within the browser";
	exit;
}

if ( ! defined( 'ABSPATH' ) ) exit;



class weld_Processor {

	private $key = null;
	private $cipher = MCRYPT_RIJNDAEL_256;
	private $mode = MCRYPT_MODE_CBC;
	private $serverGroups = null;
	public $processList = null;
	private $loopMax = 2;
	private $loopsRun = 0;
	private $sshbase = "ssh -o StrictHostKeyChecking=no ";

	function __construct() {
echo "\n Constructing \n";
		$this->key = weld_get_key();

		// Load Server Settings
		$this->serverGroups = get_option('weld_server_array');

		$this->serverGroups = mcrypt_decrypt(
		    $this->cipher,
		    substr(md5($this->key),0,mcrypt_get_key_size($this->cipher, $this->mode)),
		    base64_decode($this->serverGroups),
		    $this->mode,
		    substr(md5($this->key),0,mcrypt_get_block_size($this->cipher, $this->mode))
		  );

		$this->serverGroups = rtrim($this->serverGroups);
		$this->serverGroups = json_decode($this->serverGroups,true);

	}// end __construct()


	function getProcessList(){
		echo "\nINFO: Retrieving Process List";
		// Get setting
		$processListFlat = get_option('weld_process_list');

		// json_decode
		$this->processList = json_decode($processListFlat,true);

		// Temporary Construction Setting

	}// end getProcessList()


	function checkProcessList(){
		global $log;

		echo "\nINFO: Checking Process List";
		// Check to see if there are any remaining entries in the cached process list
		if(is_array($this->processList)){
			if(count($this->processList)<1){
				$this->getProcessList();
			}
		}else{
			$this->getProcessList();
		}

		// Check to see if anything has been added to the process list since it was last retrieved
		if(count($this->processList)<1){
			echo "\nINFO: No Processes to Run\n";
			return false;
		}else{
			echo "\nINFO: Found ".count($this->processList)." Processes to Run";
			return count($this->processList);
		}

	} // checkProcessList

	function processNextEntry(){
		global $log;

		$processEntry = $this->processList[0];

		$log->logEntry("INFO: Processing Loop Number ".$this->loopsRun,"Processing",$processEntry['hashID']);

		$serverGroup=null;
		// Find Server Group that Matches Job
		foreach($this->serverGroups as $group){
			if($group['hashID'] == $processEntry['hashID']){
				$serverGroup = $group;
			}
		}

		// If no group matches, make log entry and halt with warning
		if($serverGroup==null){
			$log->error("There is no server group that matches the process list entry ".$processEntry['serverGroupName'].".",$processEntry['hashID']);
		}


		// Process Settings
		$serverGroup['serverPort'] = intval($serverGroup['serverPort']);
		if($serverGroup['serverPort']==0){
			if($serverGroup['serverTransferType']=="ftp"){
				$serverGroup['serverPort']=21;
				if($serverGroup['serverUsername']==""){
					$log->error("ERROR: No username defined for FTP.",$processEntry['hashID']);
				}
			}else{
				$serverGroup['serverPort']=22;
			}
		}

		$this->sshbase = $this->sshbase." -p ".$serverGroup['serverPort']." ";

		if($serverGroup['serverTargetDir']==""){
			$serverGroup['serverTargetDir'] = ABSPATH;
		}

		if($serverGroup['serverLoadBalancerFile']!="" || strrpos($serverGroup['serverLoadBalancerFile'],"/")!=0){
			$serverGroup['serverLoadBalancerFile'] = $serverGroup['serverTargetDir']."/".$serverGroup['serverLoadBalancerFile'];
		}

		$dbTransfer = new WELD_DatabaseTransfer();
		$dbTransfer->hashID = $processEntry['hashID'];
echo "\n\n SSL IS: ".$serverGroup['databaseSSL']."\n\n";
		$dbTransfer->databaseSSL = $serverGroup['databaseSSL'];
		$dbTransfer->databaseSSLclientCert = trim($serverGroup['databaseSSLclientCert']);
		$dbTransfer->databaseSSLclientKey = trim($serverGroup['databaseSSLclientKey']);


		// Initialize Connections
		$log->logEntry("INFO: Initializing Database Transfer Object for ".$dbTransfer->targetHost,"Processing",$processEntry['hashID']);



		// Create MySQL transfer Object
		$return = $dbTransfer->init();
		if($return !== true){
			$log->logEntry("WARNING: Database Init Error: $return","Processing",$processEntry['hashID']);
			}

		// Create MySQL Dump
		$currentURL = get_bloginfo('url');
		$currentURL = str_replace("http://","",$currentURL);
		$currentURL = str_replace("https://","",$currentURL);

		if($serverGroup['targetPublishURL'] != ""){
			$targetURL = $serverGroup['targetPublishURL'];
		}else{
			$targetURL = $currentURL;
		}

		$return=$dbTransfer->dump($currentURL,$targetURL);

		if($return != 0){
			$log->logEntry("WARNING: Database Error ".$dbTransfer->last_entry,"Processing",$processEntry['hashID']);
		}


		foreach($serverGroup["serverAddr"] as $serverKey => $targetServer){

			// For each server

				// Process target server settings

				if($serverAddr["databaseAddr"][$serverKey] == "" || $serverAddr['databaseServersDifferent'] == false){
					$dbTransfer->targetHost = $targetServer; 
				}else{
					$dbTransfer->targetHost = $serverGroup["databaseAddr"][$serverKey]; 
				}

				$dbTransfer->targetUser = $serverGroup['databaseUsername'];
				$dbTransfer->targetPassword = $serverGroup['databasePassword'];
				$dbTransfer->targetPort = $serverGroup['databasePort'];

				$return = $dbTransfer->initNewTarget();

				if($return !== true){
					$log->logEntry("WARNING: Database Error Connecting to Target Database Host ".$return,"Processing",$processEntry['hashID']);
				}

				$proceed = false;
				
				$return = $dbTransfer->checkRemoteStatus();

				switch ($return) {
					case "ready":
						$proceed = true;
					break;
					case "notReady":
						$proceed = false;
						if(count($serverGroup["serverAddr"]) > 1 && count($serverGroup["serverAddr"]) > count($serverGroup['skippedHosts'])+1){
							$log->logEntry("WARNING: Remote server $targetServer not ready. Skipping Host.","Processing",$processEntry['hashID']);
							$serverGroup['skippedHosts'][$serverKey] = true;
						}else{
							$log->error("ERROR: Remote server $targetServer not ready. Either there are no spare hosts OR too many hosts have been skipped.",$processEntry['hashID']);
						}
					break;
					case "wrongdb":
						$proceed = false;
						if(count($serverGroup["serverAddr"]) > 1 && count($serverGroup["serverAddr"]) > count($serverGroup['skippedHosts'])+1){
							$log->logEntry("WARNING: Database on $targetServer exists but is not a wordpress database OR is from a different blog. Skipping Host.","Processing",$processEntry['hashID']);
							$serverGroup['skippedHosts'][$serverKey] = true;
						}else{
							$log->error("ERROR: Database on $targetServer exists but is not a wordpress database OR is from a different blog. Either there are no spare hosts OR too many hosts have been skipped.",$processEntry['hashID']);
						}
					break;
					case "emptydb":
						$proceed = true;
						$initNewSystem = true;
					break;
					case "nodb":
						$initNewSystem = true;
						$dbTransfer->createRemote($serverGroup['excludeAdmin']);
						$proceed = true;
					break;
				}

				$log->logEntry("INFO: Server Transfer Type is ".$serverGroup['serverTransferType'],"Processing",$processEntry['hashID']);
				// Test Connectivity
					// Test Filesystem Access
					if($serverGroup['serverTransferType'] == "ftp"){
						$ftph = @ftp_connect($targetServer, $serverGroup['serverPort']);
						$login_result = ftp_login($ftph, $serverGroup['serverUsername'], $serverGroup['serverPassword']);

						if($login_result === FALSE) {
							// Connect failed
							$log->error("WARNING: Unable to connect to ".$targetServer." via FTP",$processEntry['hashID']);
							$proceed = false;
						}else{
							$log->logEntry("INFO: Connection to ".$targetServer." via FTP verified","Processing",$processEntry['hashID']);
							$proceed = true;
						}

					}else{

						$bashCommand = "[ -d ".$serverGroup['serverTargetDir']." ] || mkdir ".$serverGroup['serverTargetDir']; 
						$command = $this->sshbase.escapeshellarg($targetServer)." ".escapeshellarg($bashCommand);
						$last_line = system($command,$exit_status);

						if($exit_status===0){
							$log->logEntry("INFO: Connection to ".$targetServer." via SSH verified","Processing",$processEntry['hashID']);
							$proceed = true;
						}else{
							$log->logEntry("WARNING: Unable to connect to ".$targetServer." via SSH with Return Code $exit_status","Processing",$processEntry['hashID']);
							$proceed = false;
						}

					}


				// Run the Transfer
				if($proceed==true){
                    
                     if($serverGroup['serverLoadBalancerFile']!="" && $initNewSystem==true){
                         $log->logEntry("INFO: Attempting to create heartbeat file","Processing",$processEntry['hashID']);
                        	if($serverGroup['serverTransferType'] == "ftp"){
                                $temp = tmpfile();
				                fwrite($temp,"Server OK");
                                //Upload the temporary file to server
                                $log->logEntry("INFO: Attempting to upload new heartbeat file via ftp","Processing",$processEntry['hashID']);
                                $return = ftp_fput($ftph, $serverGroup['serverLoadBalancerFile'], $temp, FTP_ASCII);
                                if($return == false){
							     // If heartbeat missing halt with error
							     $log->error("Failed to create the load balancer heartbeat (via FTP) ".$serverGroup['serverLoadBalancerFile'],$processEntry['hashID']);     }
                            }else{
                                $log->logEntry("INFO: Attempting to create new heartbeat file via ssh","Processing",$processEntry['hashID']);
                                $command = $this->sshbase.escapeshellarg($targetServer)." 'echo \"Server OK\" > ".escapeshellarg($serverGroup['serverLoadBalancerFile']."'");
							    $last_line = system($command,$exit_status);
                                if($exit_status!==0){
                                      $log->logEntry("WARNING: Unable to connect to ".$targetServer." via SSH with Return Code $exit_status (attempting to move heartbeat)","Processing",$processEntry['hashID']);
                                }                  
                            }
                        
                    }

					// IF heartbeat File setting, Check Heartbeat
					if($serverGroup['serverLoadBalancerFile']!="" && $initNewSystem == false){
						if($serverGroup['serverTransferType'] == "ftp"){
						// If heartbeat missing halt with error

							// Rename Heartbeat to heartbeat.disabled
							if (ftp_rename($ftph, $serverGroup['serverLoadBalancerFile'], $serverGroup['serverLoadBalancerFile'].".disabled")) {
							$log->logEntry("INFO: Successfully renamed ".$serverGroup['serverLoadBalancerFile']." to ".$serverGroup['serverLoadBalancerFile'].".disabled ","Processing",$processEntry['hashID']);
							}else{
							// If heartbeat missing halt with error
							$log->error("Failed to toggle the load balancer heartbeat (via ftp) ".$serverGroup['serverLoadBalancerFile']." to ".$serverGroup['serverLoadBalancerFile'].".disabled ",$processEntry['hashID']);							}
						}else{

						// Rename Heartbeat to heartbeat.disabled
						$command = $this->sshbase.escapeshellarg($targetServer)." 'mv ".$serverGroup['serverLoadBalancerFile']." ".escapeshellarg($serverGroup['serverLoadBalancerFile'].".disabled'");
						$last_line = system($command,$exit_status);


							if($exit_status===0){
								// If heartbeat missing halt with error

							}else{
								$log->error("Unable to connect to ".$targetServer." via SSH with Return Code $exit_status (attempting to move heartbeat)","Processing",$processEntry['hashID']);

							}
						}
					}// load balancer heartbeat file toggled



					// Sync Files
					$log->logEntry("INFO: Starting Sync to  ".$targetServer." ","Processing",$processEntry['hashID']);

						// Create Exclude List
							$filesToExclude[]=".svn";
							$filesToExclude[]=".hg";
							$filesToExclude[]=".git";
							$filesToExclude[]="weld-logs";
							if($serverGroup['serverLoadBalancerFile']!=""){
								// If a loadbalancer file is present, exclude that too
								$filesToExclude[]=str_replace(ABSPATH,"",$serverGroup['serverLoadBalancerFile']);
								$filesToExclude[]=str_replace(ABSPATH,"",$serverGroup['serverLoadBalancerFile']).".disabled";
							}
							if($serverGroup['excludeAdmin'] == true){
								// If setting is prod, then exclude admin folders, WELD folders
								$adminPreventCopy = get_option('weld_hardening_wpadmin');
								if($adminPreventCopy === true || $adminPreventCopy == "true"){
									$filesToExclude[]="wp-admin";
									$filesToExclude[]="wp-login.php";
								}
								$filesToExclude[]=str_replace(ABSPATH,"",WELD_PLUGIN_DIR);

								// Folders of plugins marked for exclusion
								$settingFlat = get_option( 'weld_hardening_pluginexcludes' );
								$setting = json_decode($settingFlat,true);

								foreach($setting as $plugin){
									$filesToExclude[]=str_replace(ABSPATH,"",plugin_dir_path($plugin));
								}
							}
			



							// Exclude .svn, .hg, .git, heartbeat, heartbeat.disabled

						if($serverGroup['serverTransferType'] == "ftp"){
							// FTP
							// Run FTP
							$log->logEntry("INFO: Starting FTP Process","Processing",$processEntry['hashID']);
							$ftp_bulk = new FTPBulkTransfer($targetServer, $serverGroup['serverPort'], $serverGroup['serverUsername'], $serverGroup['serverPassword']);
							$ftp_bulk->hashID = $processEntry['hashID'];
							if($ftp_bulk->is_open()){
                						$log->logEntry("INFO: Opened ".$ftp_bulk->connection_count()." ftp connections to $targetServer ...","Processing",$processEntry['hashID']);
							}else{
								$log->error("Failed to open ".WELD_FTP_MAXIMUM_CONNECTIONS." ftp connections to $targetServer.",$processEntry['hashID']);
							}

							// Extend Execution time
							if(intval(ini_get("max_execution_time"))<21600 && intval(ini_get("max_execution_time"))!=0){
							ini_set("max_execution_time", "21600");   
							}

							$log->logEntry("INFO: Queuing files and folders for FTP transfer.","Processing",$processEntry['hashID']);
							$ftp_bulk->transferFolder($ftph,ABSPATH,$serverGroup['serverTargetDir'],$filesToExclude);
							$log->logEntry("INFO: Starting main FTP transfer.","Processing",$processEntry['hashID']);
							if($ftp_bulk->is_open()){
								while($ret = $ftp_bulk->poll()){
								ftp_pwd($ftph);   //  keep connection open
								}
							}
							$log->logEntry("INFO: Main FTP transfer process has completed.","Processing",$processEntry['hashID']);

						}else{
						// If Rsync
							// Create Rsync command with --delete-after
							$syncExclude = null;
							foreach($filesToExclude as $excludeMe){
								$syncExclude .= "--exclude $excludeMe ";
							}
//echo "\nSync Exclude\n$syncExclude\n";
							$sync_command="rsync -azcO --quiet --port=".$serverGroup['serverPort']." -e ssh --delete-after $syncExclude ".escapeshellarg(ABSPATH)." ".escapeshellarg($targetServer).":".escapeshellarg($serverGroup['serverTargetDir']);

							// Run Rsync
							$log->logEntry("INFO: Starting Rsync Process","Processing",$processEntry['hashID']);
							system($sync_command, $exit_code);

							// Make up to 5 attempts to run.
							$retry_loop = 0;
							$exit_code = 12;
							while($exit_code!=0 && $retry_loop < 5){
								system($sync_command, $exit_code);
								$retry_loop++;
								// On the 3rd try, modify the rsync switches to work with older centos boxes
								$sync_command = str_replace(" -azcO "," -az ",$sync_command);
							}
							if($exit_code!=0 && $retry_loop == 5){
								// If five critical fails, halt with error
								$entry = "The Rsync command $sync_command has failed with Return Code ".intval($exit_code)." for $targetServer";
								if($exit_code < 22 && $exit_core >1){
									// Severe Errors
									$log->error("$entry",$processEntry['hashID']);
								}else{
									// Errors
									$log->logEntry("WARNING: $entry","Processing",$processEntry['hashID']);
								}
							}

							if($adminPreventCopy === false || $adminPreventCopy == "false"){
								$log->logEntry("INFO: Hardening Server by sending .htaccess file to wp-admin.","Processing",$processEntry['hashID']);
								$sync_command="scp -q -P ".$serverGroup['serverPort']." ".WP_PLUGIN_DIR ."/weld-cron/.htaccess_wpadmin"." ".escapeshellarg($targetServer).":".escapeshellarg($serverGroup['serverTargetDir']."/wp-admin/.htaccess");
							$exit_code = 12;
							while($exit_code!=0 && $retry_loop < 5){
								system($sync_command, $exit_code);
								$retry_loop++;
								}
							if($exit_code!=0 && $retry_loop == 5){
								$log->logEntry("WARNING: Upload of .htaccess file has failed with Return Code".intval($exit_code),"Processing",$processEntry['hashID']);
							}

							}
									// If success with warnings, continue
						
								// Run ACL Mask clear
								// Log entry and continue
						}
						// ElseIf 
								// ??

					// MySQL Transfer

						// Run Command
						// If fail, make up to 3 attempts to run
						$iterator = 0;
						$return = false;
						while($iterator < 3 && $return != true){
							$return = $dbTransfer->deploy();
							$iterator++;
						}
						if($return !== true && $iterator == 3){
							$log->error("Failed sending database to $targetServer ",$processEntry['hashID']);
						}
							

						// Verify Data?
						$return = $dbTransfer->verifyDataTransfer();
						if($return !== true){
							$log->error("Verification of Database on $targetServer has failed",$processEntry['hashID']);
						}

						// Update remote server settings
						$dbTransfer->updateTargetServerSettings();

						// If setting is prod, then remove WELD settings
						if($serverGroup['excludeAdmin']==true){
							$dbTransfer->hardenServerDB();
							}


					// Flush rewrite rules on remote

					// Update remote WELD status
					$dbTransfer->remoteStatus("Ready");

					// If heartbeat file setting
						
					if($serverGroup['serverLoadBalancerFile']!="" && $initNewSystem == false){
						if($serverGroup['serverTransferType'] == "ftp"){
						// If heartbeat missing halt with error

							// Rename Heartbeat to heartbeat.disabled
							if (ftp_rename($ftph, $serverGroup['serverLoadBalancerFile'].".disabled", $serverGroup['serverLoadBalancerFile'])) {
							     $log->logEntry("INFO: Successfully renamed ".$serverGroup['serverLoadBalancerFile'].".disabled to ".$serverGroup['serverLoadBalancerFile']." ","Processing",$processEntry['hashID']);
							}else{
                                //Create a temporary file
                                $temp = tmpfile();
				                fwrite($temp,"Server OK");
                                //Upload the temporary file to server
                                $return = ftp_fput($ftph, $serverGroup['serverLoadBalancerFile'], $temp, FTP_ASCII);
                                
                                if($return == false){
							     // If heartbeat missing halt with error
							     $log->error("Failed to toggle the load balancer heartbeat (via FTP) ".$serverGroup['serverLoadBalancerFile'].".disabled to ".$serverGroup['serverLoadBalancerFile']." ",$processEntry['hashID']);     }
							}
						}else{

						// Rename Heartbeat to heartbeat.disabled
							$command = $this->sshbase.escapeshellarg($targetServer)." 'mv ".$serverGroup['serverLoadBalancerFile'].".disabled ".escapeshellarg($serverGroup['serverLoadBalancerFile']."'");
							$last_line = system($command,$exit_status);

							if($exit_status===0){
								// If heartbeat missing halt with error
                                
                                
							}else{
                                // Attempt to create remote log file
                                $command = $this->sshbase.escapeshellarg($targetServer)." 'echo \"Server OK\" ".escapeshellarg($serverGroup['serverLoadBalancerFile']."'");
							     $last_line = system($command,$exit_status);
                                if($exit_status!==0){
                                      $log->logEntry("WARNING: Unable to connect to ".$targetServer." via SSH with Return Code $exit_status (attempting to move heartbeat)","Processing",$processEntry['hashID']);
                                }
                                
								

							}
						}
					}// load balancer heartbeat file toggled

                    
                   
                    

					}


					// Clean up various connections and objects
					if(isset($ftph)){
						ftp_close($ftph);
						unset($ftph);
					}

					// Server Complete
				} // end server send loop

				$dbTransfer->removeDump();
				unset($dbTransfer);


		// Remove Entry from To-Do List
		$newProcessList = $this->processList;
		unset($newProcessList[0]);
		$this->processList = array_values($newProcessList);
		$processListFlat = json_encode($newProcessList);

		update_option('weld_process_list',$processListFlat);

		$log->logEntry("Finished processing entry. ".$processEntry['hashID'],"Processing",$processEntry['hashID']);

		// Check for more entries to process AND increment the infinite loop safety
		$this->loopsRun++;
		if($this->checkProcessList() != false){
			if($this->loopsRun <= $this->loopMax){
				$this->processNextEntry();
				return true;
			}else{
				$log->logEntry("Reached maximum number of entries processed (".$this->loopMax.").  Halting on error.  ","Error",$processEntry['hashID']);
				return false;
			}
		}else{
				$log->logEntry("Finished processing entries. ","Processing",$processEntry['hashID']);
				return true;
		}
	} // processNextEntry()


} // end class weld_Processor



