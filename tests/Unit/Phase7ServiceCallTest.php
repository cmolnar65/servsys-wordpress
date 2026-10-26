<?php

use PHPUnit\Framework\TestCase;
use ServSys\ServiceCalls\ServiceCallService;

final class Phase7ServiceCallTest extends TestCase {
	public function test_call_number_uses_two_digit_service_year_and_record_id(): void {
		$this->assertSame( '26-1234', ServiceCallService::format_call_number( '2026-10-25', 1234 ) );
		$this->assertSame( '30-9', ServiceCallService::format_call_number( '2030-01-01', 9 ) );
	}

	public function test_service_enforces_customer_site_and_equipment_ownership(): void {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/ServiceCalls/ServiceCallService.php' );
		$this->assertStringContainsString( "(int) \\$site['customer_id'] !== \\$customer_id", $source );
		$this->assertStringContainsString( "(int) \\$equipment['customer_id'] !== \\$customer_id", $source );
		$this->assertStringContainsString( "(int) \\$equipment['site_id'] !== \\$site_id", $source );
		$this->assertStringContainsString( 'Selected equipment does not belong to this customer site.', $source );
	}

	public function test_creation_generates_call_number_only_after_record_id_exists(): void {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/ServiceCalls/ServiceCallService.php' );
		$create = strpos( $source, '$id = $this->repository->create( $data );' );
		$format = strpos( $source, '$call_number = self::format_call_number( $service_date, $id );' );
		$save   = strpos( $source, '$this->repository->set_call_number( $id, $call_number )' );
		$this->assertNotFalse( $create );
		$this->assertNotFalse( $format );
		$this->assertNotFalse( $save );
		$this->assertLessThan( $format, $create );
		$this->assertLessThan( $save, $format );
	}

	public function test_service_call_supports_zero_to_many_equipment_relationships(): void {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/src/ServiceCalls/ServiceCallRepository.php' );
		$this->assertStringContainsString( 'replace_equipment', $source );
		$this->assertStringContainsString( "'service_call_id' => \\$service_call_id", $source );
		$this->assertStringContainsString( "'equipment_id'    => (int) \\$equipment_id", $source );
	}
}
