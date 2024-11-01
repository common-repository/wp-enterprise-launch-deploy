<?php

    // a class that handles multiple connections at once to enable many files to be sent simultaneously over ftp
    class FTPBulkTransfer {
        var $connections;       // all connections
        var $active_connections;// connections that are busy performing an action
        var $transfers;         // transfers that still need doing
	var $successfulTransfers;
	var $hashID;
        
        function FTPBulkTransfer($ftp_server, $ftp_port, $ftp_login, $ftp_password) {

            $this->transfers = array();
            $this->active_connections = array();
            $this->connections = array();
            
            $tries = 10;
            $maximum_connections = WELD_FTP_MAXIMUM_CONNECTIONS - 1; // one connection for the automate script
            if($maximum_connections > 0)
            while($ftph = @ftp_connect($ftp_server, $ftp_port?"$ftp_port":"21")) {
                // login to server
                if(!@ftp_login($ftph, $ftp_login, $ftp_password)) {
                    ftp_close($ftph);
                    --$tries;
                    if($tries > 0)
                        continue;
                    break;
                }
                $this->connections[] = $ftph;
                if(count($this->connections) >= $maximum_connections)
                    break;
                $tries = 10;    // reset tries cause obviously credentials are correct
            }
        }
        
        function close() {
            foreach($this->connections as $ftph)
                ftp_close($ftph);
        }
        
        function is_open() {
            return count($this->connections);
        }
        
        // checks which connections are done uploading
        // returns 0 if done
        // returns -1 if no connections are open
        // returns 1 if busy
        function poll() {

            if(!count($this->connections))
                return -1;  // no connections open
        
            $return = 0;
        
            // we have transfers in queue
            if(count($this->transfers)) :
                foreach($this->transfers as $k=>$transfer):
                    // if we have an open connection
                    if($ftph = $this->get_free_connection()) 
                        $this->activate_transfer($ftph, $k);
                    else    // none available so stop sifting through the queue
                        break;
                endforeach;
                $return = 1;   // we are busy
            endif;
            
            foreach($this->active_connections as $k=>$transfer):
                $ftph = $transfer['ftph'];
                if($transfer['type'] == 'put') :
                    if(!$transfer['status']) {

                        $ret = ftp_nb_put($ftph, $transfer['remote'], $transfer['local'], FTP_BINARY);

                        if($ret == FTP_FAILED)
                            $this->transferFailure($transfer, $ret);
                        else if($ret == FTP_MOREDATA)
                            $this->active_connections[$k]['status'] = 'busy';
                        else if($ret == FTP_FINISHED )
                             $this->transferSuccessful($transfer, $ret);
                            
                        if($ret == FTP_FAILED || $ret == FTP_FINISHED) {
                            $this->free_connection($ftph);
                        }
                    } elseif($transfer['status'] == 'busy') {
                        $ret = ftp_nb_continue($ftph);
                        if($ret == FTP_FAILED)
                            $this->transferFailure($transfer, $ret);
                        else if($ret == FTP_FINISHED )
                            $this->transferSuccessful($transfer, $ret);
                            
                        if($ret == FTP_FAILED || $ret == FTP_FINISHED) {
                            $this->free_connection($ftph);
                        }
                    }
                endif;
                $return = 1;   // we are busy
            endforeach;

            return $return;   // we are done
        }
        
        function put($remote_file, $local_file, $error_callback = '', $success_callback = '') {
            // add to the transfer
            $this->transfers[] = array (
                'type' => 'put',
                'remote' => $remote_file,
                'local' => $local_file,
            );
        }
        
        function transfer_count() {
            return count($this->active_connections) + count($this->transfers);
        }
        
        function connection_count() {
            return count($this->connections);
        }
        
        protected function activate_transfer($ftph, $k) {
            $this->active_connections[$ftph] = $this->transfers[$k];
            $this->active_connections[$ftph]['ftph'] = $ftph;
            unset($this->transfers[$k]);
            return false;
        }
        
        protected function free_connection($ftph) {
            if(isset($this->active_connections[$ftph]))
                unset($this->active_connections[$ftph]);
        }
        
        protected function get_free_connection() {
            if(count($this->active_connections) >= $this->connections)
                return false;
            foreach($this->connections as $ftph) {
                if(!isset($this->active_connections[$ftph]))
                    return $ftph;
            }
        }

	function transferFolder($ftph,$sourceFolder,$targetFolder,$excludeList){
		global $log;

		foreach($excludeList as $key => $excludeItem){
			$excludeList[$key] = trim($excludeItem,"/");
			$excludeList[$key] = rtrim($excludeItem,"/");
		}

		$excludeList = "..";
		$excludeList = ".";

		if(!is_dir($sourceFolder)){
			return false;
		}


		$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceFolder), RecursiveIteratorIterator::SELF_FIRST);
		 foreach($objects as $entry => $object){

				$matcher[0] = trim($object->getFileName(),"/");  // filename 
				$matcher[1] = trim(str_replace($object->getFileName(),"",str_replace(ABSPATH,"",$object->getPathName())),"/"); // subfolder name
				$matcher[2] = trim($object->getPathname(),"/"); // full path starting with /
				$matcher[3] = trim(str_replace(ABSPATH,"",$object->getPathname()),"/"); // path relative to the root of wordpress

				foreach($excludeList as $test){
					if(in_array($test,$matcher)){
						$exclude = true;
					}
					foreach($matcher as $match){
						if(strpos($match,$test)===0){
							$exclude = true;
						}
					}
				}

				if($exclude == false){
					if($object->isDir()){
						$fobject=str_replace("//","/","/".$targetFolder."/".str_replace(ABSPATH,"",$object->getPathName()));
						$r=$this->ftpmkdir($ftph,$fobject);
						if($r == false){
						$log->logEntry("WARNING: Creation of folder has failed.","Processing",$this->hashID);
						}
					}else{
						$subPath = str_replace($object->getFileName(),"",str_replace(ABSPATH,"",$object->getPathName()));
						$remoteOb = "/".$targetFolder."/".$subPath.$object->getFileName();

						$this->put(str_replace("//","/",$remoteOb),$object->getPathname());
					}
				}


			}
	}// transferFolder

	function transferSuccessful ($transfer, $ret){
		// $transferRemaining = $this->transfers - $ftp_bulk->transfer_count();
	}// end transferSuccessful

	function transferFailure ($transfer, $ret){
		global $log, $processEntry;
		$log->logEntry("WARNING: The ftp file ".$transfer['local']." $ret has failed.","Processing",$this->hashID);


	} // end transferFailure

	function ftpmkdir($ftp_stream, $dir) {
		global $log;

		if ($this->ftp_is_dir($ftp_stream, $dir) || @ftp_mkdir($ftp_stream, $dir)) return true;
		if (!$this->ftpmkdir($ftp_stream, dirname($dir))) return false;
		return @ftp_mkdir($ftp_stream, $dir);
	} // ftpmkdir

	function ftp_is_dir($ftp_stream, $dir) {
	       $original_directory = @ftp_pwd($ftp_stream);
	       if ( @ftp_chdir( $ftp_stream, $dir ) ) {
		   @ftp_chdir( $ftp_stream, $original_directory );
		   return true;
	       }
	       return false;
	}//ftp_is_dir
    } // FTPBulkTransfer

