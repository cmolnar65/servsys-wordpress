<?php
use PHPUnit\Framework\TestCase;

final class Phase9FullRegressionUpgradeTest extends TestCase {
 private function current_db_version(): int {
  $boot=file_get_contents(__DIR__.'/../../servsys.php');
  preg_match("/SERVSYS_DB_VERSION',\\s*(\\d+)/",$boot,$m);
  return (int)($m[1]??0);
 }
 public function test_phase_9_current_schema_version_is_19(): void {
  $this->assertSame(19,$this->current_db_version());
 }
 public function test_upgrade_runs_in_place_without_reinstall_or_destructive_reset(): void {
  $installer=file_get_contents(__DIR__.'/../../src/Database/Installer.php');
  $migrator=file_get_contents(__DIR__.'/../../src/Database/Migrator.php');
  $this->assertStringContainsString('if ( $installed < SERVSYS_DB_VERSION )',$installer);
  $this->assertStringContainsString('dbDelta( $sql )',$installer);
  $this->assertStringContainsString("update_option( 'servsys_db_version', SERVSYS_DB_VERSION, false )",$installer);
  $this->assertStringContainsString('Installer::maybe_upgrade();',$migrator);
  foreach(array('DROP TABLE','TRUNCATE TABLE','DELETE FROM wp_servsys','uninstall.php') as $destructive){$this->assertStringNotContainsString($destructive,$installer);}
 }
 public function test_phase_9_schema_contains_every_operational_table_added_during_phase(): void {
  $schema=file_get_contents(__DIR__.'/../../src/Database/Schema.php');
  foreach(array(
   'servsys_flat_rate_config','servsys_flat_rate_categories','servsys_flat_rate_items','servsys_flat_rate_item_materials',
   'servsys_pm_plans','servsys_pm_plan_terms','servsys_pm_agreements','servsys_pm_agreement_equipment','servsys_pm_scheduled_visits',
   'servsys_service_call_charges','servsys_service_call_activity','servsys_completion_codes','servsys_service_call_completion_codes'
  ) as $table){$this->assertStringContainsString($table,$schema,$table);}
  foreach(array('completed_at datetime NULL','completed_by bigint(20) unsigned NULL','reopened_at datetime NULL','reopened_by bigint(20) unsigned NULL') as $field){$this->assertStringContainsString($field,$schema,$field);}
 }
 public function test_existing_pre_phase_9_operational_tables_remain_in_schema(): void {
  $schema=file_get_contents(__DIR__.'/../../src/Database/Schema.php');
  foreach(array('servsys_customers','servsys_sites','servsys_customer_notes','servsys_equipment','servsys_service_calls','servsys_service_call_equipment','servsys_service_call_notes','servsys_inventory_locations','servsys_inventory_items','servsys_inventory_transactions','servsys_service_call_inventory') as $table){$this->assertStringContainsString($table,$schema,$table);}
 }
 public function test_existing_business_data_is_not_replaced_by_phase_9_upgrade(): void {
  $installer=file_get_contents(__DIR__.'/../../src/Database/Installer.php');
  $schema=file_get_contents(__DIR__.'/../../src/Database/Schema.php');
  $this->assertStringNotContainsString('DROP TABLE',$installer);
  $this->assertStringNotContainsString('TRUNCATE',$installer);
  $this->assertStringContainsString('dbDelta( $sql )',$installer);
  $this->assertStringContainsString('legacy_custnum',$schema);
  $this->assertStringContainsString('legacy_enum',$schema);
  $this->assertStringContainsString('legacy_crecnum',$schema);
 }
 public function test_phase_9_regression_suites_exist_for_all_units(): void {
  foreach(array(
   'Phase9FlatRateSchemaTest.php','Phase9FlatRatePricingTest.php','Phase9FlatRateAdminTest.php','Phase9PMPriceBookTest.php',
   'Phase9PMAgreementsTest.php','Phase9PMScheduledVisitsTest.php','Phase9LegacyFlatRatePMMigrationTest.php',
   'Phase9ServiceCallChargesTest.php','Phase9ServiceCallActivityTest.php','Phase9CompletionCodesTest.php',
   'Phase9ServiceCallCloseoutTest.php','Phase9ServiceCallTotalsTest.php','Phase9CompanyIsolationAuthorizationAuditTest.php'
  ) as $file){$this->assertFileExists(__DIR__.'/'.$file,$file);}
 }
 public function test_pre_phase_9_regression_suites_remain_present(): void {
  foreach(array('Phase7ServiceCallTest.php','Phase7ServiceCallNotesTest.php','Phase7FollowUpStatusTest.php','Phase6EquipmentTest.php','Phase8InventoryDomainTest.php','Phase8ServiceCallInventoryTest.php','CompanyPermissionsTest.php') as $file){$this->assertFileExists(__DIR__.'/'.$file,$file);}
 }
 public function test_phase_9_does_not_change_development_plugin_version(): void {
  $boot=file_get_contents(__DIR__.'/../../servsys.php');
  $this->assertStringContainsString("SERVSYS_VERSION', '0.1.0-dev'",$boot);
 }
}
