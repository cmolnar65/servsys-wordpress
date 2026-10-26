<?php

namespace ServSys\ServiceCalls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ServSys\Customers\CustomerRepository;
use ServSys\Equipment\EquipmentRepository;
use ServSys\Sites\SiteRepository;

final class ServiceCallService {
	private $repository;
	private $customer_repository;
	private $site_repository;
	private $equipment_repository;

	public function __construct(
		ServiceCallRepository $repository = null,
		CustomerRepository $customer_repository = null,
		SiteRepository $site_repository = null,
		EquipmentRepository $equipment_repository = null
	) {
		$this->repository           = $repository ?: new ServiceCallRepository();
		$this->customer_repository  = $customer_repository ?: new CustomerRepository();
		$this->site_repository      = $site_repository ?: new SiteRepository();
		$this->equipment_repository = $equipment_repository ?: new EquipmentRepository();
	}

	public function save( int $customer_id, int $site_id, array $input, int $id = 0 ): int {
		$this->validate_customer_site( $customer_id, $site_id );

		$equipment_ids = $this->validate_equipment( $customer_id, $site_id, isset( $input['equipment_ids'] ) ? (array) $input['equipment_ids'] : array() );
		$service_date  = isset( $input['service_date'] ) ? sanitize_text_field( $input['service_date'] ) : '';
		if ( ! $this->valid_date( $service_date ) ) {
			throw new \InvalidArgumentException( 'A valid service date is required.' );
		}

		$status = isset( $input['status'] ) ? sanitize_key( $input['status'] ) : 'open';
		if ( ! in_array( $status, array( 'open', 'completed', 'cancelled' ), true ) ) {
			throw new \InvalidArgumentException( 'Invalid service call status.' );
		}

		$now  = current_time( 'mysql', true );
		$user = get_current_user_id();
		$data = array(
			'customer_id'          => $customer_id,
			'site_id'              => $site_id,
			'service_date'         => $service_date,
			'technician_user_id'   => isset( $input['technician_user_id'] ) && absint( $input['technician_user_id'] ) ? absint( $input['technician_user_id'] ) : null,
			'status'               => $status,
			'reason'               => isset( $input['reason'] ) ? sanitize_textarea_field( $input['reason'] ) : null,
			'services_performed'   => isset( $input['services_performed'] ) ? sanitize_textarea_field( $input['services_performed'] ) : null,
			'recommendations'      => isset( $input['recommendations'] ) ? sanitize_textarea_field( $input['recommendations'] ) : null,
			'parts_notes'          => isset( $input['parts_notes'] ) ? sanitize_textarea_field( $input['parts_notes'] ) : null,
			'follow_up_required'   => empty( $input['follow_up_required'] ) ? 0 : 1,
			'follow_up_date'       => isset( $input['follow_up_date'] ) && '' !== $input['follow_up_date'] ? sanitize_text_field( $input['follow_up_date'] ) : null,
			'updated_at'           => $now,
			'updated_by'           => $user ?: null,
		);

		if ( null !== $data['follow_up_date'] && ! $this->valid_date( $data['follow_up_date'] ) ) {
			throw new \InvalidArgumentException( 'Follow-up date is invalid.' );
		}
		if ( ! $data['follow_up_required'] ) {
			$data['follow_up_date'] = null;
		}

		if ( $id ) {
			$existing = $this->repository->find( $id );
			if ( ! $existing || (int) $existing['customer_id'] !== $customer_id || (int) $existing['site_id'] !== $site_id ) {
				throw new \InvalidArgumentException( 'Service call does not belong to this customer site.' );
			}
			if ( ! $this->repository->update( $id, $data ) || ! $this->repository->replace_equipment( $id, $equipment_ids ) ) {
				throw new \RuntimeException( 'Unable to update service call.' );
			}
			return $id;
		}

		$data['created_at'] = $now;
		$data['created_by'] = $user ?: null;
		$id = $this->repository->create( $data );
		if ( ! $id ) {
			throw new \RuntimeException( 'Unable to create service call.' );
		}

		$call_number = self::format_call_number( $service_date, $id );
		if ( ! $this->repository->set_call_number( $id, $call_number ) || ! $this->repository->replace_equipment( $id, $equipment_ids ) ) {
			throw new \RuntimeException( 'Unable to finalize service call.' );
		}
		return $id;
	}

	public static function format_call_number( string $service_date, int $id ): string {
		$year = substr( $service_date, 2, 2 );
		return $year . '-' . $id;
	}

	private function validate_customer_site( int $customer_id, int $site_id ): void {
		if ( $customer_id < 1 || $site_id < 1 ) {
			throw new \InvalidArgumentException( 'Customer and site are required.' );
		}
		$customer = $this->customer_repository->find( $customer_id );
		$site     = $this->site_repository->find( $site_id );
		if ( ! $customer || ! $site || (int) $site['customer_id'] !== $customer_id ) {
			throw new \InvalidArgumentException( 'Selected site does not belong to this customer.' );
		}
	}

	private function validate_equipment( int $customer_id, int $site_id, array $equipment_ids ): array {
		$valid = array();
		foreach ( array_unique( array_map( 'absint', $equipment_ids ) ) as $equipment_id ) {
			if ( ! $equipment_id ) {
				continue;
			}
			$equipment = $this->equipment_repository->find( $equipment_id );
			if ( ! $equipment || (int) $equipment['customer_id'] !== $customer_id || (int) $equipment['site_id'] !== $site_id ) {
				throw new \InvalidArgumentException( 'Selected equipment does not belong to this customer site.' );
			}
			$valid[] = $equipment_id;
		}
		return $valid;
	}

	private function valid_date( string $date ): bool {
		$parsed = \DateTime::createFromFormat( '!Y-m-d', $date );
		return $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}
}
