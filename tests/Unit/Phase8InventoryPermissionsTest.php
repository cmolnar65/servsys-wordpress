<?php

use PHPUnit\Framework\TestCase;

final class Phase8InventoryPermissionsTest extends TestCase {
	public function test_inventory_capabilities_are_declared(): void {
		$source = file_get_contents( __DIR__ . '/../../src/Users/Capabilities.php' );

		foreach ( array(
			"const VIEW_INVENTORY = 'servsys_view_inventory';",
			"const MANAGE_INVENTORY = 'servsys_manage_inventory';",
			"const ADJUST_INVENTORY = 'servsys_adjust_inventory';",
			"const USE_INVENTORY = 'servsys_use_inventory';",
		) as $capability ) {
			$this->assertStringContainsString( $capability, $source );
		}
	}

	public function test_inventory_capabilities_are_company_scoped_operational_permissions(): void {
		$source = file_get_contents( __DIR__ . '/../../src/Users/Capabilities.php' );

		$start = strpos( $source, 'public static function company_capabilities()' );
		$end   = strpos( $source, 'public static function all()', $start );
		$this->assertNotFalse( $start );
		$this->assertNotFalse( $end );
		$company_capabilities = substr( $source, $start, $end - $start );

		foreach ( array(
			'self::VIEW_INVENTORY',
			'self::MANAGE_INVENTORY',
			'self::ADJUST_INVENTORY',
			'self::USE_INVENTORY',
		) as $capability ) {
			$this->assertStringContainsString( $capability, $company_capabilities );
		}

		$this->assertStringNotContainsString( 'self::MANAGE_COMPANIES', $company_capabilities );
	}

	public function test_wordpress_and_company_admin_authorization_includes_inventory_capabilities(): void {
		$authorization = file_get_contents( __DIR__ . '/../../src/Users/Authorization.php' );

		$this->assertStringContainsString( 'self::is_wordpress_admin($user_id) ) { return true; }', $authorization );
		$this->assertStringContainsString( 'Capabilities::company_capabilities()', $authorization );
		$this->assertStringContainsString( 'in_array($capability,$permissions,true)', $authorization );
	}

	public function test_company_users_page_exposes_inventory_permissions_to_regular_users(): void {
		$page = file_get_contents( __DIR__ . '/../../src/Admin/CompanyUsersPage.php' );

		$this->assertStringContainsString( 'Capabilities::company_capabilities()', $page );
		$this->assertStringContainsString( 'name="permissions[]"', $page );
		$this->assertStringContainsString( 'array_intersect($requested,$allowed)', $page );
	}

	public function test_inventory_permissions_do_not_change_database_version(): void {
		$bootstrap = file_get_contents( __DIR__ . '/../../servsys.php' );

		$this->assertStringContainsString( "SERVSYS_DB_VERSION', 10", $bootstrap );
		$this->assertStringNotContainsString( "SERVSYS_DB_VERSION', 11", $bootstrap );
	}
}
