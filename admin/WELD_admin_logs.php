<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


function WELD_settings_logs(){
	?>
	<div id="poststuff">
	<div id="post-body-content">
	<?php weld_render_system_status(); ?>
	<h2>Log Files</h2>
	<?php
		$enableLogging = get_option('weld_enableLogging');

		if( $enableLogging === "true" ){$enableLogging=true;}
		if( $enableLogging === "false" ){$enableLogging=false;}

		if($enableLogging == true){
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
		}

		if($enableLogging == true && is_dir($logFolder)){
			// List Log Files
			$dir = new DirectoryIterator($logFolder);
			$logList = null;

			// Create listing of every file that exists
			foreach ($dir as $fileinfo) {
			    if (!$fileinfo->isDot()) {	
				if(pathinfo($fileinfo->getFilename(), PATHINFO_EXTENSION)=="weld"){
					// Parse filename for date
					$filenameSansExt = str_replace(".weld","",$fileinfo->getFilename());
					$dateInfo = DateTime::createFromFormat('Y-m-d-H-i-s', $filenameSansExt);	
					$logList[] = "\n<li><a href=\"".admin_url("/tools.php?page=weld-settings&tab=weld_logs&L=".$filenameSansExt)."\" target=\"_blank\">".$dateInfo->format('l jS \of F Y h:i:s A')."</a></li>";

				}
			    }
			}

			if(count($logList)>0){
				echo "\n<ul class=\"weld-log-list\">\n";
				sort($logList);
				echo implode("\n",$logList);
				echo "\n</ul>\n";

				if(class_exists('ZipArchive')){
					echo "<p><a class=\"button-secondary\" href=\"".admin_url("/tools.php?page=weld-settings&tab=weld_logs&L=lda")."\" target=\"_blank\">Download All Logs [zip]</a></p>";
				}
			}else{
				echo "\n<p>No files in the log folder.</p>\n";
			}



		}else{
			?>
			<p>Either logging has not been enabled or no log folder has been detected.  If logging is enabled, then either no deployments have been run OR the logging folder cannot be created. Check the permissions of the folder where the log folder should exist to ensure that the WELD cron job can write to it.</p>
			<?php
		}



		?>
		<h2>Alerts / Setting Flags</h2>
		<?php
	// Server Settings Log Information
	if(!class_exists('ZipArchive')){
	?>
		<h3>ZipArchive PHP</h3>
		<p>PHP ZipArchive is not available. Log files will be available as individual files (no archive of all files available for download).</p>
	<?php
	}

	$apacheVersion = apache_get_version();
	$OS = strtoupper(substr(PHP_OS, 0, 3));
	if($apacheVersion === false || $OS === "WIN"){
		?>
		<h3>Server Environment</h3>	
		<?php
		if($OS === "WIN"){
			?>
			<p>It appears that this server is running a variant of Windows. The deployment system requires the presence of a unix-like shell that accpets the commands: mv, ssh, mkdir, and rsync along with BASH logic operators. While it is possible to configure a windows server to support WELD, it is not recommended.  If absolutely necessary see <a href="http://www.cygwin.com/" target="_blank">The Cygwin Project</a>.</p>
			<?php
		}

		if($apacheVersion === false){
			?>
			<p>This server is not running Apache.  WELD's security system assumes that the server is capable of processing .htaccess files. If your server does not have this ability, it is strongly recommended that you limit direct access to WELD's plugin files at the server configuration level.</p>
			<?php
		}

	}

	if(!WELD_MYSQL_DUMP_USE_EXEC){
		?>
		<h3>PHP Safemode / MySQL Command Line</h3>

		<p>This installation is either running in PHP safe mode or the mysql command line client is not present.  WELD will still function but will fall back on a pure PHP-based solution. This requires that the entire database fits inside the PHP memory limit (and still have enough memory to run the WELD script).</p>

		<p>Current PHP memory limit: <strong><?php echo ini_get('memory_limit'); ?></strong>*</p>

		<p>*Note, this number is the limit set for php scripts running on the web server.  WELD's deployment processor runnings on a PHP command line (PHP-CLI) instance which MAY be different. Contact your server administrator if you have questions</p>

		<?php
	}

	if('WELD_CUSTOM_CRYP_KEY' !== true){
		?>
		<h3>WELD_key Cryptography</h3>
		<p>Unable to load custom cryptographic key for server settings. Please verify that:</p>
		<ol>
			<li>WELD_key.php exists the plugin folder</li>
			<li>WELD_key.php is executable</li>
		</ol>
		<h4>If WELD_key.php does not exist:</h4>
		<ol>
			<li>Verify that the plugin can write to the plugin folder (at least temporarily)</li>
			<li>Deactivate and Reactivate the plugin. This will cause a new WELD_key.php to generate. NOTE: This will REPLACE the crypto keys currently in use on the server. This means that you will need to re-input the server settings as previous ones will be unrecovarable if the key is changed.</li>
		</ol>
		<?php
	}

	?>
	</div><!-- end post-body-content -->
	</div><!-- end poststuff -->
	<?php

} // end WELD_settings_logs
