<?php

namespace ServSys\Inventory;

use ServSys\Companies\CompanyContext;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class InventoryBalanceRepository {
	private $wpdb;
	private $table;

	public function __construct( $wpdb = null ) {
		$this->wpdb = $wpdb ?: $GLOBALS['wpdb'];
		$this->table = $this->wpdb->prefix . 'servsys_inventory_balances';
	}

	public function find( int $item_id, int $location_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Inventory balance must be current.
		return $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE company_id = %d AND inventory_item_id = %d AND inventory_location_id = %d', $this->table, CompanyContext::current_company_id(), $item_id, $location_id ), ARRAY_A );
	}

	public function lock_quantity( int $item_id, int $location_id ): float {
		$company_id = CompanyContext::current_company_id();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Row lock is required for atomic inventory quantity changes.
		$row = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE company_id = %d AND inventory_item_id = %d AND inventory_location_id = %d FOR UPDATE', $this->table, $company_id, $item_id, $location_id ), ARRAY_A );
		if ( $row ) {
			return (float) $row['quantity_on_hand'];
		}
		$now = current_time( 'mysql', true );
		$user = get_current_user_id();
		$this->wpdb->insert( $this->table, array( 'company_id'=>$company_id, 'inventory_item_id'=>$item_id, 'inventory_location_id'=>$location_id, 'quantity_on_hand'=>0, 'updated_at'=>$now, 'updated_by'=>$user ?: null ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock the balance row just created or concurrently created.
		$row = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE company_id = %d AND inventory_item_id = %d AND inventory_location_id = %d FOR UPDATE', $this->table, $company_id, $item_id, $location_id ), ARRAY_A );
		if ( ! $row ) {
			throw new \RuntimeException( 'Unable to lock inventory balance.' );
		}
		return (float) $row['quantity_on_hand'];
	}

	public function quantity( int $item_id, int $location_id ): float {
		$row=$this->find($item_id,$location_id);
		return $row ? (float)$row['quantity_on_hand'] : 0.0;
	}

	public function set_quantity( int $item_id, int $location_id, float $quantity ): bool {
		$company_id=CompanyContext::current_company_id(); $now=current_time('mysql',true); $user=get_current_user_id();
		$existing=$this->find($item_id,$location_id);
		if ($existing) {
			return false !== $this->wpdb->update($this->table,array('quantity_on_hand'=>$quantity,'updated_at'=>$now,'updated_by'=>$user?:null),array('id'=>(int)$existing['id'],'company_id'=>$company_id));
		}
		return false !== $this->wpdb->insert($this->table,array('company_id'=>$company_id,'inventory_item_id'=>$item_id,'inventory_location_id'=>$location_id,'quantity_on_hand'=>$quantity,'updated_at'=>$now,'updated_by'=>$user?:null));
	}
}
