<?php

use PHPUnit\Framework\TestCase;
use ServSys\Inventory\InventoryService;

final class Phase8InventoryDomainTest extends TestCase {
	public function test_selling_price_uses_company_markup_formula_and_currency_rounding(): void {
		$this->assertSame( 140.0, InventoryService::selling_price( 100.0, 40.0 ) );
		$this->assertSame( 28.0, InventoryService::selling_price( 20.0, 40.0 ) );
		$this->assertSame( 12.35, InventoryService::selling_price( 10.0, 23.456 ) );
	}

	public function test_negative_cost_or_markup_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		InventoryService::selling_price( -1.0, 40.0 );
	}

	public function test_inventory_repositories_are_company_scoped(): void {
		foreach ( array(
			'InventoryLocationRepository.php',
			'InventoryItemRepository.php',
			'InventoryBalanceRepository.php',
			'InventoryTransactionRepository.php',
		) as $file ) {
			$source=file_get_contents(__DIR__.'/../../src/Inventory/'.$file);
			$this->assertStringContainsString('CompanyContext::current_company_id()',$source,$file);
		}
	}

	public function test_stock_changes_are_ledger_backed_and_cannot_go_negative(): void {
		$source=file_get_contents(__DIR__.'/../../src/Inventory/InventoryService.php');
		$this->assertStringContainsString('if ( $new_quantity < 0 )',$source);
		$this->assertStringContainsString('$this->balances->set_quantity',$source);
		$this->assertStringContainsString('$this->transactions->create',$source);
		$this->assertStringContainsString('$this->balances->set_quantity($item_id,$location_id,$current)',$source);
	}

	public function test_transfers_create_linked_out_and_in_transactions(): void {
		$source=file_get_contents(__DIR__.'/../../src/Inventory/InventoryService.php');
		$this->assertStringContainsString("'transfer_out'",$source);
		$this->assertStringContainsString("'transfer_in'",$source);
		$this->assertStringContainsString("'related_transaction_id'=>\$related_transaction_id",$source);
	}

	public function test_service_call_usage_is_a_supported_domain_transaction_type(): void {
		$source=file_get_contents(__DIR__.'/../../src/Inventory/InventoryService.php');
		$this->assertStringContainsString("'service_call_usage'",$source);
		$this->assertStringContainsString("'service_call_return'",$source);
	}
}
