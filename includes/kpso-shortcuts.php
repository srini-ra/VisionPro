<?php


// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}


// Rearrange Settings Links
function kpso_rearrange_settings_links($links)
{
    $plugin_shortcuts = array(
        '<a href="'.admin_url("options-general.php?page=kpso").'">Settings</a>'
    );
    return array_merge($links, $plugin_shortcuts);
}
add_filter('plugin_action_links_' . KPSO_PLUGIN_BASENAME, 'kpso_rearrange_settings_links');


// Add Promotion Link in Plugin Row Meta
function kpso_plugin_row_meta( $links_array, $plugin_file_name, $plugin_data, $status )
{
    if ( strpos( $plugin_file_name, KPSO_FILE_BASENAME ) )
	{
        $links_array[] = '<a href="https://www.visionvpro.com/pricing/" target="_blank" style="color:#93003c;font-weight:bold;">WordPress Speed Maintenance Service</a>';
    }
    return $links_array;
}
add_filter( 'plugin_row_meta', 'kpso_plugin_row_meta', 10, 4 );


// Change WordPress Admin Branding on KPSO Plugin Page
function kpso_change_admin_footer ( $hooks )
{
	if( 'settings_page_kpso' == KPSO_PLUGIN_SLUG )
		echo '<b><a href="https://www.visionvpro.com/pricing/" target="_blank" style="color:#d30c5c;text-decoration:none">WordPress Speed Maintenance Service &#8594;</a></b>';
	else
		echo $hooks;
}
add_filter('admin_footer_text', 'kpso_change_admin_footer');