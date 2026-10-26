<?php

namespace ServSys\Customers;

use ServSys\Companies\CompanyContext;

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
		$wpdb = $this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses a plugin-owned custom table and must remain current.
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d AND company_id = %d', $this->table, $id, CompanyContext::current_company_id() ),
			ARRAY_A
		);
	}

	public function search( string $term = '', bool $include_inactive = false ): array {
		$wpdb = $this->wpdb;
		if ( '' !== $term ) {
			$like = '%' . $wpdb->esc_like( $term ) . '%';
			if ( $include_inactive ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses plugin-owned custom tables and must remain current.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses plugin-owned custom tables and must remain current.
		return $wpdb->get_results(
					$wpdb->prepare( 'SELECT * FROM %i WHERE company_id = %d AND (name LIKE %s OR email LIKE %s OR primary_phone LIKE %s) ORDER BY name ASC LIMIT 250', $this->table, CompanyContext::current_company_id(), $like, $like, $like ),
					ARRAY_A
				);
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses plugin-owned custom tables and must remain current.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses plugin-owned custom tables and must remain current.
		return $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE company_id = %d AND active = 1 AND (name LIKE %s OR email LIKE %s OR primary_phone LIKE %s) ORDER BY name ASC LIMIT 250', $this->table, CompanyContext::current_company_id(), $like, $like, $like ),
				ARRAY_A
			);
		}
		if ( $include_inactive ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses plugin-owned custom tables and must remain current.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses plugin-owned custom tables and must remain current.
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE company_id = %d ORDER BY name ASC LIMIT 250', $this->table, CompanyContext::current_company_id() ), ARRAY_A );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses plugin-owned custom tables and must remain current.
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE company_id = %d AND active = 1 ORDER BY name ASC LIMIT 250', $this->table, CompanyContext::current_company_id() ), ARRAY_A );
	}

	public function create( array $data ): int {
		$this->wpdb->insert( $this->table, $data );
		return (int) $this->wpdb->insert_id;
	}

	public function update( int $id, array $data ): bool {
		return false !== $this->wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}
}
