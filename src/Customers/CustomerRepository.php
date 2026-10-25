<?php

namespace ServSys\Customers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CustomerRepository {
	private $wpdb;
	private $table;

	public function __construct( $wpdb = null ) {
		$this->wpdb  = $wpdb ?: $GLOBALS['wpdb'];
		$this->table = $this->wpdb->prefix . 'servsys_customers';
	}

	public function find( int $id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ),
			ARRAY_A
		);
	}

	public function search( string $term = '', bool $include_inactive = false ): array {
		if ( '' !== $term ) {
			$like = '%' . $this->wpdb->esc_like( $term ) . '%';
			if ( $include_inactive ) {
				return $this->wpdb->get_results(
					$this->wpdb->prepare( 'SELECT * FROM %i WHERE (name LIKE %s OR email LIKE %s OR primary_phone LIKE %s) ORDER BY name ASC LIMIT 250', $this->table, $like, $like, $like ),
					ARRAY_A
				);
			}
			return $this->wpdb->get_results(
				$this->wpdb->prepare( 'SELECT * FROM %i WHERE active = 1 AND (name LIKE %s OR email LIKE %s OR primary_phone LIKE %s) ORDER BY name ASC LIMIT 250', $this->table, $like, $like, $like ),
				ARRAY_A
			);
		}
		if ( $include_inactive ) {
			return $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT * FROM %i ORDER BY name ASC LIMIT 250', $this->table ), ARRAY_A );
		}
		return $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT * FROM %i WHERE active = 1 ORDER BY name ASC LIMIT 250', $this->table ), ARRAY_A );
	}

	public function create( array $data ): int {
		$this->wpdb->insert( $this->table, $data );
		return (int) $this->wpdb->insert_id;
	}

	public function update( int $id, array $data ): bool {
		return false !== $this->wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}
}
