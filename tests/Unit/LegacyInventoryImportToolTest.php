<?php

use PHPUnit\Framework\TestCase;

final class LegacyInventoryImportToolTest extends TestCase {
	public function test_legacy_import_tool_is_safe_by_default_and_applies_agreed_normalization_rules(): void {
		$tool = file_get_contents( __DIR__ . '/../../tools/legacy-inventory-import.php' );
		$this->assertStringContainsString( "'/home/cmolnar/ourworldservices_inventory_dump.sql'", $tool );
		$this->assertStringContainsString( "\$apply       = isset( \$options['apply'] );", $tool );
		$this->assertStringContainsString( "\$current_company_id = \\ServSys\\Companies\\CompanyContext::current_company_id();", $tool );
		$this->assertStringContainsString( "if ( ! \$apply )", $tool );
		$this->assertStringContainsString( "Dry run will not change company context.", $tool );
		$this->assertStringContainsString( "return 'MISC';", $tool );
		$this->assertStringContainsString( "return 'EACH';", $tool );
		$this->assertStringContainsString( "if ( \$quantity < 0 )", $tool );
		$this->assertStringContainsString( "\$quantity = 0.0;", $tool );
		$this->assertStringContainsString( "'Legacy Inventory'", $tool );
		$this->assertStringContainsString( "'initial'", $tool );
		$this->assertStringContainsString( 'Existing ServSys SKUs are never overwritten', $tool );
	}

	public function test_release_builder_removes_and_audits_tools_directory(): void {
		$builder = file_get_contents( __DIR__ . '/../../build-release.sh' );
		$this->assertStringContainsString( '"${STAGE_DIR}/tools"', $builder );
		$this->assertStringContainsString( '"tools/"', $builder );
	}
}
