<?php
namespace ServSys\ServiceCalls;

use ServSys\Inventory\ServiceCallInventoryRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ServiceCallTotalsService {
 private $calls; private $inventory; private $charges;
 public function __construct(ServiceCallRepository $calls=null,ServiceCallInventoryRepository $inventory=null,ServiceCallChargeRepository $charges=null){$this->calls=$calls?:new ServiceCallRepository();$this->inventory=$inventory?:new ServiceCallInventoryRepository();$this->charges=$charges?:new ServiceCallChargeRepository();}

 public function totals(int $service_call_id): array {
  if(!$this->calls->find($service_call_id)){throw new \InvalidArgumentException('Service Call does not belong to the current company.');}
  $inventory=round($this->inventory->total_for_service_call($service_call_id),4);
  $charges=round($this->charges->total_for_service_call($service_call_id),4);
  return array(
   'inventory_material_selling_total'=>$inventory,
   'service_charge_total'=>$charges,
   'combined_operational_total'=>round($inventory+$charges,4),
  );
 }
}
