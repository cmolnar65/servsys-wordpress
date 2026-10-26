<?php

namespace ServSys\Sites;

use ServSys\Companies\CompanyContext;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SiteRepository {
	private $wpdb;
	private $table;

	public function __construct( $wpdb = null ) {
		$this->wpdb  = $wpdb ?: $GLOBALS['wpdb'];
		$this->table = $this->wpdb->prefix . 'servsys_sites';
	}

	public function for_customer( int $customer_id, bool $include_inactive = true ): array {
		$wpdb = $this->wpdb;
		if ( $include_inactive ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses a plugin-owned custom table and must remain current.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses a plugin-owned custom table and must remain current.
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE company_id = %d AND customer_id = %d ORDER BY name, city, address1', $this->table, CompanyContext::current_company_id(), $customer_id ), ARRAY_A );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses a plugin-owned custom table and must remain current.
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE company_id = %d AND customer_id = %d AND active = 1 ORDER BY name, city, address1', $this->table, CompanyContext::current_company_id(), $customer_id ), ARRAY_A );
	}

	public function find( int $id ) {
		$wpdb = $this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses a plugin-owned custom table and must remain current.
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d AND company_id = %d', $this->table, $id, CompanyContext::current_company_id() ),
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
