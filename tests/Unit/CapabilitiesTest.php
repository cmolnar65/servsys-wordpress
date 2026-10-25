<?php

use PHPUnit\Framework\TestCase;
use ServSys\Users\Capabilities;

final class CapabilitiesTest extends TestCase {
	public function test_phase_five_customer_capabilities_are_defined(): void {
		$this->assertSame(
			array(
				'servsys_access',
				'servsys_view_customers',
				'servsys_create_customers',
				'servsys_edit_customers',
			),
			Capabilities::all()
		);
	}
}
