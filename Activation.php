<?php

namespace WowMediaLibraryFix;

class Activation {
	static public function deactivate() {
		Util::status_delete();
		Util::status_unreferenced_basenames_delete();

		$wp_upload_dir = wp_upload_dir();
		$log_to_file_filename = $wp_upload_dir['basedir'] .
			DIRECTORY_SEPARATOR . 'media-library.log';
		@unlink( $log_to_file_filename );
	}
}
