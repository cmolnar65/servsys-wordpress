<?php

namespace ServSys\ServiceCalls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ServiceCallNoteRepository {
	private $wpdb;
	private $table;

	public function __construct( $wpdb = null ) {
		$this->wpdb = $wpdb ?: $GLOBALS['wpdb'];
		$this->table = $this->wpdb->prefix . 'servsys_service_call_notes';
	}

	public function for_service_call( int $service_call_id ): array {
		$wpdb = $this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational notes use a plugin-owned custom table and must remain current.
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE service_call_id = %d ORDER BY note_date DESC, id DESC', $this->table, $service_call_id ),
			ARRAY_A
		);
	}

	public function create( array $data ): int {
		$this->wpdb->insert( $this->table, $data );
		return (int) $this->wpdb->insert_id;
	}
}
