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

	public function report_rows(): array {
		$items=$this->wpdb->prefix . 'servsys_inventory_items';
		$locations=$this->wpdb->prefix . 'servsys_inventory_locations';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Inventory reporting must reflect current quantities.
		return $this->wpdb->get_results($this->wpdb->prepare(
			'SELECT b.*, i.sku AS item_sku, i.description AS item_description, i.unit_cost, i.reorder_quantity, i.active AS item_active, l.name AS location_name, l.active AS location_active FROM %i b INNER JOIN %i i ON i.id=b.inventory_item_id AND i.company_id=b.company_id INNER JOIN %i l ON l.id=b.inventory_location_id AND l.company_id=b.company_id WHERE b.company_id=%d ORDER BY i.description,i.sku,l.name',
			$this->table,$items,$locations,CompanyContext::current_company_id()
		),ARRAY_A);
	}

	public function totals_by_item(): array {
		$totals=array();
		foreach($this->report_rows() as $row){
			$item_id=(int)$row['inventory_item_id'];
			if(!isset($totals[$item_id])){
				$totals[$item_id]=array(
					'inventory_item_id'=>$item_id,
					'item_sku'=>$row['item_sku'],
					'item_description'=>$row['item_description'],
					'unit_cost'=>(float)$row['unit_cost'],
					'reorder_quantity'=>(float)$row['reorder_quantity'],
					'quantity_on_hand'=>0.0,
				);
			}
			$totals[$item_id]['quantity_on_hand']=round($totals[$item_id]['quantity_on_hand']+(float)$row['quantity_on_hand'],4);
		}
		return array_values($totals);
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
