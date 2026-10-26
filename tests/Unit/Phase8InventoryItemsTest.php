<?php

use PHPUnit\Framework\TestCase;

final class Phase8InventoryItemsTest extends TestCase {
	public function test_item_repository_forces_company_and_scopes_find_search_and_sku(): void {
		$source = file_get_contents( __DIR__ . '/../../src/Inventory/InventoryItemRepository.php' );
		$this->assertStringContainsString( "\$data['company_id'] = CompanyContext::current_company_id();", $source );
		$this->assertGreaterThanOrEqual( 4, substr_count( $source, 'CompanyContext::current_company_id()' ) );
		$this->assertStringContainsString( 'find_by_sku', $source );
		$this->assertStringContainsString( 'public function search', $source );
	}

	public function test_item_service_requires_unique_sku_and_description_and_nonnegative_values(): void {
		$source = file_get_contents( __DIR__ . '/../../src/Inventory/InventoryItemService.php' );
		$this->assertStringContainsString( 'Inventory SKU is required.', $source );
		$this->assertStringContainsString( 'Inventory description is required.', $source );
		$this->assertStringContainsString( 'Inventory SKU must be unique within the current company.', $source );
		$this->assertStringContainsString( 'Unit cost and reorder quantity cannot be negative.', $source );
		$this->assertStringContainsString( 'round( (float)', $source );
	}

	public function test_items_distinguish_internal_sku_from_manufacturer_part_number(): void {
		$page = file_get_contents( __DIR__ . '/../../src/Admin/InventoryItemsPage.php' );
		$this->assertStringContainsString( 'Internal SKU / Part Number', $page );
		$this->assertStringContainsString( 'Manufacturer Part Number', $page );
		$this->assertStringContainsString( 'manufacturer_part_number', $page );
	}

	public function test_item_list_search_detail_and_edit_are_permission_controlled(): void {
		$page = file_get_contents( __DIR__ . '/../../src/Admin/InventoryItemsPage.php' );
		$this->assertStringContainsString( 'Capabilities::VIEW_INVENTORY', $page );
		$this->assertStringContainsString( 'Capabilities::MANAGE_INVENTORY', $page );
		$this->assertStringContainsString( 'Search Inventory', $page );
		$this->assertStringContainsString( 'render_detail', $page );
		$this->assertStringContainsString( 'render_form', $page );
	}

	public function test_selling_price_is_calculated_from_company_markup_not_saved_on_item(): void {
		$page = file_get_contents( __DIR__ . '/../../src/Admin/InventoryItemsPage.php' );
		$service = file_get_contents( __DIR__ . '/../../src/Inventory/InventoryItemService.php' );
		$this->assertStringContainsString( 'current_markup_percent()', $page );
		$this->assertStringContainsString( 'InventoryService::selling_price', $page );
		$this->assertStringNotContainsString( "'selling_price'", $service );
		$this->assertStringNotContainsString( "'markup_percent'", $service );
	}

	public function test_item_lifecycle_is_active_inactive_not_delete(): void {
		$page = file_get_contents( __DIR__ . '/../../src/Admin/InventoryItemsPage.php' );
		$repository = file_get_contents( __DIR__ . '/../../src/Inventory/InventoryItemRepository.php' );
		$this->assertStringContainsString( 'name="active"', $page );
		$this->assertStringNotContainsString( 'DELETE FROM', $repository );
		$this->assertStringNotContainsString( 'function delete', $repository );
	}

	public function test_configuration_and_plugin_register_inventory_items(): void {
		$config = file_get_contents( __DIR__ . '/../../src/Admin/ConfigurationPage.php' );
		$plugin = file_get_contents( __DIR__ . '/../../src/Plugin.php' );
		$this->assertStringContainsString( "'Inventory Items'", $config );
		$this->assertStringContainsString( "'servsys-inventory-items'", $config );
		$this->assertStringContainsString( 'new InventoryItemsPage()', $plugin );
	}

	public function test_p8_5_does_not_change_database_version(): void {
		$bootstrap = file_get_contents( __DIR__ . '/../../servsys.php' );
		$this->assertStringContainsString( "SERVSYS_DB_VERSION', 10", $bootstrap );
	}
}
