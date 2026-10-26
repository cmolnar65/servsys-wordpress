<?php

namespace ServSys\Inventory;

use ServSys\Companies\CompanyContext;
use ServSys\Companies\CompanyRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class InventoryService {
	private $items;
	private $locations;
	private $balances;
	private $transactions;
	private $companies;
	private $service_call_inventory;

	public function __construct(
		InventoryItemRepository $items = null,
		InventoryLocationRepository $locations = null,
		InventoryBalanceRepository $balances = null,
		InventoryTransactionRepository $transactions = null,
		CompanyRepository $companies = null,
		ServiceCallInventoryRepository $service_call_inventory = null
	) {
		$this->items=$items ?: new InventoryItemRepository();
		$this->locations=$locations ?: new InventoryLocationRepository();
		$this->balances=$balances ?: new InventoryBalanceRepository();
		$this->transactions=$transactions ?: new InventoryTransactionRepository();
		$this->companies=$companies ?: new CompanyRepository();
		$this->service_call_inventory=$service_call_inventory ?: new ServiceCallInventoryRepository();
	}

	public static function selling_price( float $unit_cost, float $markup_percent ): float {
		if ( $unit_cost < 0 || $markup_percent < 0 ) { throw new \InvalidArgumentException( 'Cost and markup cannot be negative.' ); }
		return round( $unit_cost * ( 1 + ( $markup_percent / 100 ) ), 2 );
	}

	public function current_markup_percent(): float {
		$company=$this->companies->find_unscoped( CompanyContext::current_company_id() );
		if ( ! $company ) { throw new \RuntimeException( 'Current company is not available.' ); }
		return max( 0.0, (float) $company['inventory_markup_percent'] );
	}

	public function current_selling_price( int $item_id ): float {
		$item=$this->require_item($item_id);
		return self::selling_price((float)$item['unit_cost'],$this->current_markup_percent());
	}

	public function adjust_stock( int $item_id, int $location_id, float $quantity_change, string $transaction_type, string $note = '', int $service_call_id = 0, int $related_transaction_id = 0 ): int {
		$quantity_change = round( $quantity_change, 4 );
		if ( 0.0 === $quantity_change ) { throw new \InvalidArgumentException( 'Inventory quantity change cannot be zero.' ); }
		$allowed=array('initial','receipt','adjustment','transfer_in','transfer_out','service_call_usage','service_call_return');
		if ( ! in_array($transaction_type,$allowed,true) ) { throw new \InvalidArgumentException( 'Invalid inventory transaction type.' ); }
		$item=$this->require_item($item_id);
		$location=$this->require_location($location_id);
		if ( empty( $item['active'] ) || empty( $location['active'] ) ) { throw new \InvalidArgumentException( 'New inventory activity requires an active item and active location.' ); }

		$wpdb = $GLOBALS['wpdb'];
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Inventory balance and audit ledger must commit atomically.
		$wpdb->query( 'START TRANSACTION' );
		try {
			$current=$this->balances->lock_quantity($item_id,$location_id);
			$new_quantity=round($current+$quantity_change,4);
			if ( $new_quantity < 0 ) { throw new \InvalidArgumentException( 'Inventory quantity cannot be reduced below zero.' ); }
			$now=current_time('mysql',true); $user=get_current_user_id();
			if ( ! $this->balances->set_quantity($item_id,$location_id,$new_quantity) ) { throw new \RuntimeException( 'Unable to update inventory balance.' ); }
			$transaction_id=$this->transactions->create(array(
				'inventory_item_id'=>$item_id,
				'inventory_location_id'=>$location_id,
				'transaction_type'=>$transaction_type,
				'quantity_change'=>$quantity_change,
				'unit_cost'=>(float)$item['unit_cost'],
				'service_call_id'=>$service_call_id ?: null,
				'related_transaction_id'=>$related_transaction_id ?: null,
				'note'=>sanitize_textarea_field($note),
				'transaction_at'=>$now,
				'created_at'=>$now,
				'created_by'=>$user ?: null,
			));
			if ( ! $transaction_id ) { throw new \RuntimeException( 'Unable to record inventory transaction.' ); }
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Inventory balance and audit ledger must commit atomically.
			$wpdb->query( 'COMMIT' );
			return $transaction_id;
		} catch ( \Throwable $exception ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Roll back any partial inventory quantity or ledger change.
			$wpdb->query( 'ROLLBACK' );
			throw $exception;
		}
	}

	public function transfer_stock( int $item_id, int $from_location_id, int $to_location_id, float $quantity, string $note = '' ): array {
		$quantity = round( $quantity, 4 );
		if ( $from_location_id === $to_location_id ) { throw new \InvalidArgumentException( 'Inventory transfer locations must be different.' ); }
		if ( $quantity <= 0 ) { throw new \InvalidArgumentException( 'Inventory transfer quantity must be greater than zero.' ); }
		if ( '' === trim( $note ) ) { throw new \InvalidArgumentException( 'A note or reference is required for inventory transfers.' ); }
		$item=$this->require_item($item_id);
		$from=$this->require_location($from_location_id);
		$to=$this->require_location($to_location_id);
		if ( empty( $item['active'] ) || empty( $from['active'] ) || empty( $to['active'] ) ) { throw new \InvalidArgumentException( 'Inventory transfers require an active item and active locations.' ); }

		$wpdb=$GLOBALS['wpdb'];
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Both transfer balances and ledger entries must commit atomically.
		$wpdb->query( 'START TRANSACTION' );
		try {
			$first_location_id=min($from_location_id,$to_location_id);
			$second_location_id=max($from_location_id,$to_location_id);
			$first_quantity=$this->balances->lock_quantity($item_id,$first_location_id);
			$second_quantity=$this->balances->lock_quantity($item_id,$second_location_id);
			$from_quantity=$from_location_id === $first_location_id ? $first_quantity : $second_quantity;
			$to_quantity=$to_location_id === $first_location_id ? $first_quantity : $second_quantity;
			if ( $from_quantity < $quantity ) { throw new \InvalidArgumentException( 'Insufficient inventory at the source location.' ); }
			$new_from=round($from_quantity-$quantity,4);
			$new_to=round($to_quantity+$quantity,4);
			if ( ! $this->balances->set_quantity($item_id,$from_location_id,$new_from) || ! $this->balances->set_quantity($item_id,$to_location_id,$new_to) ) { throw new \RuntimeException( 'Unable to update inventory transfer balances.' ); }
			$now=current_time('mysql',true); $user=get_current_user_id();
			$out=$this->transactions->create(array(
				'inventory_item_id'=>$item_id,'inventory_location_id'=>$from_location_id,'transaction_type'=>'transfer_out',
				'quantity_change'=>-$quantity,'unit_cost'=>(float)$item['unit_cost'],'service_call_id'=>null,'related_transaction_id'=>null,
				'note'=>sanitize_textarea_field($note),'transaction_at'=>$now,'created_at'=>$now,'created_by'=>$user ?: null,
			));
			if ( ! $out ) { throw new \RuntimeException( 'Unable to record source inventory transfer.' ); }
			$in=$this->transactions->create(array(
				'inventory_item_id'=>$item_id,'inventory_location_id'=>$to_location_id,'transaction_type'=>'transfer_in',
				'quantity_change'=>$quantity,'unit_cost'=>(float)$item['unit_cost'],'service_call_id'=>null,'related_transaction_id'=>$out,
				'note'=>sanitize_textarea_field($note),'transaction_at'=>$now,'created_at'=>$now,'created_by'=>$user ?: null,
			));
			if ( ! $in ) { throw new \RuntimeException( 'Unable to record destination inventory transfer.' ); }
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Both transfer balances and ledger entries must commit atomically.
			$wpdb->query( 'COMMIT' );
			return array('transfer_out_transaction_id'=>$out,'transfer_in_transaction_id'=>$in);
		} catch ( \Throwable $exception ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Roll back the complete inventory transfer.
			$wpdb->query( 'ROLLBACK' );
			throw $exception;
		}
	}

	public function consume_for_service_call( int $service_call_id, int $item_id, int $location_id, float $quantity, string $note = '' ): int {
		$quantity=round($quantity,4);
		if ( $quantity <= 0 ) { throw new \InvalidArgumentException( 'Inventory usage quantity must be greater than zero.' ); }
		$item=$this->require_item($item_id);
		$location=$this->require_location($location_id);
		if ( empty($item['active']) || empty($location['active']) ) { throw new \InvalidArgumentException( 'Service Call inventory usage requires an active item and active location.' ); }
		$service_call=(new \ServSys\ServiceCalls\ServiceCallRepository())->find($service_call_id);
		if ( ! $service_call || (int)$service_call['company_id'] !== CompanyContext::current_company_id() ) { throw new \InvalidArgumentException( 'Service Call does not belong to the current company.' ); }
		$markup=$this->current_markup_percent();
		$unit_cost=round((float)$item['unit_cost'],4);
		$unit_selling=round(self::selling_price($unit_cost,$markup),4);
		$extended=round($unit_selling*$quantity,4);
		$wpdb=$GLOBALS['wpdb'];
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Stock deduction, ledger and Service Call usage snapshot must commit atomically.
		$wpdb->query('START TRANSACTION');
		try {
			$current=$this->balances->lock_quantity($item_id,$location_id);
			if ( $current < $quantity ) { throw new \InvalidArgumentException( 'Insufficient inventory at the selected location.' ); }
			if ( ! $this->balances->set_quantity($item_id,$location_id,round($current-$quantity,4)) ) { throw new \RuntimeException( 'Unable to update inventory balance.' ); }
			$now=current_time('mysql',true); $user=get_current_user_id();
			$transaction_id=$this->transactions->create(array(
				'inventory_item_id'=>$item_id,'inventory_location_id'=>$location_id,'transaction_type'=>'service_call_usage',
				'quantity_change'=>-$quantity,'unit_cost'=>$unit_cost,'service_call_id'=>$service_call_id,'related_transaction_id'=>null,
				'note'=>sanitize_textarea_field($note),'transaction_at'=>$now,'created_at'=>$now,'created_by'=>$user ?: null,
			));
			if ( ! $transaction_id ) { throw new \RuntimeException( 'Unable to record Service Call inventory transaction.' ); }
			$usage_id=$this->service_call_inventory->create(array(
				'service_call_id'=>$service_call_id,'inventory_item_id'=>$item_id,'inventory_location_id'=>$location_id,'inventory_transaction_id'=>$transaction_id,
				'quantity'=>$quantity,'item_sku_snapshot'=>$item['sku'],'item_description_snapshot'=>$item['description'],
				'unit_cost_snapshot'=>$unit_cost,'markup_percent_snapshot'=>$markup,'unit_selling_price_snapshot'=>$unit_selling,'extended_selling_price'=>$extended,
				'created_at'=>$now,'updated_at'=>$now,'created_by'=>$user ?: null,'updated_by'=>$user ?: null,
			));
			if ( ! $usage_id ) { throw new \RuntimeException( 'Unable to record Service Call inventory usage.' ); }
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Stock deduction, ledger and Service Call usage snapshot must commit atomically.
			$wpdb->query('COMMIT');
			return $usage_id;
		} catch ( \Throwable $exception ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Roll back partial Service Call inventory usage.
			$wpdb->query('ROLLBACK');
			throw $exception;
		}
	}

	public function return_service_call_usage( int $usage_id, string $note = '' ): int {
		$usage=$this->service_call_inventory->find($usage_id);
		if ( ! $usage ) { throw new \InvalidArgumentException( 'Service Call inventory usage not found.' ); }
		$quantity=round((float)$usage['quantity'],4);
		if ( $this->transactions->has_related_transaction( (int)$usage['inventory_transaction_id'], 'service_call_return' ) ) { throw new \InvalidArgumentException( 'This Service Call inventory usage has already been reversed.' ); }
		if ( $quantity <= 0 ) { throw new \InvalidArgumentException( 'This Service Call inventory usage has already been reversed.' ); }
		$item=$this->require_item((int)$usage['inventory_item_id']);
		$this->require_location((int)$usage['inventory_location_id']);
		$wpdb=$GLOBALS['wpdb'];
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Return balance, ledger and usage reversal must commit atomically.
		$wpdb->query('START TRANSACTION');
		try {
			$current=$this->balances->lock_quantity((int)$usage['inventory_item_id'],(int)$usage['inventory_location_id']);
			if ( ! $this->balances->set_quantity((int)$usage['inventory_item_id'],(int)$usage['inventory_location_id'],round($current+$quantity,4)) ) { throw new \RuntimeException( 'Unable to return inventory to stock.' ); }
			$now=current_time('mysql',true); $user=get_current_user_id();
			$transaction_id=$this->transactions->create(array(
				'inventory_item_id'=>(int)$usage['inventory_item_id'],'inventory_location_id'=>(int)$usage['inventory_location_id'],'transaction_type'=>'service_call_return',
				'quantity_change'=>$quantity,'unit_cost'=>(float)$usage['unit_cost_snapshot'],'service_call_id'=>(int)$usage['service_call_id'],'related_transaction_id'=>(int)$usage['inventory_transaction_id'],
				'note'=>sanitize_textarea_field($note ?: 'Service Call inventory usage reversal.'),'transaction_at'=>$now,'created_at'=>$now,'created_by'=>$user ?: null,
			));
			if ( ! $transaction_id ) { throw new \RuntimeException( 'Unable to record inventory return.' ); }
			$reversal_id=$this->service_call_inventory->create(array(
				'service_call_id'=>(int)$usage['service_call_id'],'inventory_item_id'=>(int)$usage['inventory_item_id'],'inventory_location_id'=>(int)$usage['inventory_location_id'],'inventory_transaction_id'=>$transaction_id,
				'quantity'=>-$quantity,'item_sku_snapshot'=>$usage['item_sku_snapshot'],'item_description_snapshot'=>$usage['item_description_snapshot'],
				'unit_cost_snapshot'=>(float)$usage['unit_cost_snapshot'],'markup_percent_snapshot'=>(float)$usage['markup_percent_snapshot'],'unit_selling_price_snapshot'=>(float)$usage['unit_selling_price_snapshot'],'extended_selling_price'=>-(float)$usage['extended_selling_price'],
				'created_at'=>$now,'updated_at'=>$now,'created_by'=>$user ?: null,'updated_by'=>$user ?: null,
			));
			if ( ! $reversal_id ) { throw new \RuntimeException( 'Unable to record Service Call inventory reversal.' ); }
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Return balance, ledger and usage reversal must commit atomically.
			$wpdb->query('COMMIT');
			return $reversal_id;
		} catch ( \Throwable $exception ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Roll back partial Service Call inventory reversal.
			$wpdb->query('ROLLBACK');
			throw $exception;
		}
	}

	private function require_item( int $id ): array {
		$item=$this->items->find($id);
		if ( ! $item ) { throw new \InvalidArgumentException( 'Inventory item does not belong to the current company.' ); }
		return $item;
	}

	private function require_location( int $id ): array {
		$location=$this->locations->find($id);
		if ( ! $location ) { throw new \InvalidArgumentException( 'Inventory location does not belong to the current company.' ); }
		return $location;
	}
}
