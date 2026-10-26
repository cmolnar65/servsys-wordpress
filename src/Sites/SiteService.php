<?php

namespace ServSys\Sites;

use ServSys\Customers\CustomerRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SiteService {
	private $repository;

	public function __construct( SiteRepository $repository = null ) {
		$this->repository = $repository ?: new SiteRepository();
	}

	public function save( int $customer_id, array $input, int $id = 0 ): int {
		if ( $customer_id < 1 ) {
			throw new \InvalidArgumentException( 'Customer is required.' );
		}

		$customer = ( new CustomerRepository() )->find( $customer_id );
		if ( ! $customer ) {
			throw new \InvalidArgumentException( 'Customer does not belong to the current company.' );
		}

		foreach ( array( 'address1', 'city', 'state', 'postal_code' ) as $required ) {
			if ( empty( $input[ $required ] ) ) {
				throw new \InvalidArgumentException( 'Site address, city, state, and postal code are required.' );
			}
		}

		$now  = current_time( 'mysql', true );
		$user = get_current_user_id();
		$data = array(
			'company_id'  => (int) $customer['company_id'],
			'customer_id' => $customer_id,
			'name'        => isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : null,
			'address1'    => sanitize_text_field( $input['address1'] ),
			'address2'    => isset( $input['address2'] ) ? sanitize_text_field( $input['address2'] ) : null,
			'city'        => sanitize_text_field( $input['city'] ),
			'state'       => sanitize_text_field( $input['state'] ),
			'postal_code' => sanitize_text_field( $input['postal_code'] ),
			'phone'       => isset( $input['phone'] ) ? sanitize_text_field( $input['phone'] ) : null,
			'notes'       => isset( $input['notes'] ) ? sanitize_textarea_field( $input['notes'] ) : null,
			'active'      => isset( $input['active'] ) ? (int) (bool) $input['active'] : 1,
			'updated_at'  => $now,
			'updated_by'  => $user ?: null,
		);

		if ( $id ) {
			$existing = $this->repository->find( $id );
			if ( ! $existing || (int) $existing['customer_id'] !== $customer_id ) {
				throw new \InvalidArgumentException( 'Site does not belong to this customer.' );
			}
			if ( ! $this->repository->update( $id, $data ) ) {
				throw new \RuntimeException( 'Unable to update site.' );
			}
			return $id;
		}

		$data['created_at'] = $now;
		$data['created_by'] = $user ?: null;
		$id = $this->repository->create( $data );
		if ( ! $id ) {
			throw new \RuntimeException( 'Unable to create site.' );
		}
		return $id;
	}
}
