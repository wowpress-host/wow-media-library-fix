<?php
/*
Plugin Name: Fix Media Library
Plugin URI: https://wowpress.host/plugins/wow-
Description: Fixes Media Library
Version: 1.0
Author: WowPress.host
Author URI: https://wowpress.host
License: GPL2
*/

if ( !defined( 'ABSPATH' ) ) {
	die();
}



/*
 * PSR-4 class autoloader
 */
function wow_media_library_fix_spl_autoload( $class ) {
	$class = rtrim( $class, '\\' );
	if ( substr( $class, 0, 19 ) == 'WowMediaLibraryFix\\' ) {
		$filename = __DIR__ . DIRECTORY_SEPARATOR .
			str_replace( '\\', DIRECTORY_SEPARATOR, substr( $class, 19 ) ) .
			'.php';

		if ( file_exists( $filename ) ) {
			require $filename;
		}
	}
}

spl_autoload_register( 'wow_media_library_fix_spl_autoload' );



register_deactivation_hook( __FILE__,
	array( 'WowMediaLibraryFix\Activation', 'deactivate' ) );

add_action( 'admin_init', array( 'WowMediaLibraryFix\AdminInit', 'admin_init' ) );
add_action( 'admin_menu', array( 'WowMediaLibraryFix\AdminInit', 'admin_menu' ) );
