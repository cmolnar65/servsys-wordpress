<?php

use PHPUnit\Framework\TestCase;

final class Phase8InventorySchemaTest extends TestCase {
	public function test_phase_8_schema_upgrade_is_versioned(): void {
		$bootstrap = file_get_contents( __DIR__ . '/../../servsys.php' );
		$this->assertStringContainsString( "SERVSYS_DB_VERSION', 10", $bootstrap );
	}

	public function test_company_has_inventory_markup_with_zero_default(): void {
		$schema = file_get_contents( __DIR__ . '/../../src/Database/Schema.php' );
		$this->assertStringContainsString( 'inventory_markup_percent decimal(7,3) NOT NULL DEFAULT 0.000', $schema );
	}

	public function test_inventory_foundation_tables_are_company_owned(): void {
		$schema = file_get_contents( __DIR__ . '/../../src/Database/Schema.php' );
		foreach ( array(
			'servsys_inventory_locations',
			'servsys_inventory_items',
			'servsys_inventory_balances',
			'servsys_inventory_transactions',
			'servsys_service_call_inventory',
		) as $table ) {
			$this->assertStringContainsString( $table, $schema, $table );
		}
		$this->assertGreaterThanOrEqual( 5, substr_count( $schema, 'company_id bigint(20) unsigned NOT NULL,' ) );
	}

	public function test_inventory_is_location_based_and_has_a_transaction_ledger(): void {
		$schema = file_get_contents( __DIR__ . '/../../src/Database/Schema.php' );
		$this->assertStringContainsString( 'UNIQUE KEY item_location (inventory_item_id,inventory_location_id)', $schema );
		$this->assertStringContainsString( 'transaction_type varchar(30) NOT NULL', $schema );
		$this->assertStringContainsString( 'quantity_change decimal(13,4) NOT NULL', $schema );
		$this->assertStringContainsString( 'related_transaction_id bigint(20) unsigned NULL', $schema );
	}

	public function test_service_call_inventory_preserves_historical_pricing(): void {
		$schema = file_get_contents( __DIR__ . '/../../src/Database/Schema.php' );
		foreach ( array(
			'unit_cost_snapshot decimal(13,4) NOT NULL',
			'markup_percent_snapshot decimal(7,3) NOT NULL',
			'unit_selling_price_snapshot decimal(13,4) NOT NULL',
			'extended_selling_price decimal(13,4) NOT NULL',
		) as $column ) {
			$this->assertStringContainsString( $column, $schema );
		}
	}

	public function test_company_configuration_exposes_inventory_markup(): void {
		$service = file_get_contents( __DIR__ . '/../../src/Companies/CompanyService.php' );
		$page = file_get_contents( __DIR__ . '/../../src/Admin/CompaniesPage.php' );
		$this->assertStringContainsString( "'inventory_markup_percent'=>max( 0,", $service );
		$this->assertStringContainsString( 'Inventory Markup (%)', $page );
		$this->assertStringContainsString( 'name="inventory_markup_percent"', $page );
	}
}
