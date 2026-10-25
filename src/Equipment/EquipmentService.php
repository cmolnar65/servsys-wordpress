<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace ServSys\Equipment;

use ServSys\Customers\CustomerRepository;
use ServSys\Sites\SiteRepository;

final class EquipmentService {
	private $repository;
	private $customer_repository;
	private $site_repository;

	public function __construct( EquipmentRepository $repository = null, CustomerRepository $customer_repository = null, SiteRepository $site_repository = null ) {
		$this->repository          = $repository ?: new EquipmentRepository();
		$this->customer_repository = $customer_repository ?: new CustomerRepository();
		$this->site_repository      = $site_repository ?: new SiteRepository();
	}

	public function save( int $customer_id, int $site_id, array $input, int $id = 0 ): int {
		if ( $customer_id < 1 || $site_id < 1 ) {
			throw new \InvalidArgumentException( 'Customer and site are required.' );
		}

		$customer = $this->customer_repository->find( $customer_id );
		$site     = $this->site_repository->find( $site_id );
		if ( ! $customer || ! $site || (int) $site['customer_id'] !== $customer_id ) {
			throw new \InvalidArgumentException( 'Selected site does not belong to this customer.' );
		}

		$type_id = isset( $input['equipment_type_id'] ) ? absint( $input['equipment_type_id'] ) : 0;
		$type_record = $type_id ? ( new EquipmentTypeRepository() )->find( $type_id ) : null;
		if ( ! $type_record ) {
			throw new \InvalidArgumentException( 'A valid equipment type is required.' );
		}
		$type = $type_record['name'];

		$now  = current_time( 'mysql', true );
		$user = get_current_user_id();
		$data = array(
			'customer_id'    => $customer_id,
			'site_id'        => $site_id,
			'equipment_type' => $type,
			'equipment_type_id' => $type_id,
			'brand'          => isset( $input['brand'] ) ? sanitize_text_field( $input['brand'] ) : null,
			'model_number'   => isset( $input['model_number'] ) ? sanitize_text_field( $input['model_number'] ) : null,
			'serial_number'  => isset( $input['serial_number'] ) ? sanitize_text_field( $input['serial_number'] ) : null,
			'filter'         => isset( $input['filter'] ) ? sanitize_text_field( $input['filter'] ) : null,
			'refrigerant_type' => isset( $input['refrigerant_type'] ) ? sanitize_text_field( $input['refrigerant_type'] ) : null,
			'seer'           => isset( $input['seer'] ) ? sanitize_text_field( $input['seer'] ) : null,
			'btu_output'     => isset( $input['btu_output'] ) ? sanitize_text_field( $input['btu_output'] ) : null,
			'notes'          => isset( $input['notes'] ) ? sanitize_textarea_field( $input['notes'] ) : null,
			'active'         => isset( $input['active'] ) ? (int) (bool) $input['active'] : 1,
			'updated_at'     => $now,
			'updated_by'     => $user ?: null,
		);

		if ( $id ) {
			$existing = $this->repository->find( $id );
			if ( ! $existing || (int) $existing['customer_id'] !== $customer_id || (int) $existing['site_id'] !== $site_id ) {
				throw new \InvalidArgumentException( 'Equipment does not belong to this customer site.' );
			}
			if ( ! $this->repository->update( $id, $data ) ) {
				throw new \RuntimeException( 'Unable to update equipment.' );
			}
			return $id;
		}

		$data['created_at'] = $now;
		$data['created_by'] = $user ?: null;
		$id = $this->repository->create( $data );
		if ( ! $id ) {
			throw new \RuntimeException( 'Unable to create equipment.' );
		}
		return $id;
	}
}
