<?php

namespace ServSys\Equipment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EquipmentRepository {
	private $wpdb;
	private $table;

	public function __construct( $wpdb = null ) {
		$this->wpdb  = $wpdb ?: $GLOBALS['wpdb'];
		$this->table = $this->wpdb->prefix . 'servsys_equipment';
	}

	public function find( int $id ) {
		$wpdb = $this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses a plugin-owned custom table and must remain current.
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ),
			ARRAY_A
		);
	}

	public function for_site( int $site_id, bool $include_inactive = true ): array {
		$wpdb = $this->wpdb;
		if ( $include_inactive ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses plugin-owned custom tables and must remain current.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses plugin-owned custom tables and must remain current.
		return $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE site_id = %d ORDER BY equipment_type, brand, model_number, id', $this->table, $site_id ),
				ARRAY_A
			);
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses plugin-owned custom tables and must remain current.
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE site_id = %d AND active = 1 ORDER BY equipment_type, brand, model_number, id', $this->table, $site_id ),
			ARRAY_A
		);
	}

	public function search( string $term = '', bool $include_inactive = true ): array {
		$wpdb = $this->wpdb;
		$customers = $wpdb->prefix . 'servsys_customers';
		$sites = $wpdb->prefix . 'servsys_sites';
		$active = $include_inactive ? 0 : 1;
		if ( '' !== $term ) {
			$like = '%' . $wpdb->esc_like( $term ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses plugin-owned custom tables and must remain current.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses plugin-owned custom tables and must remain current.
		return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT e.*, c.name AS customer_name, s.name AS site_name, s.address1 AS site_address, s.city AS site_city, s.state AS site_state
					FROM %i e INNER JOIN %i c ON c.id = e.customer_id INNER JOIN %i s ON s.id = e.site_id
					WHERE (%d = 0 OR e.active = 1) AND (e.equipment_type LIKE %s OR e.brand LIKE %s OR e.model_number LIKE %s OR e.serial_number LIKE %s OR c.name LIKE %s OR s.name LIKE %s)
					ORDER BY c.name, s.name, e.equipment_type, e.brand, e.model_number LIMIT 250',
					$this->table, $customers, $sites, $active, $like, $like, $like, $like, $like, $like
				),
				ARRAY_A
			);
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ServSys operational data uses plugin-owned custom tables and must remain current.
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT e.*, c.name AS customer_name, s.name AS site_name, s.address1 AS site_address, s.city AS site_city, s.state AS site_state
				FROM %i e INNER JOIN %i c ON c.id = e.customer_id INNER JOIN %i s ON s.id = e.site_id
				WHERE (%d = 0 OR e.active = 1) ORDER BY c.name, s.name, e.equipment_type, e.brand, e.model_number LIMIT 250',
				$this->table, $customers, $sites, $active
			),
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
}
