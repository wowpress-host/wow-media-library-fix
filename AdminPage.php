<?php

namespace WowMediaLibraryFix;

class AdminPage {
	static public function admin_print_styles() {
		wp_enqueue_style( 'wow_media_library_fix',
			plugin_dir_url( __FILE__ ) . 'AdminPage_View.css',
			array(), '1.0' );
	}



	static public function admin_print_scripts() {
		wp_enqueue_script( 'wow_media_library_fix',
			plugin_dir_url( __FILE__ ) . 'AdminPage_View.js',
			array( 'jquery' ), '1.0' );

		wp_localize_script( 'wow_media_library_fix', 'wow_media_library_fix_nonce',
			wp_create_nonce( 'wow_media_library_fix' ) );

		$status = Util::status();
		$value = 'start';

		if ( isset( $status['status'] ) && $status['status'] == 'working' ) {
			$value = 'paused';
		}
		wp_localize_script( 'wow_media_library_fix', 'wow_mlf_state', $value );
	}



	static public function render() {
		add_filter( 'admin_footer_text',
			array( '\WowMediaLibraryFix\AdminPage', 'admin_footer_text' ), 1 );

		$status = Util::status();

		$hide = 'style="display: none"';

		$messages = '';
		$style_config = '';
		$style_start_outer = '';
		$style_continue_outer = $hide;
		$style_process = $hide;
		$style_working_now = '';
		$process_total = 'starting...';
		$process_processed = '0';
		$process_errors = '0';
		$style_ufiles_processed = $hide;
		$process_ufiles_processed = '0';

		if ( isset( $status['status'] ) &&
				Util::starts_with( $status['status'], 'working_' ) ) {
			$messages =
				'<div class="updated settings-error notice is-dismissible">' .
				'<p><strong>Previous processing was not finished. Continue execution now or start new processing.</strong></p></div>';
			$style_config = $hide;
			$style_start_outer = $hide;
			$style_continue_outer = '';
			$style_process = '';
			$style_working_now = $hide;
			$process_total = $status['posts']['all'];
			$process_processed = $status['posts']['processed'];
			$process_errors = $status['errors_count'];
			$style_ufiles_processed_outer = $hide;
			if ( $status['unreferenced_files']['processed'] > 0 ) {
				$style_ufiles_processed = '';
				$process_ufiles_processed =
					$status['unreferenced_files']['processed'];
			}
		}

		include( __DIR__ . DIRECTORY_SEPARATOR . 'AdminPage_View.php' );
	}



	static public function admin_footer_text() {
		$footer_text = sprintf(
			'If you like <strong>Fix Media Library plugin</strong> please leave us a %s rating. A huge thanks in advance!',
			'<a href="https://wordpress.org/support/plugin/wow-media-library-fix/reviews?rate=5#new-post" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
		);

		return $footer_text;
	}



	static private function v( $key, $defaul_value ) {
		$status = Util::status();

		if ( isset( $status[$key] ) ) {
			return $status[$key];
		}

		return $defaul_value;
	}



	static public function wp_ajax_wow_media_library_fix_process() {
		if ( !wp_verify_nonce( $_REQUEST['_wpnonce'], 'wow_media_library_fix' ) ) {
			wp_nonce_ays( 'wow_media_library_fix' );
			exit;
		}
		if ( !current_user_can( 'manage_options') ) {
			wp_nonce_ays( 'wow_media_library_fix' );
			exit;
		}

		$secs_to_execute = 2;
		$time_end = time() + $secs_to_execute;
		$status = Util::status();

		if ( isset( $_REQUEST['wmlf_action'] ) ) {
			$action = $_REQUEST['wmlf_action'];
			if ( $action == 'start' ) {
				$status = array(
					'version' => '1.0',

					// config
					'guid' => $_REQUEST['guid'],
					'posts_delete_with_missing_images' =>
						( $_REQUEST['posts_delete_with_missing_images'] == 'true' ),
					'posts_delete_duplicate_url' =>
						$_REQUEST['posts_delete_duplicate_url'],
					'files_thumbnails' => $_REQUEST['files_thumbnails'],
					'files_unreferenced' => $_REQUEST['files_unreferenced'],
					'files_weak_references' =>
						$_REQUEST['files_weak_references'],
					'regenerate_metadata' =>
						( $_REQUEST['regenerate_metadata'] == 'true' ),
					'log_to' => $_REQUEST['log_to'],
					'log_verbose' =>
						( $_REQUEST['log_verbose'] == 'true' ),

					// common status
					'errors_count' => 0,
					'error_database' => false,
					'last_processed_description' => '',
					'status' => 'working_database',

					// posts status
					'posts' => array(
						'all' => ProcessPost::posts_count(),
						'processed' => 0,
						'last_processed_id' => 0
					),

					// unreferenced files status
					'unreferenced_files' => array(
						'processed' => 0,
						'current_index_file' => array(
							'filename' => '',
							'total_to_process' => 0,
							'next_to_process' => 0
						),
						'index_files' => array(),
					)
				);
			}
		}

		AdminPageAjaxWorker::execute( $time_end, $status );
		exit;
	}
}
