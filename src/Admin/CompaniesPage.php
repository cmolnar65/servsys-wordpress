<?php
namespace ServSys\Admin;
if ( ! defined( 'ABSPATH' ) ) { exit; }
use ServSys\Companies\CompanyContext;
use ServSys\Companies\CompanyRepository;
use ServSys\Companies\CompanyService;
use ServSys\Users\Capabilities;
use ServSys\Users\Authorization;
use ServSys\Users\CompanyUserRepository;
final class CompaniesPage {
	public function register(): void { add_action( 'admin_menu', array( $this,'menu' ) ); add_action( 'admin_post_servsys_save_company', array( $this,'save' ) ); add_action( 'admin_post_servsys_switch_company', array( $this,'switch_company' ) ); add_action( 'admin_post_servsys_assign_company_admin', array( $this,'assign_company_admin' ) ); }
	public function menu(): void { add_submenu_page( null,'Companies','Companies','manage_options','servsys-companies',array( $this,'render' ) ); }
	public function render(): void {
		if ( ! Authorization::is_wordpress_admin() ) { wp_die( esc_html__( 'Permission denied.','servsys' ) ); }
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation.
		$id=isset( $_GET['company_id'] ) ? absint( $_GET['company_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation.
		$add=isset( $_GET['action'] ) && 'add' === sanitize_key( wp_unslash( $_GET['action'] ) );
		$repo=new CompanyRepository(); $company=$id ? $repo->find_unscoped( $id ) : null;
		echo '<div class="wrap"><h1>ServSys Companies</h1><p>Each ServSys business record belongs to one company. Existing records are assigned to Company 1, Installation Company.</p>';
		if ( $company || $add ) { $this->form( $company ); echo '</div>'; return; }
		$current=CompanyContext::current_company_id(); echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=servsys-companies&action=add' ) ) . '">Add Company</a></p>';
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Company</th><th>Status</th><th>Current</th><th>Actions</th></tr></thead><tbody>';
		foreach ( $repo->all( true ) as $item ) {
			echo '<tr><td>' . esc_html( (string) $item['id'] ) . '</td><td>' . esc_html( $item['name'] ) . '</td><td>' . ( $item['active'] ? 'Active':'Inactive' ) . '</td><td>' . ( (int)$item['id']===$current ? 'Yes':'' ) . '</td><td><a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=servsys-companies&company_id='.(int)$item['id'] ) ) . '">Edit</a> ';
			if ( $item['active'] && (int)$item['id'] !== $current ) { echo '<form class="servsys-inline-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'servsys_switch_company' ); echo '<input type="hidden" name="action" value="servsys_switch_company"><input type="hidden" name="company_id" value="' . esc_attr( (string)$item['id'] ) . '"><button class="button button-small" type="submit">Use Company</button></form>'; }
			echo '</td></tr>';
		} echo '</tbody></table></div>';
	}
	private function form( $c ): void {
		$c=$c ?: array( 'id'=>0,'name'=>'','logo_attachment_id'=>'','tagline'=>'','address1'=>'','address2'=>'','city'=>'','state'=>'','postal_code'=>'','phone'=>'','use_preprinted_letterhead'=>0,'fiscal_year_end_month'=>12,'fiscal_year_end_day'=>31,'customer_portal_enabled'=>0,'active'=>1 );
		echo '<h2>' . ( $c['id'] ? 'Edit Company #' . esc_html( (string)$c['id'] ) : 'Add Company' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'servsys_save_company' );
		echo '<input type="hidden" name="action" value="servsys_save_company"><input type="hidden" name="company_id" value="' . esc_attr( (string)$c['id'] ) . '">';
		foreach ( array( 'name'=>'Company Name','logo_attachment_id'=>'Logo Media Attachment ID','address1'=>'Address 1','address2'=>'Address 2','city'=>'City','state'=>'State','postal_code'=>'ZIP / Postal Code','phone'=>'Phone' ) as $f=>$l ) { echo '<p><label>' . esc_html( $l ) . '<br><input class="regular-text" name="' . esc_attr( $f ) . '" value="' . esc_attr( (string)$c[$f] ) . '"' . ( 'name'===$f ? ' required':'' ) . '></label></p>'; }
		echo '<p><label>Company Tagline<br><textarea class="large-text" rows="3" name="tagline">' . esc_textarea( $c['tagline'] ) . '</textarea></label></p>';
		echo '<p><label><input type="checkbox" name="use_preprinted_letterhead" value="1" ' . checked( (int)$c['use_preprinted_letterhead'],1,false ) . '> Use pre-printed letterhead (suppress logo and tagline on printed documents)</label></p>';
		echo '<p><label>Fiscal Year End Month <input class="small-text" type="number" min="1" max="12" name="fiscal_year_end_month" value="' . esc_attr( (string)$c['fiscal_year_end_month'] ) . '"></label> <label>Day <input class="small-text" type="number" min="1" max="31" name="fiscal_year_end_day" value="' . esc_attr( (string)$c['fiscal_year_end_day'] ) . '"></label></p>';
		echo '<p><label><input type="checkbox" name="customer_portal_enabled" value="1" ' . checked( (int)$c['customer_portal_enabled'],1,false ) . '> Customer Portal Enabled (reserved for future customer-login functionality)</label></p><p><label><input type="checkbox" name="active" value="1" ' . checked( (int)$c['active'],1,false ) . '> Active</label></p>';
		$current_admin_user_id = 0;
		if ( $c['id'] ) {
			foreach ( ( new CompanyUserRepository() )->for_company( (int) $c['id'] ) as $membership ) {
				if ( ! empty( $membership['active'] ) && ! empty( $membership['is_company_admin'] ) ) {
					$current_admin_user_id = (int) $membership['user_id'];
					break;
				}
			}
		}
		echo '<h3>Company Administrator</h3><p>WordPress Administrators assign the ServSys Company Administrator. Company Administrators manage users only inside this company.</p><p><label>WordPress User<br><select name="company_admin_user_id"><option value="">No change</option>';
		foreach ( get_users( array( 'orderby'=>'display_name' ) ) as $user ) { echo '<option value="' . esc_attr((string)$user->ID) . '"' . selected( $current_admin_user_id, (int) $user->ID, false ) . '>' . esc_html($user->display_name . ' (' . $user->user_login . ')') . '</option>'; }
		echo '</select></label></p>';
		submit_button( $c['id'] ? 'Save Company':'Create Company' ); echo '</form><p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=servsys-companies' ) ) . '">&larr; Return to Companies</a></p>';
	}
	public function save(): void { check_admin_referer( 'servsys_save_company' ); if ( ! Authorization::is_wordpress_admin() ) { wp_die( esc_html__( 'Permission denied.','servsys' ) ); } $id=absint( wp_unslash( $_POST['company_id'] ?? 0 ) ); $d=wp_unslash( $_POST ); $d['active']=isset($d['active']); $d['use_preprinted_letterhead']=isset($d['use_preprinted_letterhead']); $d['customer_portal_enabled']=isset($d['customer_portal_enabled']); $id=(new CompanyService())->save($d,$id);
		$admin_user_id=absint($d['company_admin_user_id'] ?? 0);
		if ($admin_user_id) { $this->set_company_admin($id,$admin_user_id); }
		wp_safe_redirect( admin_url( 'admin.php?page=servsys-companies&company_id='.$id ) ); exit; }
	private function set_company_admin( int $company_id, int $user_id ): void {
		$repo=new CompanyUserRepository(); $existing=$repo->find($company_id,$user_id); $now=current_time('mysql',true);
		$data=array('company_id'=>$company_id,'user_id'=>$user_id,'is_company_admin'=>1,'active'=>1,'permissions'=>'[]','updated_at'=>$now,'updated_by'=>get_current_user_id());
		if ($existing) { $repo->save($data,(int)$existing['id']); } else { $data['created_at']=$now; $data['created_by']=get_current_user_id(); $repo->save($data); }
	}
	public function assign_company_admin(): void { check_admin_referer('servsys_assign_company_admin'); if(!Authorization::is_wordpress_admin()){wp_die(esc_html__('Permission denied.','servsys'));} $this->set_company_admin(absint($_POST['company_id']??0),absint($_POST['user_id']??0)); wp_safe_redirect(admin_url('admin.php?page=servsys-companies')); exit; }

	public function switch_company(): void { check_admin_referer( 'servsys_switch_company' ); if ( ! Authorization::is_wordpress_admin() ) { wp_die( esc_html__( 'Permission denied.','servsys' ) ); } $id=absint( wp_unslash( $_POST['company_id'] ?? 0 ) ); if ( ! CompanyContext::set_current_company_id($id) ) { wp_die( esc_html__( 'Unable to select company.','servsys' ) ); } wp_safe_redirect( admin_url( 'admin.php?page=servsys-companies' ) ); exit; }
}
