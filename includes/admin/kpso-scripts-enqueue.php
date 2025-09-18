<?php


// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}


// Load CSS & JS on Plugin Setting Page
function kpso_admin_scripts( $hook )
{	
	// Define KPSO_PLUGIN_SLUG as a PHP Constant
	define ( 'KPSO_PLUGIN_SLUG', $hook );
	
	if( 'settings_page_kpso' == KPSO_PLUGIN_SLUG )
	{
		wp_enqueue_style( 'kp-admin-css', KPSO_DIR_URL . 'assets/css/kpso-backend.css', array(), time() );
		wp_enqueue_script( 'kp-admin-js', KPSO_DIR_URL . 'assets/js/kpso-backend.js', array(), time() );
	}
}
add_action( 'admin_enqueue_scripts', 'kpso_admin_scripts' );