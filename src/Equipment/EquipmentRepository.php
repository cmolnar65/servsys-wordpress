<?php

namespace ServSys\Equipment;

final class EquipmentRepository {
	private $wpdb;
	private $table;

	public function __construct( $wpdb = null ) {
		$this->wpdb  = $wpdb ?: $GLOBALS['wpdb'];
		$this->table = $this->wpdb->prefix . 'servsys_equipment';
	}

	public function find( int $id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ),
			ARRAY_A
		);
	}

	public function for_site( int $site_id, bool $include_inactive = true ): array {
		$active = $include_inactive ? '' : ' AND active = 1';
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE site_id = %d{$active} ORDER BY equipment_type, brand, model_number, id",
				$site_id
			),
			ARRAY_A
		);
	}

	public function search( string $term = '', bool $include_inactive = true ): array {
		$where = $include_inactive ? '1=1' : 'e.active = 1';
		$args  = array();

		if ( '' !== $term ) {
			$like   = '%' . $this->wpdb->esc_like( $term ) . '%';
			$where .= ' AND (e.equipment_type LIKE %s OR e.brand LIKE %s OR e.model_number LIKE %s OR e.serial_number LIKE %s OR c.name LIKE %s OR s.name LIKE %s)';
			$args   = array_fill( 0, 6, $like );
		}

		$sql = "SELECT e.*, c.name AS customer_name, s.name AS site_name, s.address1 AS site_address, s.city AS site_city, s.state AS site_state
			FROM {$this->table} e
			INNER JOIN {$this->wpdb->prefix}servsys_customers c ON c.id = e.customer_id
			INNER JOIN {$this->wpdb->prefix}servsys_sites s ON s.id = e.site_id
			WHERE {$where}
			ORDER BY c.name, s.name, e.equipment_type, e.brand, e.model_number
			LIMIT 250";

		if ( $args ) {
			$sql = $this->wpdb->prepare( $sql, ...$args );
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
