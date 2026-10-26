<?php

namespace ServSys\Inventory;

use ServSys\Companies\CompanyContext;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class InventoryTransactionRepository {
	private $wpdb;
	private $table;

	public function __construct( $wpdb = null ) {
		$this->wpdb = $wpdb ?: $GLOBALS['wpdb'];
		$this->table = $this->wpdb->prefix . 'servsys_inventory_transactions';
	}

	public function create( array $data ): int {
		$data['company_id']=CompanyContext::current_company_id();
		$this->wpdb->insert($this->table,$data);
		return (int)$this->wpdb->insert_id;
	}

	public function has_related_transaction( int $transaction_id, string $transaction_type ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Audit ledger relationship must remain current.
		return (bool) $this->wpdb->get_var($this->wpdb->prepare('SELECT id FROM %i WHERE company_id = %d AND related_transaction_id = %d AND transaction_type = %s LIMIT 1',$this->table,CompanyContext::current_company_id(),$transaction_id,$transaction_type));
	}

	public function for_item_location( int $item_id, int $location_id, int $limit = 100 ): array {
		$limit=max(1,min(500,$limit));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Inventory audit ledger must be current.
		return $this->wpdb->get_results($this->wpdb->prepare('SELECT * FROM %i WHERE company_id = %d AND inventory_item_id = %d AND inventory_location_id = %d ORDER BY transaction_at DESC,id DESC LIMIT %d',$this->table,CompanyContext::current_company_id(),$item_id,$location_id,$limit),ARRAY_A);
	}

	public function for_item( int $item_id, int $limit = 100 ): array {
		$limit=max(1,min(500,$limit));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Inventory audit ledger must be current.
		return $this->wpdb->get_results($this->wpdb->prepare('SELECT * FROM %i WHERE company_id = %d AND inventory_item_id = %d ORDER BY transaction_at DESC,id DESC LIMIT %d',$this->table,CompanyContext::current_company_id(),$item_id,$limit),ARRAY_A);
	}
}
