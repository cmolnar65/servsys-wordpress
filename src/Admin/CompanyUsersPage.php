<?php
namespace ServSys\Admin;
if ( ! defined( 'ABSPATH' ) ) { exit; }
use ServSys\Companies\CompanyContext;
use ServSys\Users\Authorization;
use ServSys\Users\Capabilities;
use ServSys\Users\CompanyUserRepository;
final class CompanyUsersPage {
	public function register(): void { add_action('admin_menu',array($this,'menu')); add_action('admin_post_servsys_save_company_user',array($this,'save')); }
	public function menu(): void { add_submenu_page('servsys','Company Users','Company Users',Capabilities::ACCESS,'servsys-company-users',array($this,'render')); }
	public function render(): void {
		$company_id=CompanyContext::current_company_id();
		if ( !$company_id || !Authorization::is_company_admin($company_id) ) { wp_die(esc_html__('Permission denied.','servsys')); }
		$repo=new CompanyUserRepository(); echo '<div class="wrap"><h1>ServSys Company Users</h1><p>Manage ServSys access for the current company only. WordPress Administrators control company records and Company Administrator assignment.</p>';
		echo '<table class="widefat striped"><thead><tr><th>User</th><th>Company Admin</th><th>Active</th><th>Permissions</th></tr></thead><tbody>';
		foreach($repo->for_company($company_id) as $m){$u=get_userdata((int)$m['user_id']); if(!$u){continue;} $p=json_decode((string)$m['permissions'],true); echo '<tr><td>'.esc_html($u->display_name).' ('.esc_html($u->user_login).')</td><td>'.($m['is_company_admin']?'Yes':'No').'</td><td>'.($m['active']?'Yes':'No').'</td><td>'.esc_html($m['is_company_admin']?'All company operational permissions':implode(', ',is_array($p)?$p:array())).'</td></tr>';}
		echo '</tbody></table><h2>Add or Update Company User</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('servsys_save_company_user'); echo '<input type="hidden" name="action" value="servsys_save_company_user">';
		echo '<p><label>WordPress User<br><select name="user_id" required><option value="">Select user</option>'; foreach(get_users(array('orderby'=>'display_name')) as $u){echo '<option value="'.esc_attr((string)$u->ID).'">'.esc_html($u->display_name.' ('.$u->user_login.')').'</option>';} echo '</select></label></p>';
		echo '<fieldset><legend><strong>ServSys Permissions</strong></legend>'; foreach(Capabilities::company_capabilities() as $cap){if(in_array($cap,array(Capabilities::ACCESS,Capabilities::MANAGE_COMPANY_USERS),true)){continue;} echo '<label><input type="checkbox" name="permissions[]" value="'.esc_attr($cap).'"> '.esc_html($cap).'</label><br>'; } echo '</fieldset><p><label><input type="checkbox" name="active" value="1" checked> Active</label></p>'; submit_button('Save Company User'); echo '</form></div>';
	}
	public function save(): void {
		check_admin_referer('servsys_save_company_user'); $company_id=CompanyContext::current_company_id();
		if(!$company_id || !Authorization::is_company_admin($company_id)){wp_die(esc_html__('Permission denied.','servsys'));}
		$user_id=absint(wp_unslash($_POST['user_id']??0)); if(!$user_id || !get_userdata($user_id)){wp_die(esc_html__('Invalid WordPress user.','servsys'));}
		$allowed=array_diff(Capabilities::company_capabilities(),array(Capabilities::MANAGE_COMPANY_USERS)); $requested=array_map('sanitize_key',(array)wp_unslash($_POST['permissions']??array())); $permissions=array_values(array_intersect($requested,$allowed));
		$repo=new CompanyUserRepository(); $existing=$repo->find($company_id,$user_id);
		if($existing && !empty($existing['is_company_admin']) && !Authorization::is_wordpress_admin()){wp_die(esc_html__('Only a WordPress Administrator may change a Company Administrator.','servsys'));}
		$now=current_time('mysql',true); $data=array('company_id'=>$company_id,'user_id'=>$user_id,'is_company_admin'=>$existing?(int)$existing['is_company_admin']:0,'active'=>isset($_POST['active'])?1:0,'permissions'=>wp_json_encode($permissions),'updated_at'=>$now,'updated_by'=>get_current_user_id());
		if($existing){$repo->save($data,(int)$existing['id']);}else{$data['created_at']=$now;$data['created_by']=get_current_user_id();$repo->save($data);}
		wp_safe_redirect(admin_url('admin.php?page=servsys-company-users')); exit;
	}
}
