<?php
/**
 * @package WP Enterprise Launch / Deploy
 * @version 0.1.1
 */
/*
Plugin Name: WP Enterprise Launch Deploy
Plugin URI: 
Description: Deploy wordpress sites without externally generated content from stage to production servers.
Author: Tor N. Johnson
Author URI: http://profiles.wordpress.org/kasigi/
Version: 0.1.1
Tested up to: 3.8.1
License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define('WELD_URL', plugins_url('',__FILE__));
define('WELD_DIR', dirname(__FILE__));

define('WELD_ARCHIVING_ENABLED', class_exists('ZipArchive')); // this is so that you can utilize minimal upload size and therefore speedious ftp .. requires that the Zip extension is installed on both your servers

define('WELD_MYSQL_DUMP_USE_EXEC', !ini_get('safe_mode') && !ini_get('safe_mode_exec_dir'));  // this will use the system shell for mysql dumping/importing or use built in import/export .. built in import / export is experimental
define('WELD_FTP_MAXIMUM_CONNECTIONS', 10); // must be at least 1


	// Define shorthand constants
	if (!defined('WELD_PLUGIN_NAME')){
	    define('WELD_PLUGIN_NAME', trim(dirname(plugin_basename(__FILE__)), '/'));
	}

	if (!defined('WELD_PLUGIN_DIR')){
	    define('WELD_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . WELD_PLUGIN_NAME);
	}

	if (!defined('WELD_PLUGIN_URL')){
	    define('WELD_PLUGIN_URL', plugins_url() . '/' . WELD_PLUGIN_NAME);
	}

	// Set version information
	if (!defined('WELD_VERSION_KEY')){
	    define('WELD_VERSION_KEY', 'WELD_version');
	}

	if (!defined('WELD_VERSION_NUM')){
	    define('WELD_VERSION_NUM', '0.1.0');
	}
	add_option(WELD_VERSION_KEY, WELD_VERSION_NUM);


	// Check to see if updates need to occur
	if (get_option(WELD_VERSION_KEY) != WELD_VERSION_NUM) {
		// If there is any future update code needed it will go here

	    // Then update the version value
	    update_option(WELD_VERSION_KEY, WELD_VERSION_NUM);
	}

function weld_create_options(){
	add_option('weld_server_array',null,'','no');
	add_option('weld_enableLogging',false,'','no');
	add_option('weld_logFolder','wp-content/weld-logs/','','no');
	add_option('weld_logDuration',14,'','no');
	add_option('weld_status',false,'','no');
	add_option('weld_statusTime',0,'','no');
	add_option('weld_process_list',null,'','no');
	add_option('weld_warningInterval',30,'','no');
	add_option('weld_warningIntervalSent',0,'','no');
	add_option('weld_hardening_wpadmin',false,'','no');
	add_option('weld_hardening_pluginexcludes',false,'','no');
	add_option('weld_statusServerArray',false,'','no');
	add_option('weld_remote_status_override','None','','no');
	} //weld_create_options

register_activation_hook( __FILE__, 'weld_create_options' );

// Activation

function weld_create_new_key() {
	$newkey = '';
	  for ($i = 0; $i < 32; $i++) {
          $chr = chr(mt_rand(33, 126));
          if($chr == "\""){$chr ="\\".$chir;}
	    $newkey .= $chr;
	  }

	$filetext = "<?php
		define('WELD_CUSTOM_CRYP_KEY', true);
		function weld_get_key(){
		\$key = \"".$newkey."\";
		return \$key;
		}?>";

	file_put_contents(WELD_PLUGIN_DIR."/WELD_key.php",$filetext);
	chmod(WELD_PLUGIN_DIR."/WELD_key.php",644);
} //weld_create_new_key

register_activation_hook( __FILE__, 'weld_create_new_key' );




// Load in all the component files
		if ( is_admin() ) {
			include_once(WELD_PLUGIN_DIR."/admin/WELD_admin_functions.php");
			include_once(WELD_PLUGIN_DIR."/admin/WELD_admin.php");
			add_action( 'init', 'WELD_admin_inject' );
		}

// Attempt to load a custom crypto key file
if(file_exists(WELD_PLUGIN_DIR."/WELD_key.php")){
	include_once(WELD_PLUGIN_DIR."/WELD_key.php");
}else{
	define('WELD_CUSTOM_CRYP_KEY', false);
	if(!function_exists('weld_get_key')){
		function weld_get_key(){
			$key = "jn5cTNv42NNV3L48FPHNvLDM6Uvc3v5W";
			return $key;
			}
	}
}



// Add css for admin panels
add_action('admin_enqueue_scripts', 'WELD_admin_theme_style');
function WELD_admin_theme_style() {
    wp_enqueue_style('WELD-admin-css', WELD_PLUGIN_URL.'/admin/WELD-admin.css');
    wp_enqueue_script('WELD-admin-js', WELD_PLUGIN_URL.'/admin/WELD-admin.js');
}






