<?php
use PHPUnit\Framework\TestCase;

final class Phase7ServiceCallNotesTest extends TestCase {
	public function test_service_call_notes_use_existing_phase7_table(): void {
		$repository=file_get_contents(dirname(__DIR__,2).'/src/ServiceCalls/ServiceCallNoteRepository.php');
		$this->assertStringContainsString("servsys_service_call_notes",$repository);
		$this->assertStringContainsString('WHERE service_call_id = %d',$repository);
		$this->assertStringContainsString('ORDER BY note_date DESC, id DESC',$repository);
	}
	public function test_note_service_requires_existing_service_call_and_note_text(): void {
		$service=file_get_contents(dirname(__DIR__,2).'/src/ServiceCalls/ServiceCallNoteService.php');
		$this->assertStringContainsString("service_call_repository->find( $service_call_id )",$service);
		$this->assertStringContainsString('Note text is required.',$service);
		$this->assertStringContainsString("'created_by' => $user ?: null",$service);
	}
	public function test_workspace_displays_and_adds_notes_with_edit_capability(): void {
		$page=file_get_contents(dirname(__DIR__,2).'/src/Admin/ServiceCallsPage.php');
		$this->assertStringContainsString('Service Call Notes',$page);
		$this->assertStringContainsString('admin_post_servsys_add_service_call_note',$page);
		$this->assertStringContainsString("check_admin_referer( 'servsys_add_service_call_note' )",$page);
		$this->assertStringContainsString('Capabilities::EDIT_SERVICE_CALLS',$page);
	}
}
