<?php

namespace ServSys\Customers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CustomerTypeRepository {
	private $wpdb;
	private $table;

	public function __construct( $wpdb = null ) {
		$this->wpdb  = $wpdb ?: $GLOBALS['wpdb'];
		$this->table = $this->wpdb->prefix . 'servsys_customer_types';
	}

	public function all( bool $include_inactive = true ): array {
		$sql = $include_inactive
			? $this->wpdb->prepare( 'SELECT * FROM %i ORDER BY sort_order ASC, name ASC', $this->table )
			: $this->wpdb->prepare( 'SELECT * FROM %i WHERE active = 1 ORDER BY sort_order ASC, name ASC', $this->table );
		return $this->wpdb->get_results( $sql, ARRAY_A );
	}

	public function find( int $id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ),
			ARRAY_A
		);
	}

	public function save( array $input, int $id = 0 ): int {
		$name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';
		if ( '' === $name ) {
			throw new \InvalidArgumentException( 'Customer type name is required.' );
		}

		$now  = current_time( 'mysql', true );
		$slug = sanitize_title( $name );
		$data = array(
			'name'       => $name,
			'slug'       => $slug,
			'sort_order' => isset( $input['sort_order'] ) ? (int) $input['sort_order'] : 0,
			'active'     => isset( $input['active'] ) ? (int) (bool) $input['active'] : 0,
			'updated_at' => $now,
		);

		if ( $id ) {
			if ( false === $this->wpdb->update( $this->table, $data, array( 'id' => $id ) ) ) {
				throw new \RuntimeException( 'Unable to update customer type.' );
			}
			return $id;
		}

		$data['created_at'] = $now;
		if ( false === $this->wpdb->insert( $this->table, $data ) ) {
			throw new \RuntimeException( 'Unable to create customer type.' );
		}
		return (int) $this->wpdb->insert_id;
	}
}
