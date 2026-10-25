<?php

namespace ServSys\Database;

use ServSys\Customers\CustomerTypeRepository;
use ServSys\Equipment\EquipmentTypeRepository;

final class Installer {
	public static function activate(): void {
		self::install_schema();
		\ServSys\Users\Capabilities::install();
	}

	public static function maybe_upgrade(): void {
		$installed = (int) get_option( 'servsys_db_version', 0 );

		if ( $installed < SERVSYS_DB_VERSION ) {
			self::install_schema();
			\ServSys\Users\Capabilities::install();
		}
	}

	private static function install_schema(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( Schema::tables( $wpdb ) as $sql ) {
			dbDelta( $sql );
		}

		self::seed_customer_types( $wpdb );
		self::seed_equipment_types( $wpdb );
		self::preserve_equipment_types( $wpdb );
		self::merge_duplicate_equipment_types( $wpdb );
		self::migrate_equipment_types( $wpdb );
		self::preserve_legacy_customer_types( $wpdb );
		self::migrate_customer_types( $wpdb );

		update_option( 'servsys_db_version', SERVSYS_DB_VERSION, false );
	}

	private static function seed_customer_types( $wpdb ): void {
		$table = $wpdb->prefix . 'servsys_customer_types';
		$now   = current_time( 'mysql', true );

		foreach ( array( 'Residential', 'Commercial', 'Agricultural' ) as $order => $name ) {
			$slug = sanitize_title( $name );
			$id   = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $slug ) );
			if ( ! $id ) {
				$wpdb->insert(
					$table,
					array(
						'name'       => $name,
						'slug'       => $slug,
						'sort_order' => ( $order + 1 ) * 10,
						'active'     => 1,
						'created_at' => $now,
						'updated_at' => $now,
					)
				);
			}
		}
	}

	private static function seed_equipment_types( $wpdb ): void {
		$table = $wpdb->prefix . 'servsys_equipment_types';
		$now = current_time( 'mysql', true );
		$names = array( 'Air Conditioner', 'Air Handler', 'Boiler', 'Furnace', 'Heat Pump', 'Mini-Split', 'Package Unit', 'Water Heater', 'Other' );
		foreach ( $names as $order => $name ) {
			$slug = sanitize_title( $name );
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $slug ) ) ) {
				$wpdb->insert( $table, array( 'name' => $name, 'slug' => $slug, 'sort_order' => ( $order + 1 ) * 10, 'active' => 1, 'created_at' => $now, 'updated_at' => $now ) );
			}
		}
	}

	private static function preserve_equipment_types( $wpdb ): void {
		$equipment = $wpdb->prefix . 'servsys_equipment';
		$types = $wpdb->prefix . 'servsys_equipment_types';
		$now = current_time( 'mysql', true );
		$names = $wpdb->get_col( "SELECT DISTINCT TRIM(equipment_type) FROM {$equipment} WHERE equipment_type IS NOT NULL AND TRIM(equipment_type) <> ''" );
		foreach ( $names as $name ) {
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$types} WHERE LOWER(name) = LOWER(%s)", $name ) ) ) {
				$slug = sanitize_title( $name );
				if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$types} WHERE slug = %s", $slug ) ) ) {
					$slug .= '-' . substr( md5( $name ), 0, 8 );
				}
				$wpdb->insert( $types, array( 'name' => $name, 'slug' => $slug, 'sort_order' => 100, 'active' => 1, 'created_at' => $now, 'updated_at' => $now ) );
			}
		}
	}

	private static function merge_duplicate_equipment_types( $wpdb ): void {
		$equipment = $wpdb->prefix . 'servsys_equipment';
		$types = $wpdb->prefix . 'servsys_equipment_types';
		$groups = $wpdb->get_results(
			"SELECT LOWER(TRIM(name)) AS normalized_name, GROUP_CONCAT(id ORDER BY active DESC, id ASC) AS ids, COUNT(*) AS type_count
			FROM {$types}
			GROUP BY LOWER(TRIM(name))
			HAVING type_count > 1",
			ARRAY_A
		);

		foreach ( $groups as $group ) {
			$ids = array_values( array_filter( array_map( 'absint', explode( ',', $group['ids'] ) ) ) );
			if ( count( $ids ) < 2 ) {
				continue;
			}
			$keep_id = array_shift( $ids );
			foreach ( $ids as $duplicate_id ) {
				$wpdb->update( $equipment, array( 'equipment_type_id' => $keep_id ), array( 'equipment_type_id' => $duplicate_id ) );
				$wpdb->delete( $types, array( 'id' => $duplicate_id ) );
			}
		}
	}

	private static function migrate_equipment_types( $wpdb ): void {
		$equipment = $wpdb->prefix . 'servsys_equipment';
		$types = $wpdb->prefix . 'servsys_equipment_types';
		$wpdb->query( "UPDATE {$equipment} e INNER JOIN {$types} t ON LOWER(TRIM(e.equipment_type)) = LOWER(TRIM(t.name)) SET e.equipment_type_id = t.id WHERE e.equipment_type_id IS NULL AND e.equipment_type IS NOT NULL AND e.equipment_type <> ''" );
	}

	private static function preserve_legacy_customer_types( $wpdb ): void {
		$customers = $wpdb->prefix . 'servsys_customers';
		$types     = $wpdb->prefix . 'servsys_customer_types';
		$now       = current_time( 'mysql', true );
		$names     = $wpdb->get_col( "SELECT DISTINCT TRIM(customer_type) FROM {$customers} WHERE customer_type IS NOT NULL AND TRIM(customer_type) <> ''" );

		foreach ( $names as $name ) {
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$types} WHERE LOWER(name) = LOWER(%s)", $name ) );
			if ( ! $existing ) {
				$slug = sanitize_title( $name );
				if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$types} WHERE slug = %s", $slug ) ) ) {
					$slug .= '-' . substr( md5( $name ), 0, 8 );
				}
				$wpdb->insert(
					$types,
					array(
						'name'       => $name,
						'slug'       => $slug,
						'sort_order' => 100,
						'active'     => 1,
						'created_at' => $now,
						'updated_at' => $now,
					)
				);
			}
		}
	}

	private static function migrate_customer_types( $wpdb ): void {
		$customers = $wpdb->prefix . 'servsys_customers';
		$types     = $wpdb->prefix . 'servsys_customer_types';

		$wpdb->query(
			"UPDATE {$customers} c
			INNER JOIN {$types} t ON LOWER(TRIM(c.customer_type)) = LOWER(TRIM(t.name))
			SET c.customer_type_id = t.id
			WHERE c.customer_type_id IS NULL AND c.customer_type IS NOT NULL AND c.customer_type <> ''"
		);
	}
}
