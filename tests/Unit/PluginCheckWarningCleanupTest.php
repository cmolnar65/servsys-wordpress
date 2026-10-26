<?php
use PHPUnit\Framework\TestCase;
final class PluginCheckWarningCleanupTest extends TestCase {
	public function test_equipment_return_navigation_is_sanitized_before_validation(): void {
		$source=file_get_contents(__DIR__.'/../../src/Admin/EquipmentPage.php');
		$this->assertStringContainsString("\$return_to_raw = isset( \$_GET['return_to'] ) ? sanitize_text_field( wp_unslash( \$_GET['return_to'] ) ) : '';",$source);
		$this->assertStringContainsString("wp_validate_redirect( rawurldecode( \$return_to_raw ), '' )",$source);
		$this->assertStringContainsString("\$return_to_raw = isset( \$_POST['return_to'] ) ? sanitize_text_field( wp_unslash( \$_POST['return_to'] ) ) : '';",$source);
	}
	public function test_direct_database_annotations_cover_complete_write_expressions(): void {
		$service=file_get_contents(__DIR__.'/../../src/ServiceCalls/ServiceCallRepository.php');
		$installer=file_get_contents(__DIR__.'/../../src/Database/Installer.php');
		$this->assertStringContainsString('phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic replacement',$service);
		$this->assertStringContainsString('phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching',$service);
		$this->assertStringContainsString('phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Upgrade-safe seed write',$installer);
	}
}
