<?php

use PHPUnit\Framework\TestCase;

final class Phase8CloseoutTest extends TestCase {
	public function test_generic_adjustment_cannot_bypass_dedicated_transfer_or_service_call_workflows(): void {
		$service=file_get_contents(__DIR__ . '/../../src/Inventory/InventoryService.php');
		$this->assertStringContainsString("\$allowed=array('initial','receipt','adjustment');",$service);
		$this->assertStringContainsString('public function transfer_stock',$service);
		$this->assertStringContainsString('public function consume_for_service_call',$service);
		$this->assertStringContainsString('public function return_service_call_usage',$service);
	}

	public function test_transfer_transactions_are_symmetrically_linked(): void {
		$service=file_get_contents(__DIR__ . '/../../src/Inventory/InventoryService.php');
		$transactions=file_get_contents(__DIR__ . '/../../src/Inventory/InventoryTransactionRepository.php');
		$this->assertStringContainsString("'related_transaction_id'=>\$out",$service);
		$this->assertStringContainsString('set_related_transaction( $out, $in )',$service);
		$this->assertStringContainsString('company_id',$transactions);
	}

	public function test_database_upgrade_order_preserves_existing_installation_before_version_update(): void {
		$installer=file_get_contents(__DIR__ . '/../../src/Database/Installer.php');
		$this->assertLessThan(strpos($installer,"update_option( 'servsys_db_version'"),strpos($installer,'foreach ( Schema::tables( $wpdb ) as $sql )'));
		$this->assertLessThan(strpos($installer,"update_option( 'servsys_db_version'"),strpos($installer,'self::seed_installation_company( $wpdb )'));
		$this->assertLessThan(strpos($installer,"update_option( 'servsys_db_version'"),strpos($installer,'self::backfill_company_ownership( $wpdb )'));
	}

	public function test_phase_8_schema_version_is_10(): void {
		$bootstrap=file_get_contents(__DIR__ . '/../../servsys.php');
		$this->assertStringContainsString("SERVSYS_DB_VERSION', 10",$bootstrap);
	}
}
