<?php

namespace WowMediaLibraryFix\Plugins;

/**
 * Updates _thumbnail_id metadata (Featured Image)
 * when duplicate post found
 **/
class KeepThumbnailReference {
	static public function wow_mlf_duplicate_post_migrate( $old_post_id,
			$new_post_id, $log ) {
		global $wpdb;
		$sql = $wpdb->prepare( "SELECT post_id
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_thumbnail_id' AND
				meta_value = %d", $old_post_id );
		$post_ids = $wpdb->get_results( $sql );
		if ( !empty( $wpdb->last_error ) ) {
			throw new \Exception( $wpdb->last_error );
		}

		foreach ( $post_ids as $row ) {
			$affected_post_id = $row->post_id;
			update_post_meta( $affected_post_id, '_thumbnail_id', $new_post_id );
			$log->log( $old_post_id,
				"Updating _thumbnail_id reference at post $affected_post_id'" );
		}
	}
}
