<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


function WELD_settings_status_override(){







	?>
	<div id="poststuff">
	<div id="post-body-content">

	<?php weld_render_system_status(); ?>

	<h2>System Status Manual Override</h2>
	<p><strong>While critical for error recovery, if not carefully used, these two settings can cause a lot of damage. USE WITH CARE.</strong></p>

	<?php if(is_admin()){ ?>
	<form method="post" action="options.php" class="postbox-container" id="postbox-container-2"> 
			<?php 
				settings_fields( 'weld-status-group' );
				do_settings_sections( 'weld-status-settings' ); 
				submit_button(); 
			?>

		</form>
	<?php }else{ ?>

		<p>You do not have adequate user privileges to operate this panel.</p>

	<?php } ?>
	</div><!-- end post-body-content -->
	</div><!-- end poststuff -->
	<?php

}// end WELD_settings_status_override

