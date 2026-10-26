<?php

namespace ServSys\Users;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Capabilities {
	const ACCESS = 'servsys_access';
	const MANAGE_COMPANIES = 'servsys_manage_companies';
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

	public static function install(): void {
		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return;
		}

		foreach ( self::all() as $capability ) {
			$role->add_cap( $capability );
		}
	}

	public static function all(): array {
		return array(
			self::ACCESS,
			self::MANAGE_COMPANIES,
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
		);
	}
}
