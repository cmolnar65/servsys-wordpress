<?php

namespace ServSys\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ServSys\Equipment\EquipmentRepository;
use ServSys\ServiceCalls\ServiceCallRepository;
use ServSys\Users\Capabilities;

final class ServiceCallsPage {
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
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

		echo '<div class="wrap"><h1>ServSys Service Calls</h1>';
		if ( $id ) {
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

	private function row( string $label, string $value ): void {
		echo '<tr><th scope="row" style="width:180px">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
	}

	private function section( string $heading, $value ): void {
		echo '<h3>' . esc_html( $heading ) . '</h3><p>' . nl2br( esc_html( (string) $value ) ) . '</p>';
	}
}
