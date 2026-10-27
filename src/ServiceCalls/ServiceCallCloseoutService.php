<?php
namespace ServSys\ServiceCalls;

use ServSys\Customers\CustomerRepository;
use ServSys\Equipment\EquipmentRepository;
use ServSys\Sites\SiteRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ServiceCallCloseoutService {
 private $calls; private $activities; private $completions; private $customers; private $sites; private $equipment;
 public function __construct(ServiceCallRepository $calls=null,ServiceCallActivityRepository $activities=null,ServiceCallCompletionRepository $completions=null,CustomerRepository $customers=null,SiteRepository $sites=null,EquipmentRepository $equipment=null){$this->calls=$calls?:new ServiceCallRepository();$this->activities=$activities?:new ServiceCallActivityRepository();$this->completions=$completions?:new ServiceCallCompletionRepository();$this->customers=$customers?:new CustomerRepository();$this->sites=$sites?:new SiteRepository();$this->equipment=$equipment?:new EquipmentRepository();}

 public function complete(int $service_call_id): void {
  $call=$this->require_call($service_call_id);
  if('completed'===$call['status']){throw new \InvalidArgumentException('Service Call is already completed.');}
  if('cancelled'===$call['status']){throw new \InvalidArgumentException('Cancelled Service Calls must be reopened before completion.');}
  $this->validate_relationships($call);
  if(!$this->valid_date((string)$call['service_date'])){throw new \InvalidArgumentException('A valid service date is required before completion.');}
  if(''===trim((string)$call['services_performed'])){throw new \InvalidArgumentException('Services Performed is required before completion.');}
  if($this->activities->has_open_activity($service_call_id)){throw new \InvalidArgumentException('Technician work activity is still open. End all technician work before completion.');}
  if(!$this->completions->for_service_call($service_call_id)){throw new \InvalidArgumentException('At least one Completion Code is required before completion.');}
  $now=current_time('mysql',true);$user=get_current_user_id()?:null;
  if(!$this->calls->update($service_call_id,array('status'=>'completed','completed_at'=>$now,'completed_by'=>$user,'updated_at'=>$now,'updated_by'=>$user))){throw new \RuntimeException('Unable to complete Service Call.');}
 }

 public function reopen(int $service_call_id): void {
  $call=$this->require_call($service_call_id);
  if(!in_array($call['status'],array('completed','cancelled'),true)){throw new \InvalidArgumentException('Only completed or cancelled Service Calls can be reopened.');}
  $now=current_time('mysql',true);$user=get_current_user_id()?:null;
  if(!$this->calls->update($service_call_id,array('status'=>'open','reopened_at'=>$now,'reopened_by'=>$user,'updated_at'=>$now,'updated_by'=>$user))){throw new \RuntimeException('Unable to reopen Service Call.');}
 }

 private function validate_relationships(array $call): void {
  $customer=$this->customers->find((int)$call['customer_id']);$site=$this->sites->find((int)$call['site_id']);
  if(!$customer||!$site||(int)$customer['company_id']!==(int)$call['company_id']||(int)$site['company_id']!==(int)$call['company_id']||(int)$site['customer_id']!==(int)$call['customer_id']){throw new \InvalidArgumentException('Service Call customer/site relationship is invalid.');}
  foreach($this->calls->equipment_ids((int)$call['id']) as $equipment_id){$item=$this->equipment->find($equipment_id);if(!$item||(int)$item['company_id']!==(int)$call['company_id']||(int)$item['customer_id']!==(int)$call['customer_id']||(int)$item['site_id']!==(int)$call['site_id']){throw new \InvalidArgumentException('Service Call equipment relationship is invalid.');}}
 }
 private function require_call(int $id): array {$call=$this->calls->find($id);if(!$call){throw new \InvalidArgumentException('Service Call does not belong to the current company.');}return $call;}
 private function valid_date(string $date): bool {$parsed=\DateTime::createFromFormat('!Y-m-d',$date);return $parsed&&$parsed->format('Y-m-d')===$date;}
}
