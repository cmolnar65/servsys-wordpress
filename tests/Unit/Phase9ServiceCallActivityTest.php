<?php
use PHPUnit\Framework\TestCase;
use ServSys\Users\Capabilities;

final class Phase9ServiceCallActivityTest extends TestCase {
 public function test_activity_schema_is_versioned_and_preserves_odometer_mileage(): void {
  $boot=file_get_contents(__DIR__.'/../../servsys.php');
  $schema=file_get_contents(__DIR__.'/../../src/Database/Schema.php');
  $this->assertStringContainsString("SERVSYS_DB_VERSION', 17",$boot);
  $this->assertStringContainsString('servsys_service_call_activity',$schema);
  foreach(array('technician_user_id','dispatched_at datetime NULL','arrived_at datetime NULL','work_started_at datetime NULL','work_ended_at datetime NULL','odometer_start decimal(13,3) NULL','odometer_end decimal(13,3) NULL','travel_miles decimal(13,3) NULL','odometer_source varchar(20)','truck_number_snapshot varchar(50)','legacy_tsid bigint(20) unsigned NULL') as $field){$this->assertStringContainsString($field,$schema);}
 }
 public function test_activity_repository_is_company_scoped_and_exposes_open_work_check(): void {
  $p=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallActivityRepository.php');
  $this->assertStringContainsString('CompanyContext::current_company_id()',$p);
  $this->assertStringContainsString('has_open_activity',$p);
  $this->assertStringContainsString('work_started_at IS NOT NULL AND work_ended_at IS NULL',$p);
 }
 public function test_activity_requires_valid_company_technician(): void {
  $p=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallActivityService.php');
  $this->assertStringContainsString('Authorization::can_access_company',$p);
  $this->assertStringContainsString('Technician is not an active user of this company.',$p);
 }
 public function test_activity_timestamps_must_be_chronological(): void {
  $p=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallActivityService.php');
  $this->assertStringContainsString("array('dispatched_at','arrived_at','work_started_at','work_ended_at')",$p);
  $this->assertStringContainsString('Technician activity timestamps must be chronological.',$p);
 }
 public function test_odometer_readings_are_validated_and_mileage_is_snapshotted(): void {
  $p=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallActivityService.php');
  $this->assertStringContainsString('Starting odometer reading is required when an ending reading is entered.',$p);
  $this->assertStringContainsString('Ending odometer reading cannot be less than the starting reading.',$p);
  $this->assertStringContainsString("'travel_miles'=>(null!==\$odo_start&&null!==\$odo_end)?round(\$odo_end-\$odo_start,3):null",$p);
 }
 public function test_odometer_source_is_future_extensible_without_inventing_location_data(): void {
  $p=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallActivityService.php');
  $this->assertStringContainsString("array('manual','location','gps','legacy_import')",$p);
  $admin=file_get_contents(__DIR__.'/../../src/Admin/ServiceCallActivityPage.php');
  $this->assertStringContainsString('Odometer source is manual today and can support location/GPS sources later.',$admin);
 }
 public function test_truck_number_is_snapshotted_for_history(): void {
  $p=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallActivityService.php');
  $this->assertStringContainsString("'truck_number_snapshot'=>\$truck",$p);
  $this->assertStringContainsString("\$existing['truck_number_snapshot']?:\$truck",$p);
 }
 public function test_activity_permissions_are_company_scoped(): void {
  $this->assertContains(Capabilities::VIEW_SERVICE_CALL_ACTIVITY,Capabilities::company_capabilities());
  $this->assertContains(Capabilities::MANAGE_SERVICE_CALL_ACTIVITY,Capabilities::company_capabilities());
  $this->assertContains(Capabilities::VIEW_SERVICE_CALL_ACTIVITY,Capabilities::all());
  $this->assertContains(Capabilities::MANAGE_SERVICE_CALL_ACTIVITY,Capabilities::all());
 }
 public function test_admin_requires_nonce_and_has_no_delete_workflow(): void {
  $p=file_get_contents(__DIR__.'/../../src/Admin/ServiceCallActivityPage.php');
  $this->assertStringContainsString("check_admin_referer('servsys_save_service_call_activity_'.\$service_call_id.'_'.\$activity_id)",$p);
  $this->assertStringContainsString('Capabilities::MANAGE_SERVICE_CALL_ACTIVITY',$p);
  $this->assertStringNotContainsString('delete_activity',$p);
  $this->assertStringNotContainsString('admin_post_servsys_delete_service_call_activity',$p);
 }
 public function test_installer_upgrade_remains_in_place(): void {
  $p=file_get_contents(__DIR__.'/../../src/Database/Installer.php');
  $this->assertStringContainsString('if ( $installed < SERVSYS_DB_VERSION )',$p);
  $this->assertStringContainsString('dbDelta( $sql )',$p);
 }
}
