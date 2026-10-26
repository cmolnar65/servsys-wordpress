<?php

namespace ServSys\ServiceCalls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ServiceCallNoteService {
	private $repository;
	private $service_call_repository;

	public function __construct( ServiceCallNoteRepository $repository = null, ServiceCallRepository $service_call_repository = null ) {
		$this->repository = $repository ?: new ServiceCallNoteRepository();
		$this->service_call_repository = $service_call_repository ?: new ServiceCallRepository();
	}

	public function create( int $service_call_id, array $input ): int {
		$service_call = $service_call_id > 0 ? $this->service_call_repository->find( $service_call_id ) : null;
		if ( ! $service_call ) {
			throw new \InvalidArgumentException( 'A valid service call is required.' );
		}
		$note = isset( $input['note'] ) ? trim( sanitize_textarea_field( $input['note'] ) ) : '';
		if ( '' === $note ) {
			throw new \InvalidArgumentException( 'Note text is required.' );
		}
		$now = current_time( 'mysql', true );
		$user = get_current_user_id();
		$id = $this->repository->create(
			array(
				'company_id' => (int) $service_call['company_id'],
				'service_call_id' => $service_call_id,
				'note' => $note,
				'note_date' => $now,
				'created_at' => $now,
				'updated_at' => $now,
				'created_by' => $user ?: null,
				'updated_by' => $user ?: null,
			)
		);
		if ( ! $id ) {
			throw new \RuntimeException( 'Unable to create service call note.' );
		}
		return $id;
	}
}
