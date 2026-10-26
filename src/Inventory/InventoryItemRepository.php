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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Current plugin-owned inventory data.
		return $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d AND company_id = %d', $this->table, $id, CompanyContext::current_company_id() ), ARRAY_A );
	}

	public function find_by_sku( string $sku ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Current plugin-owned inventory data.
		return $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE sku = %s AND company_id = %d', $this->table, $sku, CompanyContext::current_company_id() ), ARRAY_A );
	}

	public function search( string $term = '', bool $include_inactive = true ): array {
		$company_id = CompanyContext::current_company_id();
		if ( '' === $term ) {
			$sql = $include_inactive
				? $this->wpdb->prepare( 'SELECT * FROM %i WHERE company_id = %d ORDER BY description,sku,id', $this->table, $company_id )
				: $this->wpdb->prepare( 'SELECT * FROM %i WHERE company_id = %d AND active = 1 ORDER BY description,sku,id', $this->table, $company_id );
		} else {
			$like = '%' . $this->wpdb->esc_like( $term ) . '%';
			$sql = $include_inactive
				? $this->wpdb->prepare( 'SELECT * FROM %i WHERE company_id = %d AND (sku LIKE %s OR description LIKE %s OR manufacturer LIKE %s OR manufacturer_part_number LIKE %s OR category LIKE %s) ORDER BY description,sku,id', $this->table, $company_id, $like, $like, $like, $like, $like )
				: $this->wpdb->prepare( 'SELECT * FROM %i WHERE company_id = %d AND active = 1 AND (sku LIKE %s OR description LIKE %s OR manufacturer LIKE %s OR manufacturer_part_number LIKE %s OR category LIKE %s) ORDER BY description,sku,id', $this->table, $company_id, $like, $like, $like, $like, $like );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Current plugin-owned inventory data.
		return $this->wpdb->get_results( $sql, ARRAY_A );
	}

	public function create( array $data ): int {
		$data['company_id'] = CompanyContext::current_company_id();
		$this->wpdb->insert( $this->table, $data );
		return (int) $this->wpdb->insert_id;
	}
	public function update( int $id, array $data ): bool { return false !== $this->wpdb->update( $this->table, $data, array( 'id'=>$id, 'company_id'=>CompanyContext::current_company_id() ) ); }
}
