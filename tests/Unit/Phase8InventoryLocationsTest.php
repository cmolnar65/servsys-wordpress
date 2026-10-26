<?php

use PHPUnit\Framework\TestCase;

final class Phase8InventoryLocationsTest extends TestCase {
	public function test_location_creation_forces_current_company(): void {
		$source = file_get_contents( __DIR__ . '/../../src/Inventory/InventoryLocationRepository.php' );
		$this->assertStringContainsString( "\$data['company_id'] = CompanyContext::current_company_id();", $source );
		$this->assertStringContainsString( "'company_id'=>CompanyContext::current_company_id()", str_replace( ' ', '', $source ) );
	}

	public function test_location_service_validates_company_membership_and_existing_location_scope(): void {
		$source = file_get_contents( __DIR__ . '/../../src/Inventory/InventoryLocationService.php' );
		$this->assertStringContainsString( 'CompanyContext::current_company_id()', $source );
		$this->assertStringContainsString( '\$this->company_users->find', $source );
		$this->assertStringContainsString( '\$this->locations->find( $id )', $source );
		$this->assertStringContainsString( 'active member of the current company', $source );
	}

	public function test_locations_use_view_and_manage_inventory_permissions(): void {
		$source = file_get_contents( __DIR__ . '/../../src/Admin/InventoryLocationsPage.php' );
		$this->assertStringContainsString( 'Capabilities::VIEW_INVENTORY', $source );
		$this->assertStringContainsString( 'Capabilities::MANAGE_INVENTORY', $source );
	}

	public function test_location_lifecycle_is_deactivation_not_deletion(): void {
		$page = file_get_contents( __DIR__ . '/../../src/Admin/InventoryLocationsPage.php' );
		$repository = file_get_contents( __DIR__ . '/../../src/Inventory/InventoryLocationRepository.php' );
		$this->assertStringContainsString( "name=\"active\"", $page );
		$this->assertStringNotContainsString( 'delete(', $repository );
		$this->assertStringNotContainsString( 'DELETE FROM', $repository );
	}

	public function test_configuration_links_inventory_locations_and_plugin_registers_page(): void {
		$config = file_get_contents( __DIR__ . '/../../src/Admin/ConfigurationPage.php' );
		$plugin = file_get_contents( __DIR__ . '/../../src/Plugin.php' );
		$this->assertStringContainsString( "'Inventory Locations'", $config );
		$this->assertStringContainsString( "'servsys-inventory-locations'", $config );
		$this->assertStringContainsString( 'new InventoryLocationsPage()', $plugin );
	}

	public function test_p8_4_does_not_change_database_version(): void {
		$bootstrap = file_get_contents( __DIR__ . '/../../servsys.php' );
		$this->assertStringContainsString( "SERVSYS_DB_VERSION', 10", $bootstrap );
	}
}
