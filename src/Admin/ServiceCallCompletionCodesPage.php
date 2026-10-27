<?php
namespace ServSys\Admin;
use ServSys\ServiceCalls\CompletionCodeRepository;
use ServSys\ServiceCalls\CompletionCodeService;
use ServSys\ServiceCalls\ServiceCallCompletionRepository;
use ServSys\Users\Capabilities;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class ServiceCallCompletionCodesPage {
 public function register(): void {add_action('admin_post_servsys_assign_completion_code',array($this,'assign'));add_action('admin_post_servsys_unassign_completion_code',array($this,'unassign'));}
 public function render(int $service_call_id): void {
  if(!current_user_can(Capabilities::VIEW_COMPLETION_CODES)){return;}$assigned=(new ServiceCallCompletionRepository())->for_service_call($service_call_id);
  echo '<h3>Completion Codes</h3>';
  if(!$assigned){echo '<p>No Completion Codes assigned.</p>';}else{echo '<ul>';foreach($assigned as $row){echo '<li>'.esc_html($row['description']).(empty($row['active'])?' <em>(inactive)</em>':'');if(current_user_can(Capabilities::ASSIGN_COMPLETION_CODES)){echo ' <form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline">';wp_nonce_field('servsys_unassign_completion_code');echo '<input type="hidden" name="action" value="servsys_unassign_completion_code"><input type="hidden" name="service_call_id" value="'.esc_attr((string)$service_call_id).'"><input type="hidden" name="completion_code_id" value="'.esc_attr((string)$row['completion_code_id']).'"><button class="button-link" type="submit">Remove</button></form>';}echo '</li>';}echo '</ul>';}
  if(!current_user_can(Capabilities::ASSIGN_COMPLETION_CODES)){return;}$codes=(new CompletionCodeRepository())->all(false);$used=array_map('intval',array_column($assigned,'completion_code_id'));echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('servsys_assign_completion_code');echo '<input type="hidden" name="action" value="servsys_assign_completion_code"><input type="hidden" name="service_call_id" value="'.esc_attr((string)$service_call_id).'"><select name="completion_code_id" required><option value="">Select Completion Code</option>';foreach($codes as $code){if(in_array((int)$code['id'],$used,true)){continue;}echo '<option value="'.esc_attr((string)$code['id']).'">'.esc_html($code['description']).'</option>';}echo '</select> ';submit_button('Assign Completion Code','secondary','',false);echo '</form>';
 }
 public function assign(): void {check_admin_referer('servsys_assign_completion_code');$call=$this->posted('service_call_id');$code=$this->posted('completion_code_id');$this->guard();$this->run($call,function()use($call,$code){(new CompletionCodeService())->assign($call,$code);});}
 public function unassign(): void {check_admin_referer('servsys_unassign_completion_code');$call=$this->posted('service_call_id');$code=$this->posted('completion_code_id');$this->guard();$this->run($call,function()use($call,$code){(new CompletionCodeService())->unassign($call,$code);});}
 private function posted(string $key): int {return isset($_POST[$key])?absint(wp_unslash($_POST[$key])):0;}
 private function guard(): void {if(!current_user_can(Capabilities::ASSIGN_COMPLETION_CODES)){wp_die(esc_html__('Permission denied.','servsys'));}}
 private function run(int $call,callable $callback): void {$url=admin_url('admin.php?page=servsys-service-calls&service_call_id='.$call);try{$callback();}catch(\InvalidArgumentException|\RuntimeException $e){$url=add_query_arg('servsys_error',$e->getMessage(),$url);}wp_safe_redirect($url);exit;}
}
