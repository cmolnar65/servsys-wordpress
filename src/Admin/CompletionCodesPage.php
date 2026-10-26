<?php
namespace ServSys\Admin;
use ServSys\ServiceCalls\CompletionCodeRepository;
use ServSys\ServiceCalls\CompletionCodeService;
use ServSys\Users\Capabilities;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class CompletionCodesPage {
 public function register(): void {add_action('admin_menu',array($this,'register_menu'));add_action('admin_post_servsys_save_completion_code',array($this,'save'));}
 public function register_menu(): void {add_submenu_page('servsys','Completion Codes','Completion Codes',Capabilities::VIEW_COMPLETION_CODES,'servsys-completion-codes',array($this,'render'));}
 public function render(): void {
  if(!current_user_can(Capabilities::VIEW_COMPLETION_CODES)){wp_die(esc_html__('You do not have permission to view Completion Codes.','servsys'));}
  $repo=new CompletionCodeRepository();$rows=$repo->all(true);
  echo '<div class="wrap"><h1>Completion Codes</h1><p>Completion Codes are company-specific and can be assigned many-to-many to Service Calls. Make obsolete codes inactive rather than deleting them so historical Service Calls remain intact.</p>';
  echo '<table class="widefat striped"><thead><tr><th>Description</th><th>Sort</th><th>Status</th></tr></thead><tbody>';
  if(!$rows){echo '<tr><td colspan="3">No Completion Codes configured.</td></tr>';}
  foreach($rows as $row){echo '<tr><td>'.esc_html($row['description']).'</td><td>'.esc_html((string)$row['sort_order']).'</td><td>'.esc_html($row['active']?'Active':'Inactive').'</td></tr>';}
  echo '</tbody></table>';
  if(current_user_can(Capabilities::MANAGE_COMPLETION_CODES)){echo '<h2>Add / Update Completion Code</h2>';foreach(array_merge(array(array()),$rows) as $row){$row=wp_parse_args($row,array('id'=>0,'description'=>'','sort_order'=>0,'active'=>1));echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin-bottom:12px">';wp_nonce_field('servsys_save_completion_code_'.(int)$row['id']);echo '<input type="hidden" name="action" value="servsys_save_completion_code"><input type="hidden" name="completion_code_id" value="'.esc_attr((string)$row['id']).'"><input class="regular-text" name="description" value="'.esc_attr($row['description']).'" placeholder="Description" required> <input type="number" name="sort_order" value="'.esc_attr((string)$row['sort_order']).'" style="width:80px"> <label><input type="checkbox" name="active" value="1" '.checked((int)$row['active'],1,false).'> Active</label> ';submit_button($row['id']?'Update':'Add','secondary','',false);echo '</form>';}}
  echo '</div>';
 }
 public function save(): void {$id=isset($_POST['completion_code_id'])?absint(wp_unslash($_POST['completion_code_id'])):0;check_admin_referer('servsys_save_completion_code_'.$id);if(!current_user_can(Capabilities::MANAGE_COMPLETION_CODES)){wp_die(esc_html__('Permission denied.','servsys'));}$url=admin_url('admin.php?page=servsys-completion-codes');try{(new CompletionCodeService())->save(wp_unslash($_POST),$id);}catch(\InvalidArgumentException|\RuntimeException $e){$url=add_query_arg('servsys_error',$e->getMessage(),$url);}wp_safe_redirect($url);exit;}
}
