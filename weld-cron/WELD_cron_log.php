<?php

// Prevent this from running in a browser
if(!php_sapi_name() == 'cli'){
	echo "WELD Error: This CANNOT be executed from within the browser";
	exit;
}

if ( ! defined( 'ABSPATH' ) ) exit;



// Status Object
class weld_status {

	public $logType = "screen";
	public $logCapabilities = array();
	private $logFileName = null;
	private $logFolder = "";
	public $enableLogging = false;
	private $logInit = false;
	private $mailer;
	public $globalStatusOptions = array("Pending","Ready","Processing","Error");

	function __construct() {
	// Construct

		$this->enableLogging = get_option('weld_enableLogging');

		if( $this->enableLogging === "true" ){$this->enableLogging=true;}
		if( $this->enableLogging === "false" ){$this->enableLogging=false;}


		if($this->enableLogging == true){
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


			// If Log Folder doesn't exist, create
			if(!is_dir($this->logFolder)){
				$return = mkdir($this->logFolder, 0766,true);

				if($return == false){
					echo "Warning: Unable to Create Log Folder".PHP_EOL;
					$this->logType = "screen";
					$this->logCapabilities['logFolderExists']=false;
				}else{
					echo "Info: Log Folder Created at ".$this->logFolder.PHP_EOL;
					$this->logCapabilities['logFolderExists']=true;
				}
			}else{
				$this->logCapabilities['logFolderExists']=true;
			}


			// Check log directory permissions
			if(is_dir($this->logFolder)){
				$perms = substr(sprintf('%o', fileperms($this->logFolder)), -4);

				if($perms != "0766" && $perms != 766){
					$this->logCapabilities['logFolderPermsSecured'] = $perms;
				}else{
					$this->logCapabilities['logFolderPermsSecured'] = true;
				}
			}

			// Check for htaccess file existence
			if(!is_file($this->logFolder."/.htaccess")){
				$return = copy(WELD_PLUGIN_DIR."/WELD_cron/.htaccess_logFolder",$this->logFolder."/.htaccess");
				if($return == false){
					$this->logCapabilities['logFolderApacheSecured']=false;
				}else{
					$this->logCapabilities['logFolderApacheSecured']=true;
				}
			}else{
				$this->logCapabilities['logFolderApacheSecured']=true;
			}
		}else{
			echo "Notice: Logging is not enabled. Messages will be output to screen.".PHP_EOL;
			$this->logType = "screen";
		}
		
		$this->logDuration = intval(get_option('weld_logDuration'));
		if($this->logDuration <1){
			$this->logDuration=1;
		}

		$this->mailer = new weld_mail();


	} // end __construct

	function initLog(){

		// Check to see if log writing is enabled
		if($this->enableLogging == true){
			// Create Log File
			if(is_dir($this->logFolder)){
				$filename = $this->logFolder."/".date('Y-m-d-H-i-s').".weld";
				$this->logFileName = str_replace(array('//','\\'),'/',$filename);
				$data = "WELD LOG: ".date('l jS \of F Y h:i:s A').PHP_EOL;
				file_put_contents($this->logFileName,$data);
				$data = PHP_EOL;
				$return = file_put_contents($this->logFileName,$data,FILE_APPEND);

				if($return !== false){
					echo "Info: Created Log File ".$this->logFileName.PHP_EOL;
					$this->logType = "file";
				}else{
					echo "Warning: Unable to Create Log File".PHP_EOL;
					$this->logType = "screen";
				}
			}
		}else{
				$this->logType = "screen";
		}

		$this->logInit = true;
	} // end initLog


	function logEntry($entryText,$status,$processID){	
	// Log entry
		if($this->logInit == false){$this->initLog();}

		if($processID==null){
			$processIDText = "Global";
		}else{
			$processIDText = $processID;
		}

		$entryText = date('l jS \of F Y h:i:s A')."  :  $processIDText  :  $status  :  ".$entryText.PHP_EOL;

		// If Toggle == File
		if($this->logType == "file"){
			// Write Line to File
			file_put_contents($this->logFileName,$entryText,FILE_APPEND);
		}else{
			echo $entryText;
		}

		$this->updateStatus($status,$processID);

	}// end logEntry


	function updateStatus($status,$processID){

		$processID = intval($processID);

		if($processID > 0 && in_array($status,$this->globalStatusOptions)){
			// Update the process status info
			$processListFlat = rtrim(get_option('weld_process_list'));
			$processList = json_decode($processListFlat, true);
//var_dump($processList);
			
				$statusArray = json_decode(rtrim(get_option('weld_statusServerArray')),true);
//var_dump($statusArray);
				$statusArray[$processID]['status'] = $status;
				$statusArray[$processID]['statusTime'] = time();

				foreach($processList as $key=>$process){
					if($process['hashID'] == $processID){
						$processList[$key]['status']=$status;
						$processList[$key]['lastUpdated']=time();
					}
				}

			update_option('weld_process_list',json_encode($processList));
			update_option('weld_statusServerArray',json_encode($statusArray));
		}




		if(in_array($status,$this->globalStatusOptions,false)){
			update_option('weld_status',$status);
		}else{
			update_option('weld_status',"Processing");
		}

		update_option('weld_statusTime',time());
		//$this->logEntry("Status Update: ",$status,$processID);
	}// end updateStatus

	function error($entryText,$processID){
		if($this->logInit == false){$this->initLog();}

		$processID = intval($processID);

		$blogName = get_bloginfo("name");

		$message = "The WELD deployment engine has failed for the site: $blogName\n\n Time: ".date('l jS \of F Y h:i:s A')."\nERROR: $entryText";
		$this->mailer->adminMail($message,$this->logFileName);

		$this->updateStatus("Error",$processID);
		update_option('weld_statusTime',time());
		update_option('weld_status',"Error");
		$this->logEntry("Error: ".$entryText,"Error",$processID);
		exit;
	}

	function cleanLogFolder(){
		$this->logEntry("Info: Cleaning Log Folder ".$this->logFolder,"Processing","");
		if(is_dir($this->logFolder) && $this->enableLogging == true && $this->logFolder!=ABSPATH && $this->logFolder!=WP_PLUGIN_DIR && $this->logFolder != WELD_PLUGIN_DIR){
			$now = new DateTime("now");

			$dir = new DirectoryIterator($this->logFolder);
			foreach ($dir as $fileinfo) {
			    if (!$fileinfo->isDot()) {	
				if(pathinfo($fileinfo->getFilename(), PATHINFO_EXTENSION)=="weld"){

					// Parse filename for date
					$filenameSansExt = str_replace(".weld","",$fileinfo->getFilename());
					$dateInfo = DateTime::createFromFormat('Y-m-d-H-i-s', $filenameSansExt);	

					$interval = $now->diff($dateInfo)->days;
					if($interval > $this->logDuration){


if($return === true){$return = "True";}
if($return === false){$return = "False";}
if($return !== "False" && $return !== "True"){$return = "Other";}
clearstatcache();
echo "\nWrite ".is_writable($fileinfo->getPathname());
echo "\nExist ".file_exists($fileinfo->getPathname());
clearstatcache();
						$return = unlink($fileinfo->getPathname());

    echo ($return)?"\nyes\n":"\nno\n";

echo "\n DELETE: ".$fileinfo->getPathname()."  S:$return".PHP_EOL;
					}else{
echo "\n KEEP: ".$fileinfo->getFilename().PHP_EOL;
					}

					// Determine delta between now and log creation

					// If delta > log duration delete file


				}
			    }
			}
		}
	}// end CleanLogFolder

} // end class weld_status


