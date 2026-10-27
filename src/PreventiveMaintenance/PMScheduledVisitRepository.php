<?php
namespace ServSys\PreventiveMaintenance;
use ServSys\Companies\CompanyContext;
if(!defined('ABSPATH')){exit;}
final class PMScheduledVisitRepository {
 private $wpdb;private $table;
 public function __construct($wpdb=null){$this->wpdb=$wpdb?:$GLOBALS['wpdb'];$this->table=$this->wpdb->prefix.'servsys_pm_scheduled_visits';}
 public function find(int $id){$wpdb=$this->wpdb;/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Current PM scheduling data. */return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d AND company_id=%d',$this->table,$id,CompanyContext::current_company_id()),ARRAY_A);}
 public function for_agreement(int $agreement_id): array {$wpdb=$this->wpdb;/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Current PM scheduling data. */return $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE pm_agreement_id=%d AND company_id=%d ORDER BY scheduled_year,scheduled_month,scheduled_date,id',$this->table,$agreement_id,CompanyContext::current_company_id()),ARRAY_A);}
 public function create(array $data): int {$data['company_id']=CompanyContext::current_company_id();$this->wpdb->insert($this->table,$data);return (int)$this->wpdb->insert_id;}
 public function update(int $id,array $data): bool {return false!==$this->wpdb->update($this->table,$data,array('id'=>$id,'company_id'=>CompanyContext::current_company_id()));}
 public function delete(int $id): bool {return false!==$this->wpdb->delete($this->table,array('id'=>$id,'company_id'=>CompanyContext::current_company_id()));}
}
