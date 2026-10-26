<?php

namespace ServSys\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ServSys\Users\Capabilities;
use ServSys\Users\Authorization;

final class ConfigurationPage {
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
	}

	public function register_menu(): void {
		add_submenu_page(
			'servsys',
			'Configuration',
			'Configuration',
			Capabilities::ACCESS,
			'servsys-configuration',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::ACCESS ) ) {
			wp_die( esc_html__( 'You do not have permission to access ServSys configuration.', 'servsys' ) );
		}

		echo '<div class="wrap"><h1>ServSys Configuration</h1>';
		echo '<p>Manage company setup, ServSys users, and configurable lookup values.</p>';
		echo '<div class="card"><h2>Configuration</h2><ul>';

		if ( Authorization::is_wordpress_admin() ) {
			$this->link( 'Companies', 'Create and maintain ServSys company records and assign Company Administrators.', 'servsys-companies' );
		}

		if ( current_user_can( Capabilities::MANAGE_COMPANY_USERS ) ) {
			$this->link( 'Company Users', 'Manage ServSys users and company-specific permissions.', 'servsys-company-users' );
		}

		if ( current_user_can( Capabilities::EDIT_CUSTOMERS ) ) {
			$this->link( 'Customer Types', 'Manage the customer type lookup values used by ServSys.', 'servsys-customer-types' );
		}

		if ( current_user_can( Capabilities::VIEW_INVENTORY ) ) {
			$this->link( 'Inventory Items', 'Manage company inventory catalog, identification, costs, and calculated selling prices.', 'servsys-inventory-items' );
			$this->link( 'Inventory Locations', 'Manage company warehouses, shops, trucks, and technician inventory locations.', 'servsys-inventory-locations' );
			$this->link( 'Inventory History & Reporting', 'Review current stock, filtered transaction history, and quantity movement.', 'servsys-inventory-reports' );
		}

		if ( current_user_can( Capabilities::VIEW_FLAT_RATE ) ) {
			$this->link( 'Flat Rate Pricing', 'Manage company Flat Rate configuration, categories, price-book items, and material recipes.', 'servsys-flat-rate' );
		}

		if ( current_user_can( Capabilities::VIEW_PM_PLANS ) ) {
			$this->link( 'Preventive Maintenance Plans', 'Manage company PM plan templates and term pricing.', 'servsys-pm-plans' );
		}

		if ( current_user_can( Capabilities::EDIT_EQUIPMENT ) ) {
			$this->link( 'Equipment Types', 'Manage the equipment type lookup values used by ServSys.', 'servsys-equipment-types' );
		}

		echo '</ul></div></div>';
	}

	private function link( string $label, string $description, string $page ): void {
		echo '<li><p><a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=' . $page ) ) . '">' . esc_html( $label ) . '</a><br><span class="description">' . esc_html( $description ) . '</span></p></li>';
	}
}
