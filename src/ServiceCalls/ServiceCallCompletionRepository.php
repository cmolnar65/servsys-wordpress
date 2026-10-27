<?php
namespace ServSys\ServiceCalls;
use ServSys\Companies\CompanyContext;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class ServiceCallCompletionRepository {
 private $wpdb; private $table; private $codes;
 public function __construct($wpdb=null){$this->wpdb=$wpdb?:$GLOBALS['wpdb'];$this->table=$this->wpdb->prefix.'servsys_service_call_completion_codes';$this->codes=$this->wpdb->prefix.'servsys_completion_codes';}
 public function for_service_call(int $service_call_id): array {$wpdb=$this->wpdb;/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Current Service Call completion relationships. */return $wpdb->get_results($wpdb->prepare('SELECT r.*,c.description,c.active FROM %i r INNER JOIN %i c ON c.id=r.completion_code_id AND c.company_id=r.company_id WHERE r.service_call_id=%d AND r.company_id=%d ORDER BY c.sort_order,c.description,c.id',$this->table,$this->codes,$service_call_id,CompanyContext::current_company_id()),ARRAY_A);}
 public function exists(int $service_call_id,int $completion_code_id): bool {$wpdb=$this->wpdb;/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Duplicate relationship validation. */return (bool)$wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE service_call_id=%d AND completion_code_id=%d AND company_id=%d LIMIT 1',$this->table,$service_call_id,$completion_code_id,CompanyContext::current_company_id()));}
 public function add(int $service_call_id,int $completion_code_id): int {$this->wpdb->insert($this->table,array('company_id'=>CompanyContext::current_company_id(),'service_call_id'=>$service_call_id,'completion_code_id'=>$completion_code_id,'created_at'=>current_time('mysql',true),'created_by'=>get_current_user_id()?:null));return (int)$this->wpdb->insert_id;}
 public function remove(int $service_call_id,int $completion_code_id): bool {return false!==$this->wpdb->delete($this->table,array('company_id'=>CompanyContext::current_company_id(),'service_call_id'=>$service_call_id,'completion_code_id'=>$completion_code_id));}
}
