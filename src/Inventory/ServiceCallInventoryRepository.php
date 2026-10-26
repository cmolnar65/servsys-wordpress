<?php

namespace ServSys\Inventory;

use ServSys\Companies\CompanyContext;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ServiceCallInventoryRepository {
	private $wpdb;
	private $table;

	public function __construct( $wpdb = null ) {
		$this->wpdb=$wpdb ?: $GLOBALS['wpdb'];
		$this->table=$this->wpdb->prefix . 'servsys_service_call_inventory';
	}

	public function create( array $data ): int {
		$data['company_id']=CompanyContext::current_company_id();
		$this->wpdb->insert($this->table,$data);
		return (int)$this->wpdb->insert_id;
	}

	public function for_service_call( int $service_call_id ): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Service call inventory usage must remain current.
		return $this->wpdb->get_results($this->wpdb->prepare('SELECT * FROM %i WHERE company_id = %d AND service_call_id = %d ORDER BY created_at,id',$this->table,CompanyContext::current_company_id(),$service_call_id),ARRAY_A);
	}

	public function find( int $id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Service call inventory usage must remain current.
		return $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM %i WHERE company_id = %d AND id = %d',$this->table,CompanyContext::current_company_id(),$id),ARRAY_A);
	}
}
