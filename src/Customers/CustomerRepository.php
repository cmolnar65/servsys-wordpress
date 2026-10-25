<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
			$this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ),
			ARRAY_A
		);
	}

	public function search( string $term = '', bool $include_inactive = false ): array {
		if ( '' !== $term ) {
			$like = '%' . $this->wpdb->esc_like( $term ) . '%';
			if ( $include_inactive ) {
				$sql = $this->wpdb->prepare( 'SELECT * FROM %i WHERE (name LIKE %s OR email LIKE %s OR primary_phone LIKE %s) ORDER BY name ASC LIMIT 250', $this->table, $like, $like, $like );
			} else {
				$sql = $this->wpdb->prepare( 'SELECT * FROM %i WHERE active = 1 AND (name LIKE %s OR email LIKE %s OR primary_phone LIKE %s) ORDER BY name ASC LIMIT 250', $this->table, $like, $like, $like );
			}
		} elseif ( $include_inactive ) {
			$sql = $this->wpdb->prepare( 'SELECT * FROM %i ORDER BY name ASC LIMIT 250', $this->table );
		} else {
			$sql = $this->wpdb->prepare( 'SELECT * FROM %i WHERE active = 1 ORDER BY name ASC LIMIT 250', $this->table );
		}
		return $this->wpdb->get_results( $sql, ARRAY_A );
	}

	public function create( array $data ): int {
		$this->wpdb->insert( $this->table, $data );
		return (int) $this->wpdb->insert_id;
	}

	public function update( int $id, array $data ): bool {
		return false !== $this->wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}
}
