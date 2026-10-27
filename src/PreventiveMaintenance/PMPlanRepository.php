<?php
namespace ServSys\PreventiveMaintenance;
use ServSys\Companies\CompanyContext;
if(!defined('ABSPATH')){exit;}
final class PMPlanRepository {
 private $wpdb;private $table;
 public function __construct($wpdb=null){$this->wpdb=$wpdb?:$GLOBALS['wpdb'];$this->table=$this->wpdb->prefix.'servsys_pm_plans';}
 public function find(int $id){$wpdb=$this->wpdb;/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned PM price-book data. */return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d AND company_id = %d',$this->table,$id,CompanyContext::current_company_id()),ARRAY_A);}
 public function search(string $term='',bool $include_inactive=true): array {$wpdb=$this->wpdb;$cid=CompanyContext::current_company_id();$like='%'.$this->wpdb->esc_like($term).'%';if(''===$term){$sql=$include_inactive?$wpdb->prepare('SELECT * FROM %i WHERE company_id = %d ORDER BY description,id',$this->table,$cid):$wpdb->prepare('SELECT * FROM %i WHERE company_id = %d AND active = 1 ORDER BY description,id',$this->table,$cid);}else{$sql=$include_inactive?$wpdb->prepare('SELECT * FROM %i WHERE company_id = %d AND description LIKE %s ORDER BY description,id',$this->table,$cid,$like):$wpdb->prepare('SELECT * FROM %i WHERE company_id = %d AND active = 1 AND description LIKE %s ORDER BY description,id',$this->table,$cid,$like);}/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned PM price-book data. *//* phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is produced exclusively by $wpdb->prepare() in the branches above. */return $wpdb->get_results($sql,ARRAY_A);}
 public function create(array $data): int {$data['company_id']=CompanyContext::current_company_id();$this->wpdb->insert($this->table,$data);return (int)$this->wpdb->insert_id;}
 public function update(int $id,array $data): bool {return false!==$this->wpdb->update($this->table,$data,array('id'=>$id,'company_id'=>CompanyContext::current_company_id()));}
}
