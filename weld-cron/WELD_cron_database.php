<?php

// Prevent this from running in a browser
if(!php_sapi_name() == 'cli'){
	echo "WELD Error: This CANNOT be executed from within the browser";
	exit;
}

if ( ! defined( 'ABSPATH' ) ) exit;


class WELD_DatabaseTransfer {
	var $sourceDBname = DB_NAME;
	var $targetDBname;
	var $sourceHost;
	var $sourcePort;
	var $targetHost;
	var $targetUser;
	var $databaseSSL;
	var $databaseSSLclientCert;
	var $databaseSSLclientKey;
	var $targetPassword;
	var $targetPort;
	var $hashID;
	var $dumpfilename;
	private $old_url;
	private $new_url;
	private $sqlDump;
	private $sourceConnection;
	private $targetConnection;
	private $sourceChecksum;
	var $tableList;

	function init(){
		global $log;

		// Configure Setting
		

		if(strrpos(DB_HOST,":")===false){
			$this->sourceHost = DB_HOST;
			$this->sourcePort = 3306;
		}else{
			$database_bits = explode(":",DB_HOST);
			$this->sourceHost = $database_bits[0];
			$this->sourcePort = $database_bits[1];
		}
		
		if(($this->databaseSSL !== true && $this->databaseSSL !== "true" )|| $this->databaseSSLclientCert == "" || $this->databaseSSLclientKey == ""){
			$this->databaseSSLclientCert = "";
			$this->databaseSSLclientKey = "";
			$this->databaseSSL = false;
		}

		// Connect to databases
		$this->sourceConnection = new mysqli($this->sourceHost, DB_USER, DB_PASSWORD, DB_NAME, $this->sourcePort);
			if($this->sourceConnection->connect_error){
				return 'Source Connection Error '.$this->sourceHost.' (' . $this->sourceConnection->connect_errno . ') '. $this->sourceConnection->connect_error;
			}
		
		
		// Calculate Source Checksums
		$query = "SELECT GROUP_CONCAT(TABLE_NAME SEPARATOR \", \") AS TableList FROM information_schema.TABLES WHERE TABLE_SCHEMA = '".$this->targetDBname."'";
		$result = $this->sourceConnection->query($query);
		if($result->num_rows>0){
			while($row = $result->fetch_row()){
				$rows[]=$row;
			}
			$this->tableList = $rows[0]['TableList'];
		}

		$query = "CHECKSUM TABLE ".$this->tableList;
		$result = $this->sourceConnection->query($query);
		if($result->num_rows>0){
			while($row = $result->fetch_row()){
				$this->sourceChecksum[$row['Table']]=$row['Checksum'];
			}
		}


		return true;
	}// end init();

	function initNewTarget(){
		global $log;

		if($this->targetHost == ""){
			return "Target Host Undefined";
			}

		if($this->targetDBname ==""){
			$this->targetDBname = $this->sourceDBname;
		}

		if($this->targetUser == ""){
			$this->targetUser = DB_USER;
		}

		if($this->targetPassword == ""){
			$this->targetPassword = DB_PASSWORD;
		}

		if($this->targetPort == "" || intval($this->targetPort)==0){
			$this->targetPort = $this->sourcePort;
		}

		$this->targetConnection = mysqli_init();

		if($this->databaseSSL){
			$log->logEntry("INFO: Attempting to connect to database over SSL ".$this->targetHost,"Processing",$this->hashID);

			$this->targetConnection->ssl_set($this->databaseSSLclientKey, $this->databaseSSLclientCert, NULL, NULL,NULL);

			$link = mysqli_real_connect($this->targetConnection,$this->targetHost, $this->targetUser, $this->targetPassword, $this->targetDBname, $this->targetPort,MYSQLI_CLIENT_SSL);

			if($this->targetConnection->connect_error){
				$link = mysqli_real_connect($this->targetConnection,$this->targetHost, $this->targetUser, $this->targetPassword, null, $this->targetPort,MYSQLI_CLIENT_SSL);
				if($this->targetConnection->connect_error){
					return 'Target Connection Error '.$this->targetHost.' (' . $this->targetConnection->connect_errno . ') '. $this->targetConnection->connect_error;
				}
			}else{
				$log->logEntry("INFO: Initialized Target Database Connection ".$this->targetHost,"Processing",$this->hashID);
				return true;
			}
		}else{
			$link = mysqli_real_connect($this->targetConnection,$this->targetHost, $this->targetUser, $this->targetPassword, $this->targetDBname, $this->targetPort);

			if($this->targetConnection->connect_error){
				$this->targetConnection = new mysqli($this->targetHost, $this->targetUser, $this->targetPassword, null, $this->targetPort);
				if($this->targetConnection->connect_error){
					return 'Target Connection Error '.$this->targetHost.' (' . $this->targetConnection->connect_errno . ') '. $this->targetConnection->connect_error;
				}
			}else{
				$log->logEntry("INFO: Initialized Target Database Connection ".$this->targetHost,"Processing",$this->hashID);
				return true;
			}


		}

		
	} // end initNewTarget

	function checkRemoteStatus(){
		global $wpdb, $log;
//		$wpdb->prefix

		$log->logEntry("INFO: Checking Remote Status for ".$this->targetHost,"Processing",$this->hashID);
		// Run a table create in case it's a new pristine database
		$query = "SELECT * FROM information_schema.schemata WHERE SCHEMA_NAME = '".$this->targetDBname."';";

		$result= $this->targetConnection->query($query);

		if($result->num_rows==0){
			// Database doesn't exist
			$log->logEntry("WARNING: Database ".$this->targetDBname." does not exist on ".$this->targetHost,"Processing",$this->hashID);
			return "nodb";
		}


		// Database Exists, Check to see if it's a WP Database
		$query = "SELECT * 
			FROM information_schema.TABLES 
			WHERE TABLE_SCHEMA = '".$this->targetDBname."'";
		$result= $this->targetConnection->query($query);

		if($result->num_rows==0){
			// Database is empty
		$log->logEntry("WARNING: Database ".$this->targetDBname." exists but is empty on ".$this->targetHost,"Processing",$this->hashID);
			return "emptydb";
		}else{
			$query = "SELECT * 
				FROM information_schema.TABLES 
				WHERE TABLE_SCHEMA = '".$this->targetDBname."' AND TABLE_NAME NOT LIKE '%".$wpdb->prefix."%';";
			$result= $this->targetConnection->query($query);
			if($result->num_rows==0){	
				// Database is wordpress DB
				$log->logEntry("INFO: Database ".$this->targetDBname." exists and is the correct wordpress database on ".$this->targetHost,"Processing",$this->hashID);
				$query = "SELECT option_value FROM ".$this->targetDBname.".".$wpdb->prefix."options WHERE option_name = \"weld_status\" LIMIT 1;";
				$result= $this->targetConnection->query($query);

				$remoteStatusOverride = get_option('weld_remote_status_override');
				$remoteStatusOverrideStatusesNegative = array("Processing","Error");
				$remoteStatusOverrideStatusesPositive = array("Ready");

				if($result->num_rows>0){
					while($row = $result->fetch_row()){
						$rows[]=$row;
					}
				}else{
					$rows[0][0]="Ready";
				}


				if((strtolower($rows[0][0])=="ready" || in_array($remoteStatusOverride,$remoteStatusOverrideStatusesPositive)) && (!in_array($remoteStatusOverride,$remoteStatusOverrideStatusesNegative))){
					return "Ready";
				}else{
					$log->logEntry("WARNING: Target ".$this->targetHost." is reporting that it is not ready.","Processing",$this->hashID);

					return "notReady";
				}
			}else{
				// Database is different from source.  Error.
				$log->logEntry("WARNING: Database ".$this->targetDBname." exists but does not appear to be a wordpress database OR does not have matching table prefixes on ".$this->targetHost,"Processing",$this->hashID);
				return "wrongdb";
			}
		}


	}// end checkRemoteStatus


	function createRemote($excludeAdmin){
		global $log;
		$log->logEntry("INFO: Attempting to create ".$this->targetDBname." database and users on ".$this->targetHost,"Processing",$this->hashID);
		$queries[]="CREATE SCHEMA ".$this->targetConnection->real_escape_string($this->targetDBname).";";
		$queries[]="CREATE USER ".$this->targetConnection->real_escape_string(DB_USER)."@localhost identified by '".$this->targetConnection->real_escape_string(DB_PASSWORD)."';";

		if($excludeAdmin == true){				
			$queries[]="GRANT SELECT ON ".$this->targetConnection->real_escape_string($this->targetDBname).".* TO ".$this->targetConnection->real_escape_string(DB_USER)."@localhost WITH GRANT OPTION;";
		}else{
			$queries[]="GRANT ALL ON ".$this->targetConnection->real_escape_string($this->targetDBname).".* TO ".$this->targetConnection->real_escape_string(DB_USER)."@localhost WITH GRANT OPTION;";
		}

		$queries[]="CREATE USER ".$this->targetConnection->real_escape_string(DB_USER)."@'%' identified by '".$this->targetConnection->real_escape_string($this->targetPassword)."';";
		$queries[]="GRANT ALL ON ".$this->targetConnection->real_escape_string($this->targetDBname).".* TO ".$this->targetConnection->real_escape_string(DB_USER)."@'%' WITH GRANT OPTION;";

		foreach($queries as $query){
			$this->targetConnection->query($query);
		}
		$this->targetConnection->close();
		$this->initNewTarget();

	}// end createRemote




        function dump($old_url = NULL, $new_url = NULL, $exec = false) {
		global $log;
		$this->old_url = $old_url;
		$this->new_url = $new_url;
		$log->logEntry("INFO: Starting MySQL dump cycle.","Processing",$this->hashID);        
		$this->dumpfilename = WELD_DIR.'/weld-cron/WELD_dump.sql';
       		$log->logEntry("INFO: MySQL dump file is ".$this->dumpfilename,"Processing",$this->hashID);        

            if(WELD_MYSQL_DUMP_USE_EXEC || $exec){
                $worked = $this->exec_dump($this->dumpfilename, $old_url, $new_url);
                switch($worked){
                    case 0:
                        break;
                    case 1:
                        $this->last_result = "WARNING: Database Error ".$this->last_result;
			$log->logEntry($this->last_result,"Processing",$this->hashID);
                        break;
                    case 2:
                        $this->last_result = "ERROR: Unrecoverable Database Error ".$this->last_result;
			$log->logEntry($this->last_result,"Processing",$this->hashID);
                        break;
                }
                if(!$worked) return 0;
            } 
          
            return $this->php_dump($this->dumpfilename, $old_url, $new_url);
        }
        


        
        protected function exec_dump($filename, $old_url = NULL, $new_url = NULL) {
		global $log;
		$log->logEntry("INFO: MySQL dump via command line.","Processing",$this->hashID);        
            if(DB_PASSWORD) {
                $password = ' -p' .escapeshellarg(DB_PASSWORD);
		}

		$ssl = "";
		if($this->databaseSSL ==true){
			$ssl = " --ssl-cert=".$this->databaseSSLclientCert." --ssl-key=".$this->databaseSSLclientKey;
		}

            $command='mysqldump --opt -R -q -h'.escapeshellarg($this->sourceHost). "$ssl --port=". escapeshellarg($this->sourcePort) .' -u' .escapeshellarg(DB_USER) .$password .' ' .escapeshellarg($this->sourceDBname) .' > '.escapeshellarg($filename);

            $this->last_result = system($command,$worked);

            if(!$worked && $old_url != $new_url) :
                $dump = file_get_contents($filename);
		$log->logEntry("INFO: Updating URL to target's Publish URL.","Processing",$this->hashID);   
                $dump = $this->recalcserializedlengths(str_replace($old_url, $new_url, $dump), false);
                file_put_contents($filename, $dump);
            endif;
            return $worked;
        }
        
        // slightly edited version of davidcoveney's serialize recalculator http://davidcoveney.com/575/php-serialization-fix-for-wordpress-migrations/
        // used to fix serialized strings when URL's are changed
        protected function recalcserializedlengths($string, $escaped = false) {
		global $log;
		$log->logEntry("INFO: Recalculating Serialized Arrays","Processing",$this->hashID);
            if($escaped) {
		$log->logEntry("INFO: Recalculating Serialized Arrays: Assuming Data is Escaped","Processing",$this->hashID);
                $__ret =preg_replace('!s:(\d+):\\\\"(.*?)\\\\";!e', "'s:'.strlen('$2').':\\\"$2\\\";'", $string );
            }else{
		$log->logEntry("INFO: Recalculating Serialized Arrays: Assuming Data is Not Escaped","Processing",$this->hashID);
               // $__ret =preg_replace('!s:(\d+):"(.*?)";!e', "'s:'.strlen('$2').':\"$2\";'", $string );
            	$__ret =preg_replace('!s:(\d+):"(.*?)";!e', "'s:'.strlen(stripslashes('$2')).':\"$2\";'", $string );
		}
            return $__ret;
        } 
        
        protected function php_dump($filename, $old_url = NULL, $new_url = NULL) {
		global $log;
		$log->logEntry("INFO: MySQL dump via PHP & MySQLi.","Processing",$this->hashID);        
            
		$dump = $this->php_dump_data($filename, $old_url, $new_url);

            $fp = fopen($filename, 'w');
            if(!$fp) return 4;
            $return = fwrite($fp, $dump);
		if($return === false){
			$log->logEntry("WARNING: Unable to write dumpfile.","Processing",$this->hashID);
			$this->sqlDump = $dump;  
					
		}
            fclose($fp);
            return 0;
        } // end php_dump

	
	protected function php_dump_data($filename, $old_url = NULL, $new_url = NULL){
		global $log;

		if(!$this->sourceConnection) :
		        $this->sourceConnection = new mysqli($this->sourceHost, DB_USER, DB_PASSWORD);
		        if(!$this->sourceConnection || !$this->sourceConnection->select_db($this->sourceDBname)) {
		           if($this->sourceConnection){
		                $this->last_result = $this->sourceConnection->error;
				$log->logEntry("WARNING: PHP-Based Dump Error from Source Database ".$this->sourceConnection->error,"Processing",$this->hashID);
		           }else {
		                $this->last_result = "Unable to connect to mysql database";
				$log->logEntry("WARNING: PHP-Based Dump Error from Source Database  ".$this->sourceConnection->error,"Processing",$this->hashID);
				$log->logEntry($this->last_result,"Processing",$this->hashID);
				}
		            return 3;
		        }
		    endif;
		    
		    // list tables
		    $sql = 'SHOW TABLES FROM '.$this->sourceDBname;
		    $result = $this->sourceConnection->query($sql);
		    if (!$result) {
		        $this->last_result = $this->sourceConnection->error;
		        return 3;
		    } 
		    
		    $dump = "-- WELD MySQL Dump \n"
		    ."--\n"
		    ."-- Host: ".$this->sourceHost."    Database: ".$this->sourceDBname."\n"
		    ."-- ------------------------------------------------------\n"
		    ."-- Server version ".mysql_get_server_info()."\n";
		    
		    $column_Set = false;
		    // go through each table
		    while ($row = mysqli_fetch_row($result)) :
		        $table = $row[0];
		        
		        $dump .= $this->dump_comment("Table structure for table `".$table."`");
		        
		        $dump .= "DROP TABLE IF EXISTS `".$table."`;\n";
			$sql = "SHOW CREATE TABLE `".$table."`;\n";
 			$cresult = $this->sourceConnection->query($sql);
		        if (!$cresult) {
		            echo $sql;
		            $this->last_result = $this->sourceConnection->error;
		            return 3;
		        }else{
				while ($column = mysqli_fetch_assoc($cresult)) :
				   $dump .= $column['Create Table']."\n";
				   
				endwhile;
			}
/*
		        $dump .= "CREATE TABLE `".$table."` (\n";
		        // list columns
		        $sql = 'SHOW COLUMNS FROM `'.$table.'`';
		        $cresult = $this->sourceConnection->query($sql);
		        if (!$cresult) {
		            echo $sql;
		            $this->last_result = $this->sourceConnection->error;
		            return 3;
		        }
		        $primary_key = "";
		        $keys = "";
		        
		        while ($column = mysqli_fetch_assoc($cresult)) :
		            $scolumn = "`".$column['Field']."` ";
		            $scolumn .= ($column['Type'])." ";
		            if($column['Null'] == "NO") $scolumn .= "NOT NULL ";
		            if(isset($column['Default']) || !($column['Null'] == "NO" && $column['Default'] == NULL)) {
		                if(!isset($column['Default']) && !is_string($column['Default'])){
					 $scolumn .= "NULL ";
		                }else{
					if(preg_match("|^([A-Z]*)_([A-Z]*)$|",$column['Default'])){
						$wlDefault = $column['Default'];
					}else{
						$wlDefault = "'{$column['Default']}'";
					}
					if($wlDefault == ""){$wlDefault= "''";}
					$scolumn .= "DEFAULT $wlDefault ";
				}
		            }
		            $scolumn .= strtoupper($column['Extra'])." ";
		            $dump .= "  ".trim($scolumn).",\n";
		        endwhile;

		        $sql = 'SHOW INDEXES FROM `'.$table.'`';
		        $cresult = $this->sourceConnection->query($sql);
		        if (!$cresult) {
		            echo $sql;
		            $this->last_result = $this->sourceConnection->error;
		            return 3;
		        }
		        $primary_key = "";
		        $keys = "";
		        
		        $indexes = array();
		        
		        while ($column = mysqli_fetch_assoc($cresult)) :
		            if(!@$indexes[$column['Key_name']]) :
		                $indexes[$column['Key_name']] = $column;
		            else :
		                $indexes[$column['Key_name']]['Column_name'] = $indexes[$column['Key_name']]['Column_name'] .= '`, `'.$column['Column_name'];
		            endif;
		        endwhile;
		        
		        foreach ($indexes as $column) :
		            $type = null;
		            if(!$column['Non_unique']) :
		                if($column['Key_name'] == "PRIMARY")
		                    $type = "PRIMARY";
		                else
		                    $type = "UNIQUE";
		            else :
		                $type = "KEY";
		            endif;
				if($column['Sub_part']!=""){
					$keyLength = "(".intval($column['Sub_part']).")";
				}else{
					$keyLength = "";
				}
		            switch($type) :
		                case 'PRIMARY':
		                    $dump .= "  PRIMARY KEY (`".$column['Column_name']."`".$keyLength."),\n";
		                    break;
		                case 'UNIQUE':
		                    $dump .= "  UNIQUE KEY `".$column['Key_name']."` (`".$column['Column_name']."`".$keyLength."),\n";
		                    break;
		                case 'KEY':
		                    $dump .= "  KEY `".$column['Key_name']."` (`".$column['Column_name']."`".$keyLength."),\n";
		                    break;
		            endswitch;
		        endforeach;
		        
		        $sql = 'SHOW TABLE STATUS LIKE "'.$table.'"';
		        $cresult = $this->sourceConnection->query($sql);
		        if (!$cresult) {
		            echo $sql;
		            $this->last_result = $this->sourceConnection->error;
		            return 3;
		        }
		        $attributes = "";
		        
		        while ($column = mysqli_fetch_assoc($cresult)) :
		            $attributes .= " ENGINE=".$column['Engine'];
		            if($column['Auto_increment']) $attributes .= " AUTO_INCREMENT=".$column['Auto_increment'];
		            if($column['Collation']) $attributes .= " DEFAULT CHARSET=".current(explode('_',$column['Collation']));
		        endwhile;
*/
		        // cut of the last comma
		        $dump = trim($dump);
		        if($dump[strlen($dump)-1] == ',') $dump[strlen($dump)-1] = "\n";

		        $dump .= " $attributes;\n";
		        
		        $dump .= $this->dump_comment("Dumping data for table `".$table."`");
		        $dump .= "LOCK TABLES `".$table."` WRITE;\n";
		        //$dump .= "/*!40000 ALTER TABLE `".$table."` DISABLE KEYS */;\n";
		        
		        $sql = 'SELECT * FROM `'.$table.'`';
		        $cresult = $this->sourceConnection->query($sql);
		        if (!$cresult) {
		            echo $sql;
		            $this->last_result = $this->sourceConnection->error;
		            return 3;
		        }
		        
		        while ($column = mysqli_fetch_assoc($cresult)) :
		            $dump .= "INSERT INTO `$table` VALUES (";
		            $first = true;
		            foreach($column as $v) {
		                if(!$first) $dump .= ", ";
		                if($old_url != $new_url)
		                    $v = $this->recalcserializedlengths(str_replace($old_url, $new_url, $v));
		                $dump .= "'".$this->sourceConnection->real_escape_string($v)."'";
		                $first = false;
		            }
		            $dump .= ");\n";
		            $column;
		        endwhile;
		        
		        //$dump .= "/*!40000 ALTER TABLE `".$table."` ENABLE KEYS */;\n";
		        $dump .= "UNLOCK TABLES;\n";
		        
		        
		    endwhile;

			$log->logEntry("INFO: MySQL dump via PHP & MySQLi Complete.","Processing",$this->hashID);  
		    mysqli_free_result($result);

		return utf8_encode($dump);
	}// end php_dump_data

        protected function dump_comment($text) {
            return "\n--\n".
            "-- ".$text."\n".
            "--\n\n";
        }


	function deploy($exec){
		global $log;
		$log->logEntry("INFO: Starting deployment of database to ".$this->targetHost.".","Processing",$this->hashID);  
		if($this->targetDBname =="" || $this->targetUser == "" || $this->targetPassword == "" || $this->targetPort == "" || intval($this->targetPort)==0){
			$log->logEntry("WARNING: Database target connection is not initialized.","Processing",$this->hashID);  
			return false;
		}

		if($this->sqlDump == ""){
			if($this->dumpfilename == ""){
				$log->logEntry("WARNING: MySQL Dump Filename is Undefined.","Processing",$this->hashID);  
				return false;
				}

			if(!file_exists($this->dumpfilename)){
				$log->logEntry("WARNING: MySQL Dump File does not exist.","Processing",$this->hashID);  
				return false;
				}
			}

		if(WELD_MYSQL_DUMP_USE_EXEC || $exec){
			$return = $this->deploy_exec();
			if($return === true){
				return true;
			}
		}

		$return = $this->deploy_php();

		if($return === true){
			return true;
		}else{
			return false;
		}

	} // end deploy()


	function deploy_exec(){
		global $log;

		if($this->targetDBname =="" || $this->targetUser == "" || $this->targetPassword == "" || $this->targetPort == "" || intval($this->targetPort)==0){
			$this->initNewTarget();
		}

		if($this->targetPassword != ""){
			$password=" --password=". escapeshellarg($this->targetPassword);
		}

		$ssl = "";
		if($this->databaseSSL ==true){
			$ssl = " --ssl-cert=".$this->databaseSSLclientCert." --ssl-key=".$this->databaseSSLclientKey;
		}

		$command = "mysql -u ".escapeshellarg($this->targetUser)."$ssl --port=".escapeshellarg($this->targetPort)." $password -h ".escapeshellarg($this->targetHost)." ".escapeshellarg($this->targetDBname)." < ".$this->dumpfilename;

		$commandEntry = "mysql -u ".escapeshellarg($this->targetUser)."$ssl --port=".escapeshellarg($this->targetPort)." --password=PASSWORD -h ".escapeshellarg($this->targetHost)." ".escapeshellarg($this->targetDBname)." < ".$this->dumpfilename;

		$log->logEntry("INFO: Sending MySQL dump file to remote server ".$this->targetHost." with the command \"$commandEntry\"","Processing",$this->hashID);
		system($command, $exit_code);

							// Make up to 5 attempts to run.
		$retry_loop = 0;
		while($exit_code!=0 && $retry_loop < 5){
			system($sync_command, $exit_code);
			$retry_loop++;
			// On the 3rd try, modify the rsync switches to work with older centos boxes
			$sync_command = str_replace(" -azO "," -az ",$sync_command);
		}
		if($exit_code!=0 && $retry_loop == 5){
			// If five critical fails, halt with error
			$entry = "The MySQL command sync command has failed with Return Code ".intval($exit_code)." for ".$targetServer;
			$log->logEntry("WARNING: ".$entry,$this->hashID);
			return false;
			}

		if($exit_code == 0){
			$log->logEntry("INFO: MySQL dump file succesfully sent to remote server ".$this->targetHost,"Processing",$this->hashID);
			return true;
		}
	} // end deploy_exec()

	function deploy_php(){
		global $log;

		$log->logEntry("INFO: Sending MySQL database to ".$this->targetHost." via PHP & MySQLi","Processing",$this->hashID);

		if(file_exists($this->dumpfilename)){
	   	if(!$this->targetConnection->query(file_get_contents($this->dumpfilename))) {
			$sqlError = $this->last_result = $this->targetConnection->error;
			$log->logEntry("WARNING: MySQL error ".$sqlError," processing from ".$this->dumpfilename,$this->hashID);  
			}else{
				return true;
			}
		}

		if($this->sqlDump==""){
			$log->logEntry("INFO: MySQL Dump is not in memory. Attempting to load... ","Processing",$this->hashID);
			$this->sqlDump = $this->php_dump_data($this->old_url,$this->new_url);

			if($this->sqlDump==""){
				$log->logEntry("WARNING: MySQL Dump failed to load into memory","Processing",$this->hashID);
				return false;
			}else{
				$log->logEntry("INFO: MySQL Dump has been loaded into memory.","Processing",$this->hashID);
			}
		}

		$SQLs = explode(";\n",$this->sqlDump);
		foreach($SQLs as $query){
		if($query !== ""){
			if(!$this->targetConnection->query($query)) {
				$sqlError = $this->last_result = $this->targetConnection->error;
				$log->logEntry("WARNING: MySQL error ".$sqlError." processing from MySQL Dump in memory.",$this->hashID);  
				return false;
				}
			}
		}
		$log->logEntry("INFO: MySQL database has been sent to ".$this->targetHost." via PHP & MySQLi","Processing",$this->hashID);
		return true;
	} // end deploy_php

	function removeDump(){
		global $log;
		echo "\n\n".$this->dumpfilename."\n\n";
		if(file_exists($this->dumpfilename)){
			unlink($this->dumpfilename);
		}else{
			$log->logEntry("INFO: No MySQL Dump file present to remove","Processing",$this->hashID);  
		}

		if(file_exists($this->dumpfilename)){
			$log->logEntry("WARNING: The attempt to remove MySQL Dump file have failed.","Processing",$this->hashID);  
		}else{
			$log->logEntry("INFO: MySQL Dump file has been removed","Processing",$this->hashID);  
		}

	} // end removeDump()

	function updateTargetServerSettings(){
		global $log,$wpdb;

		if($this->targetDBname =="" || $this->targetUser == "" || $this->targetPassword == "" || $this->targetPort == "" || intval($this->targetPort)==0){
			$this->initNewTarget();
		}

		$query[] = "UPDATE ".$this->targetDBname.".".$wpdb->prefix."options SET option_value = \"[]\" WHERE option_name=\"weld_process_list\"";

		foreach($query as $thisQuery){
		if(!$this->targetConnection->query($thisQuery)) {
			$sqlError = $this->last_result = $this->targetConnection->error;
			$log->logEntry("WARNING: MySQL ERROR Attempting to Remove WELD Options from production server failed: ".$sqlError." FOR QUERY $thisQuery","Processing",$this->hashID);  
			return false;
			}
		}

	}// updateTargetServerSettings

	function hardenServerDB(){
		global $log,$wpdb;
		if($this->targetDBname =="" || $this->targetUser == "" || $this->targetPassword == "" || $this->targetPort == "" || intval($this->targetPort)==0){
			$this->initNewTarget();
			}

		$settingFlat = get_option( 'weld_hardening_pluginexcludes' );
		$setting = json_decode($settingFlat,true);
		$activePlugins = get_option( 'active_plugins' );

		foreach($activePlugins as $key=>$activePlugin){
				if(in_array($activePlugin,$setting)){
					unset($activePlugins[$key]);
				}
			}

		$replacementSetting = serialize(array_values($activePlugins));
		$query[] = "UPDATE ".$this->targetDBname.".".$wpdb->prefix."options SET option_value = \"".$this->targetConnection->real_escape_string($replacementSetting)."\" WHERE option_name=\"active_plugins\"";


		// Remove sensitive WELD options
		$query[] = "DELETE FROM ".$this->targetDBname.".".$wpdb->prefix."options WHERE option_name = \"weld_process_list\";";
		$query[] = "DELETE FROM ".$this->targetDBname.".".$wpdb->prefix."options WHERE option_name = \"weld_logFolder\";";
		$query[] = "DELETE FROM ".$this->targetDBname.".".$wpdb->prefix."options WHERE option_name = \"weld_server_array\";";
		$query[] = "DELETE FROM ".$this->targetDBname.".".$wpdb->prefix."options WHERE option_name = \"weld_processing\";";
		$query[] = "DELETE FROM ".$this->targetDBname.".".$wpdb->prefix."options WHERE option_name = \"weld_endableLogging\";";

		foreach($query as $thisQuery){
		if(!$this->targetConnection->query($thisQuery)) {
			$sqlError = $this->last_result = $this->targetConnection->error;
			$log->logEntry("WARNING: MySQL ERROR Attempting to Remove WELD Options from production server failed: ".$sqlError." FOR QUERY $thisQuery","Processing",$this->hashID);  
			return false;
			}
		}

	} // end hardenServerDB

	function verifyDataTransfer(){
		global $log;

		$query = "CHECKSUM TABLE ".$this->tableList;
		$result = $this->targetConnection->query($query);
		$errors = null;
		if($result->num_rows>0){
			while($row = $result->fetch_row()){
				if($this->sourceChecksum[$row['Table']]!==$row['Checksum']){
					$errors[]=$row['Table'];
				}
			}
		}

		if(count($errors)>0){
			return implode(", ",$errors);
		}else{
			return true;
		}

	}// end verifyDataTransfer

	function remoteStatus($status){
		global $wpdb;
		//$log->logEntry("INFO: Setting status of target: ".$this->targetHost." to $status","Processing",$this->hashID);  
		if($this->targetDBname =="" || $this->targetUser == "" || $this->targetPassword == "" || $this->targetPort == "" || intval($this->targetPort)==0){
					$this->initNewTarget();
				}
		$query = "UPDATE ".$this->targetDBname.".".$wpdb->prefix."options SET option_value = \"".$this->targetConnection->real_escape_string($status)."\" WHERE option_name = \"weld_status\";";
		$result= $this->targetConnection->query($query);

	}

}// end WELD_DatabaseTransfer






