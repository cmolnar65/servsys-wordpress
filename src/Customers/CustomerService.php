<?php

namespace ServSys\Customers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CustomerService {
	private $repository;
	private $type_repository;

	public function __construct( CustomerRepository $repository = null, CustomerTypeRepository $type_repository = null ) {
		$this->repository      = $repository ?: new CustomerRepository();
		$this->type_repository = $type_repository ?: new CustomerTypeRepository();
	}

	public function save( array $input, int $id = 0 ): int {
		$name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';

		if ( '' === $name ) {
			throw new \InvalidArgumentException( 'Customer name is required.' );
		}

		$type_id = isset( $input['customer_type_id'] ) ? absint( $input['customer_type_id'] ) : 0;
		$type    = $type_id ? $this->type_repository->find( $type_id ) : null;
		if ( $type_id && ! $type ) {
			throw new \InvalidArgumentException( 'Invalid customer type.' );
		}

		$now  = current_time( 'mysql', true );
		$user = get_current_user_id();
		$data = array(
			'name'             => $name,
			'customer_type_id' => $type_id ?: null,
			'customer_type'    => $type ? $type['name'] : null,
			'primary_phone'    => isset( $input['primary_phone'] ) ? sanitize_text_field( $input['primary_phone'] ) : null,
			'alternate_phone'  => isset( $input['alternate_phone'] ) ? sanitize_text_field( $input['alternate_phone'] ) : null,
			'email'            => isset( $input['email'] ) ? sanitize_email( $input['email'] ) : null,
			'notes'            => isset( $input['notes'] ) ? sanitize_textarea_field( $input['notes'] ) : null,
			'active'           => isset( $input['active'] ) ? (int) (bool) $input['active'] : 1,
			'updated_at'       => $now,
			'updated_by'       => $user ?: null,
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
