<?php

namespace ServSys\Users;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Capabilities {
	const ACCESS = 'servsys_access';
	const MANAGE_COMPANIES = 'servsys_manage_companies';
	const MANAGE_COMPANY_USERS = 'servsys_manage_company_users';
	const VIEW_CUSTOMERS = 'servsys_view_customers';
	const CREATE_CUSTOMERS = 'servsys_create_customers';
	const EDIT_CUSTOMERS = 'servsys_edit_customers';
	const CREATE_SITES = 'servsys_create_sites';
	const EDIT_SITES = 'servsys_edit_sites';
	const VIEW_EQUIPMENT = 'servsys_view_equipment';
	const CREATE_EQUIPMENT = 'servsys_create_equipment';
	const EDIT_EQUIPMENT = 'servsys_edit_equipment';
	const VIEW_SERVICE_CALLS = 'servsys_view_service_calls';
	const CREATE_SERVICE_CALLS = 'servsys_create_service_calls';
	const EDIT_SERVICE_CALLS = 'servsys_edit_service_calls';
	const VIEW_INVENTORY = 'servsys_view_inventory';
	const MANAGE_INVENTORY = 'servsys_manage_inventory';
	const ADJUST_INVENTORY = 'servsys_adjust_inventory';
	const USE_INVENTORY = 'servsys_use_inventory';
	const VIEW_FLAT_RATE = 'servsys_view_flat_rate';
	const MANAGE_FLAT_RATE = 'servsys_manage_flat_rate';
	const VIEW_PM_PLANS = 'servsys_view_pm_plans';
	const MANAGE_PM_PLANS = 'servsys_manage_pm_plans';
	const VIEW_PM_AGREEMENTS = 'servsys_view_pm_agreements';
	const MANAGE_PM_AGREEMENTS = 'servsys_manage_pm_agreements';

	public static function install(): void {
		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return;
		}

		foreach ( self::all() as $capability ) {
			$role->add_cap( $capability );
		}
	}

	public static function company_capabilities(): array {
		return array(
			self::ACCESS, self::MANAGE_COMPANY_USERS, self::VIEW_CUSTOMERS, self::CREATE_CUSTOMERS,
			self::EDIT_CUSTOMERS, self::CREATE_SITES, self::EDIT_SITES, self::VIEW_EQUIPMENT,
			self::CREATE_EQUIPMENT, self::EDIT_EQUIPMENT, self::VIEW_SERVICE_CALLS,
			self::CREATE_SERVICE_CALLS, self::EDIT_SERVICE_CALLS, self::VIEW_INVENTORY,
			self::MANAGE_INVENTORY, self::ADJUST_INVENTORY, self::USE_INVENTORY, self::VIEW_FLAT_RATE,
			self::MANAGE_FLAT_RATE, self::VIEW_PM_PLANS, self::MANAGE_PM_PLANS,
			self::VIEW_PM_AGREEMENTS, self::MANAGE_PM_AGREEMENTS
		);
	}

	public static function all(): array {
		return array(
			self::ACCESS,
			self::MANAGE_COMPANIES,
			self::MANAGE_COMPANY_USERS,
			self::VIEW_CUSTOMERS,
			self::CREATE_CUSTOMERS,
			self::EDIT_CUSTOMERS,
			self::CREATE_SITES,
			self::EDIT_SITES,
			self::VIEW_EQUIPMENT,
			self::CREATE_EQUIPMENT,
			self::EDIT_EQUIPMENT,
			self::VIEW_SERVICE_CALLS,
			self::CREATE_SERVICE_CALLS,
			self::EDIT_SERVICE_CALLS,
			self::VIEW_INVENTORY,
			self::MANAGE_INVENTORY,
			self::ADJUST_INVENTORY,
			self::USE_INVENTORY,
			self::VIEW_FLAT_RATE,
			self::MANAGE_FLAT_RATE,
			self::VIEW_PM_PLANS,
			self::MANAGE_PM_PLANS,
			self::VIEW_PM_AGREEMENTS,
			self::MANAGE_PM_AGREEMENTS,
		);
	}
}
