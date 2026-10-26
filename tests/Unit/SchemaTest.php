<?php

use PHPUnit\Framework\TestCase;
use ServSys\Database\Schema;

final class SchemaTest extends TestCase {
	public function test_phase_seven_tables_use_wordpress_prefix(): void {
		$wpdb = new class {
			public $prefix = 'test_';
			public function get_charset_collate(): string {
				return 'DEFAULT CHARACTER SET utf8mb4';
			}
		};

		$tables = Schema::tables( $wpdb );

		$this->assertSame(
			array(
				'test_servsys_equipment_types',
				'test_servsys_customer_types',
				'test_servsys_customers',
				'test_servsys_sites',
				'test_servsys_customer_notes',
				'test_servsys_equipment',
				'test_servsys_service_calls',
				'test_servsys_service_call_equipment',
				'test_servsys_service_call_notes',
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
		$this->assertStringContainsString( 'slug varchar(100) NOT NULL', $tables['wp_servsys_equipment_types'] );
		$this->assertStringContainsString( 'site_id bigint(20) unsigned NOT NULL', $tables['wp_servsys_equipment'] );
		$this->assertStringContainsString( 'legacy_enum bigint(20) unsigned NULL', $tables['wp_servsys_equipment'] );
		$this->assertStringContainsString( 'equipment_type_id bigint(20) unsigned NULL', $tables['wp_servsys_equipment'] );
		$this->assertStringContainsString( 'refrigerant_type varchar(50) NULL', $tables['wp_servsys_equipment'] );
		$this->assertStringNotContainsString( 'servsync', $tables['wp_servsys_equipment'] );
		$this->assertStringContainsString( 'customer_id bigint(20) unsigned NOT NULL', $tables['wp_servsys_service_calls'] );
		$this->assertStringContainsString( 'site_id bigint(20) unsigned NOT NULL', $tables['wp_servsys_service_calls'] );
		$this->assertStringContainsString( 'legacy_crecnum bigint(20) unsigned NULL', $tables['wp_servsys_service_calls'] );
		$this->assertStringContainsString( 'legacy_callslip varchar(100) NULL', $tables['wp_servsys_service_calls'] );
		$this->assertStringContainsString( 'call_number varchar(50) NULL', $tables['wp_servsys_service_calls'] );
		$this->assertStringContainsString( 'technician_user_id bigint(20) unsigned NULL', $tables['wp_servsys_service_calls'] );
		$this->assertStringNotContainsString( 'servsync', $tables['wp_servsys_service_calls'] );
		$this->assertStringContainsString( 'UNIQUE KEY service_call_equipment (service_call_id,equipment_id)', $tables['wp_servsys_service_call_equipment'] );
		$this->assertStringContainsString( 'service_call_id bigint(20) unsigned NOT NULL', $tables['wp_servsys_service_call_notes'] );
	}

	public function test_phase_seven_call_number_schema_supports_year_and_record_id_format(): void {
		$wpdb = new class {
			public $prefix = 'wp_';
			public function get_charset_collate(): string {
				return 'DEFAULT CHARACTER SET utf8mb4';
			}
		};

		$table = Schema::tables( $wpdb )['wp_servsys_service_calls'];
		$this->assertStringContainsString( 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT', $table );
		$this->assertStringContainsString( 'call_number varchar(50) NULL', $table );
		$this->assertStringContainsString( 'UNIQUE KEY call_number (call_number)', $table );
	}
}
