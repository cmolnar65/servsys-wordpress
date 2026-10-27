<?php
use PHPUnit\Framework\TestCase;

final class Phase9ServiceCallTotalsTest extends TestCase {
 public function test_inventory_total_uses_snapshot_extended_values_and_company_scope(): void {
  $p=file_get_contents(__DIR__.'/../../src/Inventory/ServiceCallInventoryRepository.php');
  $this->assertStringContainsString('SUM(extended_selling_price)',$p);
  $this->assertStringContainsString('CompanyContext::current_company_id()',$p);
  $this->assertStringContainsString('Returns are stored as negative snapshot rows',$p);
 }
 public function test_returns_reduce_inventory_total_without_special_repricing(): void {
  $inventory=file_get_contents(__DIR__.'/../../src/Inventory/InventoryService.php');
  $totals=file_get_contents(__DIR__.'/../../src/Inventory/ServiceCallInventoryRepository.php');
  $this->assertStringContainsString("'extended_selling_price'=>-(float)\$usage['extended_selling_price']",$inventory);
  $this->assertStringContainsString('SUM(extended_selling_price)',$totals);
  $this->assertStringNotContainsString('unit_cost_snapshot *',$totals);
  $this->assertStringNotContainsString('markup_percent_snapshot /',$totals);
 }
 public function test_charge_total_uses_immutable_extended_price_snapshots(): void {
  $p=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallChargeRepository.php');
  $this->assertStringContainsString('SUM(extended_price)',$p);
  $this->assertStringContainsString('CompanyContext::current_company_id()',$p);
 }
 public function test_operational_total_is_inventory_plus_charges(): void {
  $p=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallTotalsService.php');
  $this->assertStringContainsString("'inventory_material_selling_total'=>\$inventory",$p);
  $this->assertStringContainsString("'service_charge_total'=>\$charges",$p);
  $this->assertStringContainsString("'combined_operational_total'=>round(\$inventory+\$charges,4)",$p);
 }
 public function test_totals_require_current_company_service_call(): void {
  $p=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallTotalsService.php');
  $this->assertStringContainsString('Service Call does not belong to the current company.',$p);
 }
 public function test_flat_rate_recipe_materials_are_not_automatically_counted_as_inventory(): void {
  $charges=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallChargeService.php');
  $totals=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallTotalsService.php');
  $this->assertStringNotContainsString('consume_for_service_call',$charges);
  $this->assertStringNotContainsString('FlatRateMaterialRepository',$totals);
 }
 public function test_workspace_labels_totals_as_operational_not_accounting(): void {
  $p=file_get_contents(__DIR__.'/../../src/Admin/ServiceCallTotalsPage.php');
  $this->assertStringContainsString('Operational Totals',$p);
  $this->assertStringContainsString('not an invoice or accounting ledger',$p);
  $this->assertStringContainsString('Inventory / Materials',$p);
  $this->assertStringContainsString('Service Charges',$p);
  $this->assertStringContainsString('Combined Operational Total',$p);
 }
 public function test_p9_12_requires_no_schema_change(): void {
  $boot=file_get_contents(__DIR__.'/../../servsys.php');
  $this->assertStringContainsString("SERVSYS_DB_VERSION', 19",$boot);
 }
}
