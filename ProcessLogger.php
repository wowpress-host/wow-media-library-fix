<?php

namespace WowMediaLibraryFix;

class ProcessLogger {
	private $log_to_file;
	private $log_to_file_filename;

	public $verbose;
	public $notices;



	public function __construct( $log_to_file, $log_verbose, $wp_upload_dir ) {
		$this->log_to_file = $log_to_file;
		$this->verbose = $log_verbose;

		$this->log_to_file_filename = $wp_upload_dir['basedir'] .
			DIRECTORY_SEPARATOR . 'media-library.log';
	}



	public function clear() {
		file_put_contents( $this->log_to_file_filename, '' );
	}



	public function log( $post_id, $message ) {
		if ( $this->log_to_file ) {
			file_put_contents( $this->log_to_file_filename,
				date('c') . ' post ' . $post_id . ': ' . $message . "\n",
				FILE_APPEND );
		} else {
			$this->notices[] = array(
				'post_id' => $post_id,
				'message' => $message
			);
		}
	}
}
