<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace ServSys\Customers;

final class NoteRepository {
	private $wpdb;
	private $table;

	public function __construct( $wpdb = null ) {
		$this->wpdb  = $wpdb ?: $GLOBALS['wpdb'];
		$this->table = $this->wpdb->prefix . 'servsys_customer_notes';
	}

	public function for_customer( int $customer_id ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE customer_id = %d ORDER BY note_date DESC, id DESC',
				$this->table,
				$customer_id
			),
			ARRAY_A
		);
	}

	public function create( array $data ): int {
		$this->wpdb->insert( $this->table, $data );
		return (int) $this->wpdb->insert_id;
	}
}
