<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace ServSys\Database;

final class Migrator {
	public static function current_version(): int {
		return (int) get_option( 'servsys_db_version', 0 );
	}

	public static function migrate(): void {
		Installer::maybe_upgrade();
	}
}
