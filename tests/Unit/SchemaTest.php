<?php

use PHPUnit\Framework\TestCase;
use ServSys\Database\Schema;

final class SchemaTest extends TestCase {
	public function test_phase_six_tables_use_wordpress_prefix(): void {
		$wpdb = new class {
			public $prefix = 'test_';
			public function get_charset_collate(): string {
				return 'DEFAULT CHARACTER SET utf8mb4';
			}
		};

		$tables = Schema::tables( $wpdb );

		$this->assertSame(
			array(
				'test_servsys_customer_types',
				'test_servsys_customers',
				'test_servsys_sites',
				'test_servsys_customer_notes',
				'test_servsys_equipment',
			),
			array_keys( $tables )
		);
	}

	public function test_schema_contains_customer_site_and_equipment_relationships_and_legacy_ids(): void {
		$wpdb = new class {
			public $prefix = 'wp_';
			public function get_charset_collate(): string {
				return 'DEFAULT CHARACTER SET utf8mb4';
			}
		};

		$tables = Schema::tables( $wpdb );

		$this->assertStringContainsString( 'slug varchar(100) NOT NULL', $tables['wp_servsys_customer_types'] );
		$this->assertStringContainsString( 'customer_type_id bigint(20) unsigned NULL', $tables['wp_servsys_customers'] );
		$this->assertStringContainsString( 'legacy_custnum', $tables['wp_servsys_customers'] );
		$this->assertStringContainsString( 'customer_id bigint(20) unsigned NOT NULL', $tables['wp_servsys_sites'] );
		$this->assertStringContainsString( 'legacy_notenum', $tables['wp_servsys_customer_notes'] );
		$this->assertStringContainsString( 'site_id bigint(20) unsigned NOT NULL', $tables['wp_servsys_equipment'] );
		$this->assertStringContainsString( 'legacy_enum bigint(20) unsigned NULL', $tables['wp_servsys_equipment'] );
		$this->assertStringContainsString( 'refrigerant_type varchar(50) NULL', $tables['wp_servsys_equipment'] );
		$this->assertStringNotContainsString( 'servsync', $tables['wp_servsys_equipment'] );
	}
}
