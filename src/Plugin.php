<?php

namespace ServSys;

use ServSys\Admin\CustomersPage;
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
		}

		add_action(
			'rest_api_init',
			function () {
				( new CustomersController() )->register_routes();
			}
		);
	}
}
