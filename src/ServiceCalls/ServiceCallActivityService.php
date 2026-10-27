<?php
namespace ServSys\ServiceCalls;
use ServSys\Users\CompanyUserRepository;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class ServiceCallActivityService {
 private $activities; private $calls; private $members;
 public function __construct(ServiceCallActivityRepository $activities=null,ServiceCallRepository $calls=null,CompanyUserRepository $members=null){$this->activities=$activities?:new ServiceCallActivityRepository();$this->calls=$calls?:new ServiceCallRepository();$this->members=$members?:new CompanyUserRepository();}
 public function save(int $service_call_id,array $input,int $id=0): int {
  $call=$this->calls->find($service_call_id);if(!$call){throw new \InvalidArgumentException('Service Call does not belong to the current company.');}
  $technician=absint($input['technician_user_id']??0);$membership=$technician?$this->members->find((int)$call['company_id'],$technician):null;if(!$technician||!get_userdata($technician)||!$membership||empty($membership['active'])){throw new \InvalidArgumentException('Technician is not an active user of this company.');}$truck=$membership&& !empty($membership['truck_number'])?sanitize_text_field($membership['truck_number']):null;
  $times=array();foreach(array('dispatched_at','arrived_at','work_started_at','work_ended_at') as $key){$times[$key]=$this->datetime($input[$key]??'',$key);}
  $last=null;foreach(array('dispatched_at','arrived_at','work_started_at','work_ended_at') as $key){if(null===$times[$key]){continue;}if(null!==$last&&$times[$key]<$last){throw new \InvalidArgumentException('Technician activity timestamps must be chronological.');}$last=$times[$key];}
  $odo_start=$this->odometer($input['odometer_start']??null,'start');$odo_end=$this->odometer($input['odometer_end']??null,'end');if(null!==$odo_end&&null===$odo_start){throw new \InvalidArgumentException('Starting odometer reading is required when an ending reading is entered.');}if(null!==$odo_start&&null!==$odo_end&&$odo_end<$odo_start){throw new \InvalidArgumentException('Ending odometer reading cannot be less than the starting reading.');}
  $source=sanitize_key($input['odometer_source']??'manual');if(!in_array($source,array('manual','location','gps','legacy_import'),true)){throw new \InvalidArgumentException('Invalid odometer source.');}
  $data=array('service_call_id'=>$service_call_id,'technician_user_id'=>$technician,'dispatched_at'=>$times['dispatched_at'],'arrived_at'=>$times['arrived_at'],'work_started_at'=>$times['work_started_at'],'work_ended_at'=>$times['work_ended_at'],'odometer_start'=>$odo_start,'odometer_end'=>$odo_end,'travel_miles'=>(null!==$odo_start&&null!==$odo_end)?round($odo_end-$odo_start,3):null,'odometer_source'=>$source,'truck_number_snapshot'=>$truck,'notes'=>sanitize_textarea_field($input['notes']??''),'updated_at'=>current_time('mysql',true),'updated_by'=>get_current_user_id()?:null);
  if($id){$existing=$this->activities->find($id);if(!$existing||(int)$existing['service_call_id']!==$service_call_id){throw new \InvalidArgumentException('Technician activity does not belong to this Service Call.');}$data['truck_number_snapshot']=$existing['truck_number_snapshot']?:$truck;if(!$this->activities->update($id,$data)){throw new \RuntimeException('Unable to update technician activity.');}return $id;}
  $data['created_at']=$data['updated_at'];$data['created_by']=$data['updated_by'];$id=$this->activities->create($data);if(!$id){throw new \RuntimeException('Unable to create technician activity.');}return $id;
 }
 private function datetime($value,string $field): ?string {$value=trim((string)$value);if(''===$value){return null;}$value=str_replace('T',' ',$value);if(16===strlen($value)){$value.=':00';}$d=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value);if(!$d||$d->format('Y-m-d H:i:s')!==$value){throw new \InvalidArgumentException(str_replace('_',' ',$field).' is invalid.');}return $value;}
 private function odometer($value,string $label): ?float {if(null===$value||''===trim((string)$value)){return null;}$value=(float)$value;if($value<0){throw new \InvalidArgumentException('Odometer '.$label.' reading cannot be negative.');}return round($value,3);}
}
