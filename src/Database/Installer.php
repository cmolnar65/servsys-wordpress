<?php

namespace ServSys\Database;

final class Installer {
	public static function activate(): void {
		self::install_schema();
		\ServSys\Users\Capabilities::install();
	}

	public static function maybe_upgrade(): void {
		$installed = (int) get_option( 'servsys_db_version', 0 );

		if ( $installed < SERVSYS_DB_VERSION ) {
			self::install_schema();
		}
	}

	private static function install_schema(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( Schema::tables( $wpdb ) as $sql ) {
			dbDelta( $sql );
		}

		update_option( 'servsys_db_version', SERVSYS_DB_VERSION, false );
	}
}
