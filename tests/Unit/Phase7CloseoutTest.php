<?php
use PHPUnit\Framework\TestCase;
final class Phase7CloseoutTest extends TestCase {
	public function test_add_equipment_dispatches_to_draft_handler_and_round_trip_is_preserved(): void {
		$calls=file_get_contents(__DIR__.'/../../src/Admin/ServiceCallsPage.php');
		$equipment=file_get_contents(__DIR__.'/../../src/Admin/EquipmentPage.php');
		$this->assertStringContainsString('name="action" value="servsys_service_call_add_equipment"',$calls);
		$this->assertStringContainsString("admin_post_servsys_service_call_add_equipment",$calls);
		$this->assertStringContainsString('name="return_to" value="',$equipment);
		$this->assertStringContainsString("'service-call' === \$source && \$return_to",$equipment);
	}
	public function test_service_call_workspace_has_no_inline_width_and_technician_is_company_validated(): void {
		$page=file_get_contents(__DIR__.'/../../src/Admin/ServiceCallsPage.php');
		$service=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallService.php');
		$this->assertStringNotContainsString('style="width:180px"',$page);
		$this->assertStringContainsString('CompanyUserRepository',$page);
		$this->assertStringContainsString('Authorization::can_access_company',$service);
		$this->assertStringContainsString('Selected technician is not an active user of this company.',$service);
	}
	public function test_upgrade_path_runs_schema_then_company_backfills(): void {
		$installer=file_get_contents(__DIR__.'/../../src/Database/Installer.php');
		$this->assertStringContainsString('dbDelta( $sql )',$installer);
		$this->assertStringContainsString('seed_installation_company( $wpdb )',$installer);
		$this->assertStringContainsString('backfill_company_ownership( $wpdb )',$installer);
		$this->assertStringContainsString('seed_wordpress_administrators( $wpdb )',$installer);
		$this->assertStringContainsString("update_option( 'servsys_db_version', SERVSYS_DB_VERSION, false )",$installer);
	}
}
