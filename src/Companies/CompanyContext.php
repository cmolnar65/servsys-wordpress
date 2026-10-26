<?php
namespace ServSys\Companies;
use ServSys\Users\Authorization;
use ServSys\Users\CompanyUserRepository;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class CompanyContext {
	public static function current_company_id(): int {
		$user_id = get_current_user_id();
		$id = $user_id ? absint( get_user_meta( $user_id, 'servsys_company_id', true ) ) : 0;
		if ( $id && Authorization::can_access_company( $id, $user_id ) ) { return $id; }
		if ( Authorization::is_wordpress_admin( $user_id ) ) { return 1; }
		$memberships=(new CompanyUserRepository())->companies_for_user($user_id);
		return $memberships ? (int)$memberships[0]['company_id'] : 0;
	}
	public static function set_current_company_id( int $id ): bool {
		$company = ( new CompanyRepository() )->find_unscoped( $id );
		if ( ! $company || empty( $company['active'] ) || ! Authorization::can_access_company( $id ) ) { return false; }
		return false !== update_user_meta( get_current_user_id(), 'servsys_company_id', $id );
	}
}
