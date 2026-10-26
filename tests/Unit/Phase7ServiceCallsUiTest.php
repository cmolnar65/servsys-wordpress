<?php

use PHPUnit\Framework\TestCase;

final class Phase7ServiceCallsUiTest extends TestCase {
	public function test_service_calls_page_is_registered_with_view_capability(): void {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/Admin/ServiceCallsPage.php' );
		$this->assertStringContainsString( "Capabilities::VIEW_SERVICE_CALLS", $source );
		$this->assertStringContainsString( "'servsys-service-calls'", $source );
		$this->assertStringContainsString( 'ServSys Service Calls', $source );
	}

	public function test_global_list_supports_search_status_and_read_only_workspace(): void {
		$page = file_get_contents( dirname( __DIR__, 2 ) . '/src/Admin/ServiceCallsPage.php' );
		$repository = file_get_contents( dirname( __DIR__, 2 ) . '/src/ServiceCalls/ServiceCallRepository.php' );
		$this->assertStringContainsString( 'search( $term, $status )', $page );
		$this->assertStringContainsString( 'find_with_context( $id )', $page );
		$this->assertStringContainsString( 'All statuses', $page );
		$this->assertStringContainsString( 'Equipment Serviced', $page );
		$this->assertStringContainsString( 'sc.call_number LIKE %s', $repository );
		$this->assertStringContainsString( 'sc.status = %s', $repository );
	}

	public function test_plugin_registers_service_calls_admin_page(): void {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/Plugin.php' );
		$this->assertStringContainsString( 'use ServSys\\Admin\\ServiceCallsPage;', $source );
		$this->assertStringContainsString( '( new ServiceCallsPage() )->register();', $source );
	}
}
