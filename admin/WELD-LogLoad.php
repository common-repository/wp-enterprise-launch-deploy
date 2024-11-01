<?php
 // Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Verify that the request is valid
$potato = "/^[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}$/";
$match = preg_match($potato,$_REQUEST['L']);
if($_REQUEST['L'] == "lda"){
	$match = 1;
}
if($match <= 0 && !is_admin()){
	exit;
}else{
	$logFolder = get_option('weld_logFolder');

	// Remove any invalid characters from string
	$logFolder = str_replace(array(':','*','?','"','<','>','|'),'',$logFolder);

	while($logFolder != str_replace(array('//','\\'),'/',$logFolder)){
		$logFolder = str_replace(array('//','\\'),'/',$logFolder);
	}

	// If log folder not set
	if($logFolder == "" || $logFolder == null){
		// Create folder in default location
		$logFolder = ABSPATH ."/wp-content/weld-logs/";
	}else{
		$logFolder = ABSPATH . $logFolder;
	}

	if($_REQUEST['L'] == "lda"){


		$zipname = $logFolder.'/WELD-Logs.zip';
		$zip = new ZipArchive;
		$zip->open($zipname, ZipArchive::CREATE);
		$dir = new DirectoryIterator($logFolder);

		// Create listing of every file that exists
		foreach ($dir as $fileinfo) {
		    if (!$fileinfo->isDot()) {	
			if(pathinfo($fileinfo->getFilename(), PATHINFO_EXTENSION)=="weld"){
				$zip->addFile($fileinfo->getPathname(),$fileinfo->getFilename());
			}
		    }
		}
		$zip->close();
		header('Content-Type: application/zip');
		header('Content-disposition: attachment; filename=WELD-Logs.zip');
		header('Content-Length: ' . filesize($zipname));
		readfile($zipname);
		unlink($zipname);
	}else{

		$filename = $logFolder . "/" . $_REQUEST['L'].".weld";
		$filename = str_replace(array('//','\\'),'/',$filename);

		if(file_exists($filename)){
		    header('Content-Description: File Transfer');
		    header('Content-Type: application/octet-stream');
		    header('Content-Disposition: attachment; filename='.basename($filename));
		    header('Content-Transfer-Encoding: binary');
		    header('Expires: 0');
		    header('Cache-Control: must-revalidate');
		    header('Pragma: public');
		    header('Content-Length: ' . filesize($filename));
		    ob_clean();
		    flush();
		    readfile($filename);
		}
	}
	exit;
}
