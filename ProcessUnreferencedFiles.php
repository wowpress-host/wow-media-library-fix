<?php

namespace WowMediaLibraryFix;

class ProcessUnreferencedFiles {
	private $c_files_weak_references;
	private $c_files_unreferenced;

	private $unreferenced_basenames;

	public $status_unreferenced_files;
	public $index_files;
	public $errors_count = 0;



	public function __construct( $status, $wp_upload_dir, $log ) {
		$this->c_files_weak_references = $status['files_weak_references'];
		$this->c_files_unreferenced = $status['files_unreferenced'];
		$this->wp_upload_dir = $wp_upload_dir;
		$this->log = $log;

		$this->status_unreferenced_files = $status['unreferenced_files'];
	}



	public function clear() {
		$index_files = $this->status_unreferenced_files['index_files'];

		foreach ( $index_files as $filename => $value ) {
			if ( file_exists( $filename ) ) {
				unlink( $filename );
			}
		}

		$this->status_unreferenced_files['index_files'] = array();
	}



	public function mark_referenced_by_metadata( $filename, $meta ) {
		if ( empty( $this->c_files_weak_references ) &&
				empty( $this->c_files_unreferenced ) ) {
			return;
		}

		$primary_filename = null;
		if ( isset( $meta['file'] ) ) {
			$primary_filename = $this->wp_upload_dir['basedir'] . DIRECTORY_SEPARATOR .
				$meta['file'];
			$path = dirname( $primary_filename );

			$index_filename = $path . DIRECTORY_SEPARATOR . '.media-library-fix';
			$this->status_unreferenced_files['index_files'][$index_filename] = '*';

			$content = array( basename( $primary_filename ) );

			if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $i ) {
					if ( isset( $i['file'] ) ) {
						$content[] = $i['file'];
					}
				}
			}

			file_put_contents( $index_filename,
				implode("\n", $content ) . "\n",
				FILE_APPEND );
		}

		// attachment filename may be present while meta not for non-images
		if ( $primary_filename != $filename ) {
			$path = dirname( $filename );

			$index_filename = $path . DIRECTORY_SEPARATOR . '.media-library-fix';
			$this->status_unreferenced_files['index_files'][$index_filename] = '*';

			file_put_contents( $index_filename,
				basename( $filename ) . "\n",
				FILE_APPEND );
		}
	}



	public function process_next_file() {
		if ( empty( $this->c_files_weak_references ) &&
				empty( $this->c_files_unreferenced ) ) {
			return;
		}

		$c = $this->status_unreferenced_files['current_index_file'];
		if ( $c['next_to_process'] >= $c['total_to_process'] ) {
			$this->unreferenced_basenames = null;
			if ( !$this->take_next_index_file() ) {
				return null;   // work done
			}

			// make sure status linked to current state of unref files saved
			return 'index file';
		}


		if ( is_null( $this->unreferenced_basenames ) ) {
			$this->unreferenced_basenames = Util::status_unreferenced_basenames();
		}

		$position = $c['next_to_process'];
		$filename_for_log = '';

		if ( isset( $this->unreferenced_basenames[$position] ) ) {
			$basename = $this->unreferenced_basenames[$position];

			$filename_for_log = str_replace( ABSPATH, '',
				$c['path'] . DIRECTORY_SEPARATOR . $basename );
			if ( $this->log->verbose ) {
				$this->log->log( null, "Processing file $filename_for_log" );
			}

			if ( !$this->process_file_weak_reference( $c['path'], $basename ) ) {
				$this->process_unreferenced_file( $c['path'], $basename );
			}
		} else {
			$basename = '';
			$this->errors_count++;
			$this->log->log( null, 'Position ' . $position . ' in ' . $c['filename'] .
				' not found' );
		}

		$this->status_unreferenced_files['current_index_file']['next_to_process']++;
		$this->status_unreferenced_files['processed']++;

		return $filename_for_log;
	}



	private function take_next_index_file() {
		// remove processed index file
		$c = $this->status_unreferenced_files['current_index_file'];
		if ( !empty( $c['filename'] ) && file_exists( $c['filename'] ) ) {
			unlink( $c['filename'] );
		}

		// pop first key without array_keys
		$index_filename = null;
		$index_files = $this->status_unreferenced_files['index_files'];

		foreach ( $index_files as $filename => $key ) {
			if ( file_exists( $filename ) ) {
				$index_filename = $filename;
			}
			break;
		}

		if ( is_null( $index_filename ) ) {
			return null;
		}

		unset( $this->status_unreferenced_files['index_files'][$index_filename] );
		$path = dirname( $index_filename );

		$h = fopen( $index_filename, 'r' );
		if ( !$h ) {
			throw new \Exception( 'Faied to open ' . $index_filename );
		}

		$used_basenames = array();
		while ( ($line = fgets( $h ) ) !== false ) {
			$line = trim( $line );
			if ( !empty( $line ) ) {
				$used_basenames[ $line ] = '*';
			}
		}

		fclose( $h );

		$used_basenames = apply_filters( 'wow_mlf_referenced_files',
			$used_basenames, $path );

		$existing_basenames = scandir( $path );

		$unreferenced_files = array();

		foreach ( $existing_basenames as $existing_basename ) {
			if ( $existing_basename == '.media-library-fix' ) {
			} elseif ( !is_dir( $path . DIRECTORY_SEPARATOR . $existing_basename ) ) {
				if ( !isset( $used_basenames[$existing_basename] ) ) {
					$unreferenced_files[] = $existing_basename;
				}
			}
		}

		Util::status_unreferenced_basenames_set( $unreferenced_files );
		$this->status_unreferenced_files['current_index_file'] = array(
			'filename' => $index_filename,
			'path' => $path,
			'total_to_process' => count( $unreferenced_files ),
			'next_to_process' => 0
		);

		return true;
	}



	private function process_file_weak_reference( $path, $basename ) {
		if ( empty( $this->c_files_weak_references ) ) {
			return false;
		}

		$filename = $path . DIRECTORY_SEPARATOR . $basename;
		$cut_filename = $filename;

		if ( Util::starts_with( $filename, $this->wp_upload_dir['basedir'] ) ) {
			$cut_filename = substr( $filename, strlen( $this->wp_upload_dir['basedir'] ) );
		} elseif ( Util::starts_with( $filename, ABSPATH ) ) {
			$cut_filename = substr( $filename, strlen( ABSPATH ) );
		}

		$uri = ltrim(
			str_replace( DIRECTORY_SEPARATOR, '/', $cut_filename ), '/' );

		global $wpdb;
		$collation = $this->get_query_collation();

		//
		// search in post table
		//
		$exclude_ids = array();
		do {
			$like = '%' . $wpdb->esc_like( $uri ) . '%';
			$exclude = count( $exclude_ids ) <= 0 ? '' :
				'AND id NOT IN (' . implode( ',', $exclude_ids ) . ')';

			$sql = $wpdb->prepare( "SELECT id
				FROM {$wpdb->posts}
				WHERE post_content LIKE %s $collation
					$exclude
				LIMIT 1", $like );

			$sql = apply_filters(
				'wow_mlf_unreferenced_file_weak_reference_content_query',
				$sql );
			$present_post_id = $wpdb->get_var( $sql );
			if ( !empty( $wpdb->last_error ) ) {
				throw new \Exception( $wpdb->last_error );
			}

			// allow custom code to process it themselves
			$action = apply_filters(
				'wow_mlf_unreferenced_file_weak_reference_content_found_action',
				'', $present_post_id, $path . DIRECTORY_SEPARATOR . $basename,
				$this->log );
			if ( $action == 'exclude' ) {
				$exclude_ids[] = $wpdb->prepare( '%d', $present_post_id );
			}
		} while ( $action == 'repeat' || $action == 'exclude' );

		if ( !is_null( $present_post_id ) ) {
			return $this->process_file_weak_reference_found( $filename,
				"Image file '$filename' is not in media library but used by post '$present_post_id'" );
		}


		//
		// search in postmeta table
		//
		$exclude_ids = array();
		do {
			// _wp_attachment_metadata already processed with knowledge about
			// context. Avoid refundant false positives
			$like = '%' . $wpdb->esc_like( $uri ) . '%';
			$exclude = count( $exclude_ids ) <= 0 ? '' :
				'AND meta_id NOT IN (' . implode( ',', $exclude_ids ) . ')';

			$sql = $wpdb->prepare(
				"SELECT meta_id, post_id, meta_key, meta_value
				FROM {$wpdb->postmeta}
				WHERE meta_value LIKE %s $collation AND
					meta_key NOT IN ( '_wp_attachment_metadata', '_wp_attachment_backup_sizes' )
					$exclude
				LIMIT 1",
				$like );
			$sql = apply_filters(
				'wow_mlf_unreferenced_file_weak_reference_meta_query',
				$sql );
			$present_meta = $wpdb->get_row( $sql );
			if ( !empty( $wpdb->last_error ) ) {
				throw new \Exception( $wpdb->last_error );
			}

			// allow custom code to process it themselves
			$action = apply_filters(
				'wow_mlf_unreferenced_file_weak_reference_meta_found_action',
				'', $present_meta, $path . DIRECTORY_SEPARATOR . $basename,
				$this->log );

			if ( $action == 'exclude' ) {
				$exclude_ids[] = $wpdb->prepare( '%d', $present_meta->meta_id );
			}
		} while ( $action == 'repeat' || $action == 'exclude' );

		if ( !is_null( $present_meta ) ) {
			return $this->process_file_weak_reference_found( $filename,
				"Image file '$filename' is not in media library but used by post '$present_meta->post_id' meta_key '$present_meta->meta_key'" );
		}

		$message = apply_filters( 'wow_mlf_unreferenced_file_weak_reference',
			'', $path . DIRECTORY_SEPARATOR . $basename, $this->log );
		if ( !empty( $message ) ) {
			return $this->process_file_weak_reference_found( $filename, $message );
		}

		return false;
	}



	private function get_query_collation() {
		// utf8_general_ci or ut8mb4 is usually a default collation, what means
		// case-insensitive search. while filenames are case-sensitive
		global $wpdb;
		if ( empty( $wpdb->charset ) ) {
			return '';
		}

		$bin_collation = $wpdb->get_row( $wpdb->prepare(
			"SHOW COLLATION
			WHERE Charset = %s AND
				collation LIKE '%\_bin'", $wpdb->charset ) );
		if ( !empty( $wpdb->last_error ) ) {
			return '';
		}

		if ( is_null( $bin_collation ) ) {
			return '';
		}

		return $wpdb->prepare( "COLLATE %s", $bin_collation->Collation );
	}



	private function process_file_weak_reference_found( $filename, $message ) {
		if ( $this->c_files_weak_references == 'log' ) {
			$this->log->log( null, $message );
		} elseif ( $this->c_files_weak_references == 'add' ) {
			$this->log->log( null, $message . '. Adding to Media Library.' );

			$wp_filetype = wp_check_filetype_and_ext( $filename, $filename );
			$url = str_replace( DIRECTORY_SEPARATOR, '/',
				str_replace(
					$this->wp_upload_dir['basedir'],
					$this->wp_upload_dir['baseurl'],
					$filename ) );

			$filename_parts = pathinfo( $filename );

			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'guid' => $url,
				'post_title' => $filename_parts['filename'],
				'post_content' => '',
				'post_excerpt' => '' );

			$post_id = wp_insert_attachment( $attachment, $filename, 0, true );
			if ( is_wp_error( $post_id ) ) {
				$this->log->log( $post_id,
					'Failed to add attachment to Media Library' );
			} else {
				wp_update_attachment_metadata( $post_id,
					wp_generate_attachment_metadata( $post_id, $filename ) );
				$this->log->log( $post_id, 'Added attachment to Media Library' );
			}
		} else {
			$this->log->log( null, 'Unknown files_weak_references value' );
		}

		return true;
	}



	private function process_unreferenced_file( $path, $basename ) {
		if ( empty( $this->c_files_unreferenced ) ) {
			return;
		}

		$filename = $path . DIRECTORY_SEPARATOR . $basename;
		$filename_for_log = str_replace( ABSPATH, '', $filename );
		$this->errors_count++;

		if ( $this->c_files_unreferenced == 'log' ) {
			$this->log->log( null, 'Found unreferenced media library file ' .
				$filename_for_log );
		} elseif ( $this->c_files_unreferenced == 'move' ) {
			$this->log->log(null,
				'Move unreferenced media library file ' . $filename_for_log );

			$path_postfix = substr( $path,
				strlen( $this->wp_upload_dir['basedir'] ) + 1 );
			$new_path = $this->wp_upload_dir['basedir'] . DIRECTORY_SEPARATOR .
				'unreferenced' . DIRECTORY_SEPARATOR . $path_postfix;

			if ( !file_exists( $new_path ) ) {
				if ( !@mkdir( $new_path, 0777, true ) ) {
					$this->log->log( null,
						"Failed to create folder $new_path" );
				}
			}

			if ( !@rename( $filename,
				$new_path . DIRECTORY_SEPARATOR . $basename ) ) {
				$this->log->log(null,
					'Failed to move unreferenced media library file ' .
					$filename_for_log );
			}
		} elseif ( $this->c_files_unreferenced == 'delete' ) {
			$this->log->log(null,
				"Delete unreferenced media library file $filename_for_log" );

			if (!@unlink( $filename ) ) {
				$this->log->log(null,
					"Failed to delete unreferenced media library file $filename_for_log" );
			}
		}
	}
}
