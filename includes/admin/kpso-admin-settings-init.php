<?php

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

global $kp_active_tab;
include('kpso-scripts-enqueue.php');


// Settings Page Initialization
function kpso_settings_page()
{
	// Validate nonce
	if (isset($_POST['kpso_submit']) && !wp_verify_nonce($_POST['kpso-settings-form'], 'kpso'))
	{
		echo '<div class="notice notice-error"><p>Nonce verification failed.</p></div>';
		exit;
	}

	// Double Check For User Capabilities
	if ( !current_user_can('manage_options') )
		return;
	
	$kp_active_tab = isset($_GET['tab']) ? $_GET['tab'] : "main";
?>

	<div class="kpftc-desc"><b>VisionVpro Speed Optimization</b> is the complimentary plugin for <a style="color:#444;" href="https://www.visionvpro.com/" target="_blank">VisionVpro</a> customers.</div>

<?php

		include('kpso-admin-settings-main-fileds.php');
		include('kpso-admin-settings-extra-fileds.php');

		if (isset($_POST['kpso_submit']))
		{
			if ( is_plugin_active('wp-rocket/wp-rocket.php') )
			{
				echo '<div class="notice notice-success is-dismissible"><p>Main Plugin settings have been saved! <b>WP Rocket</b> cache has been cleared.</p></div>';
			}
			else if( is_plugin_active('autoptimize/autoptimize.php') )
			{
				echo '<div class="notice notice-success is-dismissible"><p>Main Plugin settings have been saved! <b>Autoptimize</b> cache has been cleared.</p></div>';
			}
			else
			{
				echo '<div class="notice notice-success is-dismissible"><p>Main Plugin settings have been saved! Please clear website cache.</p></div>';
			}
		}
		
		if (isset($_POST['kpso_extra_submit']))
		{
			echo '<div class="notice notice-success is-dismissible"><p>Extra Plugin settings have been saved! Clear <b>WP Rocket</b> or <b>Autoptimize</b> cache now.</p></div>';
		}
		
		if (isset($_POST['kpso_restore_default']))
		{
			echo '<div class="notice notice-success is-dismissible"><p>Default Plugin Settings have been restored!</p></div>';
		}
		
		if (isset($_POST['kpso_license_submit']))
		{
			kpso_license_confirm_key( $_POST['kpso_init_key_confirm'] );
		}
		
		if( !get_transient('kpso-key-validate-activate'))
		{
		?>
		
		<form method="POST">
		<?php wp_nonce_field('kpso', 'kpso-key-confirm'); ?>
		<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><label>License Key</label></th>
				<td>
					<input type="password" id ="kpso_init_key_confirm" name="kpso_init_key_confirm" style="width:400px"><br>
					<small class="description kp-code-desc">This plugin is licensed by VisionVpro LLC.</small><br>
				</td>
			</tr>
		</tbody>
		</table>
		<p class="submit">
			<input type="submit" name="kpso_license_submit" id="kpso_license_submit" class="button button-primary" value="Save License Key">
		</p>
		<p><b>Access to this plugin has been prevented because:</b></p>
		<ol>
			<li>This plugin can break your website if you don't know what you are doing.</li>
			<li>Only a certified VisionVpro WordPress expert can access the plugin settings.</li>
			<li>Settings closes itself out every 6 hours but plugin keeps running as configured.</li>
		</ol>
	</form>
		
		<?php
		}
		else if( get_transient('kpso-key-validate-activate'))
		{
			
			switch ($kp_active_tab)
			{
				case 'main':
					kpso_settings_view();
					break;
				case 'extra':
					kpso_extra_settings_view();
					break;
				default:
					kpso_settings_view();
			}
		}
}