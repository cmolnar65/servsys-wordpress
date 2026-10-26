<?php

use PHPUnit\Framework\TestCase;
use ServSys\Users\Capabilities;

final class CapabilitiesTest extends TestCase {
	public function test_phase_seven_capabilities_are_defined(): void {
		$this->assertSame(
			array(
				'servsys_access',
				'servsys_view_customers',
				'servsys_create_customers',
				'servsys_edit_customers',
				'servsys_create_sites',
				'servsys_edit_sites',
				'servsys_view_equipment',
				'servsys_create_equipment',
				'servsys_edit_equipment',
				'servsys_view_service_calls',
				'servsys_create_service_calls',
				'servsys_edit_service_calls',
			),
			Capabilities::all()
		);
	}
}
