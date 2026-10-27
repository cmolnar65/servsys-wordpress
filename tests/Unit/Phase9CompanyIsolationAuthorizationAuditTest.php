<?php
use PHPUnit\Framework\TestCase;
use ServSys\Users\Capabilities;

final class Phase9CompanyIsolationAuthorizationAuditTest extends TestCase {
 public function test_flat_rate_material_cannot_reference_other_company_inventory(): void {
  $p=file_get_contents(__DIR__.'/../../src/FlatRate/FlatRateService.php');
  $this->assertStringContainsString('Flat Rate material inventory item does not belong to the current company.',$p);
  $this->assertStringContainsString('$this->inventory->find($inventory_id)',$p);
 }
 public function test_pm_agreement_requires_current_company_customer_site_and_plan(): void {
  $p=file_get_contents(__DIR__.'/../../src/PreventiveMaintenance/PMAgreementService.php');
  $this->assertStringContainsString('Customer does not belong to the current company.',$p);
  $this->assertStringContainsString('Site must belong to the selected Customer and current company.',$p);
  $this->assertStringContainsString('PM Plan does not belong to the current company.',$p);
 }
 public function test_pm_agreement_equipment_must_match_agreement_site_and_customer(): void {
  $p=file_get_contents(__DIR__.'/../../src/PreventiveMaintenance/PMAgreementService.php');
  $this->assertStringContainsString('Covered Equipment must belong to the Agreement Site and Customer.',$p);
  $repo=file_get_contents(__DIR__.'/../../src/Equipment/EquipmentRepository.php');
  $this->assertStringContainsString('CompanyContext::current_company_id()',$repo);
 }
 public function test_scheduled_visit_is_company_scoped_and_call_matches_agreement(): void {
  $p=file_get_contents(__DIR__.'/../../src/PreventiveMaintenance/PMScheduledVisitService.php');
  $r=file_get_contents(__DIR__.'/../../src/PreventiveMaintenance/PMScheduledVisitRepository.php');
  $this->assertStringContainsString('PM Agreement does not belong to the current company.',$p);
  $this->assertStringContainsString('Service Call must belong to the Agreement Customer and Site.',$p);
  $this->assertStringContainsString('CompanyContext::current_company_id()',$r);
 }
 public function test_service_charge_requires_current_company_call_and_flat_rate_item(): void {
  $p=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallChargeService.php');
  $this->assertStringContainsString('Service Call does not belong to the current company.',$p);
  $this->assertStringContainsString('Flat Rate item does not belong to this Service Call company.',$p);
 }
 public function test_technician_requires_actual_active_company_membership_not_merely_admin_access(): void {
  $activity=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallActivityService.php');
  $calls=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallService.php');
  $admin=file_get_contents(__DIR__.'/../../src/Admin/ServiceCallsPage.php');
  $this->assertStringContainsString('$this->members->find((int)$call[\'company_id\'],$technician)',$activity);
  $this->assertStringContainsString("empty(\$membership['active'])",$activity);
  $this->assertStringContainsString('( new CompanyUserRepository() )->find( $company_id, $technician_user_id )',$calls);
  $this->assertStringNotContainsString("get_users( array( 'role' => 'administrator'",$admin);
 }
 public function test_completion_code_and_service_call_are_same_current_company(): void {
  $p=file_get_contents(__DIR__.'/../../src/ServiceCalls/CompletionCodeService.php');
  $this->assertStringContainsString('Service Call does not belong to the current company.',$p);
  $this->assertStringContainsString('Completion Code does not belong to this Service Call company.',$p);
  $r=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallCompletionRepository.php');
  $this->assertStringContainsString('CompanyContext::current_company_id()',$r);
 }
 public function test_wordpress_administrator_is_unrestricted_system_owner(): void {
  $p=file_get_contents(__DIR__.'/../../src/Users/Authorization.php');
  $this->assertStringContainsString("in_array( 'administrator',(array)\$user->roles,true )",$p);
  $this->assertStringContainsString('if ( self::is_wordpress_admin($user_id) ) { return true; }',$p);
  $this->assertStringContainsString('$allcaps[$capability]=true; continue;',$p);
 }
 public function test_company_admin_gets_all_company_operational_permissions_but_not_company_creation(): void {
  $auth=file_get_contents(__DIR__.'/../../src/Users/Authorization.php');
  $this->assertStringContainsString('if ( ! empty($m[\'is_company_admin\']) ) { return in_array($capability,Capabilities::company_capabilities(),true); }',$auth);
  $this->assertNotContains(Capabilities::MANAGE_COMPANIES,Capabilities::company_capabilities());
  $this->assertContains(Capabilities::MANAGE_COMPANY_USERS,Capabilities::company_capabilities());
 }
 public function test_regular_user_permissions_are_membership_specific(): void {
  $p=file_get_contents(__DIR__.'/../../src/Users/Authorization.php');
  $this->assertStringContainsString('(new CompanyUserRepository())->find($company_id,$user_id)',$p);
  $this->assertStringContainsString("json_decode((string)\$m['permissions'],true)",$p);
  $this->assertStringContainsString('in_array($capability,$permissions,true)',$p);
 }
 public function test_non_admin_can_never_manage_companies(): void {
  $p=file_get_contents(__DIR__.'/../../src/Users/Authorization.php');
  $this->assertStringContainsString('$allcaps[Capabilities::MANAGE_COMPANIES]=false',$p);
 }
 public function test_p9_13_has_no_schema_change(): void {
  $boot=file_get_contents(__DIR__.'/../../servsys.php');
  $this->assertStringContainsString("SERVSYS_DB_VERSION', 19",$boot);
 }
}
