<?php
use PHPUnit\Framework\TestCase;
final class ServiceCallCustomerSiteSelectionTest extends TestCase {
	public function test_global_service_call_creation_reloads_after_customer_selection(): void {
		$source=file_get_contents(__DIR__.'/../../src/Admin/ServiceCallsPage.php');
		$this->assertStringContainsString('<form method="get" action="' . " . esc_url( admin_url( 'admin.php' ) ) . " . '">',$source);
		$this->assertStringContainsString('<input type="hidden" name="page" value="servsys-service-calls"><input type="hidden" name="action" value="edit">',$source);
		$this->assertStringContainsString("submit_button( 'Select Customer', 'secondary', '', false );",$source);
		$this->assertStringContainsString("\$sites = \$customer_id ? ( new SiteRepository() )->for_customer( \$customer_id, false ) : array();",$source);
		$this->assertStringContainsString('<input type="hidden" name="customer_id" value="' . " . esc_attr( (string) \$customer_id ) . " . '">',$source);
		$this->assertStringContainsString('Change Customer',$source);
		$this->assertStringNotContainsString('reopen Add Service Call with that customer',$source);
	}
}
