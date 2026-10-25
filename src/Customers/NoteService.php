<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace ServSys\Customers;

final class NoteService {
	private $repository;

	public function __construct( NoteRepository $repository = null ) {
		$this->repository = $repository ?: new NoteRepository();
	}

	public function create( int $customer_id, array $input ): int {
		if ( $customer_id < 1 || empty( $input['note'] ) ) {
			throw new \InvalidArgumentException( 'Customer and note text are required.' );
		}

		$site_id = ! empty( $input['site_id'] ) ? absint( $input['site_id'] ) : 0;
		if ( $site_id ) {
			$site = ( new \ServSys\Sites\SiteRepository() )->find( $site_id );
			if ( ! $site || (int) $site['customer_id'] !== $customer_id ) {
				throw new \InvalidArgumentException( 'Selected site does not belong to this customer.' );
			}
		}

		$now  = current_time( 'mysql', true );
		$user = get_current_user_id();
		$id   = $this->repository->create(
			array(
				'customer_id' => $customer_id,
				'site_id'     => $site_id ?: null,
				'subject'     => isset( $input['subject'] ) ? sanitize_text_field( $input['subject'] ) : null,
				'note'        => sanitize_textarea_field( $input['note'] ),
				'note_date'   => $now,
				'created_at'  => $now,
				'updated_at'  => $now,
				'created_by'  => $user ?: null,
				'updated_by'  => $user ?: null,
			)
		);

		if ( ! $id ) {
			throw new \RuntimeException( 'Unable to create note.' );
		}
		return $id;
	}
}
