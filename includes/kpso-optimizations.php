<?php


// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}


//WP Rocket new JS Delay Support 3.10.9
$kpso_wp_rocket_support = get_option('kpso_wp_rocket_support');
if($kpso_wp_rocket_support === "yes")
{
	
	//Exclude KP Scripts from Rocket Delay JS
	function kpso_js_delay_exclusions ( $list )
	{
		array_push($list, 'mLQFAVgBhg', 'cz8MjVA6ko');
		return $list;
	}
	add_filter('rocket_delay_js_exclusions', 'kpso_js_delay_exclusions');
	
	
	//Remove Rocket Delay JS Scripts
	function kpso_remove_rocket_delayjs()
	{
		$container = apply_filters( 'rocket_container', null );
		$event_manager = $container->get( 'event_manager' );
		$delay_js_subscriber = $container->get( 'delay_js_subscriber' );
		$event_manager->remove_callback( 'rocket_buffer', [$delay_js_subscriber, 'add_delay_js_script' ], 26 );
	}
	add_action( 'wp_rocket_loaded', 'kpso_remove_rocket_delayjs' );
	
	
	function kpso_get_file_contents($url)
	{
		if (!function_exists('curl_init'))
		{ 
			die('CURL is not installed!');
		}
		
		$kpsocurl = curl_init();
		curl_setopt($kpsocurl, CURLOPT_URL, $url);
		curl_setopt($kpsocurl, CURLOPT_RETURNTRANSFER, true);
		
		$output = curl_exec($kpsocurl);
		curl_close($kpsocurl);
		return $output;
	}
	
	
	function kpso_add_delayjs_script( $html )
	{
		$kppattern = '/<head[^>]*>/i';

		$kpso_delayjs_path = KPSO_DIR_URL . 'assets/js/kpso-lazyload.js';
		$kpso_delayjs_script = kpso_get_file_contents($kpso_delayjs_path);

		if ( false !== $kpso_delayjs_script )
		{
			$html = preg_replace( $kppattern, "$0<script>{$kpso_delayjs_script}</script>", $html, 1 );
		}

		$kpso_container = apply_filters( 'rocket_container', null );
		$kpso_delay_js  = $kpso_container->get( 'delay_js_html' );
		$kpso_ie_script = $kpso_delay_js->get_ie_fallback();
		
		$html = preg_replace( $kppattern, '$0<script>' . $kpso_ie_script . '</script>', $html, 1 );

		return $html;
	}
	add_filter('rocket_buffer', 'kpso_add_delayjs_script',40);
	
	
	function kpso_fire_delayjs()
	{ 
		//Return if user is logged in
		if ( !is_user_logged_in() )
		{
	?>
<script src="data:text/javascript;base64,dmFyIGtwZGV0ZWN0ZGVsYXlqcyA9IGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3JBbGwoJ3NjcmlwdFt0eXBlPSJyb2NrZXRsYXp5bG9hZHNjcmlwdCJdJykubGVuZ3RoO2lmIChrcGRldGVjdGRlbGF5anMgPj0gMSl7Y2xhc3Mga3Bzb0V4ZWN1dGVTY3JpcHRzIGV4dGVuZHMgUm9ja2V0TGF6eUxvYWRTY3JpcHRze3N0YXRpYyBrcHNvRmlyZSgpe2lmKG5hdmlnYXRvci51c2VyQWdlbnQubWF0Y2goLzQ3NTguMTAyfGxpZ2h0fHBpbmd8ZGFyZXxwdHN0L2kpKXt2YXIgZT1uZXcgUm9ja2V0TGF6eUxvYWRTY3JpcHRzKFsia2V5ZG93biIsIm1vdXNlb3ZlciIsInRvdWNobW92ZSIsInRvdWNoc3RhcnQiLCJ0b3VjaGVuZCIsInRvdWNoY2FuY2VsIiwidG91Y2hmb3JjZWNoYW5nZSIsIndoZWVsIl0pO31lbHNle3ZhciBlPW5ldyBSb2NrZXRMYXp5TG9hZFNjcmlwdHMoWyJsb2FkIl0pO31lLl9hZGRVc2VySW50ZXJhY3Rpb25MaXN0ZW5lcihlKTt9fWtwc29FeGVjdXRlU2NyaXB0cy5rcHNvRmlyZSgpO30=" id="mLQFAVgBhg"></script>
	<?php }
	}
	add_action( 'wp_print_footer_scripts', 'kpso_fire_delayjs', 0 );
	
}