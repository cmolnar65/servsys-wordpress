<?php
use PHPUnit\Framework\TestCase;
final class Phase7SiteServiceCallsTest extends TestCase {
	public function test_site_workspace_replaces_activity_placeholder_with_service_calls(): void {
		$source=file_get_contents(dirname(__DIR__,2).'/src/Admin/CustomersPage.php');
		$this->assertStringContainsString('<hr><h2>Service Calls</h2>',$source);
		$this->assertStringNotContainsString('No site activity modules are available yet.',$source);
		$this->assertStringContainsString('Capabilities::VIEW_SERVICE_CALLS',$source);
		$this->assertStringContainsString('Capabilities::CREATE_SERVICE_CALLS',$source);
	}
	public function test_add_service_call_preserves_locked_site_context(): void {
		$source=file_get_contents(dirname(__DIR__,2).'/src/Admin/CustomersPage.php');
		$this->assertStringContainsString('page=servsys-service-calls&action=edit&source=site&customer_id=',$source);
		$this->assertStringContainsString("' . $customer_id . '&site_id=' . $site_id",$source);
	}
	public function test_recent_calls_are_scoped_to_exact_customer_and_site(): void {
		$source=file_get_contents(dirname(__DIR__,2).'/src/ServiceCalls/ServiceCallRepository.php');
		$this->assertStringContainsString('WHERE customer_id = %d AND site_id = %d',$source);
		$this->assertStringContainsString('ORDER BY service_date DESC, id DESC LIMIT %d',$source);
	}
}
