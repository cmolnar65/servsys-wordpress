<?php

namespace ServSys\ServiceCalls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ServiceCallRepository {
	private $wpdb;
	private $table;
	private $equipment_table;

	public function __construct( $wpdb = null ) {
		$this->wpdb            = $wpdb ?: $GLOBALS['wpdb'];
		$this->table           = $this->wpdb->prefix . 'servsys_service_calls';
		$this->equipment_table = $this->wpdb->prefix . 'servsys_service_call_equipment';
	}

	public function find( int $id ) {
		$wpdb = $this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses a plugin-owned custom table and must remain current.
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ),
			ARRAY_A
		);
	}

	public function create( array $data ): int {
		$this->wpdb->insert( $this->table, $data );
		return (int) $this->wpdb->insert_id;
	}

	public function update( int $id, array $data ): bool {
		return false !== $this->wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}

	public function set_call_number( int $id, string $call_number ): bool {
		return false !== $this->wpdb->update( $this->table, array( 'call_number' => $call_number ), array( 'id' => $id ) );
	}

	public function equipment_ids( int $service_call_id ): array {
		$wpdb = $this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational relationship data must remain current.
		$ids = $wpdb->get_col(
			$wpdb->prepare( 'SELECT equipment_id FROM %i WHERE service_call_id = %d ORDER BY id', $this->equipment_table, $service_call_id )
		);
		return array_map( 'intval', $ids );
	}

	public function replace_equipment( int $service_call_id, array $equipment_ids ): bool {
		$wpdb = $this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- ServSys owns this relationship table and replacement is an intentional atomic domain operation.
		if ( false === $wpdb->delete( $this->equipment_table, array( 'service_call_id' => $service_call_id ) ) ) {
			return false;
		}

		$now  = current_time( 'mysql', true );
		$user = get_current_user_id();
		foreach ( $equipment_ids as $equipment_id ) {
			if ( false === $wpdb->insert(
				$this->equipment_table,
				array(
					'service_call_id' => $service_call_id,
					'equipment_id'    => (int) $equipment_id,
					'created_at'      => $now,
					'created_by'      => $user ?: null,
				)
			) ) {
				return false;
			}
		}
		return true;
	}
}
