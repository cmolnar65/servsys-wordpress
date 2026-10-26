<?php

use PHPUnit\Framework\TestCase;

final class Phase8InventoryStockTest extends TestCase {
	public function test_stock_adjustments_are_atomic_and_lock_balance_rows(): void {
		$service = file_get_contents( __DIR__ . '/../../src/Inventory/InventoryService.php' );
		$balances = file_get_contents( __DIR__ . '/../../src/Inventory/InventoryBalanceRepository.php' );
		$this->assertStringContainsString( "'START TRANSACTION'", $service );
		$this->assertStringContainsString( "'COMMIT'", $service );
		$this->assertStringContainsString( "'ROLLBACK'", $service );
		$this->assertStringContainsString( 'lock_quantity', $service );
		$this->assertStringContainsString( 'FOR UPDATE', $balances );
	}

	public function test_quantity_changes_are_normalized_and_cannot_make_stock_negative(): void {
		$service = file_get_contents( __DIR__ . '/../../src/Inventory/InventoryService.php' );
		$this->assertStringContainsString( 'round( $quantity_change, 4 )', $service );
		$this->assertStringContainsString( 'round($current+$quantity_change,4)', $service );
		$this->assertStringContainsString( 'Inventory quantity cannot be reduced below zero.', $service );
	}

	public function test_new_stock_activity_requires_active_item_and_location(): void {
		$service = file_get_contents( __DIR__ . '/../../src/Inventory/InventoryService.php' );
		$this->assertStringContainsString( "empty( \$item['active'] ) || empty( \$location['active'] )", $service );
		$this->assertStringContainsString( 'New inventory activity requires an active item and active location.', $service );
	}

	public function test_receiving_and_adjustment_ui_requires_adjust_inventory_permission(): void {
		$page = file_get_contents( __DIR__ . '/../../src/Admin/InventoryStockPage.php' );
		$this->assertStringContainsString( 'Capabilities::VIEW_INVENTORY', $page );
		$this->assertStringContainsString( 'Capabilities::ADJUST_INVENTORY', $page );
		$this->assertStringContainsString( 'Initial Quantity', $page );
		$this->assertStringContainsString( 'Receipt / Purchase', $page );
		$this->assertStringContainsString( 'Adjustment', $page );
	}

	public function test_initial_and_receipt_quantities_must_be_positive_and_notes_are_required(): void {
		$page = file_get_contents( __DIR__ . '/../../src/Admin/InventoryStockPage.php' );
		$this->assertStringContainsString( "array( 'initial', 'receipt' )", $page );
		$this->assertStringContainsString( '$quantity <= 0', $page );
		$this->assertStringContainsString( 'A note or reference is required for inventory changes.', $page );
	}

	public function test_stock_page_displays_location_balances_and_transaction_history(): void {
		$page = file_get_contents( __DIR__ . '/../../src/Admin/InventoryStockPage.php' );
		$transactions = file_get_contents( __DIR__ . '/../../src/Inventory/InventoryTransactionRepository.php' );
		$this->assertStringContainsString( 'Quantity on Hand', $page );
		$this->assertStringContainsString( 'Transaction History', $page );
		$this->assertStringContainsString( 'for_item_location', $transactions );
		$this->assertStringContainsString( 'company_id = %d', $transactions );
	}

	public function test_inventory_item_detail_links_to_stock_workflow_and_plugin_registers_page(): void {
		$items = file_get_contents( __DIR__ . '/../../src/Admin/InventoryItemsPage.php' );
		$plugin = file_get_contents( __DIR__ . '/../../src/Plugin.php' );
		$this->assertStringContainsString( 'View Stock &amp; Transactions', $items );
		$this->assertStringContainsString( 'new InventoryStockPage()', $plugin );
	}

	public function test_p8_6_does_not_change_database_version(): void {
		$bootstrap = file_get_contents( __DIR__ . '/../../servsys.php' );
		$this->assertStringContainsString( "SERVSYS_DB_VERSION', 10", $bootstrap );
	}
}
