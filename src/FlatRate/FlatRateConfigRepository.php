<?php
namespace ServSys\FlatRate;
use ServSys\Companies\CompanyContext;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class FlatRateConfigRepository {
 private $wpdb; private $table;
 public function __construct( $wpdb=null ){ $this->wpdb=$wpdb ?: $GLOBALS['wpdb']; $this->table=$this->wpdb->prefix.'servsys_flat_rate_config'; }
 public function current(){$wpdb=$this->wpdb; $company_id=CompanyContext::current_company_id(); /* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned current pricing configuration. */ return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE company_id = %d',$this->table,$company_id),ARRAY_A); }
 public function save(array $data): bool { $company_id=CompanyContext::current_company_id(); $existing=$this->current(); $data['company_id']=$company_id; if($existing){ return false !== $this->wpdb->update($this->table,$data,array('id'=>(int)$existing['id'],'company_id'=>$company_id)); } return false !== $this->wpdb->insert($this->table,$data); }
}
