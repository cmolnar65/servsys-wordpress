<?php

use PHPUnit\Framework\TestCase;

final class Phase6EquipmentTest extends TestCase {
	private function source( string $path ): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/' . $path );
	}

	public function test_equipment_service_requires_valid_customer_site_and_blocks_new_equipment_on_inactive_parents(): void {
		$source = $this->source( 'src/Equipment/EquipmentService.php' );

		$this->assertStringContainsString( "Selected site does not belong to this customer.", $source );
		$this->assertStringContainsString( "! \\$id && ( empty( \\$customer['active'] ) || empty( \\$site['active'] ) )", $source );
		$this->assertStringContainsString( "Equipment cannot be added to an inactive customer or site.", $source );
		$this->assertStringContainsString( "Equipment does not belong to this customer site.", $source );
	}

	public function test_global_equipment_search_includes_refrigerant_type(): void {
		$source = $this->source( 'src/Equipment/EquipmentRepository.php' );
		$this->assertStringContainsString( 'e.refrigerant_type LIKE %s', $source );
	}

	public function test_site_origin_equipment_returns_to_dedicated_site_workspace(): void {
		$source = $this->source( 'src/Admin/EquipmentPage.php' );
		$this->assertStringContainsString( 'action=view-site&customer_id=', $source );
		$this->assertStringContainsString( "\\$return_url     = \\$from_site ? \\$site_url : \\$customer_url;", $source );
	}

	public function test_nested_equipment_table_includes_refrigerant_and_guards_edit_action(): void {
		$source = $this->source( 'src/Admin/CustomersPage.php' );
		$this->assertStringContainsString( '<th>Refrigerant</th>', $source );
		$this->assertStringContainsString( "current_user_can( Capabilities::EDIT_EQUIPMENT )", $source );
	}
}
