<?php

use PHPUnit\Framework\TestCase;

final class Phase8ServiceCallInventoryTest extends TestCase {
	public function test_service_call_inventory_usage_is_company_scoped(): void {
		$repository=file_get_contents(__DIR__ . '/../../src/Inventory/ServiceCallInventoryRepository.php');
		$service=file_get_contents(__DIR__ . '/../../src/Inventory/InventoryService.php');
		$this->assertStringContainsString("['company_id']=CompanyContext::current_company_id()",str_replace(' ','',$repository));
		$this->assertStringContainsString('Service Call does not belong to the current company.',$service);
	}

	public function test_usage_deducts_stock_and_writes_ledger_and_snapshot_atomically(): void {
		$service=file_get_contents(__DIR__ . '/../../src/Inventory/InventoryService.php');
		$start=strpos($service,'public function consume_for_service_call');
		$end=strpos($service,'public function return_service_call_usage',$start);
		$method=substr($service,$start,$end-$start);
		$this->assertStringContainsString("'START TRANSACTION'",$method);
		$this->assertStringContainsString("'service_call_usage'",$method);
		$this->assertStringContainsString("'quantity_change'=>-\$quantity",$method);
		$this->assertStringContainsString("'unit_cost_snapshot'=>\$unit_cost",$method);
		$this->assertStringContainsString("'markup_percent_snapshot'=>\$markup",$method);
		$this->assertStringContainsString("'unit_selling_price_snapshot'=>\$unit_selling",$method);
		$this->assertStringContainsString("'extended_selling_price'=>\$extended",$method);
		$this->assertStringContainsString("'COMMIT'",$method);
		$this->assertStringContainsString("'ROLLBACK'",$method);
	}

	public function test_usage_requires_active_item_location_positive_quantity_and_available_stock(): void {
		$service=file_get_contents(__DIR__ . '/../../src/Inventory/InventoryService.php');
		$this->assertStringContainsString('Inventory usage quantity must be greater than zero.',$service);
		$this->assertStringContainsString('Service Call inventory usage requires an active item and active location.',$service);
		$this->assertStringContainsString('Insufficient inventory at the selected location.',$service);
	}

	public function test_service_call_inventory_return_is_a_linked_reversal_not_delete(): void {
		$service=file_get_contents(__DIR__ . '/../../src/Inventory/InventoryService.php');
		$repository=file_get_contents(__DIR__ . '/../../src/Inventory/ServiceCallInventoryRepository.php');
		$this->assertStringContainsString("'service_call_return'",$service);
		$this->assertStringContainsString("'related_transaction_id'=>(int)\$usage['inventory_transaction_id']",$service);
		$this->assertStringContainsString("'quantity'=>-\$quantity",$service);
		$this->assertStringContainsString('has_related_transaction',$service);
		$this->assertStringNotContainsString('DELETE FROM',$repository);
	}

	public function test_service_call_workspace_uses_inventory_permission_and_preserves_free_text_notes(): void {
		$page=file_get_contents(__DIR__ . '/../../src/Admin/ServiceCallsPage.php');
		$inventory_page=file_get_contents(__DIR__ . '/../../src/Admin/ServiceCallInventoryPage.php');
		$this->assertStringContainsString('Capabilities::USE_INVENTORY',$inventory_page);
		$this->assertStringContainsString('Add Inventory Used',$inventory_page);
		$this->assertStringContainsString('Return / Reverse',$inventory_page);
		$this->assertStringContainsString('Additional Parts / Materials Notes',$page);
		$this->assertStringContainsString("\$call['parts_notes']",$page);
	}

	public function test_pricing_uses_four_decimal_storage_precision(): void {
		$service=file_get_contents(__DIR__ . '/../../src/Inventory/InventoryService.php');
		$this->assertStringContainsString('return round( $unit_cost * ( 1 + ( $markup_percent / 100 ) ), 4 );',$service);
	}

	public function test_p8_8_uses_existing_schema_version_10(): void {
		$bootstrap=file_get_contents(__DIR__ . '/../../servsys.php');
		$this->assertStringContainsString("SERVSYS_DB_VERSION', 10",$bootstrap);
	}
}
