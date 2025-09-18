<?php

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

function kpso_format_list($list)
{
    $list = trim($list);
    $list = $list ? array_map('trim', explode("\n", str_replace("\r", "", sanitize_textarea_field($list)))) : [];
    return $list;
}

function kpso_settings_view()
{
	$kp_active_tab = isset($_GET['tab']) ? $_GET['tab'] : "main";
?>

<h2 class="nav-tab-wrapper">
    <a href="?page=kpso&tab=main" class="nav-tab <?php echo $kp_active_tab == 'main' ? 'nav-tab-active' : ''; ?>">Main Settings</a>
    <a href="?page=kpso&tab=extra" class="nav-tab <?php echo $kp_active_tab == 'extra' ? 'nav-tab-active' : ''; ?>">Extra Settings</a>
</h2>

<?php

    if (isset($_POST['kpso_submit'])) {
        update_option('kpso_css_include_list', kpso_format_list($_POST['kpso_css_include_list']));
		update_option('kpso_js_include_list', kpso_format_list($_POST['kpso_js_include_list']));
        update_option('kpso_disabled_pages', kpso_format_list($_POST['kpso_disabled_pages']));
		update_option('kpso_css_mobile_disabled', $_POST['kpso_css_mobile_disabled']);
		update_option('kpso_js_mobile_disabled', $_POST['kpso_js_mobile_disabled']);
		update_option('kpso_wp_rocket_support', $_POST['kpso_wp_rocket_support']);
		
		if ( is_plugin_active('wp-rocket/wp-rocket.php') )
		{
			rocket_clean_minify();
			rocket_clean_domain();
		}
		
		if( is_plugin_active('autoptimize/autoptimize.php') )
		{
			autoptimizeCache::clearall();
		}
    }
	
	if (isset($_POST['kpso_restore_default'])) {
        kpso_restore_default_settings();
    }
	
	if (isset($_POST['kpso_close_settings'])) {
        kpso_close_settings();
    }

    $kpso_css_include_list = get_option('kpso_css_include_list');
	if($kpso_css_include_list)
	{
		$kpso_css_include_list = implode("\n", $kpso_css_include_list);
		$kpso_css_include_list = esc_textarea($kpso_css_include_list);
	} else
	{
		$kpso_css_include_list = "";
	}
	
	$kpso_js_include_list = get_option('kpso_js_include_list');
	if($kpso_js_include_list)
	{
		$kpso_js_include_list = implode("\n", $kpso_js_include_list);
		$kpso_js_include_list = esc_textarea($kpso_js_include_list);
	} else
	{
		$kpso_js_include_list = "";
	}

    $kpso_disabled_pages = get_option('kpso_disabled_pages');
    $kpso_disabled_pages = implode("\n", $kpso_disabled_pages);
    $kpso_disabled_pages = esc_textarea($kpso_disabled_pages);
	
	
	$kpso_css_mobile_disabled = get_option('kpso_css_mobile_disabled');
	$kpso_js_mobile_disabled = get_option('kpso_js_mobile_disabled');
	
	$kpso_wp_rocket_support = get_option('kpso_wp_rocket_support');

    ?>
	<form method="POST">
		<?php wp_nonce_field('kpso', 'kpso-settings-form'); ?>
		<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><label>Delay JS Support </label></th>
				<td>
					<input type="hidden" name="kpso_wp_rocket_support" value="no">
					<input type="checkbox" id="kpso_wp_rocket_support" name="kpso_wp_rocket_support" <?php if($kpso_wp_rocket_support == "yes") { echo "checked"; } ?> value="<?php if($kpso_wp_rocket_support == "yes") { echo "yes"; } else { echo "no"; } ?>"><label for="kpso_wp_rocket_support">Execute delayed JS of <b>WP Rocket (3.11.0.4)</b></label>
					<br>
				</td>
			</tr>
			<tr>
				<th scope="row"><label>CSS Keywords</label></th>
				<td>
					<textarea name="kpso_css_include_list" rows="2" cols="50"><?php echo $kpso_css_include_list ?></textarea><br>
					<small class="description kp-code-desc">Keywords to identify styles for user interaction.</small><br><br>
					<small>
					<input type="hidden" name="kpso_css_mobile_disabled" value="no">
					<input type="checkbox" id="kpso_css_mobile_disabled" name="kpso_css_mobile_disabled" <?php if($kpso_css_mobile_disabled == "yes") { echo "checked"; } ?> value="<?php if($kpso_css_mobile_disabled == "yes") { echo "yes"; } else { echo "no"; } ?>"><label for="kpso_css_mobile_disabled">Disable CSS Optimization in Mobile</label>
					</small><br>
				</td>
			</tr>
			<tr>
				<th scope="row"><label>JS Keywords</label></th>
				<td>
					<textarea name="kpso_js_include_list" rows="2" cols="50"><?php echo $kpso_js_include_list ?></textarea><br>
					<small class="description">Keywords to identify scripts for user interaction.</small><br><br>
					<small>
					<input type="hidden" name="kpso_js_mobile_disabled" value="no">
					<input type="checkbox" id="kpso_js_mobile_disabled" name="kpso_js_mobile_disabled" <?php if($kpso_js_mobile_disabled == "yes") { echo "checked"; } ?> value="<?php if($kpso_js_mobile_disabled == "yes") { echo "yes"; } else { echo "no"; } ?>"><label for="kpso_js_mobile_disabled">Disable JS Optimization in Mobile</label>
					</small><br>
				</td>
			</tr>
			<tr>
				<th scope="row"><label>Disable on Pages</label></th>
				<td>
					<textarea name="kpso_disabled_pages" rows="2" cols="50"><?php echo $kpso_disabled_pages; ?></textarea><br>
					<small class="description">Page Slug Keywords to disable this plugin on specific pages.</small>
				</td>
			</tr>
			<tr>
				<th scope="row"><label>Restore Defaults</label></th>
				<td>
					<input type="submit" name="kpso_restore_default" id="kpso_restore_default" class="button button-primary" value="Restore Default Settings">
					<input type="submit" name="kpso_close_settings" id="kpso_close_settings" class="button button-primary" value="Close Plugin Settings">
				</td>
			</tr>
		</tbody>
		</table>
		<p class="submit">
			<input type="submit" name="kpso_submit" id="kpso_submit" class="button button-primary" value="Save Changes">
		</p>
	</form>
	<?php
}