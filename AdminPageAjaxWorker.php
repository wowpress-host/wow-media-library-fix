<?php

namespace WowMediaLibraryFix;

class AdminPageAjaxWorker {
	static public function execute( $time_end, $status ) {
		//
		// init processors
		//
		$wp_upload_dir = wp_upload_dir();
		$log = new ProcessLogger(
			( $status['log_to'] == 'file' ),
			$status['log_verbose'],
			$wp_upload_dir
		);

		$process_unreferenced_files = new ProcessUnreferencedFiles( $status,
			$wp_upload_dir, $log );
		$process_post = new ProcessPost( $status, $wp_upload_dir, $log,
			$process_unreferenced_files );

		// on start
		if ( $status['posts']['processed'] == 0 ) {
			$log->clear();
			$process_unreferenced_files->clear();
		}



		//
		// run processors
		//
		$last_processed_description = '';

		try {
			if ( $status['status'] == 'working_database' ) {
				$process_db = new ProcessDatabase( $log );
				$process_db->process();
				$status['status'] = 'working_posts';
				$status['error_database'] = ( $process_db->errors_count > 0 );
				$status['errors_count'] += $process_db->errors_count;
			}
			if ( $status['status'] == 'working_posts' ) {
				for (;;) {
					$post_id = $process_post->get_post_after(
						$status['posts']['last_processed_id'] );
					$status['posts']['processed']++;
					if ( is_null( $post_id ) ) {
						$status['status'] = 'working_index_files';
						$status['posts']['processed'] = $status['posts']['all'];
						break;
					}

					$process_post->process_post( $post_id );
					$status['posts']['last_processed_id'] = $post_id;

					if ( time() >= $time_end ) {
						break;
					}
				}

				$last_processed_description = $process_post->last_processed_description;
			}
			if ( $status['status'] == 'working_index_files' ) {
				for (;;) {
					$filename = $process_unreferenced_files->process_next_file();
					$last_processed_description = $filename;
					if ( is_null( $filename ) ) {
						$status['status'] = 'done';
						break;
					}

					if ( time() >= $time_end ) {
						break;
					}
				}
			}

			$status['errors_count'] += $process_post->errors_count;
			$status['errors_count'] += $process_unreferenced_files->errors_count;
			$status['unreferenced_files'] =
				$process_unreferenced_files->status_unreferenced_files;
			Util::status_set($status);
		} catch ( \Exception $e ) {
			die( $e->getMessage() );
		}

		echo json_encode(array(
			'posts_all' => $status['posts']['all'],
			'posts_processed' => $status['posts']['processed'],
			'unreferenced_files_processed' =>
				$status['unreferenced_files']['processed'],
			'errors_count' => $status['errors_count'],
			'error_database' => $status['error_database'],
			'last_processed_description' => $last_processed_description,
			'status' => $status['status'],
			'new_notices' => $log->notices
		));
	}
}
