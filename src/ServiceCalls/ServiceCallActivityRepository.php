<?php
namespace ServSys\ServiceCalls;
use ServSys\Companies\CompanyContext;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class ServiceCallActivityRepository {
 private $wpdb; private $table;
 public function __construct($wpdb=null){$this->wpdb=$wpdb?:$GLOBALS['wpdb'];$this->table=$this->wpdb->prefix.'servsys_service_call_activity';}
 public function find(int $id){/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Current technician activity data. */return $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM %i WHERE id=%d AND company_id=%d',$this->table,$id,CompanyContext::current_company_id()),ARRAY_A);}
 public function for_service_call(int $service_call_id): array {/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Current technician activity data. */return $this->wpdb->get_results($this->wpdb->prepare('SELECT * FROM %i WHERE service_call_id=%d AND company_id=%d ORDER BY COALESCE(dispatched_at,work_started_at,created_at),id',$this->table,$service_call_id,CompanyContext::current_company_id()),ARRAY_A);}
 public function create(array $data): int {$data['company_id']=CompanyContext::current_company_id();$this->wpdb->insert($this->table,$data);return (int)$this->wpdb->insert_id;}
 public function update(int $id,array $data): bool {return false!==$this->wpdb->update($this->table,$data,array('id'=>$id,'company_id'=>CompanyContext::current_company_id()));}
 public function has_open_activity(int $service_call_id): bool {/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Closeout validation requires current activity state. */return (bool)$this->wpdb->get_var($this->wpdb->prepare('SELECT id FROM %i WHERE service_call_id=%d AND company_id=%d AND work_started_at IS NOT NULL AND work_ended_at IS NULL LIMIT 1',$this->table,$service_call_id,CompanyContext::current_company_id()));}
}
