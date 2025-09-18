<?php
/**
 * Plugin Name: VisionVpro Speed Optimization
 * Description: This plugin speeds up your WordPress website.
 * Author: VisionVpro
 * Author URI: https://www.visionvpro.com/
 * Version: 1.2.1
 * Text Domain: visionvpro-speed-optimization
 */


// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}


// Define Constants
define('KPSO_VERSION', '1.2.1');
define('KPSO_FILE_BASENAME', basename(__FILE__) );
define( 'KPSO_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'KPSO_PLUGIN_BASENAME', plugin_basename(__FILE__) );


//Register Plugin Activation/Deactivation Hook
register_activation_hook( __FILE__, 'kpso_admin_notice_transient' );
register_uninstall_hook( __FILE__, 'kpso_delete_settings' );


//include Pluggable File
if( !function_exists('is_user_logged_in') )
{
	include_once(ABSPATH . 'wp-includes/pluggable.php');
}


//include Frontend Plugin Files
include('includes/kpso-init-config.php');
include('includes/kpso-load-files.php');
include('includes/library/dom-parser.php');
include('includes/kpso-html-rewrite.php');
include('includes/kpso-shortcuts.php');
include('includes/kpso-optimizations.php');


//include Backend Plugin Files
include('includes/admin/kpso-admin-settings-init.php');