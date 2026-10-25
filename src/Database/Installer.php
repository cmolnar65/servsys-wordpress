<?php

namespace ServSys\Database;

use ServSys\Customers\CustomerTypeRepository;

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
