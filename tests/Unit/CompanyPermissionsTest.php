<?php
use PHPUnit\Framework\TestCase;
final class CompanyPermissionsTest extends TestCase {
	public function test_permissions_schema_and_upgrade_are_versioned(): void {
		$schema=file_get_contents(__DIR__.'/../../src/Database/Schema.php'); $bootstrap=file_get_contents(__DIR__.'/../../servsys.php');
		$this->assertStringContainsString('servsys_company_users',$schema);
		$this->assertStringContainsString('UNIQUE KEY company_user (company_id,user_id)',$schema);
		$this->assertStringContainsString('is_company_admin',$schema);
		$this->assertStringContainsString("SERVSYS_DB_VERSION', 10",$bootstrap);
	}
	public function test_wordpress_admin_is_explicit_system_authority(): void {
		$auth=file_get_contents(__DIR__.'/../../src/Users/Authorization.php'); $companies=file_get_contents(__DIR__.'/../../src/Admin/CompaniesPage.php');
		$this->assertStringContainsString("in_array( 'administrator'",$auth);
		$this->assertStringContainsString('Authorization::is_wordpress_admin()',$companies);
		$this->assertStringContainsString("'manage_options'",$companies);
	}
	public function test_company_admin_cannot_receive_system_company_management(): void {
		$caps=file_get_contents(__DIR__.'/../../src/Users/Capabilities.php'); $auth=file_get_contents(__DIR__.'/../../src/Users/Authorization.php');
		$this->assertStringContainsString('MANAGE_COMPANIES',$caps);
		$this->assertStringNotContainsString('self::MANAGE_COMPANIES, self::MANAGE_COMPANY_USERS',$caps);
		$this->assertStringContainsString('Capabilities::company_capabilities()',$auth);
	}
	public function test_company_context_requires_membership_for_non_admin(): void {
		$context=file_get_contents(__DIR__.'/../../src/Companies/CompanyContext.php');
		$this->assertStringContainsString('Authorization::can_access_company',$context);
		$this->assertStringContainsString('companies_for_user',$context);
	}
	public function test_notes_are_company_scoped(): void {
		foreach(array('Customers/NoteRepository.php','ServiceCalls/ServiceCallNoteRepository.php') as $path){$source=file_get_contents(__DIR__.'/../../src/'.$path);$this->assertStringContainsString('CompanyContext::current_company_id()',$source,$path);}
	}
}
