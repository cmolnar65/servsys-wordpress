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

	public function search( string $term = '', string $status = '' ): array {
		$wpdb      = $this->wpdb;
		$customers = $wpdb->prefix . 'servsys_customers';
		$sites     = $wpdb->prefix . 'servsys_sites';
		$where     = array( '1=1' );
		$args      = array( $this->table, $customers, $sites );

		if ( '' !== $term ) {
			$like    = '%' . $wpdb->esc_like( $term ) . '%';
			$where[] = '(sc.call_number LIKE %s OR c.name LIKE %s OR s.name LIKE %s OR s.address1 LIKE %s OR sc.reason LIKE %s)';
			array_push( $args, $like, $like, $like, $like, $like );
		}
		if ( in_array( $status, array( 'open', 'completed', 'cancelled' ), true ) ) {
			$where[] = 'sc.status = %s';
			$args[]  = $status;
		}

		$sql = 'SELECT sc.*, c.name AS customer_name, s.name AS site_name, s.address1 AS site_address FROM %i sc INNER JOIN %i c ON c.id = sc.customer_id INNER JOIN %i s ON s.id = sc.site_id WHERE ' . implode( ' AND ', $where ) . ' ORDER BY sc.service_date DESC, sc.id DESC LIMIT 250';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses plugin-owned custom tables and must remain current.
		return $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );
	}


	public function for_site( int $customer_id, int $site_id, int $limit = 10 ): array {
		$wpdb = $this->wpdb;
		$limit = max( 1, min( 100, $limit ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses a plugin-owned custom table and must remain current.
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE customer_id = %d AND site_id = %d ORDER BY service_date DESC, id DESC LIMIT %d',
				$this->table,
				$customer_id,
				$site_id,
				$limit
			),
			ARRAY_A
		);
	}

	public function find_with_context( int $id ) {
		$wpdb      = $this->wpdb;
		$customers = $wpdb->prefix . 'servsys_customers';
		$sites     = $wpdb->prefix . 'servsys_sites';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses plugin-owned custom tables and must remain current.
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT sc.*, c.name AS customer_name, s.name AS site_name, s.address1 AS site_address, s.city AS site_city, s.state AS site_state FROM %i sc INNER JOIN %i c ON c.id = sc.customer_id INNER JOIN %i s ON s.id = sc.site_id WHERE sc.id = %d', $this->table, $customers, $sites, $id ),
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
