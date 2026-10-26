<?php

use PHPUnit\Framework\TestCase;

final class Phase9FlatRateSchemaTest extends TestCase {
	public function test_phase_9_flat_rate_schema_upgrade_is_versioned(): void {
		$bootstrap = file_get_contents( __DIR__ . '/../../servsys.php' );
		$this->assertStringContainsString( "SERVSYS_DB_VERSION', 11", $bootstrap );
	}

	public function test_flat_rate_foundation_is_company_scoped(): void {
		$schema = file_get_contents( __DIR__ . '/../../src/Database/Schema.php' );
		foreach ( array(
			'servsys_flat_rate_config',
			'servsys_flat_rate_categories',
			'servsys_flat_rate_items',
			'servsys_flat_rate_item_materials',
		) as $table ) {
			$this->assertStringContainsString( $table, $schema, $table );
		}
		$this->assertStringContainsString( 'UNIQUE KEY company_id (company_id)', $schema );
		$this->assertStringContainsString( 'UNIQUE KEY company_code (company_id,code)', $schema );
		$this->assertStringContainsString( 'UNIQUE KEY company_name (company_id,name)', $schema );
	}

	public function test_flat_rate_items_preserve_legacy_business_flags_without_legacy_sync_fields(): void {
		$schema = file_get_contents( __DIR__ . '/../../src/Database/Schema.php' );
		$this->assertStringContainsString( 'no_discount tinyint(1) NOT NULL DEFAULT 0', $schema );
		$this->assertStringContainsString( 'special_item tinyint(1) NOT NULL DEFAULT 0', $schema );
		$this->assertStringContainsString( 'customer_notes longtext NULL', $schema );
		$this->assertStringContainsString( 'labor_hours decimal(9,4) NOT NULL DEFAULT 0.0000', $schema );
		$this->assertStringNotContainsString( 'flat_rate_date', $schema );
		$this->assertStringNotContainsString( 'servsync', $schema );
	}

	public function test_flat_rate_material_recipe_supports_inventory_and_manual_materials(): void {
		$schema = file_get_contents( __DIR__ . '/../../src/Database/Schema.php' );
		$this->assertStringContainsString( 'flat_rate_item_id bigint(20) unsigned NOT NULL', $schema );
		$this->assertStringContainsString( 'inventory_item_id bigint(20) unsigned NULL', $schema );
		$this->assertStringContainsString( 'quantity decimal(13,4) NOT NULL DEFAULT 1.0000', $schema );
		$this->assertStringContainsString( 'manual_description varchar(255) NULL', $schema );
		$this->assertStringContainsString( 'manual_unit_cost decimal(13,4) NULL', $schema );
	}

	public function test_flat_rate_config_preserves_pricing_inputs_for_phase_9_calculator(): void {
		$schema = file_get_contents( __DIR__ . '/../../src/Database/Schema.php' );
		foreach ( array(
			'labor_rate decimal(13,4) NOT NULL DEFAULT 0.0000',
			'additional_repair_multiplier decimal(9,4) NOT NULL DEFAULT 1.0000',
			'agreement_repair_multiplier decimal(9,4) NOT NULL DEFAULT 1.0000',
			'commercial_multiplier decimal(9,4) NOT NULL DEFAULT 1.0000',
			'part_markup_multiplier decimal(9,4) NOT NULL DEFAULT 1.0000',
			'sales_tax_percent decimal(7,4) NOT NULL DEFAULT 0.0000',
			'special_item_low_price decimal(13,4) NOT NULL DEFAULT 0.0000',
			'special_item_high_price decimal(13,4) NOT NULL DEFAULT 0.0000',
			'special_item_high_hours decimal(9,4) NOT NULL DEFAULT 0.0000',
		) as $column ) {
			$this->assertStringContainsString( $column, $schema );
		}
	}

	public function test_installer_uses_dbdelta_for_upgrade_safe_schema_changes(): void {
		$installer = file_get_contents( __DIR__ . '/../../src/Database/Installer.php' );
		$this->assertStringContainsString( 'if ( $installed < SERVSYS_DB_VERSION )', $installer );
		$this->assertStringContainsString( 'foreach ( Schema::tables( $wpdb ) as $sql )', $installer );
		$this->assertStringContainsString( 'dbDelta( $sql )', $installer );
		$this->assertStringContainsString( "update_option( 'servsys_db_version', SERVSYS_DB_VERSION, false )", $installer );
	}
}
