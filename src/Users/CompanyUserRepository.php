<?php
namespace ServSys\Users;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class CompanyUserRepository {
	private $wpdb; private $table;
	public function __construct( $wpdb=null ) { $this->wpdb=$wpdb ?: $GLOBALS['wpdb']; $this->table=$this->wpdb->prefix . 'servsys_company_users'; }
	public function find( int $company_id, int $user_id ) {
		$wpdb=$this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Authorization membership must be current.
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE company_id=%d AND user_id=%d', $this->table,$company_id,$user_id ), ARRAY_A );
	}
	public function for_company( int $company_id ): array {
		$wpdb=$this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Authorization membership must be current.
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE company_id=%d ORDER BY is_company_admin DESC,employee_name,user_id', $this->table,$company_id ), ARRAY_A );
	}
	public function companies_for_user( int $user_id ): array {
		$wpdb=$this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Authorization membership must be current.
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id=%d AND active=1 ORDER BY company_id', $this->table,$user_id ), ARRAY_A );
	}
	public function save( array $data, int $id=0 ): int {
		if ( $id ) { $this->wpdb->update( $this->table,$data,array('id'=>$id) ); return $id; }
		$this->wpdb->insert( $this->table,$data ); return (int)$this->wpdb->insert_id;
	}
}
