<?php
use PHPUnit\Framework\TestCase;
final class Phase7ServiceCallEquipmentUiTest extends TestCase {
	public function test_equipment_selection_is_site_scoped_and_submitted_to_domain_service(): void {
		$source=file_get_contents(dirname(__DIR__,2).'/src/Admin/ServiceCallsPage.php');
		$this->assertStringContainsString('for_site( $site_id, true )',$source);
		$this->assertStringContainsString('name="equipment_ids[]"',$source);
		$this->assertStringContainsString('Capabilities::CREATE_EQUIPMENT',$source);
		$this->assertStringContainsString('Add New Equipment',$source);
	}
	public function test_add_new_equipment_preserves_unsaved_service_call_state(): void {
		$source=file_get_contents(dirname(__DIR__,2).'/src/Admin/ServiceCallsPage.php');
		$this->assertStringContainsString('set_transient( \'servsys_sc_draft_\' . get_current_user_id()',$source);
		$this->assertStringContainsString('get_transient( \'servsys_sc_draft_\' . get_current_user_id()',$source);
		$this->assertStringContainsString('30 * MINUTE_IN_SECONDS',$source);
		$this->assertStringContainsString("'source' => 'service-call'",$source);
	}
	public function test_equipment_save_can_return_to_preserved_service_call_form(): void {
		$source=file_get_contents(dirname(__DIR__,2).'/src/Admin/EquipmentPage.php');
		$this->assertStringContainsString("'service-call' === $source",$source);
		$this->assertStringContainsString('wp_validate_redirect',$source);
		$this->assertStringContainsString('wp_safe_redirect( $return_to )',$source);
	}
}
