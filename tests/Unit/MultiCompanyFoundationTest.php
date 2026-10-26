<?php

use PHPUnit\Framework\TestCase;

final class MultiCompanyFoundationTest extends TestCase {
	public function test_upgrade_creates_company_one_and_backfills_existing_records(): void {
		$schema=file_get_contents( __DIR__ . '/../../src/Database/Schema.php' );
		$installer=file_get_contents( __DIR__ . '/../../src/Database/Installer.php' );
		$bootstrap=file_get_contents( __DIR__ . '/../../servsys.php' );
		$this->assertStringContainsString( 'servsys_companies', $schema );
		$this->assertStringContainsString( 'company_id bigint(20) unsigned NOT NULL DEFAULT 1', $schema );
		$this->assertStringContainsString( "'id' => 1", $installer );
		$this->assertStringContainsString( "'name' => 'Installation Company'", $installer );
		$this->assertStringContainsString( 'backfill_company_ownership', $installer );
		$this->assertStringContainsString( "SERVSYS_DB_VERSION', 8", $bootstrap );
	}

	public function test_company_configuration_reflects_legacy_decisions(): void {
		$schema=file_get_contents( __DIR__ . '/../../src/Database/Schema.php' );
		$this->assertStringContainsString( 'tagline text NULL', $schema );
		$this->assertStringContainsString( 'use_preprinted_letterhead', $schema );
		$this->assertStringContainsString( 'fiscal_year_end_month', $schema );
		$this->assertStringContainsString( 'customer_portal_enabled', $schema );
		$this->assertStringNotContainsString( 'imagehight', $schema );
		$this->assertStringNotContainsString( 'laptopretentiontime', $schema );
		$this->assertStringNotContainsString( 'reportemail', $schema );
	}

	public function test_core_operational_repositories_are_company_scoped(): void {
		foreach ( array( 'Customers/CustomerRepository.php','Sites/SiteRepository.php','Equipment/EquipmentRepository.php','ServiceCalls/ServiceCallRepository.php' ) as $path ) {
			$source=file_get_contents( __DIR__ . '/../../src/' . $path );
			$this->assertStringContainsString( 'CompanyContext::current_company_id()', $source, $path );
		}
	}
}
