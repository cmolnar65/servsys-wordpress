<?php

use PHPUnit\Framework\TestCase;

final class Phase8InventoryReportingTest extends TestCase {
	public function test_reporting_queries_are_company_scoped_and_filterable(): void {
		$transactions=file_get_contents(__DIR__ . '/../../src/Inventory/InventoryTransactionRepository.php');
		$this->assertStringContainsString('CompanyContext::current_company_id()',$transactions);
		$this->assertStringContainsString("inventory_item_id=%d",$transactions);
		$this->assertStringContainsString("inventory_location_id=%d",$transactions);
		$this->assertStringContainsString("transaction_type=%s",$transactions);
		$this->assertStringContainsString("transaction_at >= %s",$transactions);
		$this->assertStringContainsString("transaction_at <= %s",$transactions);
	}

	public function test_filtered_movement_summary_uses_sql_aggregation_not_truncated_rows(): void {
		$transactions=file_get_contents(__DIR__ . '/../../src/Inventory/InventoryTransactionRepository.php');
		$this->assertStringContainsString('SUM(quantity_change) AS quantity_change',$transactions);
		$this->assertStringContainsString('GROUP BY transaction_type',$transactions);
	}

	public function test_current_stock_and_reconciliation_reports_exist(): void {
		$balances=file_get_contents(__DIR__ . '/../../src/Inventory/InventoryBalanceRepository.php');
		$transactions=file_get_contents(__DIR__ . '/../../src/Inventory/InventoryTransactionRepository.php');
		$page=file_get_contents(__DIR__ . '/../../src/Admin/InventoryReportsPage.php');
		$this->assertStringContainsString('report_rows',$balances);
		$this->assertStringContainsString('totals_by_item',$balances);
		$this->assertStringContainsString('ledger_totals_by_item_location',$transactions);
		$this->assertStringContainsString('Balance Reconciliation',$page);
		$this->assertStringContainsString("'OK':'Mismatch'",$page);
	}

	public function test_reporting_page_requires_view_inventory_and_has_filters(): void {
		$page=file_get_contents(__DIR__ . '/../../src/Admin/InventoryReportsPage.php');
		$this->assertStringContainsString('Capabilities::VIEW_INVENTORY',$page);
		$this->assertStringContainsString('Inventory History &amp; Reporting',$page);
		$this->assertStringContainsString('name="item_id"',$page);
		$this->assertStringContainsString('name="location_id"',$page);
		$this->assertStringContainsString('name="transaction_type"',$page);
		$this->assertStringContainsString('name="date_from"',$page);
		$this->assertStringContainsString('name="date_to"',$page);
	}

	public function test_transaction_history_includes_service_call_links_and_user_context(): void {
		$page=file_get_contents(__DIR__ . '/../../src/Admin/InventoryReportsPage.php');
		$this->assertStringContainsString('service_call_id',$page);
		$this->assertStringContainsString('get_userdata',$page);
		$this->assertStringContainsString('Inventory Transaction History',$page);
	}

	public function test_configuration_and_plugin_register_inventory_reporting(): void {
		$config=file_get_contents(__DIR__ . '/../../src/Admin/ConfigurationPage.php');
		$plugin=file_get_contents(__DIR__ . '/../../src/Plugin.php');
		$this->assertStringContainsString('servsys-inventory-reports',$config);
		$this->assertStringContainsString('Inventory History & Reporting',$config);
		$this->assertStringContainsString('use ServSys\\Admin\\InventoryReportsPage;',$plugin);
		$this->assertStringContainsString('( new InventoryReportsPage() )->register();',$plugin);
	}

	public function test_p8_9_does_not_change_database_version(): void {
		$bootstrap=file_get_contents(__DIR__ . '/../../servsys.php');
		$this->assertStringContainsString("SERVSYS_DB_VERSION', 10",$bootstrap);
	}
}
