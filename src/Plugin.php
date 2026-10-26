<?php

namespace ServSys;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ServSys\Admin\CompaniesPage;
use ServSys\Admin\ConfigurationPage;
use ServSys\Admin\CompanyUsersPage;
use ServSys\Admin\CustomersPage;
use ServSys\Admin\EquipmentPage;
use ServSys\Admin\InventoryLocationsPage;
use ServSys\Admin\InventoryItemsPage;
use ServSys\Admin\ServiceCallsPage;
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
		add_filter( 'user_has_cap', array( \ServSys\Users\Authorization::class, 'filter_user_caps' ), 20, 4 );
		add_action( 'plugins_loaded', array( Installer::class, 'maybe_upgrade' ) );

		if ( is_admin() ) {
			( new CustomersPage() )->register();
			( new ConfigurationPage() )->register();
			( new CompaniesPage() )->register();
			( new CompanyUsersPage() )->register();
			( new EquipmentPage() )->register();
			( new InventoryLocationsPage() )->register();
			( new InventoryItemsPage() )->register();
			( new ServiceCallsPage() )->register();
		}

		add_action(
			'rest_api_init',
			function () {
				( new CustomersController() )->register_routes();
			}
		);
	}
}
