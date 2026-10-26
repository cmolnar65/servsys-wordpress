<?php

namespace ServSys\Equipment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EquipmentTypeRepository {
	private $wpdb;
	private $table;
	private const CACHE_GROUP = 'servsys';
	private const CACHE_KEY_ALL = 'equipment_type_all';
	private const CACHE_KEY_ACTIVE = 'equipment_type_active';

	public function __construct( $wpdb = null ) {
		$this->wpdb  = $wpdb ?: $GLOBALS['wpdb'];
		$this->table = $this->wpdb->prefix . 'servsys_equipment_types';
	}

	public function all( bool $include_inactive = true ): array {
		$wpdb = $this->wpdb;
		$key = $include_inactive ? self::CACHE_KEY_ALL : self::CACHE_KEY_ACTIVE;
		$cached = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		} 
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Lookup reads use ServSys plugin-owned custom tables; results are cached immediately below.

		$results = $include_inactive
			? $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY sort_order ASC, name ASC', $this->table ), ARRAY_A )
			: $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE active = 1 ORDER BY sort_order ASC, name ASC', $this->table ), ARRAY_A );
		wp_cache_set( $key, $results, self::CACHE_GROUP );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $results;
	}

	public function find( int $id ) {
		$wpdb = $this->wpdb;
		$key = 'equipment_type_' . $id;
		$cached = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Lookup read uses a ServSys plugin-owned custom table and is cached immediately below.
		$record = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ), ARRAY_A );
		wp_cache_set( $key, $record, self::CACHE_GROUP );
		return $record;
	}

	public function save( array $input, int $id = 0 ): int {
		$wpdb = $this->wpdb;
		$name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';
		if ( '' === $name ) {
			throw new \InvalidArgumentException( 'Equipment type name is required.' );
		}
		$slug = sanitize_title( $name );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh duplicate check is required immediately before the write.
		$duplicate_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE (LOWER(TRIM(name)) = LOWER(TRIM(%s)) OR slug = %s) AND id <> %d LIMIT 1',
				$this->table,
				$name,
				$slug,
				$id
			)
		);
		if ( $duplicate_id ) {
			throw new \InvalidArgumentException( 'An equipment type with this name already exists.' );
		}

		$now = current_time( 'mysql', true );
		$data = array(
			'name' => $name,
			'slug' => $slug,
			'sort_order' => isset( $input['sort_order'] ) ? (int) $input['sort_order'] : 0,
			'active' => isset( $input['active'] ) ? (int) (bool) $input['active'] : 0,
			'updated_at' => $now,
		);
		if ( $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lookup write invalidates the related cache immediately below.
			if ( false === $wpdb->update( $this->table, $data, array( 'id' => $id ) ) ) {
				throw new \RuntimeException( 'Unable to update equipment type.' );
			}
			$this->clear_cache( $id );
			return $id;
		}
		$data['created_at'] = $now;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Lookup row insert is data, not schema; cache is invalidated below.
		if ( false === $wpdb->insert( $this->table, $data ) ) {
			throw new \RuntimeException( 'Unable to create equipment type.' );
		}
		$id = (int) $wpdb->insert_id;
		$this->clear_cache( $id );
		return $id;
	}

	private function clear_cache( int $id = 0 ): void {
		wp_cache_delete( self::CACHE_KEY_ALL, self::CACHE_GROUP );
		wp_cache_delete( self::CACHE_KEY_ACTIVE, self::CACHE_GROUP );
		if ( $id ) {
			wp_cache_delete( 'equipment_type_' . $id, self::CACHE_GROUP );
		}
	}
}
