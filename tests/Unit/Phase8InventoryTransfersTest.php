<?php

use PHPUnit\Framework\TestCase;

final class Phase8InventoryTransfersTest extends TestCase {
	public function test_transfer_is_one_atomic_database_transaction(): void {
		$source = file_get_contents( __DIR__ . '/../../src/Inventory/InventoryService.php' );
		$start = strpos( $source, 'public function transfer_stock' );
		$end = strpos( $source, 'private function require_item', $start );
		$method = substr( $source, $start, $end - $start );
		$this->assertStringContainsString( "'START TRANSACTION'", $method );
		$this->assertStringContainsString( "'COMMIT'", $method );
		$this->assertStringContainsString( "'ROLLBACK'", $method );
		$this->assertStringNotContainsString( 'Automatic reversal', $method );
		$this->assertStringNotContainsString( 'adjust_stock(', $method );
	}

	public function test_transfer_locks_both_locations_in_deterministic_order(): void {
		$source = file_get_contents( __DIR__ . '/../../src/Inventory/InventoryService.php' );
		$this->assertStringContainsString( 'min($from_location_id,$to_location_id)', $source );
		$this->assertStringContainsString( 'max($from_location_id,$to_location_id)', $source );
		$this->assertGreaterThanOrEqual( 2, substr_count( $source, 'lock_quantity($item_id' ) );
	}

	public function test_transfer_validates_locations_quantity_stock_and_active_state(): void {
		$source = file_get_contents( __DIR__ . '/../../src/Inventory/InventoryService.php' );
		$this->assertStringContainsString( 'Inventory transfer locations must be different.', $source );
		$this->assertStringContainsString( 'Inventory transfer quantity must be greater than zero.', $source );
		$this->assertStringContainsString( 'Insufficient inventory at the source location.', $source );
		$this->assertStringContainsString( 'Inventory transfers require an active item and active locations.', $source );
		$this->assertStringContainsString( 'A note or reference is required for inventory transfers.', $source );
	}

	public function test_transfer_creates_linked_out_and_in_ledger_entries(): void {
		$source = file_get_contents( __DIR__ . '/../../src/Inventory/InventoryService.php' );
		$this->assertStringContainsString( "'transaction_type'=>'transfer_out'", $source );
		$this->assertStringContainsString( "'transaction_type'=>'transfer_in'", $source );
		$this->assertStringContainsString( "'related_transaction_id'=>\$out", $source );
		$this->assertStringContainsString( "'quantity_change'=>-\$quantity", $source );
		$this->assertStringContainsString( "'quantity_change'=>\$quantity", $source );
	}

	public function test_transfer_ui_uses_adjust_permission_and_active_locations(): void {
		$page = file_get_contents( __DIR__ . '/../../src/Admin/InventoryStockPage.php' );
		$this->assertStringContainsString( 'Transfer Stock Between Locations', $page );
		$this->assertStringContainsString( 'Capabilities::ADJUST_INVENTORY', $page );
		$this->assertStringContainsString( 'all( false )', $page );
		$this->assertStringContainsString( 'servsys_transfer_inventory_stock', $page );
		$this->assertStringContainsString( 'Transfer Inventory', $page );
	}

	public function test_p8_7_does_not_change_database_version(): void {
		$bootstrap = file_get_contents( __DIR__ . '/../../servsys.php' );
		$this->assertStringContainsString( "SERVSYS_DB_VERSION', 10", $bootstrap );
	}
}
