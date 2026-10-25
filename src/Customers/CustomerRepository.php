<?php

namespace ServSys\Customers;

final class CustomerRepository {
	private $wpdb;
	private $table;

	public function __construct( $wpdb = null ) {
		$this->wpdb  = $wpdb ?: $GLOBALS['wpdb'];
		$this->table = $this->wpdb->prefix . 'servsys_customers';
	}

	public function find( int $id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ),
			ARRAY_A
		);
	}

	public function search( string $term = '', bool $include_inactive = false ): array {
		$where = $include_inactive ? '1=1' : 'active = 1';

		if ( '' !== $term ) {
			$like  = '%' . $this->wpdb->esc_like( $term ) . '%';
			$where .= $this->wpdb->prepare(
				' AND (name LIKE %s OR email LIKE %s OR primary_phone LIKE %s)',
				$like,
				$like,
				$like
			);
		}

		return $this->wpdb->get_results(
			"SELECT * FROM {$this->table} WHERE {$where} ORDER BY name ASC LIMIT 250",
			ARRAY_A
		);
	}

	public function create( array $data ): int {
		$this->wpdb->insert( $this->table, $data );
		return (int) $this->wpdb->insert_id;
	}

	public function update( int $id, array $data ): bool {
		return false !== $this->wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}
}
