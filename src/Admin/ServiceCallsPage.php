<?php

namespace ServSys\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ServSys\Customers\CustomerRepository;
use ServSys\Equipment\EquipmentRepository;
use ServSys\ServiceCalls\ServiceCallService;
use ServSys\Sites\SiteRepository;
use ServSys\ServiceCalls\ServiceCallRepository;
use ServSys\Users\Capabilities;

final class ServiceCallsPage {
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_servsys_save_service_call', array( $this, 'save_service_call' ) );
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
		$calls = ( new ServiceCallRepository() )->search( $term, $status );
		if ( current_user_can( Capabilities::CREATE_SERVICE_CALLS ) ) {
			echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=servsys-service-calls&action=edit' ) ) . '">Add Service Call</a></p>';
		}

		echo '<form method="get"><input type="hidden" name="page" value="servsys-service-calls"><p class="search-box">';
		echo '<input type="search" name="s" value="' . esc_attr( $term ) . '" placeholder="Call #, customer, site, reason"> ';
		echo '<select name="status"><option value="">All statuses</option>';
		foreach ( array( 'open' => 'Open', 'completed' => 'Completed', 'cancelled' => 'Cancelled' ) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
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
			echo '<td>' . ( $call['follow_up_required'] ? esc_html( $call['follow_up_date'] ?: 'Required' ) : '&mdash;' ) . '</td></tr>';
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
		$this->row( 'Follow-up', $call['follow_up_required'] ? ( $call['follow_up_date'] ?: 'Required' ) : 'No' );
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
		$customers = ( new CustomerRepository() )->search( '', false );
		$sites = $customer_id ? ( new SiteRepository() )->for_customer( $customer_id, false ) : array();

		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=servsys-service-calls' ) ) . '">&larr; Return to Service Calls</a></p><h2>' . ( $id ? 'Edit Service Call' : 'Add Service Call' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'servsys_save_service_call' );
		echo '<input type="hidden" name="action" value="servsys_save_service_call"><input type="hidden" name="service_call_id" value="' . esc_attr( (string) $id ) . '"><input type="hidden" name="source" value="' . esc_attr( $source ) . '">';
		if ( 'site' === $source || $id ) {
			echo '<input type="hidden" name="customer_id" value="' . esc_attr( (string) $customer_id ) . '"><input type="hidden" name="site_id" value="' . esc_attr( (string) $site_id ) . '"><p><strong>Customer:</strong> ' . esc_html( $customer ? $customer['name'] : '' ) . '<br><strong>Site:</strong> ' . esc_html( $site ? ( $site['name'] ?: $site['address1'] ) : '' ) . '</p>';
		} else {
			echo '<p><label>Customer<br><select name="customer_id" required><option value="">Select customer</option>';
			foreach ( $customers as $item ) {
				echo '<option value="' . esc_attr( (string) $item['id'] ) . '" ' . selected( $customer_id, (int) $item['id'], false ) . '>' . esc_html( $item['name'] ) . '</option>';
			}
			echo '</select></label></p><p><label>Site<br><select name="site_id" required><option value="">Select site</option>';
			foreach ( $sites as $item ) {
				echo '<option value="' . esc_attr( (string) $item['id'] ) . '" ' . selected( $site_id, (int) $item['id'], false ) . '>' . esc_html( $item['name'] ?: $item['address1'] ) . '</option>';
			}
			echo '</select></label></p><p class="description">Select a customer first and reopen Add Service Call with that customer to choose one of its sites. Site-origin creation preselects both.</p>';
		}
		echo '<p><label>Service Date<br><input type="date" name="service_date" value="' . esc_attr( $call['service_date'] ) . '" required></label></p>';
		echo '<p><label>Technician<br>'; wp_dropdown_users( array( 'name' => 'technician_user_id', 'selected' => (int) $call['technician_user_id'], 'show_option_none' => 'Unassigned' ) ); echo '</label></p>';
		echo '<p><label>Status<br><select name="status">';
		foreach ( array( 'open' => 'Open', 'completed' => 'Completed', 'cancelled' => 'Cancelled' ) as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '" ' . selected( $call['status'], $value, false ) . '>' . esc_html( $label ) . '</option>'; }
		echo '</select></label></p>';
		foreach ( array( 'reason' => 'Reason for Call', 'services_performed' => 'Services Performed', 'parts_notes' => 'Parts / Materials', 'recommendations' => 'Recommendations' ) as $name => $label ) {
			echo '<p><label>' . esc_html( $label ) . '<br><textarea class="large-text" rows="4" name="' . esc_attr( $name ) . '">' . esc_textarea( $call[$name] ) . '</textarea></label></p>';
		}
		echo '<p><label><input type="checkbox" name="follow_up_required" value="1" ' . checked( (int) $call['follow_up_required'], 1, false ) . '> Follow-up required</label></p><p><label>Follow-up Date<br><input type="date" name="follow_up_date" value="' . esc_attr( (string) $call['follow_up_date'] ) . '"></label></p>';
		submit_button( $id ? 'Save Service Call' : 'Add Service Call' );
		echo '</form>';
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
		echo '<tr><th scope="row" style="width:180px">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
	}

	private function section( string $heading, $value ): void {
		echo '<h3>' . esc_html( $heading ) . '</h3><p>' . nl2br( esc_html( (string) $value ) ) . '</p>';
	}
}
