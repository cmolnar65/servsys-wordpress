<?php

namespace ServSys\Customers;

final class CustomerService {
	private $repository;

	public function __construct( CustomerRepository $repository = null ) {
		$this->repository = $repository ?: new CustomerRepository();
	}

	public function save( array $input, int $id = 0 ): int {
		$name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';

		if ( '' === $name ) {
			throw new \InvalidArgumentException( 'Customer name is required.' );
		}

		$now  = current_time( 'mysql', true );
		$user = get_current_user_id();
		$data = array(
			'name'            => $name,
			'customer_type'   => isset( $input['customer_type'] ) ? sanitize_text_field( $input['customer_type'] ) : null,
			'primary_phone'   => isset( $input['primary_phone'] ) ? sanitize_text_field( $input['primary_phone'] ) : null,
			'alternate_phone' => isset( $input['alternate_phone'] ) ? sanitize_text_field( $input['alternate_phone'] ) : null,
			'email'           => isset( $input['email'] ) ? sanitize_email( $input['email'] ) : null,
			'notes'           => isset( $input['notes'] ) ? sanitize_textarea_field( $input['notes'] ) : null,
			'active'          => isset( $input['active'] ) ? (int) (bool) $input['active'] : 1,
			'updated_at'      => $now,
			'updated_by'      => $user ?: null,
		);

		if ( $id ) {
			if ( ! $this->repository->update( $id, $data ) ) {
				throw new \RuntimeException( 'Unable to update customer.' );
			}
			return $id;
		}

		$data['created_at'] = $now;
		$data['created_by'] = $user ?: null;
		$id = $this->repository->create( $data );

		if ( ! $id ) {
			throw new \RuntimeException( 'Unable to create customer.' );
		}

		return $id;
	}
}
