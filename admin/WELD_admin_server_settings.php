<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


function WELD_settings_server(){
	?>
	<div id="poststuff">
	<div id="post-body-content">
		<?php
			weld_render_system_status();
		?>

		<form method="post" action="" id="weld-server-settings-form">

		<fieldset id="weld-serverGroups">
		<h2>Server Management</h2>
		<p>Define the servers (or groups of servers that you will deploy to)</p>
		<table class="wp-list-table widefat">
			<thead>
				<tr>
					<th class="manage-column column-columnname">Server/Group Name</th>
					<th class="manage-column column-columnname">Server Settings</th>
					<th class="manage-column column-columnname">Database Settings</th>
					<th class="manage-column column-columnname">Server Addresses</th>
					<th class="manage-column column-columnname">Group Settings</th></tr>
			</thead>
			<tbody id="the-list">

			</tbody>
		</table>
		
		<p><a class="button-secondary weld-serverGroups-add" href="#" title="Add Server Group">Add</a></p>
		</fieldset>

		</form>
	</div><!-- end post-body-content-->



	<div id="postbox-container-2" class="postbox-container">

	<form method="post" action="options.php" class="postbox-container" id="postbox-container-2"> 
		<?php settings_fields( 'weld-settings-group' ); ?>
		<?php do_settings_sections( 'weld-h-settings' ); ?>


		<?php do_settings_sections( 'weld-settings' ); ?>
		<input type="text" name="weld_server_array" id="weld_server_array" class="weld-hiddeninput" value="<?php echo esc_attr( weld_load_server_array(get_option( 'weld_server_array' )) )?>" />
		<?php submit_button(); ?>

	</form>
	</div><!-- end id="postbox-container-2" class="postbox-container" -->
	</div><!-- end #poststuff -->
<?php
} // end WELD_settings_server

add_filter('plugin_action_links', 'WELD_plugin_action_links', 10, 2);

function WELD_plugin_action_links($links, $file) {
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
        // The "page" query string value must be equal to the slug
        // of the Settings admin page we defined earlier, which in
        // this case equals "myplugin-settings".
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=weld-settings">Settings</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}

