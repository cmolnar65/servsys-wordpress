<?php

namespace ServSys;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ServSys\Admin\CustomersPage;
use ServSys\Admin\EquipmentPage;
use ServSys\Database\Installer;
use ServSys\REST\CustomersController;

final class Plugin {
	private static $instance;

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function register_hooks(): void {
		add_action( 'plugins_loaded', array( Installer::class, 'maybe_upgrade' ) );

		if ( is_admin() ) {
			( new CustomersPage() )->register();
			( new EquipmentPage() )->register();
		}

		add_action(
			'rest_api_init',
			function () {
				( new CustomersController() )->register_routes();
			}
		);
	}
}
