<?php
namespace ServSys\Admin;

use ServSys\ServiceCalls\ServiceCallActivityRepository;
use ServSys\ServiceCalls\ServiceCallActivityService;
use ServSys\ServiceCalls\ServiceCallRepository;
use ServSys\Users\Authorization;
use ServSys\Users\Capabilities;
use ServSys\Users\CompanyUserRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ServiceCallActivityPage {
 public function register(): void {
  add_action('admin_post_servsys_save_service_call_activity',array($this,'save'));
 }

 public function render(int $service_call_id): void {
  if(!current_user_can(Capabilities::VIEW_SERVICE_CALL_ACTIVITY)){return;}
  $call=(new ServiceCallRepository())->find($service_call_id);
  if(!$call){return;}
  $repo=new ServiceCallActivityRepository();
  $rows=$repo->for_service_call($service_call_id);
  echo '<h3>Technician Dispatch / Work Activity</h3>';
  echo '<p class="description">Record dispatch, arrival, work time, and travel odometer readings. Mileage is stored from the odometer difference for historical/tax records. Odometer source is manual today and can support location/GPS sources later.</p>';
  if(!$rows){echo '<p>No technician activity has been recorded for this Service Call.</p>';}
  foreach($rows as $row){$this->render_form($call,$row,'Save Activity');}
  if(current_user_can(Capabilities::MANAGE_SERVICE_CALL_ACTIVITY)){$this->render_form($call,array(),'Add Activity');}
 }

 private function render_form(array $call,array $row,string $button): void {
  $editable=current_user_can(Capabilities::MANAGE_SERVICE_CALL_ACTIVITY);
  $row=wp_parse_args($row,array('id'=>0,'technician_user_id'=>(int)($call['technician_user_id']??0),'dispatched_at'=>'','arrived_at'=>'','work_started_at'=>'','work_ended_at'=>'','odometer_start'=>'','odometer_end'=>'','travel_miles'=>'','odometer_source'=>'manual','truck_number_snapshot'=>'','notes'=>''));
  echo '<div class="notice notice-info inline"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
  wp_nonce_field('servsys_save_service_call_activity_'.$service_call_id=(int)$call['id'].'_'.(int)$row['id']);
  echo '<input type="hidden" name="action" value="servsys_save_service_call_activity"><input type="hidden" name="service_call_id" value="'.esc_attr((string)$call['id']).'"><input type="hidden" name="activity_id" value="'.esc_attr((string)$row['id']).'">';
  echo '<table class="form-table"><tbody>';
  echo '<tr><th>Technician</th><td><select name="technician_user_id" '.($editable?'':'disabled').' required><option value="">Select technician</option>';
  foreach($this->technicians((int)$call['company_id']) as $id=>$label){echo '<option value="'.esc_attr((string)$id).'" '.selected((int)$row['technician_user_id'],$id,false).'>'.esc_html($label).'</option>';}
  echo '</select></td></tr>';
  foreach(array('dispatched_at'=>'Dispatched','arrived_at'=>'Arrived','work_started_at'=>'Work Started','work_ended_at'=>'Work Ended') as $name=>$label){
   echo '<tr><th>'.esc_html($label).'</th><td><input type="datetime-local" name="'.esc_attr($name).'" value="'.esc_attr($this->datetime_input($row[$name])).'" '.($editable?'':'disabled').'></td></tr>';
  }
  echo '<tr><th>Starting Odometer</th><td><input type="number" min="0" step="0.001" name="odometer_start" value="'.esc_attr((string)$row['odometer_start']).'" '.($editable?'':'disabled').'></td></tr>';
  echo '<tr><th>Ending Odometer</th><td><input type="number" min="0" step="0.001" name="odometer_end" value="'.esc_attr((string)$row['odometer_end']).'" '.($editable?'':'disabled').'></td></tr>';
  if($row['id']){echo '<tr><th>Travel Mileage</th><td>'.(null!==$row['travel_miles']&&''!==$row['travel_miles']?esc_html(number_format((float)$row['travel_miles'],3)):'Pending').'</td></tr><tr><th>Truck Snapshot</th><td>'.esc_html((string)$row['truck_number_snapshot']).'</td></tr><tr><th>Odometer Source</th><td>'.esc_html(ucwords(str_replace('_',' ',(string)$row['odometer_source']))).'</td></tr>';}
  echo '<tr><th>Notes</th><td><textarea class="large-text" rows="2" name="notes" '.($editable?'':'disabled').'>'.esc_textarea((string)$row['notes']).'</textarea></td></tr>';
  echo '</tbody></table>';
  echo '<input type="hidden" name="odometer_source" value="'.esc_attr((string)$row['odometer_source']).'">';
  if($editable){submit_button($button,'secondary','',false);}
  echo '</form></div>';
 }

 public function save(): void {
  $service_call_id=isset($_POST['service_call_id'])?absint(wp_unslash($_POST['service_call_id'])):0;
  $activity_id=isset($_POST['activity_id'])?absint(wp_unslash($_POST['activity_id'])):0;
  check_admin_referer('servsys_save_service_call_activity_'.$service_call_id.'_'.$activity_id);
  if(!current_user_can(Capabilities::MANAGE_SERVICE_CALL_ACTIVITY)){wp_die(esc_html__('Permission denied.','servsys'));}
  $url=admin_url('admin.php?page=servsys-service-calls&service_call_id='.$service_call_id);
  try{(new ServiceCallActivityService())->save($service_call_id,wp_unslash($_POST),$activity_id);}
  catch(\InvalidArgumentException|\RuntimeException $e){$url=add_query_arg('servsys_error',$e->getMessage(),$url);}
  wp_safe_redirect($url);exit;
 }

 private function technicians(int $company_id): array {
  $out=array();
  foreach((new CompanyUserRepository())->for_company($company_id) as $membership){
   if(empty($membership['active'])){continue;}
   $user=get_userdata((int)$membership['user_id']);if(!$user){continue;}
   $label=$membership['employee_name']?:$user->display_name;
   if(!empty($membership['truck_number'])){$label.=' — Truck '.$membership['truck_number'];}
   $out[(int)$membership['user_id']]=$label;
  }
  foreach(get_users(array('role'=>'administrator','fields'=>'ID')) as $user_id){
   if(!Authorization::can_access_company($company_id,(int)$user_id)){continue;}
   if(isset($out[(int)$user_id])){continue;}
   $user=get_userdata((int)$user_id);if($user){$out[(int)$user_id]=$user->display_name.' — Administrator';}
  }
  return $out;
 }

 private function datetime_input($value): string {
  $value=(string)$value;
  if(''===$value){return '';}
  return str_replace(' ','T',substr($value,0,16));
 }
}
