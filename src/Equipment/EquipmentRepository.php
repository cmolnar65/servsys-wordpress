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
		return $this->wpdb->get_row(
			$this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ),
			ARRAY_A
		);
	}

	public function for_site( int $site_id, bool $include_inactive = true ): array {
		$sql = $include_inactive
			? $this->wpdb->prepare( 'SELECT * FROM %i WHERE site_id = %d ORDER BY equipment_type, brand, model_number, id', $this->table, $site_id )
			: $this->wpdb->prepare( 'SELECT * FROM %i WHERE site_id = %d AND active = 1 ORDER BY equipment_type, brand, model_number, id', $this->table, $site_id );
		return $this->wpdb->get_results( $sql, ARRAY_A );
	}

	public function search( string $term = '', bool $include_inactive = true ): array {
		$customers = $this->wpdb->prefix . 'servsys_customers';
		$sites = $this->wpdb->prefix . 'servsys_sites';
		if ( '' !== $term ) {
			$like = '%' . $this->wpdb->esc_like( $term ) . '%';
			$active = $include_inactive ? 0 : 1;
			$sql = $this->wpdb->prepare(
				'SELECT e.*, c.name AS customer_name, s.name AS site_name, s.address1 AS site_address, s.city AS site_city, s.state AS site_state
				FROM %i e INNER JOIN %i c ON c.id = e.customer_id INNER JOIN %i s ON s.id = e.site_id
				WHERE (%d = 0 OR e.active = 1) AND (e.equipment_type LIKE %s OR e.brand LIKE %s OR e.model_number LIKE %s OR e.serial_number LIKE %s OR c.name LIKE %s OR s.name LIKE %s)
				ORDER BY c.name, s.name, e.equipment_type, e.brand, e.model_number LIMIT 250',
				$this->table, $customers, $sites, $active, $like, $like, $like, $like, $like, $like
			);
		} else {
			$active = $include_inactive ? 0 : 1;
			$sql = $this->wpdb->prepare(
				'SELECT e.*, c.name AS customer_name, s.name AS site_name, s.address1 AS site_address, s.city AS site_city, s.state AS site_state
				FROM %i e INNER JOIN %i c ON c.id = e.customer_id INNER JOIN %i s ON s.id = e.site_id
				WHERE (%d = 0 OR e.active = 1) ORDER BY c.name, s.name, e.equipment_type, e.brand, e.model_number LIMIT 250',
				$this->table, $customers, $sites, $active
			);
		}
		return $this->wpdb->get_results( $sql, ARRAY_A );
	}

	public function create( array $data ): int {
		$this->wpdb->insert( $this->table, $data );
		return (int) $this->wpdb->insert_id;
	}

	public function update( int $id, array $data ): bool {
		return false !== $this->wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}
}
