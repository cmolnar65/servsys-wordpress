<?php
use PHPUnit\Framework\TestCase;
final class Phase7FollowUpStatusTest extends TestCase {
	public function test_status_and_follow_up_remain_independent(): void {
		$service=file_get_contents(dirname(__DIR__,2).'/src/ServiceCalls/ServiceCallService.php');
		$this->assertStringContainsString("array( 'open', 'completed', 'cancelled' )",$service);
		$this->assertStringNotContainsString("'completed' === \$status",$service);
		$this->assertStringContainsString("if ( ! \$data['follow_up_required'] )",$service);
	}
	public function test_service_call_list_can_filter_outstanding_follow_up(): void {
		$repo=file_get_contents(dirname(__DIR__,2).'/src/ServiceCalls/ServiceCallRepository.php');
		$page=file_get_contents(dirname(__DIR__,2).'/src/Admin/ServiceCallsPage.php');
		$this->assertStringContainsString("'required' === \$follow_up",$repo);
		$this->assertStringContainsString('sc.follow_up_required = 1',$repo);
		$this->assertStringContainsString('Follow-up required</option>',$page);
	}
	public function test_ui_explains_completed_calls_may_require_follow_up(): void {
		$page=file_get_contents(dirname(__DIR__,2).'/src/Admin/ServiceCallsPage.php');
		$this->assertStringContainsString('Follow-up is independent of Service Call status. A Completed call may still require follow-up.',$page);
		$this->assertStringContainsString('Required — no date set',$page);
	}
}
