<?php

namespace ServSys\Equipment;

final class EquipmentTypeRepository {
	private $wpdb;
	private $table;

	public function __construct( $wpdb = null ) {
		$this->wpdb  = $wpdb ?: $GLOBALS['wpdb'];
		$this->table = $this->wpdb->prefix . 'servsys_equipment_types';
	}

	public function all( bool $include_inactive = true ): array {
		$where = $include_inactive ? '1=1' : 'active = 1';
		return $this->wpdb->get_results( "SELECT * FROM {$this->table} WHERE {$where} ORDER BY sort_order ASC, name ASC", ARRAY_A );
	}

	public function find( int $id ) {
		return $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ), ARRAY_A );
	}

	public function save( array $input, int $id = 0 ): int {
		$name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';
		if ( '' === $name ) {
			throw new \InvalidArgumentException( 'Equipment type name is required.' );
		}
		$now = current_time( 'mysql', true );
		$data = array(
			'name' => $name,
			'slug' => sanitize_title( $name ),
			'sort_order' => isset( $input['sort_order'] ) ? (int) $input['sort_order'] : 0,
			'active' => isset( $input['active'] ) ? (int) (bool) $input['active'] : 0,
			'updated_at' => $now,
		);
		if ( $id ) {
			if ( false === $this->wpdb->update( $this->table, $data, array( 'id' => $id ) ) ) {
				throw new \RuntimeException( 'Unable to update equipment type.' );
			}
			return $id;
		}
		$data['created_at'] = $now;
		if ( false === $this->wpdb->insert( $this->table, $data ) ) {
			throw new \RuntimeException( 'Unable to create equipment type.' );
		}
		return (int) $this->wpdb->insert_id;
	}
}
