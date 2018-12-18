<?php

namespace WowMediaLibraryFix;

class ProcessPostUnreferencedThumbnails {
	public $errors_count = 0;

	private $post_id;
	private $log;
	private $c_files_thumbnails;

	// files found that matching this post
	private $basenames = array();
	private $path;

	// cache only single dir, dont waste memory
	static private $cached_path = '';
	static private $cached_path_basenames;



	public function __construct( $post_id, $log, $c_files_thumbnails ) {
		$this->post_id = $post_id;
		$this->log = $log;
		$this->c_files_thumbnails = $c_files_thumbnails;
	}



	public function find_thumbnails_of( $filename ) {
		if ( empty( $this->c_files_thumbnails ) || empty( $filename ) ) {
			return;
		}

		$path = pathinfo( $filename );
		if ( self::$cached_path != $path['dirname'] ) {
			self::$cached_path = $path['dirname'];
			self::$cached_path_basenames = scandir( self::$cached_path );
		}

		// glob is not good enough since filenames contains ? and * often
		$prefix = $path['filename'] . '-';
		$postfix = '.' . $path['extension'];

		foreach ( self::$cached_path_basenames as $basename ) {
			if ( substr( $basename, 0, strlen( $prefix ) ) == $prefix &&
				substr( $basename, - strlen( $postfix ) ) == $postfix ) {

				$after_prefix = substr( $basename, strlen( $prefix ) );
				$after_prefix = substr( $after_prefix, 0,
					strlen( $after_prefix ) - strlen( $postfix ) );

				// thumbnails file format is 100x500
				// 100x500@2x for retina
				if ( preg_match( '~^[0-9]+x[0-9]+(@2x)?$~', $after_prefix) ) {
					$this->basenames[$basename] = '*';
				}
			}
		}

		$this->path = $path['dirname'];
	}



	public function match_with_metadata( $wp_upload_dir, $meta ) {
		if ( empty( $this->c_files_thumbnails ) ) {
			return;
		}

		// match path
		if ( Util::starts_with( $this->path, $wp_upload_dir['basedir'] ) ) {
			if ( $this->path == $wp_upload_dir['basedir'] ) {
				$path_postfix = '.';
			} else {
				$path_postfix = substr( $this->path,
					strlen( $wp_upload_dir['basedir'] ) + 1 );
			}

			// mark as files found before referenced
			if ( isset( $meta['file'] ) &&
					dirname( $meta['file'] ) == $path_postfix ) {

				$basename = basename( $meta['file'] );
				unset( $this->basenames[$basename] );

				if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
					foreach ( $meta['sizes'] as $i ) {
						if ( isset( $i['file'] ) ) {
							$basename = $i['file'];
							unset( $this->basenames[$basename] );
						}
					}
				}
			}
		}

		$this->basenames = apply_filters( 'wow_mlf_referenced_thumbnails',
			$this->basenames, $this->path );

		foreach ( $this->basenames as $filename => $value ) {
			$this->errors_count++;
			$filename_in_log = str_replace( ABSPATH, '',
				$this->path . DIRECTORY_SEPARATOR . $filename );

			if ( !file_exists( $this->path . DIRECTORY_SEPARATOR . $filename ) ) {
				// file already removed by previous attachments processings
			} elseif ( $this->c_files_thumbnails == 'log' ) {
				$this->log->log( $this->post_id,
					'Found unreferenced thumbnail ' . $filename_in_log );
			} elseif ( $this->c_files_thumbnails == 'move' ) {
				$this->log->log($this->post_id,
					'Move unreferenced thumbnail ' . $filename_in_log );

				$path_postfix = substr( $this->path,
					strlen( $wp_upload_dir['basedir'] ) + 1 );
				$new_path = $wp_upload_dir['basedir'] . DIRECTORY_SEPARATOR .
					'unreferenced' . DIRECTORY_SEPARATOR . $path_postfix;

				if ( !file_exists( $new_path ) ) {
					if ( !@mkdir( $new_path, 0777, true ) ) {
						$this->log->log( $this->post_id,
							'Failed to create folder  ' . $new_path );
					}
				}

				if ( !@rename( $this->path . DIRECTORY_SEPARATOR . $filename,
					$new_path . DIRECTORY_SEPARATOR . $filename ) ) {
					$this->log->log($this->post_id,
						'Failed to move unreferenced thumbnail ' .
						$filename_in_log );
				}
			} elseif ( $this->c_files_thumbnails == 'delete' ) {
				$this->log->log($this->post_id,
					'Delete unreferenced thumbnail ' .
					$filename_in_log );

				if (!@unlink( $this->path . DIRECTORY_SEPARATOR . $filename ) ) {
					$this->log->log($this->post_id,
						'Failed to delete unreferenced thumbnail ' .
						$filename_in_log );
				}
			}
		}
	}
}
