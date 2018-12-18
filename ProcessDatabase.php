<?php

namespace WowMediaLibraryFix;

class ProcessDatabase {
	private $log;
	public $errors_count = 0;



	public function __construct( $log ) {
		$this->log = $log;
	}



	public function process() {
		global $wpdb;
		$this->check_table( $wpdb->posts );
		$this->check_primary_key( $wpdb->posts, 'ID' );
		$this->check_table( $wpdb->postmeta );
		$this->check_primary_key( $wpdb->postmeta, 'meta_id' );
	}



	public function check_table( $table ) {
		if ( $this->log->verbose ) {
			$this->log->log( null, 'Checking status of $table table' );
		}

		global $wpdb;
		$rows = $wpdb->get_results( "CHECK TABLE $table FAST QUICK" );
		if ( !empty( $wpdb->last_error ) ) {
			$this->errors_count++;
			$this->log->log( null, "CHECK TABLE $table returned: " .
				$wpdb->last_error );
			return;
		}

		$ok = count( $rows ) <= 0;

		foreach ( $rows as $row ) {
			if ( $row->Msg_type == 'status' &&
				( $row->Msg_text == 'OK' ||
					$row->Msg_text == 'Table is already up to date' ) ) {
				$ok = true;
			}
		}

		if ( !$ok ) {
			$this->errors_count++;
			$this->log->log( null,
				"A sign of potential major database corruption found! Consider deeper database repair procedure" );
			$this->log->log( null, "CHECK TABLE $table shown problems" );
			foreach ( $rows as $row ) {
				$this->log->log( null, $row->Msg_type . ': ' . $row->Msg_text );
			}
		}
	}



	public function check_primary_key( $table, $column ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$sql = $wpdb->prepare( "SHOW COLUMNS FROM $table WHERE Field = '%s'",
				$column ) );
		if ( !empty( $wpdb->last_error ) ) {
			$this->errors_count++;
			$this->log->log( null, "SHOW COLUMNS $table returned: " .
				$wpdb->last_error );
			return;
		}

		if ( !$row ) {
			$this->errors_count++;
			$this->log->log( null, "Could not find $table.$column column" );
			return;
		}

		if ( $row->Key != 'PRI' ) {
			$this->errors_count++;
			$this->log->log( null,
				"Primary Key for $table.$column column not found" );
		}

		if ( $row->Extra != 'auto_increment' ) {
			$this->errors_count++;
			$this->log->log( null,
				"$table.$column is not marked as auto_increment field" );
		}
	}
}
