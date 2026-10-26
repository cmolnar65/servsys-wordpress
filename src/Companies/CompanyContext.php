<?php
namespace ServSys\Companies;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class CompanyContext {
	public static function current_company_id(): int {
		$user_id = get_current_user_id();
		$id = $user_id ? absint( get_user_meta( $user_id, 'servsys_company_id', true ) ) : 0;
		return $id ?: 1;
	}
	public static function set_current_company_id( int $id ): bool {
		$company = ( new CompanyRepository() )->find_unscoped( $id );
		if ( ! $company || empty( $company['active'] ) ) { return false; }
		return false !== update_user_meta( get_current_user_id(), 'servsys_company_id', $id );
	}
}
