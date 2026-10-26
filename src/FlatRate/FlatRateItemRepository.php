<?php
namespace ServSys\FlatRate;
use ServSys\Companies\CompanyContext;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class FlatRateItemRepository {
 private $wpdb; private $table;
 public function __construct($wpdb=null){$this->wpdb=$wpdb ?: $GLOBALS['wpdb'];$this->table=$this->wpdb->prefix.'servsys_flat_rate_items';}
 public function find(int $id){/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned price-book data. */ return $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM %i WHERE id = %d AND company_id = %d',$this->table,$id,CompanyContext::current_company_id()),ARRAY_A);}
 public function find_by_code(int $code){/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned price-book data. */ return $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM %i WHERE code = %d AND company_id = %d',$this->table,$code,CompanyContext::current_company_id()),ARRAY_A);}
 public function search(string $term='',bool $include_inactive=true): array {$cid=CompanyContext::current_company_id();$like='%'.$this->wpdb->esc_like($term).'%';if(''===$term){$sql=$include_inactive?$this->wpdb->prepare('SELECT * FROM %i WHERE company_id = %d ORDER BY description,code',$this->table,$cid):$this->wpdb->prepare('SELECT * FROM %i WHERE company_id = %d AND active = 1 ORDER BY description,code',$this->table,$cid);}else{$sql=$include_inactive?$this->wpdb->prepare('SELECT * FROM %i WHERE company_id = %d AND (description LIKE %s OR code LIKE %s) ORDER BY description,code',$this->table,$cid,$like,$like):$this->wpdb->prepare('SELECT * FROM %i WHERE company_id = %d AND active = 1 AND (description LIKE %s OR code LIKE %s) ORDER BY description,code',$this->table,$cid,$like,$like);}/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned price-book data. */ return $this->wpdb->get_results($sql,ARRAY_A);}
 public function create(array $data): int {$data['company_id']=CompanyContext::current_company_id();$this->wpdb->insert($this->table,$data);return (int)$this->wpdb->insert_id;}
 public function update(int $id,array $data): bool {return false !== $this->wpdb->update($this->table,$data,array('id'=>$id,'company_id'=>CompanyContext::current_company_id()));}
}
