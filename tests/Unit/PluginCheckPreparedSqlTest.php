<?php
use PHPUnit\Framework\TestCase;
final class PluginCheckPreparedSqlTest extends TestCase {
	public function test_company_repository_prepares_each_query_at_execution(): void {
		$source=file_get_contents(__DIR__.'/../../src/Companies/CompanyRepository.php');
		$this->assertStringNotContainsString('get_results( $wpdb->prepare( $sql',$source);
		$this->assertStringContainsString("get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY name,id'",$source);
	}
	public function test_service_call_search_uses_direct_prepared_statement(): void {
		$source=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallRepository.php');
		$this->assertStringNotContainsString('$sql = $wpdb->prepare',$source);
		$this->assertStringNotContainsString('get_results( $sql',$source);
		$this->assertStringContainsString('return $wpdb->get_results($wpdb->prepare(',$source);
	}
}
