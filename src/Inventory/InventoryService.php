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

	public function __construct(
		InventoryItemRepository $items = null,
		InventoryLocationRepository $locations = null,
		InventoryBalanceRepository $balances = null,
		InventoryTransactionRepository $transactions = null,
		CompanyRepository $companies = null
	) {
		$this->items=$items ?: new InventoryItemRepository();
		$this->locations=$locations ?: new InventoryLocationRepository();
		$this->balances=$balances ?: new InventoryBalanceRepository();
		$this->transactions=$transactions ?: new InventoryTransactionRepository();
		$this->companies=$companies ?: new CompanyRepository();
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
		if ( 0.0 === $quantity_change ) { throw new \InvalidArgumentException( 'Inventory quantity change cannot be zero.' ); }
		$allowed=array('initial','receipt','adjustment','transfer_in','transfer_out','service_call_usage','service_call_return');
		if ( ! in_array($transaction_type,$allowed,true) ) { throw new \InvalidArgumentException( 'Invalid inventory transaction type.' ); }
		$item=$this->require_item($item_id);
		$this->require_location($location_id);
		$current=$this->balances->quantity($item_id,$location_id);
		$new_quantity=$current+$quantity_change;
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
		if ( ! $transaction_id ) {
			$this->balances->set_quantity($item_id,$location_id,$current);
			throw new \RuntimeException( 'Unable to record inventory transaction.' );
		}
		return $transaction_id;
	}

	public function transfer_stock( int $item_id, int $from_location_id, int $to_location_id, float $quantity, string $note = '' ): array {
		if ( $from_location_id === $to_location_id ) { throw new \InvalidArgumentException( 'Inventory transfer locations must be different.' ); }
		if ( $quantity <= 0 ) { throw new \InvalidArgumentException( 'Inventory transfer quantity must be greater than zero.' ); }
		$out=$this->adjust_stock($item_id,$from_location_id,-$quantity,'transfer_out',$note);
		try {
			$in=$this->adjust_stock($item_id,$to_location_id,$quantity,'transfer_in',$note,0,$out);
		} catch ( \Throwable $e ) {
			$this->adjust_stock($item_id,$from_location_id,$quantity,'adjustment','Automatic reversal of failed inventory transfer.',0,$out);
			throw $e;
		}
		return array('transfer_out_transaction_id'=>$out,'transfer_in_transaction_id'=>$in);
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
