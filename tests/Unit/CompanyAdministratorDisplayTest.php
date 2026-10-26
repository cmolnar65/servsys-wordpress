<?php
use PHPUnit\Framework\TestCase;
final class CompanyAdministratorDisplayTest extends TestCase {
	public function test_company_edit_form_reads_and_selects_current_administrator(): void {
		$source=file_get_contents(__DIR__.'/../../src/Admin/CompaniesPage.php');
		$this->assertStringContainsString("( new CompanyUserRepository() )->for_company( (int) \$c['id'] )",$source);
		$this->assertStringContainsString("! empty( \$membership['active'] ) && ! empty( \$membership['is_company_admin'] )",$source);
		$this->assertStringContainsString("\$current_admin_user_id = (int) \$membership['user_id'];",$source);
		$this->assertStringContainsString("selected( \$current_admin_user_id, (int) \$user->ID, false )",$source);
	}
}
