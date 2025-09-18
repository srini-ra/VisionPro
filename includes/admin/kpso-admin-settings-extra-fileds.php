<?php

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

function kpso_extra_settings_view()
{
	$kp_active_tab = isset($_GET['tab']) ? $_GET['tab'] : "main";
?>

<h2 class="nav-tab-wrapper">
    <a href="?page=kpso&tab=main" class="nav-tab <?php echo $kp_active_tab == 'main' ? 'nav-tab-active' : ''; ?>">Main Settings</a>
    <a href="?page=kpso&tab=extra" class="nav-tab <?php echo $kp_active_tab == 'extra' ? 'nav-tab-active' : ''; ?>">Extra Settings</a>
</h2>

<?php

    if (isset($_POST['kpso_extra_submit'])) {
        /*update_option('kpso_woo_optimization', $_POST['kpso_woo_optimization']);*/
    }
	
	/*$kpso_woo_optimization = get_option('kpso_woo_optimization');*/

    ?>
	<form method="POST">
		<?php wp_nonce_field('kpso', 'kpso-settings-form'); ?>
		<table class="form-table" role="presentation">
		<tbody>
			<!--<tr>
				<th scope="row"><label>Woo Optimization</label></th>
				<td>
					<input type="hidden" name="kpso_woo_optimization" value="no">
					<input type="checkbox" id="kpso_woo_optimization" name="kpso_woo_optimization" <?php /*  if($kpso_woo_optimization == "yes") { echo "checked"; } */?> value="<?php/* if($kpso_woo_optimization == "yes") { echo "yes"; } else { echo "no"; } */?>"><label for="kpso_woo_optimization">Disable Woo-Scripts on Non-Woo Pages</label>
					<br>
				</td>
			</tr>-->
		</tbody>
		</table>
		<p class="submit">
			<input type="submit" name="kpso_extra_submit" id="kpso_extra_submit" class="button button-primary" value="Save Changes">
		</p>
	</form>
	<?php
}