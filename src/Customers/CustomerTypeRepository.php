<?php

namespace ServSys\Customers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CustomerTypeRepository {
	private $wpdb;
	private $table;
	private const CACHE_GROUP = 'servsys';
	private const CACHE_KEY_ALL = 'customer_type_all';
	private const CACHE_KEY_ACTIVE = 'customer_type_active';

	public function __construct( $wpdb = null ) {
		$this->wpdb  = $wpdb ?: $GLOBALS['wpdb'];
		$this->table = $this->wpdb->prefix . 'servsys_customer_types';
	}

	public function all( bool $include_inactive = true ): array {
		$key = $include_inactive ? self::CACHE_KEY_ALL : self::CACHE_KEY_ACTIVE;
		$cached = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}
		$results = $include_inactive
			? $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT * FROM %i ORDER BY sort_order ASC, name ASC', $this->table ), ARRAY_A )
			: $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT * FROM %i WHERE active = 1 ORDER BY sort_order ASC, name ASC', $this->table ), ARRAY_A );
		wp_cache_set( $key, $results, self::CACHE_GROUP );
		return $results;
	}

	public function find( int $id ) {
		$key = 'customer_type_' . $id;
		$cached = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}
		$record = $this->wpdb->get_row(
			$this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ),
			ARRAY_A
		);
		wp_cache_set( $key, $record, self::CACHE_GROUP );
		return $record;
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
			$this->clear_cache( $id );
			return $id;
		}

		$data['created_at'] = $now;
		if ( false === $this->wpdb->insert( $this->table, $data ) ) {
			throw new \RuntimeException( 'Unable to create customer type.' );
		}
		$id = (int) $this->wpdb->insert_id;
		$this->clear_cache( $id );
		return $id;
	}

	private function clear_cache( int $id = 0 ): void {
		wp_cache_delete( self::CACHE_KEY_ALL, self::CACHE_GROUP );
		wp_cache_delete( self::CACHE_KEY_ACTIVE, self::CACHE_GROUP );
		if ( $id ) {
			wp_cache_delete( 'customer_type_' . $id, self::CACHE_GROUP );
		}
	}
}
