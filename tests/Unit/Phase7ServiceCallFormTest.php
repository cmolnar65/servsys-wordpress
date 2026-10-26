<?php

use PHPUnit\Framework\TestCase;

final class Phase7ServiceCallFormTest extends TestCase {
	public function test_add_edit_service_call_workflow_is_permission_and_nonce_protected(): void {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/Admin/ServiceCallsPage.php' );
		$this->assertStringContainsString( "admin_post_servsys_save_service_call", $source );
		$this->assertStringContainsString( "check_admin_referer( 'servsys_save_service_call' )", $source );
		$this->assertStringContainsString( 'Capabilities::CREATE_SERVICE_CALLS', $source );
		$this->assertStringContainsString( 'Capabilities::EDIT_SERVICE_CALLS', $source );
		$this->assertStringContainsString( '( new ServiceCallService() )->save( $customer_id, $site_id, $data, $id )', $source );
	}

	public function test_site_origin_creation_locks_customer_and_site_context(): void {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/Admin/ServiceCallsPage.php' );
		$this->assertStringContainsString( "'site' === $source || $id", $source );
		$this->assertStringContainsString( 'name="customer_id"', $source );
		$this->assertStringContainsString( 'name="site_id"', $source );
		$this->assertStringContainsString( "Selected site does not belong to this customer.", $source );
	}

	public function test_service_call_workspace_exposes_explicit_edit_action_only_with_edit_capability(): void {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/Admin/ServiceCallsPage.php' );
		$this->assertStringContainsString( 'current_user_can( Capabilities::EDIT_SERVICE_CALLS )', $source );
		$this->assertStringContainsString( '>Edit Service Call</a>', $source );
	}
}
