<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace ServSys\Users;

final class Capabilities {
	const ACCESS = 'servsys_access';
	const VIEW_CUSTOMERS = 'servsys_view_customers';
	const CREATE_CUSTOMERS = 'servsys_create_customers';
	const EDIT_CUSTOMERS = 'servsys_edit_customers';
	const CREATE_SITES = 'servsys_create_sites';
	const EDIT_SITES = 'servsys_edit_sites';
	const VIEW_EQUIPMENT = 'servsys_view_equipment';
	const CREATE_EQUIPMENT = 'servsys_create_equipment';
	const EDIT_EQUIPMENT = 'servsys_edit_equipment';

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
			self::VIEW_CUSTOMERS,
			self::CREATE_CUSTOMERS,
			self::EDIT_CUSTOMERS,
			self::CREATE_SITES,
			self::EDIT_SITES,
			self::VIEW_EQUIPMENT,
			self::CREATE_EQUIPMENT,
			self::EDIT_EQUIPMENT,
		);
	}
}
