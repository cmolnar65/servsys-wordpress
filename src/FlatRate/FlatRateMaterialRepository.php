<?php
namespace ServSys\FlatRate;
use ServSys\Companies\CompanyContext;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class FlatRateMaterialRepository {
 private $wpdb; private $table;
 public function __construct($wpdb=null){$this->wpdb=$wpdb ?: $GLOBALS['wpdb'];$this->table=$this->wpdb->prefix.'servsys_flat_rate_item_materials';}
 public function for_item(int $item_id): array {/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned price-book recipe data. */ return $this->wpdb->get_results($this->wpdb->prepare('SELECT * FROM %i WHERE flat_rate_item_id = %d AND company_id = %d ORDER BY sort_order,id',$this->table,$item_id,CompanyContext::current_company_id()),ARRAY_A);}
 public function create(array $data): int {$data['company_id']=CompanyContext::current_company_id();$this->wpdb->insert($this->table,$data);return (int)$this->wpdb->insert_id;}
 public function delete_for_item(int $item_id): bool {return false !== $this->wpdb->delete($this->table,array('flat_rate_item_id'=>$item_id,'company_id'=>CompanyContext::current_company_id()));}
}
