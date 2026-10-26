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

	public function set_related_transaction( int $transaction_id, int $related_transaction_id ): bool {
		return false !== $this->wpdb->update(
			$this->table,
			array( 'related_transaction_id' => $related_transaction_id ),
			array( 'id' => $transaction_id, 'company_id' => CompanyContext::current_company_id() )
		);
	}

	public function has_related_transaction( int $transaction_id, string $transaction_type ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Audit ledger relationship must remain current.
		return (bool) $this->wpdb->get_var($this->wpdb->prepare('SELECT id FROM %i WHERE company_id = %d AND related_transaction_id = %d AND transaction_type = %s LIMIT 1',$this->table,CompanyContext::current_company_id(),$transaction_id,$transaction_type));
	}

	public function search( array $filters = array(), int $limit = 250 ): array {
		$limit=max(1,min(1000,$limit));
		$company_id=CompanyContext::current_company_id();
		$item_id=isset($filters['item_id'])?absint($filters['item_id']):0;
		$location_id=isset($filters['location_id'])?absint($filters['location_id']):0;
		$type=isset($filters['transaction_type'])?sanitize_key($filters['transaction_type']):'';
		$date_from=isset($filters['date_from'])?sanitize_text_field($filters['date_from']):'';
		$date_to=isset($filters['date_to'])?sanitize_text_field($filters['date_to']):'';
		$items=$this->wpdb->prefix . 'servsys_inventory_items';
		$locations=$this->wpdb->prefix . 'servsys_inventory_locations';
		$sql='SELECT t.*, i.sku AS item_sku, i.description AS item_description, l.name AS location_name FROM %i t INNER JOIN %i i ON i.id=t.inventory_item_id AND i.company_id=t.company_id INNER JOIN %i l ON l.id=t.inventory_location_id AND l.company_id=t.company_id WHERE t.company_id=%d';
		$args=array($this->table,$items,$locations,$company_id);
		if($item_id){$sql.=' AND t.inventory_item_id=%d';$args[]=$item_id;}
		if($location_id){$sql.=' AND t.inventory_location_id=%d';$args[]=$location_id;}
		if($type){$sql.=' AND t.transaction_type=%s';$args[]=$type;}
		if($date_from){$sql.=' AND t.transaction_at >= %s';$args[]=$date_from . ' 00:00:00';}
		if($date_to){$sql.=' AND t.transaction_at <= %s';$args[]=$date_to . ' 23:59:59';}
		$sql.=' ORDER BY t.transaction_at DESC,t.id DESC LIMIT %d';$args[]=$limit;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Inventory reporting must reflect the current audit ledger.
		return $this->wpdb->get_results($this->wpdb->prepare($sql,$args),ARRAY_A);
	}

	public function transaction_types(): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Inventory reporting must reflect transaction types present in the audit ledger.
		return $this->wpdb->get_col($this->wpdb->prepare('SELECT DISTINCT transaction_type FROM %i WHERE company_id=%d ORDER BY transaction_type',$this->table,CompanyContext::current_company_id()));
	}

	public function totals_by_type( array $filters = array() ): array {
		$company_id=CompanyContext::current_company_id();
		$item_id=isset($filters['item_id'])?absint($filters['item_id']):0;
		$location_id=isset($filters['location_id'])?absint($filters['location_id']):0;
		$type=isset($filters['transaction_type'])?sanitize_key($filters['transaction_type']):'';
		$date_from=isset($filters['date_from'])?sanitize_text_field($filters['date_from']):'';
		$date_to=isset($filters['date_to'])?sanitize_text_field($filters['date_to']):'';
		$sql='SELECT transaction_type, SUM(quantity_change) AS quantity_change FROM %i WHERE company_id=%d';
		$args=array($this->table,$company_id);
		if($item_id){$sql.=' AND inventory_item_id=%d';$args[]=$item_id;}
		if($location_id){$sql.=' AND inventory_location_id=%d';$args[]=$location_id;}
		if($type){$sql.=' AND transaction_type=%s';$args[]=$type;}
		if($date_from){$sql.=' AND transaction_at >= %s';$args[]=$date_from . ' 00:00:00';}
		if($date_to){$sql.=' AND transaction_at <= %s';$args[]=$date_to . ' 23:59:59';}
		$sql.=' GROUP BY transaction_type ORDER BY transaction_type';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Inventory reporting requires complete ledger aggregation.
		$rows=$this->wpdb->get_results($this->wpdb->prepare($sql,$args),ARRAY_A);
		$totals=array();
		foreach($rows as $row){$totals[$row['transaction_type']]=round((float)$row['quantity_change'],4);}
		return $totals;
	}

	public function ledger_totals_by_item_location(): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Inventory reconciliation must aggregate the complete current-company ledger.
		$rows=$this->wpdb->get_results($this->wpdb->prepare(
			'SELECT inventory_item_id, inventory_location_id, SUM(quantity_change) AS ledger_quantity FROM %i WHERE company_id=%d GROUP BY inventory_item_id,inventory_location_id',
			$this->table,CompanyContext::current_company_id()
		),ARRAY_A);
		$totals=array();
		foreach($rows as $row){$totals[(int)$row['inventory_item_id'] . ':' . (int)$row['inventory_location_id']]=round((float)$row['ledger_quantity'],4);}
		return $totals;
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
