<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


function WELD_settings_deploy(){

	$key = weld_get_key();
	$cipher = MCRYPT_RIJNDAEL_256;
	$mode = MCRYPT_MODE_CBC;

	// Load the previous settings
	$serverSettings = get_option('weld_server_array');

	$serverSettings = mcrypt_decrypt(
		    $cipher,
		    substr(md5($key),0,mcrypt_get_key_size($cipher, $mode)),
		    base64_decode($serverSettings),
		    $mode,
		    substr(md5($key),0,mcrypt_get_block_size($cipher, $mode))
		   );

	$serverSettings = rtrim($serverSettings);
	$serverSettingsARR = json_decode($serverSettings,true);

	$processListFlat = get_option('weld_process_list');

	// json_decode
	$processList = json_decode($processListFlat,true);

	if(is_array($processList)){
		foreach($processList as $process){
			$QueueByHashID[$process['hashID']] = $process;
		}
	}




		?>
	<div id="poststuff">
	<div id="post-body-content">
		
		<?php
			weld_render_system_status();
		?>
		

		<h2>Deploy to Server Group</h2>
		<table class="wp-list-table widefat">
			<thead>
				<tr>
					<th class="manage-column column-columnname">Server Group Name</th>
					<th class="manage-column column-columnname">Server Adresses</th>
					<th class="manage-column column-columnname">Status</th>
					<th class="manage-column column-columnname">Queue Time</th>
					<th class="manage-column column-columnname">Last Updated</th>
					<th class="manage-column column-columnname">Action</th>
				</tr>
			</thead>
			<tbody id="the-list">
			<?php

			if(count($serverSettingsARR)==0){
						echo PHP_EOL."<tr><td colspan=\"6\">No Server Groups Are Defined.</td></tr>";
					}

			$alternator = 0;
			if(is_array($serverSettingsARR)){
			foreach($serverSettingsARR as $settingARR){
				if($alternator == 0){
					$alternator = 1;
					$altClass = "";
				}else{
					$alternator = 0;
					$altClass = "alternate";
				}
					$statusArray = json_decode(rtrim(get_option('weld_statusServerArray')),true);

					if(isset($statusArray[$settingARR['hashID']]['status'])){
						$QS=$statusArray[$settingARR['hashID']]['status'];
						$LU=date('m-d-Y G:i:s',intval($statusArray[$settingARR['hashID']]['statusTime']));

					}else{
						$QS="Ready";
						$LU="None";

					}
				if(!isset($QueueByHashID[$settingARR['hashID']])){

					$QueueByHashID[$settingARR['hashID']]['queuedTime']="None";

				}else{
					$QueueByHashID[$settingARR['hashID']]['queuedTime']=date('m-d-Y G:i:s',intval($QueueByHashID[$settingARR['hashID']]['queuedTime']));

				}
					$QueueByHashID[$settingARR['hashID']]['serverGroupName']=$settingARR['serverGroupName'];
				?>
					<tr class="format-standard <?php echo $altClass; ?>">
						<td><?php echo $settingARR['serverGroupName']; ?></td>
						<td>
							<ul>
							<?php
							foreach($settingARR['serverAddr'] as $addr){
								echo PHP_EOL."<li>".$addr."</li>";
							}
							?>
							</ul>
						</td>
						<td><?php echo $QS; ?></td>
						<td><?php echo $QueueByHashID[$settingARR['hashID']]['queuedTime']; ?></td>
						<td><?php echo $LU; ?></td>
						<td><p data-groupName="<?php echo $settingARR['serverGroupName']; ?>" data-hash="<?php echo $settingARR['hashID']; ?>" class="button-secondary weld-admin-add-to-queue">Add to Queue</p></td>
					</tr>
				<?php
				}
				}
			?>
			</tbody>
		</table>


		<h2>Current Queue</h2>
		<table class="wp-list-table widefat">
			<thead>
				<tr>
					<th class="manage-column column-columnname">Server Group Name</th>
					<th class="manage-column column-columnname">Status</th>
					<th class="manage-column column-columnname">Queue Time</th>
					<th class="manage-column column-columnname">Last Updated</th>
					<th class="manage-column column-columnname">Action</th>
				</tr>
			</thead>
			<tbody id="the-list" class="weld-process-queue-tbody">
				<?php
					if(count($processList)==0){
						echo PHP_EOL."<tr><td colspan=\"4\">Nothing in the Deployment Queue</td></tr>";
					}

				$alternator = 0;
				if(is_array($processList)){
				foreach($processList as $settingARR){
					if($alternator == 0){
						$alternator = 1;
						$altClass = "";
					}else{
						$alternator = 0;
						$altClass = "alternate";
					}
					
					?>
						<tr class="format-standard <?php echo $altClass; ?>">
							<td><?php echo $QueueByHashID[$settingARR['hashID']]['serverGroupName']; ?></td>
							<td><?php echo $settingARR['status']; ?></td>
							<td><?php echo date('m-d-Y G:i:s',intval($settingARR['queuedTime'])); ?></td>
							<td><?php echo date('m-d-Y G:i:s',intval($settingARR['lastUpdated'])); ?></td>
							<td><p data-hash="<?php echo $settingARR['hashID']; ?>" class="button-secondary weld-admin-remove-from-queue">Remove</p></td>
						</tr>
					<?php
					}
					}

				?>

			</tbody>
		</table>

		<form method="post" action="options.php" class="postbox-container" id="postbox-container-2 weld_process_list_form"> 
		<?php settings_fields( 'weld-deploy-group' ); ?>
			<input type="text" name="weld_process_list" id="weld_process_list" class="weld-hiddeninput" value="<?php echo esc_attr( get_option( 'weld_process_list' ) ); ?>" />
			<?php submit_button(); ?>

		</form>

	</div><!-- end post-body-content -->
	</div><!-- end poststuff -->
	<?php
} // end WELD_settings_deploy
