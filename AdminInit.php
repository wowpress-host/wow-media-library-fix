<?php

namespace WowMediaLibraryFix;

class AdminInit {
	static public function admin_init() {
		add_filter( 'plugin_action_links_' .
			plugin_basename( plugin_dir_path( __FILE__ ) . 'wow-media-library-fix.php' ),
			array( __CLASS__, 'plugin_action_links' ) );

		add_action('admin_print_styles-tools_page_wow_media_library_fix',
			array( '\WowMediaLibraryFix\AdminPage', 'admin_print_styles' ) );
		add_action('admin_print_scripts-tools_page_wow_media_library_fix',
			array( '\WowMediaLibraryFix\AdminPage', 'admin_print_scripts' ) );

		add_filter( 'wp_ajax_wow_media_library_fix_process', array(
				'WowMediaLibraryFix\AdminPage',
				'wp_ajax_wow_media_library_fix_process' ) );
	}



	static public function admin_menu() {
		add_management_page(
			'Fix Media Library',
			'Fix Media Library',
			'manage_options',
			'wow_media_library_fix',
			array( 'WowMediaLibraryFix\AdminPage', 'render' )
		);
	}



	static public function plugin_action_links( $links ) {
		$url = add_query_arg( array( 'page' => 'wow_media_library_fix' ),
			admin_url( 'tools.php' ) );

		$links[] = '<a href="' . esc_url( $url ) . '">'.
			esc_html__( 'Fix Media Library' , 'wow_media_library_fix' ) .
			'</a>';

		return $links;
	}
}
