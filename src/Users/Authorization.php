<?php
namespace ServSys\Users;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Authorization {
	public static function is_wordpress_admin( int $user_id=0 ): bool {
		$user=$user_id ? get_userdata($user_id) : wp_get_current_user();
		return $user && in_array( 'administrator',(array)$user->roles,true );
	}
	public static function can_access_company( int $company_id, int $user_id=0 ): bool {
		$user_id=$user_id ?: get_current_user_id();
		if ( ! $user_id ) { return false; }
		if ( self::is_wordpress_admin($user_id) ) { return true; }
		$m=(new CompanyUserRepository())->find($company_id,$user_id);
		return $m && ! empty($m['active']);
	}
	public static function is_company_admin( int $company_id, int $user_id=0 ): bool {
		$user_id=$user_id ?: get_current_user_id();
		if ( self::is_wordpress_admin($user_id) ) { return true; }
		$m=(new CompanyUserRepository())->find($company_id,$user_id);
		return $m && ! empty($m['active']) && ! empty($m['is_company_admin']);
	}
	public static function can( string $capability, int $company_id=0, int $user_id=0 ): bool {
		$user_id=$user_id ?: get_current_user_id(); $company_id=$company_id ?: \ServSys\Companies\CompanyContext::current_company_id();
		if ( self::is_wordpress_admin($user_id) ) { return true; }
		$m=(new CompanyUserRepository())->find($company_id,$user_id);
		if ( ! $m || empty($m['active']) ) { return false; }
		if ( ! empty($m['is_company_admin']) ) { return in_array($capability,Capabilities::company_capabilities(),true); }
		$permissions=json_decode((string)$m['permissions'],true);
		return is_array($permissions) && in_array($capability,$permissions,true);
	}
	public static function filter_user_caps( array $allcaps, array $caps, array $args, $user ): array {
		foreach ( Capabilities::company_capabilities() as $capability ) {
			if ( self::is_wordpress_admin( (int) $user->ID ) ) { $allcaps[$capability]=true; continue; }
			$allcaps[$capability]=self::can($capability,0,(int)$user->ID);
		}
		if ( ! self::is_wordpress_admin( (int) $user->ID ) ) { $allcaps[Capabilities::MANAGE_COMPANIES]=false; }
		return $allcaps;
	}
}
