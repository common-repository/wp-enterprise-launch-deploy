<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once("WELD_admin_deploy.php");
require_once("WELD_admin_server_settings.php");
require_once("WELD_admin_toggle.php");
require_once("WELD_admin_logs.php");
require_once("WELD_admin_help.php");

function WELD_register_setting() {

	register_setting( 'weld-status-group', 'weld_status' ); // Creates setting in DB
	register_setting( 'weld-status-group', 'weld_remote_status_override' ); // Creates setting in DB
	register_setting( 'weld-deploy-group', 'weld_process_list','weld_process_list_save_and_validate'); // Creates setting in DB
	register_setting( 'weld-settings-group', 'weld_server_array','weld_server_array_save_and_validate'); // Creates setting in DB
	register_setting( 'weld-settings-group', 'weld_enableLogging' ); // Creates setting in DB
	register_setting( 'weld-settings-group', 'weld_logFolder' ); // Creates setting in DB
	register_setting( 'weld-settings-group', 'weld_logDuration' ); // Creates setting in DB
	register_setting( 'weld-settings-group', 'weld_warningInterval' ); // Creates setting in DB
	register_setting( 'weld-settings-group', 'weld_hardening_wpadmin' ); // Creates setting in DB
	register_setting( 'weld-settings-group', 'weld_hardening_pluginexcludes' ); // Creates setting in DB

	add_settings_section( 'weld-general-settings', 'General Settings', 'WELD_general_settings_callback', 'weld-settings' ); // Defines the Section & group of settings
	add_settings_field( 'weld_enableLogging', 'Create Logfiles of Deployments?', 'WELD_enableLogging_callback', 'weld-settings', 'weld-general-settings' ); // Creates the field
	add_settings_field( 'weld_logFolder', 'Log Folder Location', 'WELD_logfolder_callback', 'weld-settings', 'weld-general-settings' ); // Creates the field
	add_settings_field( 'weld_logDuration', 'Number of Days to Retain Logs', 'WELD_logDuration_callback', 'weld-settings', 'weld-general-settings' ); // Creates the field
	add_settings_field( 'weld_warningInterval', 'Long Deployment Warning Limit (minutes)', 'WELD_warningInterval_callback', 'weld-settings', 'weld-general-settings' ); // Creates the field

	add_settings_section( 'weld-hardening-settings', 'Hardened Server Settings', 'WELD_hardening_settings_callback', 'weld-h-settings' ); // Defines the Section & group of settings
	add_settings_field( 'weld_hardening_wpadmin', 'WP-Admin Folder', 'WELD_weld_hardening_wpadmin_callback', 'weld-h-settings', 'weld-hardening-settings' ); // Creates the field
	add_settings_field( 'weld_hardening_pluginexcludes', 'Exclude these plugins on production servers', 'WELD_pluginexcludes_callback', 'weld-h-settings', 'weld-hardening-settings' ); // Creates the field

	add_settings_section( 'weld-status-settingsG', '', 'WELD_status_group_settings_callback', 'weld-status-settings' ); // Defines the Section & group of settings
	add_settings_field( 'weld_status', 'Local System', 'WELD_status_callback', 'weld-status-settings', 'weld-status-settingsG' ); // Creates the field
	add_settings_field( 'weld_remote_status_override', 'Remote Systems', 'WELD_remote_status_override_callback', 'weld-status-settings', 'weld-status-settingsG' ); // Creates the field
/*
add_option('weld_server_array',null,'','no');
add_option('weld_enableLogging',false,'','no');
add_option('weld_logFolder',null,'','no');
add_option('weld_logDuration',"14",'','no');

*/

	//add_settings_field( 'weld-allowableFields', 'Query String Variables to Manage', 'WELD_allowableFields_callback', 'weld-settings', 'weld-general-settings' ); // Creates the field
	//add_settings_field( 'weld-targetURLs', 'Domain Names to Apply Query String Variables To', 'WELD_targetURL_callback', 'weld-settings', 'weld-general-settings' ); // Creates the field
}

function WELD_status_callback() {
?>
			<select name="weld_status">
			<?php
			$statuses = array("Ready","Processing","Error");
			$currentStatus = get_option( 'weld_status' );
			foreach($statuses as $status){
				if($currentStatus == $status){
					$selected=" selected";
				}else{
					$selected="";
				}

				echo PHP_EOL."<option value=\"$status\"$selected>$status</option>";

			}

			?>
			</select>
			<p class="weld_annotation">Use this to manually set the system status. Note: This likely cause the deployment process to restart (or if one is already active, a PARALLEL deployment processor will start). Only perform this action if the system has stalled or is recovering from an error.</p>
<?php
}// WELD_status_callback

function WELD_remote_status_override_callback() {
?>
			<select name="weld_remote_status_override">
				
			<?php
			$statuses = array("Ready","Processing","Error","None");
			$currentStatusOverride = get_option( 'weld_remote_status_override' );
			foreach($statuses as $status){
				if($currentStatusOverride == $status){
					$selected=" selected";
				}else{
					$selected="";
				}

				echo PHP_EOL."<option value=\"$status\"$selected>$status</option>";

			}

			?>
			</select>
			<p class="weld_annotation">*<strong>IMPORTANT:</strong> This will override the remote system's status. If the remote systems are not functional, this could make the problem worse.  It will reset to "None" after each run of the deploy processor.</p>
<?php

} // WELD_remote_status_override_callback

function WELD_status_group_settings_callback(){

}//WELD_status_group_settings_callback

function WELD_general_settings_callback() {
    // render group code here
}

function WELD_hardening_settings_callback() {
	?>
	<p>These settings will determine what steps are taken to harden the production server. Plugins marked as disabled will not be copied.</p>
	<?php
}

function WELD_weld_hardening_wpadmin_callback() {
    $setting = get_option( 'weld_hardening_wpadmin' );

	?>
	<input type="radio" id="weld_hardening_wpadmin" name="weld_hardening_wpadmin" <?php if($setting == true || $setting === "true"){ echo 'checked="checked"'; } ?> value="true" />Prevent wp-admin from being copied
	<input type="radio" id="weld_hardening_wpadmin" name="weld_hardening_wpadmin" <?php if($setting == false || $setting === "false"){ echo 'checked="checked"'; } ?> value="false" />Restrict access to wp-admin via .htaccess

	<p class="weld_annotation">*Some plugins REQUIRE the presence of the wp-admin folder.  If the system is set to prevent the wp-admin folder from copying it is imperative to 1) TEST the configuration and 2) exclude plugins that require wp-admin from the production server</p>

	<?php
}

function WELD_pluginexcludes_callback() {
    $settingFlat = get_option( 'weld_hardening_pluginexcludes' );
    $setting = json_decode($settingFlat,true);
    $activePlugins = get_option( 'active_plugins' );

	foreach($activePlugins as $activePlugin){
		$pluginMeta = get_plugin_data(WP_PLUGIN_DIR."/".$activePlugin,false,true);
		if(@in_array($activePlugin,$setting)){
			$checked = " checked=\"checked\"";
		}else{
			$checked = "";
		}

		echo "\n<div class=\"weld_hardening_pluginexcludes_cb\"><input type=\"checkbox\" name=\"weld_hardening_pluginexcludes_cb\" value=\"$activePlugin\" $checked/>".$pluginMeta['Name']."</div><!-- end weld_hardening_pluginexcludes_cb -->";

	}
	?>

	<input type='text' name='weld_hardening_pluginexcludes' class="weld-hiddeninput"  id='weld_hardening_pluginexcludes' value='<?php echo $settingFlat ?>' />

	<?php


}


function WELD_enableLogging_callback() {
    $setting = get_option( 'weld_enableLogging' );

	?>
	<input type="radio" id="weld_enableLogging" name="weld_enableLogging" <?php if($setting == true || $setting === "true"){ echo 'checked="checked"'; } ?> value="true" />yes
	<input type="radio" id="weld_enableLogging" name="weld_enableLogging" <?php if($setting == false || $setting === "false"){ echo 'checked="checked"'; } ?> value="false" />no
	<?php
}

function WELD_logfolder_callback() {
    $setting = esc_attr( get_option( 'weld_logFolder' ) );
    echo "<input type='text' name='weld_logFolder' id='weld_logFolder' value='$setting' /> <span class=\"weld_annotation\">Defaults to wp-content/weld-logs/. Must be writable by by sync user.  Relative to the wordpress root folder.</span>";
}

function WELD_logDuration_callback() {
    $setting = esc_attr( get_option( 'weld_logDuration' ) );
    echo "<input type='text' name='weld_logDuration' id='weld_logDuration' value='$setting' /> <span class=\"weld_annotation\">Values <1 are ignored</span>";
}

function WELD_warningInterval_callback() {
    $setting = esc_attr( get_option( 'weld_warningInterval' ) );
    echo "<input type='text' name='weld_warningInterval' id='weld_warningInterval' value='$setting' /> <span class=\"weld_annotation\">Any deployment that runs longer than this limit will trigger an email to admins.</span>";
}

function WELD_allowableFields_callback() {
    //$setting = esc_attr( get_option( 'weld-allowableFields' ) );
    //echo "<input type='text' name='weld-allowableFields' class=\"weld-hiddeninput\" value='$setting' />";
}



add_action( 'admin_init', 'WELD_register_setting' );




add_action('admin_menu', 'WELD_admin_menu');

function WELD_admin_menu() {
    $page_title = 'Wordpress Enterprise Launch/Deployment';
    $menu_title = 'Enterprise Deployment';
    $capability = 'manage_options';
    $menu_slug = 'weld-settings';
    $function = 'WELD_settings';
    add_management_page($page_title, $menu_title, $capability, $menu_slug, $function);

add_submenu_page(  
    $menu_slug,               // The ID of the top-level menu page to which this submenu item belongs  
    'Deploy Commands',                  // The value used to populate the browser's title bar when the menu page is active  
    'Deploy Commands',                  // The label of this submenu item displayed in the menu 
    'administrator',                    // What roles are able to access this submenu item 
    'weld_deploy_options',    // The ID used to represent this submenu item 
    'WELD_settings'             // The callback function used to render the options for this submenu item  
);  

add_submenu_page(  
    $menu_slug,               // The ID of the top-level menu page to which this submenu item belongs  
    'Server Settings',                  // The value used to populate the browser's title bar when the menu page is active  
    'Server Settings',                  // The label of this submenu item displayed in the menu 
    'administrator',                    // What roles are able to access this submenu item 
    'weld_server_options',    // The ID used to represent this submenu item 
    'WELD_settings'             // The callback function used to render the options for this submenu item  
); 

add_submenu_page(  
    $menu_slug,               // The ID of the top-level menu page to which this submenu item belongs  
    'Logs',                  // The value used to populate the browser's title bar when the menu page is active  
    'Logs',                  // The label of this submenu item displayed in the menu 
    'administrator',                    // What roles are able to access this submenu item 
    'weld_logs',    // The ID used to represent this submenu item 
    'WELD_settings'             // The callback function used to render the options for this submenu item  
); 
add_submenu_page(  
    $menu_slug,               // The ID of the top-level menu page to which this submenu item belongs  
    'Status Override',                  // The value used to populate the browser's title bar when the menu page is active  
    'Status Override',                  // The label of this submenu item displayed in the menu 
    'administrator',                    // What roles are able to access this submenu item 
    'status_override',    // The ID used to represent this submenu item 
    'WELD_settings'             // The callback function used to render the options for this submenu item  
); 
add_submenu_page(  
    $menu_slug,               // The ID of the top-level menu page to which this submenu item belongs  
    'Help',                  // The value used to populate the browser's title bar when the menu page is active  
    'Help',                  // The label of this submenu item displayed in the menu 
    'administrator',                    // What roles are able to access this submenu item 
    'weld_help',    // The ID used to represent this submenu item 
    'WELD_settings'             // The callback function used to render the options for this submenu item  
); 
}





function WELD_settings($active_tab=null) {
	$possibleTabs = array("weld_deploy_options","weld_server_options","weld_logs","status_override","weld_help");
	$possibleTabNames = array("Deploy","Servers","Logs","Status Override Toggle","Help");

	if( isset( $_GET[ 'tab' ] ) ){
		if(in_array($_GET[ 'tab' ],$possibleTabs,false)){
			$active_tab = $_GET[ 'tab' ];
		}else{
			$active_tab = "weld_deploy_options";
		}
	}else{
		$active_tab = "weld_deploy_options";
	}


    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
	?>
	<script type="text/javascript">
	var weld_admin = true;
	</script>
	<div class="wrap">
	<?php screen_icon(); ?>
	<h2>Wordpress Enterprise Launch/Deployment (Multi-server)</h2>

	<h2 class="nav-tab-wrapper">  
		<?php
		foreach($possibleTabs as $index => $thisTab){
			if($active_tab == $thisTab){
				$activo = "nav-tab-active";
			}else{
				$activo = "";
			}
			echo "\n<a href=\"?page=weld-settings&tab=$thisTab\" class=\"nav-tab $activo\">".$possibleTabNames[$index]."</a>\n";
		}

		?>
           
        </h2> 

	

	<?php

	if($active_tab=="weld_deploy_options"){
		WELD_settings_deploy();
	}elseif($active_tab=="weld_server_options"){
		WELD_settings_server();
	}elseif($active_tab=="weld_logs"){
		WELD_settings_logs();
	}elseif($active_tab=="status_override"){
		WELD_settings_status_override();
	}elseif($active_tab=="weld_help"){
		WELD_help();
	}



} // end WELD_settings








