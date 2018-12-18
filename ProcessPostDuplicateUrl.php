<?php

namespace WowMediaLibraryFix;

class ProcessPostDuplicateUrl {
	private $post;
	private $filename;
	private $log;
	private $c_posts_delete_duplicate_url;



	public function __construct( $post, $filename,
			$log, $c_posts_delete_duplicate_url ) {
		$this->post = $post;
		$this->filename = $filename;
		$this->log = $log;
		$this->c_posts_delete_duplicate_url = $c_posts_delete_duplicate_url;
	}



	public function maybe_delete_post() {
		$my_file = get_post_meta( $this->post->ID, '_wp_attached_file', true );
		if ( empty( $my_file ) ) {
			if ( $this->log->verbose ) {
				$this->log->log( $this->post->ID,
					"Duplicate post check skipped. '_wp_attached_file' is empty" );
			}

			return false;
		}

		// fast query to check it fast
		global $wpdb;
		$sql = $wpdb->prepare( "SELECT post_id
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_wp_attached_file' AND
				meta_value = %s AND
				post_id > %d
			LIMIT 1", $my_file, $this->post->ID );
		$duplicate_post_id = $wpdb->get_var( $sql );
		if ( !empty( $wpdb->last_error ) ) {
			throw new \Exception( $wpdb->last_error );
		}

		if ( is_null( $duplicate_post_id ) ) {
			return false;
		}

		// longer query to make sure its dup
		// take latest dup since freshier posts are usually most valid
		$fields_extra = ', post_parent';
		$post_parent_check = $wpdb->prepare( "p.post_parent = %d AND",
			$this->post->post_parent );

		if ( $this->c_posts_delete_duplicate_url == 'delete_ignore_parent' ) {
			$post_parent_check = '';
			$fields_extra = '';
		}

		$sql = $wpdb->prepare( "
			SELECT pm.post_id, pm.meta_value
			FROM {$wpdb->postmeta} AS pm
				INNER JOIN {$wpdb->posts} AS p
					ON pm.post_id = p.id
			WHERE pm.meta_key = '_wp_attached_file' AND
				pm.meta_value = %s AND
				pm.post_id > %d AND
				p.post_type = 'attachment' AND
				$post_parent_check
				p.post_status = %s
			ORDER BY post_id DESC
			LIMIT 1", $my_file, $this->post->ID, $this->post->post_status );
		$rows = $wpdb->get_results( $sql );
		if ( !empty( $wpdb->last_error ) ) {
			throw new \Exception( $wpdb->last_error );
		}

		$duplicate_post_id = null;
		// do case-senstive check
		// mysql often does case-insensitive check
		// and collation consversion in mysql to prevent it makes it slow,
		// but filenames are case-sensitive (ignore win)
		foreach ( $rows as $row ) {
			if ( $row->meta_value == $my_file ) {
				$duplicate_post_id = $row->post_id;
				break;
			}
		}

		$duplicate_post_id = apply_filters( 'wow_mlf_duplicate_post',
			$duplicate_post_id, $this->post, '_wp_attached_file' );

		if ( is_null( $duplicate_post_id ) ) {
			if ( $this->log->verbose ) {
				$this->log->log( $this->post->ID,
					"Post with duplicate '_wp_attached_file' = '$my_file' present, but those posts don't have equal post_type$fields_extra, post_status fields. Skipped. " . $sql );
			}

			return false;
		}

		if ( $this->c_posts_delete_duplicate_url == 'log' ) {
			$this->log->log( $this->post->ID,
				"Duplicate post '$duplicate_post_id' found poiting the same file '$my_file'" );
		} elseif ( $this->c_posts_delete_duplicate_url == 'delete' ||
				$this->c_posts_delete_duplicate_url == 'delete_ignore_parent' ) {
			// simple call to wp_delete_post will delete images too
			$this->post_duplicate_delete( $duplicate_post_id, $my_file );
			return true;
		}

		return false;
	}



	private function post_duplicate_delete( $new_post_id, $my_file ) {
		// change references
		do_action( 'wow_mlf_duplicate_post_migrate', $this->post->ID,
			$new_post_id, $this->log );

		// regular wp_delete_post would cause deletion of images associated
		// with it therefore breaking duplicate post
		//
		// so remove metas first
		$metas = get_post_meta( $this->post->ID );
		foreach ( $metas as $meta_key => $value) {
			delete_post_meta( $this->post->ID, $meta_key );
		}

		wp_delete_post( $this->post->ID, true );

		$this->log->log( $this->post->ID,
			"Attachment deleted because duplicate post '$new_post_id' found pointing '$my_file'" );
	}
}
