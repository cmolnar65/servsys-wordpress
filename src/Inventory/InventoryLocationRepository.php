<?php

namespace ServSys\Inventory;

use ServSys\Companies\CompanyContext;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class InventoryLocationRepository {
	private $wpdb;
	private $table;

	public function __construct( $wpdb = null ) {
		$this->wpdb = $wpdb ?: $GLOBALS['wpdb'];
		$this->table = $this->wpdb->prefix . 'servsys_inventory_locations';
	}

	public function find( int $id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Current plugin-owned inventory data.
		return $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d AND company_id = %d', $this->table, $id, CompanyContext::current_company_id() ), ARRAY_A );
	}

	public function all( bool $include_inactive = true ): array {
		if ( $include_inactive ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Current plugin-owned inventory data.
			return $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT * FROM %i WHERE company_id = %d ORDER BY name,id', $this->table, CompanyContext::current_company_id() ), ARRAY_A );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Current plugin-owned inventory data.
		return $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT * FROM %i WHERE company_id = %d AND active = 1 ORDER BY name,id', $this->table, CompanyContext::current_company_id() ), ARRAY_A );
	}

	public function create( array $data ): int {
		$data['company_id'] = CompanyContext::current_company_id();
		$this->wpdb->insert( $this->table, $data );
		return (int) $this->wpdb->insert_id;
	}
	public function update( int $id, array $data ): bool { return false !== $this->wpdb->update( $this->table, $data, array( 'id'=>$id, 'company_id'=>CompanyContext::current_company_id() ) ); }
}
