<?php

namespace ServSys\Inventory;

use ServSys\Companies\CompanyContext;
use ServSys\Users\CompanyUserRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InventoryLocationService {
	private $locations;
	private $company_users;

	public function __construct( InventoryLocationRepository $locations = null, CompanyUserRepository $company_users = null ) {
		$this->locations     = $locations ?: new InventoryLocationRepository();
		$this->company_users = $company_users ?: new CompanyUserRepository();
	}

	public function save( array $input, int $id = 0 ): int {
		$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		if ( '' === $name ) {
			throw new \InvalidArgumentException( 'Inventory location name is required.' );
		}

		$allowed_types = array( 'warehouse', 'shop', 'truck', 'technician_vehicle', 'other' );
		$location_type = sanitize_key( (string) ( $input['location_type'] ?? 'other' ) );
		if ( ! in_array( $location_type, $allowed_types, true ) ) {
			throw new \InvalidArgumentException( 'Invalid inventory location type.' );
		}

		$company_user_id = absint( $input['company_user_id'] ?? 0 );
		if ( $company_user_id ) {
			$membership = $this->company_users->find( CompanyContext::current_company_id(), $company_user_id );
			if ( ! $membership || empty( $membership['active'] ) ) {
				throw new \InvalidArgumentException( 'Assigned user must be an active member of the current company.' );
			}
		}

		if ( $id && ! $this->locations->find( $id ) ) {
			throw new \InvalidArgumentException( 'Inventory location not found for the current company.' );
		}

		$now  = current_time( 'mysql', true );
		$data = array(
			'name'            => $name,
			'location_type'   => $location_type,
			'company_user_id' => $company_user_id ?: null,
			'truck_number'    => sanitize_text_field( (string) ( $input['truck_number'] ?? '' ) ),
			'notes'           => sanitize_textarea_field( (string) ( $input['notes'] ?? '' ) ),
			'active'          => ! empty( $input['active'] ) ? 1 : 0,
			'updated_at'      => $now,
			'updated_by'      => get_current_user_id(),
		);

		if ( $id ) {
			if ( ! $this->locations->update( $id, $data ) ) {
				throw new \RuntimeException( 'Unable to update inventory location.' );
			}
			return $id;
		}

		$data['created_at'] = $now;
		$data['created_by'] = get_current_user_id();
		$id = $this->locations->create( $data );
		if ( ! $id ) {
			throw new \RuntimeException( 'Unable to create inventory location.' );
		}
		return $id;
	}
}
