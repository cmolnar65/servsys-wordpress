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
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses plugin-owned custom tables and must remain current.
		$wpdb = $this->wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ),
			ARRAY_A
		);		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	}

	public function search( string $term = '', bool $include_inactive = false ): array {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses plugin-owned custom tables and must remain current.
		$wpdb = $this->wpdb;
		if ( '' !== $term ) {
			$like = '%' . $wpdb->esc_like( $term ) . '%';
			if ( $include_inactive ) {
				return $wpdb->get_results(
					$wpdb->prepare( 'SELECT * FROM %i WHERE (name LIKE %s OR email LIKE %s OR primary_phone LIKE %s) ORDER BY name ASC LIMIT 250', $this->table, $like, $like, $like ),
					ARRAY_A
				);
			}
			return $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE active = 1 AND (name LIKE %s OR email LIKE %s OR primary_phone LIKE %s) ORDER BY name ASC LIMIT 250', $this->table, $like, $like, $like ),
				ARRAY_A
			);
		}
		if ( $include_inactive ) {
			return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY name ASC LIMIT 250', $this->table ), ARRAY_A );
		}
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE active = 1 ORDER BY name ASC LIMIT 250', $this->table ), ARRAY_A );		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	}

	public function create( array $data ): int {
		$this->wpdb->insert( $this->table, $data );
		return (int) $this->wpdb->insert_id;
	}

	public function update( int $id, array $data ): bool {
		return false !== $this->wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}
}
