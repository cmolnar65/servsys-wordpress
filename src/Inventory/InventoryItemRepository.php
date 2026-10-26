<?php

namespace ServSys\Inventory;

use ServSys\Companies\CompanyContext;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class InventoryItemRepository {
	private $wpdb;
	private $table;

	public function __construct( $wpdb = null ) {
		$this->wpdb = $wpdb ?: $GLOBALS['wpdb'];
		$this->table = $this->wpdb->prefix . 'servsys_inventory_items';
	}

	public function find( int $id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Current plugin-owned inventory data.
		return $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d AND company_id = %d', $this->table, $id, CompanyContext::current_company_id() ), ARRAY_A );
	}

	public function find_by_sku( string $sku ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Current plugin-owned inventory data.
		return $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE sku = %s AND company_id = %d', $this->table, $sku, CompanyContext::current_company_id() ), ARRAY_A );
	}

	public function create( array $data ): int { $this->wpdb->insert( $this->table, $data ); return (int) $this->wpdb->insert_id; }
	public function update( int $id, array $data ): bool { return false !== $this->wpdb->update( $this->table, $data, array( 'id'=>$id, 'company_id'=>CompanyContext::current_company_id() ) ); }
}
