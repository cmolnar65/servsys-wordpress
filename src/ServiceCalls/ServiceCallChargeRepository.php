<?php
namespace ServSys\ServiceCalls;
use ServSys\Companies\CompanyContext;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class ServiceCallChargeRepository {
 private $wpdb; private $table;
 public function __construct($wpdb=null){$this->wpdb=$wpdb?:$GLOBALS['wpdb'];$this->table=$this->wpdb->prefix.'servsys_service_call_charges';}
 public function find(int $id){$wpdb=$this->wpdb;/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Current plugin-owned Service Call charge data. */return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d AND company_id=%d',$this->table,$id,CompanyContext::current_company_id()),ARRAY_A);}
 public function for_service_call(int $service_call_id): array {$wpdb=$this->wpdb;/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Current plugin-owned Service Call charge data. */return $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE service_call_id=%d AND company_id=%d ORDER BY id',$this->table,$service_call_id,CompanyContext::current_company_id()),ARRAY_A);}
 public function create(array $data): int {$data['company_id']=CompanyContext::current_company_id();$this->wpdb->insert($this->table,$data);return (int)$this->wpdb->insert_id;}
 public function total_for_service_call(int $service_call_id): float {$wpdb=$this->wpdb;/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Authoritative current charge total from immutable snapshots. */return (float)$wpdb->get_var($wpdb->prepare('SELECT COALESCE(SUM(extended_price),0) FROM %i WHERE service_call_id=%d AND company_id=%d',$this->table,$service_call_id,CompanyContext::current_company_id()));}
}
