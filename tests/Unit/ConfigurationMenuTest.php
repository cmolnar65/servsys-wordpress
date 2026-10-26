<?php
use PHPUnit\Framework\TestCase;

final class ConfigurationMenuTest extends TestCase {
	public function test_configuration_menu_is_registered(): void {
		$plugin=file_get_contents(__DIR__.'/../../src/Plugin.php');
		$config=file_get_contents(__DIR__.'/../../src/Admin/ConfigurationPage.php');
		$this->assertStringContainsString('use ServSys\\Admin\\ConfigurationPage;',$plugin);
		$this->assertStringContainsString('( new ConfigurationPage() )->register();',$plugin);
		$this->assertStringContainsString("'servsys-configuration'",$config);
		$this->assertStringContainsString("'Configuration'",$config);
	}

	public function test_configuration_children_are_hidden_from_servsys_first_level_menu(): void {
		$customers=file_get_contents(__DIR__.'/../../src/Admin/CustomersPage.php');
		$equipment=file_get_contents(__DIR__.'/../../src/Admin/EquipmentPage.php');
		$companies=file_get_contents(__DIR__.'/../../src/Admin/CompaniesPage.php');
		$users=file_get_contents(__DIR__.'/../../src/Admin/CompanyUsersPage.php');

		$this->assertStringContainsString("add_submenu_page( null, 'Customer Types'",$customers);
		$this->assertStringContainsString("add_submenu_page( null, 'Equipment Types'",$equipment);
		$this->assertStringContainsString("add_submenu_page( null,'Companies'",$companies);
		$this->assertStringContainsString("add_submenu_page(null,'Company Users'",$users);
	}

	public function test_configuration_landing_page_links_to_existing_slugs(): void {
		$config=file_get_contents(__DIR__.'/../../src/Admin/ConfigurationPage.php');
		foreach ( array( 'servsys-companies','servsys-company-users','servsys-customer-types','servsys-equipment-types' ) as $slug ) {
			$this->assertStringContainsString($slug,$config);
		}
	}
}
