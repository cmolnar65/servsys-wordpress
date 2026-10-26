<?php

namespace ServSys\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ServSys\Customers\CustomerRepository;
use ServSys\Equipment\EquipmentRepository;
use ServSys\ServiceCalls\ServiceCallNoteRepository;
use ServSys\ServiceCalls\ServiceCallNoteService;
use ServSys\ServiceCalls\ServiceCallService;
use ServSys\Sites\SiteRepository;
use ServSys\ServiceCalls\ServiceCallRepository;
use ServSys\Users\Capabilities;
use ServSys\Users\Authorization;
use ServSys\Users\CompanyUserRepository;

final class ServiceCallsPage {
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_servsys_save_service_call', array( $this, 'save_service_call' ) );
		add_action( 'admin_post_servsys_add_service_call_note', array( $this, 'add_note' ) );
		add_action( 'admin_post_servsys_service_call_add_equipment', array( $this, 'add_equipment_from_form' ) );
	}

	public function register_menu(): void {
		add_submenu_page( 'servsys', 'Service Calls', 'Service Calls', Capabilities::VIEW_SERVICE_CALLS, 'servsys-service-calls', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_SERVICE_CALLS ) ) {
			wp_die( esc_html__( 'You do not have permission to view ServSys service calls.', 'servsys' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation; no state change.
		$id = isset( $_GET['service_call_id'] ) ? absint( $_GET['service_call_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation; no state change.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		echo '<div class="wrap"><h1>ServSys Service Calls</h1>';
		if ( 'edit' === $action ) {
			$this->render_form( $id );
		} elseif ( $id ) {
			$this->render_workspace( $id );
		} else {
			$this->render_list();
		}
		echo '</div>';
	}

	private function render_list(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin search/filter; no state change.
		$term = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin search/filter; no state change.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin search/filter; no state change.
		$follow_up = isset( $_GET['follow_up'] ) ? sanitize_key( wp_unslash( $_GET['follow_up'] ) ) : '';
		$calls = ( new ServiceCallRepository() )->search( $term, $status, $follow_up );
		if ( current_user_can( Capabilities::CREATE_SERVICE_CALLS ) ) {
			echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=servsys-service-calls&action=edit' ) ) . '">Add Service Call</a></p>';
		}

		echo '<form method="get"><input type="hidden" name="page" value="servsys-service-calls"><p class="search-box">';
		echo '<input type="search" name="s" value="' . esc_attr( $term ) . '" placeholder="Call #, customer, site, reason"> ';
		echo '<select name="status"><option value="">All statuses</option>';
		foreach ( array( 'open' => 'Open', 'completed' => 'Completed', 'cancelled' => 'Cancelled' ) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> <select name="follow_up"><option value="">All follow-up</option><option value="required" ' . selected( $follow_up, 'required', false ) . '>Follow-up required</option><option value="none" ' . selected( $follow_up, 'none', false ) . '>No follow-up</option></select> ';
		submit_button( 'Filter Service Calls', 'secondary', '', false );
		echo ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=servsys-service-calls' ) ) . '">Clear</a></p></form>';

		echo '<table class="widefat striped"><thead><tr><th>Call #</th><th>Date</th><th>Customer</th><th>Site</th><th>Technician</th><th>Status</th><th>Follow-up</th></tr></thead><tbody>';
		if ( ! $calls ) {
			echo '<tr><td colspan="7">No service calls found.</td></tr>';
		}
		foreach ( $calls as $call ) {
			$url = admin_url( 'admin.php?page=servsys-service-calls&service_call_id=' . (int) $call['id'] );
			$technician = $call['technician_user_id'] ? get_userdata( (int) $call['technician_user_id'] ) : null;
			echo '<tr><td><a href="' . esc_url( $url ) . '">' . esc_html( $call['call_number'] ?: (string) $call['id'] ) . '</a></td>';
			echo '<td>' . esc_html( $call['service_date'] ) . '</td><td>' . esc_html( $call['customer_name'] ) . '</td>';
			echo '<td>' . esc_html( $call['site_name'] ?: $call['site_address'] ) . '</td>';
			echo '<td>' . esc_html( $technician ? $technician->display_name : '' ) . '</td>';
			echo '<td>' . esc_html( ucfirst( $call['status'] ) ) . '</td>';
				$follow_up_label = $call['follow_up_required'] ? ( $call['follow_up_date'] ?: 'Required — no date set' ) : 'None';
			echo '<td>' . esc_html( $follow_up_label ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function render_workspace( int $id ): void {
		$repository = new ServiceCallRepository();
		$call = $repository->find_with_context( $id );
		if ( ! $call ) {
			wp_die( esc_html__( 'Service call not found.', 'servsys' ) );
		}
		$technician = $call['technician_user_id'] ? get_userdata( (int) $call['technician_user_id'] ) : null;
		$equipment_repository = new EquipmentRepository();
		$equipment = array();
		foreach ( $repository->equipment_ids( $id ) as $equipment_id ) {
			$item = $equipment_repository->find( $equipment_id );
			if ( $item ) {
				$equipment[] = $item;
			}
		}

		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=servsys-service-calls' ) ) . '">&larr; Return to Service Calls</a></p>';
		echo '<h2>Service Call ' . esc_html( $call['call_number'] ?: (string) $call['id'] ) . '</h2>';
		if ( current_user_can( Capabilities::EDIT_SERVICE_CALLS ) ) {
			echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=servsys-service-calls&action=edit&service_call_id=' . $id ) ) . '">Edit Service Call</a></p>';
		}
		echo '<table class="widefat striped"><tbody>';
		$this->row( 'Date', $call['service_date'] );
		$this->row( 'Customer', $call['customer_name'] );
		$this->row( 'Site', trim( ( $call['site_name'] ?: $call['site_address'] ) . ' ' . $call['site_city'] . ' ' . $call['site_state'] ) );
		$this->row( 'Technician', $technician ? $technician->display_name : '' );
		$this->row( 'Status', ucfirst( $call['status'] ) );
		$this->row( 'Follow-up', $call['follow_up_required'] ? ( $call['follow_up_date'] ?: 'Required — no date set' ) : 'None' );
		echo '</tbody></table>';

		$this->section( 'Reason for Call', $call['reason'] );
		echo '<h3>Equipment Serviced</h3><ul>';
		if ( ! $equipment ) {
			echo '<li>No equipment associated with this service call.</li>';
		}
		foreach ( $equipment as $item ) {
			echo '<li>' . esc_html( trim( $item['equipment_type'] . ' — ' . $item['brand'] . ' ' . $item['model_number'] . ' ' . $item['serial_number'] ) ) . '</li>';
		}
		echo '</ul>';
		$this->section( 'Services Performed', $call['services_performed'] );
		$this->section( 'Parts / Materials', $call['parts_notes'] );
		$this->section( 'Recommendations', $call['recommendations'] );
		echo '<hr><h3>Service Call Notes</h3>';
		$notes = ( new ServiceCallNoteRepository() )->for_service_call( $id );
		if ( ! $notes ) {
			echo '<p>No notes have been added to this service call.</p>';
		}
		foreach ( $notes as $note ) {
			$author = ! empty( $note['created_by'] ) ? get_userdata( (int) $note['created_by'] ) : null;
			echo '<div class="notice notice-info inline"><p><strong>' . esc_html( $note['note_date'] ) . '</strong>';
			if ( $author ) { echo ' &mdash; ' . esc_html( $author->display_name ); }
			echo '<br>' . nl2br( esc_html( $note['note'] ) ) . '</p></div>';
		}
		if ( current_user_can( Capabilities::EDIT_SERVICE_CALLS ) ) {
			echo '<h4>Add Note</h4><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'servsys_add_service_call_note' );
			echo '<input type="hidden" name="action" value="servsys_add_service_call_note"><input type="hidden" name="service_call_id" value="' . esc_attr( (string) $id ) . '"><p><label>Note<br><textarea class="large-text" rows="4" name="note" required></textarea></label></p>';
			submit_button( 'Add Note', 'secondary' );
			echo '</form>';
		}
	}

	private function render_form( int $id ): void {
		$repository = new ServiceCallRepository();
		$call = $id ? $repository->find( $id ) : null;
		if ( $id && ! $call ) {
			wp_die( esc_html__( 'Service call not found.', 'servsys' ) );
		}
		if ( ! current_user_can( $id ? Capabilities::EDIT_SERVICE_CALLS : Capabilities::CREATE_SERVICE_CALLS ) ) {
			wp_die( esc_html__( 'Permission denied.', 'servsys' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation; no state change.
		$customer_id = $call ? (int) $call['customer_id'] : ( isset( $_GET['customer_id'] ) ? absint( $_GET['customer_id'] ) : 0 );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation; no state change.
		$site_id = $call ? (int) $call['site_id'] : ( isset( $_GET['site_id'] ) ? absint( $_GET['site_id'] ) : 0 );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation; no state change.
		$source = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '';
		$customer = $customer_id ? ( new CustomerRepository() )->find( $customer_id ) : null;
		$site = $site_id ? ( new SiteRepository() )->find( $site_id ) : null;
		if ( $site && ( ! $customer || (int) $site['customer_id'] !== $customer_id ) ) {
			wp_die( esc_html__( 'Selected site does not belong to this customer.', 'servsys' ) );
		}
		$call = $call ?: array( 'service_date' => current_time( 'Y-m-d' ), 'technician_user_id' => 0, 'status' => 'open', 'reason' => '', 'services_performed' => '', 'recommendations' => '', 'parts_notes' => '', 'follow_up_required' => 0, 'follow_up_date' => '' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Signed transient key only restores previously nonce-validated form state.
		$draft_key = isset( $_GET['draft'] ) ? sanitize_key( wp_unslash( $_GET['draft'] ) ) : '';
		if ( $draft_key ) {
			$draft = get_transient( 'servsys_sc_draft_' . get_current_user_id() . '_' . $draft_key );
			if ( is_array( $draft ) ) {
				$call = array_merge( $call, $draft );
				$customer_id = absint( $draft['customer_id'] ?? $customer_id );
				$site_id = absint( $draft['site_id'] ?? $site_id );
			}
		}
		$selected_equipment = $draft_key && isset( $draft['equipment_ids'] ) ? array_map( 'absint', (array) $draft['equipment_ids'] ) : ( $id ? $repository->equipment_ids( $id ) : array() );
		$site_equipment = $site_id ? ( new EquipmentRepository() )->for_site( $site_id, true ) : array();
		$customers = ( new CustomerRepository() )->search( '', false );
		$sites = $customer_id ? ( new SiteRepository() )->for_customer( $customer_id, false ) : array();

		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=servsys-service-calls' ) ) . '">&larr; Return to Service Calls</a></p><h2>' . ( $id ? 'Edit Service Call' : 'Add Service Call' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'servsys_save_service_call' );
		echo '<input type="hidden" name="action" value="servsys_save_service_call"><input type="hidden" name="service_call_id" value="' . esc_attr( (string) $id ) . '"><input type="hidden" name="source" value="' . esc_attr( $source ) . '">';
		if ( 'site' === $source || $id ) {
			echo '<input type="hidden" name="customer_id" value="' . esc_attr( (string) $customer_id ) . '"><input type="hidden" name="site_id" value="' . esc_attr( (string) $site_id ) . '"><p><strong>Customer:</strong> ' . esc_html( $customer ? $customer['name'] : '' ) . '<br><strong>Site:</strong> ' . esc_html( $site ? ( $site['name'] ?: $site['address1'] ) : '' ) . '</p>';
		} else {
			if ( ! $customer_id ) {
				echo '</form><form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
				echo '<input type="hidden" name="page" value="servsys-service-calls"><input type="hidden" name="action" value="edit">';
				echo '<p><label>Customer<br><select name="customer_id" required><option value="">Select customer</option>';
				foreach ( $customers as $item ) {
					echo '<option value="' . esc_attr( (string) $item['id'] ) . '">' . esc_html( $item['name'] ) . '</option>';
				}
				echo '</select></label></p>';
				submit_button( 'Select Customer', 'secondary', '', false );
				echo '</form>';
				return;
			}
			echo '<input type="hidden" name="customer_id" value="' . esc_attr( (string) $customer_id ) . '">';
			echo '<p><strong>Customer:</strong> ' . esc_html( $customer ? $customer['name'] : '' ) . ' <a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=servsys-service-calls&action=edit' ) ) . '">Change Customer</a></p>';
			echo '<p><label>Site<br><select name="site_id" required><option value="">Select site</option>';
			foreach ( $sites as $item ) {
				echo '<option value="' . esc_attr( (string) $item['id'] ) . '" ' . selected( $site_id, (int) $item['id'], false ) . '>' . esc_html( $item['name'] ?: $item['address1'] ) . '</option>';
			}
			echo '</select></label></p>';
			if ( ! $sites ) {
				echo '<p class="description">This customer has no active sites. Add or activate a site before creating a service call.</p>';
			}
		}
		echo '<h3>Equipment Serviced</h3>';
		if ( $site_id ) {
			if ( ! $site_equipment ) { echo '<p>No equipment is currently recorded for this site.</p>'; }
			foreach ( $site_equipment as $item ) {
				if ( (int) $item['customer_id'] !== $customer_id ) { continue; }
				$label = trim( $item['equipment_type'] . ' — ' . $item['brand'] . ' ' . $item['model_number'] . ' ' . $item['serial_number'] );
				echo '<p><label><input type="checkbox" name="equipment_ids[]" value="' . esc_attr( (string) $item['id'] ) . '" ' . checked( in_array( (int) $item['id'], $selected_equipment, true ), true, false ) . '> ' . esc_html( $label ) . '</label></p>';
			}
			if ( current_user_can( Capabilities::CREATE_EQUIPMENT ) ) {
				echo '<p><button class="button" type="submit" name="action" value="servsys_service_call_add_equipment" formaction="' . esc_url( admin_url( 'admin-post.php' ) ) . '" formmethod="post">Add New Equipment</button></p>';
			}
		} else { echo '<p>Select a valid customer site before associating equipment.</p>'; }
		echo '<p><label>Service Date<br><input type="date" name="service_date" value="' . esc_attr( $call['service_date'] ) . '" required></label></p>';
		echo '<p><label>Technician<br><select name="technician_user_id"><option value="0">Unassigned</option>';
		$company_id = $customer ? (int) $customer['company_id'] : 0;
		$technician_ids = array();
		if ( $company_id ) {
			foreach ( ( new CompanyUserRepository() )->for_company( $company_id ) as $membership ) {
				if ( ! empty( $membership['active'] ) ) { $technician_ids[] = (int) $membership['user_id']; }
			}
			foreach ( get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) ) as $administrator_id ) { $technician_ids[] = (int) $administrator_id; }
		}
		foreach ( array_unique( $technician_ids ) as $technician_id ) {
			if ( ! Authorization::can_access_company( $company_id, $technician_id ) ) { continue; }
			$user = get_userdata( $technician_id ); if ( ! $user ) { continue; }
			echo '<option value="' . esc_attr( (string) $technician_id ) . '" ' . selected( (int) $call['technician_user_id'], $technician_id, false ) . '>' . esc_html( $user->display_name ) . '</option>';
		}
		echo '</select></label></p>';
		echo '<p><label>Status<br><select name="status">';
		foreach ( array( 'open' => 'Open', 'completed' => 'Completed', 'cancelled' => 'Cancelled' ) as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '" ' . selected( $call['status'], $value, false ) . '>' . esc_html( $label ) . '</option>'; }
		echo '</select></label></p>';
		foreach ( array( 'reason' => 'Reason for Call', 'services_performed' => 'Services Performed', 'parts_notes' => 'Parts / Materials', 'recommendations' => 'Recommendations' ) as $name => $label ) {
			echo '<p><label>' . esc_html( $label ) . '<br><textarea class="large-text" rows="4" name="' . esc_attr( $name ) . '">' . esc_textarea( $call[$name] ) . '</textarea></label></p>';
		}
		echo '<fieldset><legend><strong>Follow-up</strong></legend><p class="description">Follow-up is independent of Service Call status. A Completed call may still require follow-up.</p><p><label><input type="checkbox" name="follow_up_required" value="1" ' . checked( (int) $call['follow_up_required'], 1, false ) . '> Follow-up required</label></p><p><label>Follow-up Date (optional)<br><input type="date" name="follow_up_date" value="' . esc_attr( (string) $call['follow_up_date'] ) . '"></label></p></fieldset>';
		submit_button( $id ? 'Save Service Call' : 'Add Service Call' );
		echo '</form>';
	}

	public function add_equipment_from_form(): void {
		check_admin_referer( 'servsys_save_service_call' );
		$id = isset( $_POST['service_call_id'] ) ? absint( wp_unslash( $_POST['service_call_id'] ) ) : 0;
		if ( ! current_user_can( $id ? Capabilities::EDIT_SERVICE_CALLS : Capabilities::CREATE_SERVICE_CALLS ) || ! current_user_can( Capabilities::CREATE_EQUIPMENT ) ) {
			wp_die( esc_html__( 'Permission denied.', 'servsys' ) );
		}
		$data = wp_unslash( $_POST );
		$customer_id = isset( $data['customer_id'] ) ? absint( $data['customer_id'] ) : 0;
		$site_id = isset( $data['site_id'] ) ? absint( $data['site_id'] ) : 0;
		$site = $site_id ? ( new SiteRepository() )->find( $site_id ) : null;
		if ( ! $customer_id || ! $site || (int) $site['customer_id'] !== $customer_id ) {
			wp_die( esc_html__( 'A valid customer site is required before equipment can be added.', 'servsys' ) );
		}
		$data['customer_id'] = $customer_id;
		$data['site_id'] = $site_id;
		$data['equipment_ids'] = isset( $data['equipment_ids'] ) ? array_map( 'absint', (array) $data['equipment_ids'] ) : array();
		unset( $data['_wpnonce'], $data['_wp_http_referer'], $data['action'], $data['servsys_add_equipment'] );
		$key = wp_generate_password( 20, false, false );
		set_transient( 'servsys_sc_draft_' . get_current_user_id() . '_' . $key, $data, 30 * MINUTE_IN_SECONDS );
		$return = admin_url( 'admin.php?page=servsys-service-calls&action=edit' . ( $id ? '&service_call_id=' . $id : '' ) . '&draft=' . rawurlencode( $key ) );
		$equipment_url = add_query_arg(
			array(
				'page' => 'servsys-equipment',
				'action' => 'edit',
				'customer_id' => $customer_id,
				'site_id' => $site_id,
				'source' => 'service-call',
				'return_to' => rawurlencode( $return ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $equipment_url );
		exit;
	}

	public function add_note(): void {
		check_admin_referer( 'servsys_add_service_call_note' );
		$id = isset( $_POST['service_call_id'] ) ? absint( wp_unslash( $_POST['service_call_id'] ) ) : 0;
		if ( ! current_user_can( Capabilities::EDIT_SERVICE_CALLS ) ) {
			wp_die( esc_html__( 'Permission denied.', 'servsys' ) );
		}
		( new ServiceCallNoteService() )->create( $id, wp_unslash( $_POST ) );
		wp_safe_redirect( admin_url( 'admin.php?page=servsys-service-calls&service_call_id=' . $id ) );
		exit;
	}

	public function save_service_call(): void {
		check_admin_referer( 'servsys_save_service_call' );
		$id = isset( $_POST['service_call_id'] ) ? absint( wp_unslash( $_POST['service_call_id'] ) ) : 0;
		if ( ! current_user_can( $id ? Capabilities::EDIT_SERVICE_CALLS : Capabilities::CREATE_SERVICE_CALLS ) ) {
			wp_die( esc_html__( 'Permission denied.', 'servsys' ) );
		}
		$customer_id = isset( $_POST['customer_id'] ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0;
		$site_id = isset( $_POST['site_id'] ) ? absint( wp_unslash( $_POST['site_id'] ) ) : 0;
		$data = wp_unslash( $_POST );
		$data['follow_up_required'] = isset( $data['follow_up_required'] );
		$id = ( new ServiceCallService() )->save( $customer_id, $site_id, $data, $id );
		wp_safe_redirect( admin_url( 'admin.php?page=servsys-service-calls&service_call_id=' . $id ) );
		exit;
	}

	private function row( string $label, string $value ): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
	}

	private function section( string $heading, $value ): void {
		echo '<h3>' . esc_html( $heading ) . '</h3><p>' . nl2br( esc_html( (string) $value ) ) . '</p>';
	}
}
