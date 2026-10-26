<?php
namespace ServSys\PreventiveMaintenance;
use ServSys\Companies\CompanyContext;
if(!defined('ABSPATH')){exit;}
final class PMAgreementEquipmentRepository {
 private $wpdb;private $table;
 public function __construct($wpdb=null){$this->wpdb=$wpdb?:$GLOBALS['wpdb'];$this->table=$this->wpdb->prefix.'servsys_pm_agreement_equipment';}
 public function for_agreement(int $agreement_id): array {/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned PM agreement equipment. */return $this->wpdb->get_results($this->wpdb->prepare('SELECT * FROM %i WHERE pm_agreement_id=%d AND company_id=%d ORDER BY id',$this->table,$agreement_id,CompanyContext::current_company_id()),ARRAY_A);}
 public function create(array $data): int {$data['company_id']=CompanyContext::current_company_id();$this->wpdb->insert($this->table,$data);return (int)$this->wpdb->insert_id;}
 public function delete_for_agreement(int $agreement_id): bool {return false!==$this->wpdb->delete($this->table,array('pm_agreement_id'=>$agreement_id,'company_id'=>CompanyContext::current_company_id()));}
}
