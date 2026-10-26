<?php

namespace ServSys\Admin;

use ServSys\Companies\CompanyContext;
use ServSys\Inventory\InventoryLocationRepository;
use ServSys\Inventory\InventoryLocationService;
use ServSys\Users\Capabilities;
use ServSys\Users\CompanyUserRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InventoryLocationsPage {
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_servsys_save_inventory_location', array( $this, 'save' ) );
	}

	public function register_menu(): void {
		add_submenu_page( null, 'Inventory Locations', 'Inventory Locations', Capabilities::VIEW_INVENTORY, 'servsys-inventory-locations', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_INVENTORY ) ) {
			wp_die( esc_html__( 'You do not have permission to view inventory locations.', 'servsys' ) );
		}

		$repository = new InventoryLocationRepository();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation; no state change.
		$edit_id = isset( $_GET['location_id'] ) ? absint( wp_unslash( $_GET['location_id'] ) ) : 0;
		$editing = $edit_id ? $repository->find( $edit_id ) : null;
		if ( $edit_id && ! $editing ) {
			wp_die( esc_html__( 'Inventory location not found.', 'servsys' ) );
		}

		echo '<div class="wrap"><h1>ServSys Inventory Locations</h1>';
		echo '<p>Inventory locations belong to the current company. Deactivate locations that should no longer receive new inventory; historical records are preserved.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Type</th><th>Assigned User</th><th>Truck</th><th>Status</th><th>Action</th></tr></thead><tbody>';
		foreach ( $repository->all( true ) as $location ) {
			$user = ! empty( $location['company_user_id'] ) ? get_userdata( (int) $location['company_user_id'] ) : null;
			echo '<tr><td>' . esc_html( $location['name'] ) . '</td><td>' . esc_html( $this->type_label( $location['location_type'] ) ) . '</td><td>' . esc_html( $user ? $user->display_name : '' ) . '</td><td>' . esc_html( $location['truck_number'] ) . '</td><td>' . ( $location['active'] ? 'Active' : 'Inactive' ) . '</td><td>';
			if ( current_user_can( Capabilities::MANAGE_INVENTORY ) ) {
				echo '<a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=servsys-inventory-locations&location_id=' . (int) $location['id'] ) ) . '">Edit</a>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		if ( current_user_can( Capabilities::MANAGE_INVENTORY ) ) {
			$this->render_form( $editing ?: array() );
		}
		echo '</div>';
	}

	private function render_form( array $location ): void {
		$id = isset( $location['id'] ) ? (int) $location['id'] : 0;
		$location = wp_parse_args( $location, array( 'name'=>'', 'location_type'=>'other', 'company_user_id'=>0, 'truck_number'=>'', 'notes'=>'', 'active'=>1 ) );
		echo '<h2>' . ( $id ? 'Edit Inventory Location' : 'Add Inventory Location' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'servsys_save_inventory_location' );
		echo '<input type="hidden" name="action" value="servsys_save_inventory_location"><input type="hidden" name="location_id" value="' . esc_attr( (string) $id ) . '">';
		echo '<p><label>Name<br><input class="regular-text" type="text" name="name" value="' . esc_attr( $location['name'] ) . '" required></label></p>';
		echo '<p><label>Location Type<br><select name="location_type">';
		foreach ( array( 'warehouse'=>'Warehouse', 'shop'=>'Shop', 'truck'=>'Truck', 'technician_vehicle'=>'Technician Vehicle', 'other'=>'Other' ) as $value=>$label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $location['location_type'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label></p><p><label>Assigned Company User<br><select name="company_user_id"><option value="0">None</option>';
		foreach ( ( new CompanyUserRepository() )->for_company( CompanyContext::current_company_id() ) as $membership ) {
			if ( empty( $membership['active'] ) && (int) $membership['user_id'] !== (int) $location['company_user_id'] ) { continue; }
			$user = get_userdata( (int) $membership['user_id'] );
			if ( ! $user ) { continue; }
			echo '<option value="' . esc_attr( (string) $membership['user_id'] ) . '" ' . selected( (int) $location['company_user_id'], (int) $membership['user_id'], false ) . '>' . esc_html( $user->display_name . ( empty( $membership['active'] ) ? ' (Inactive)' : '' ) ) . '</option>';
		}
		echo '</select></label></p><p><label>Truck Number<br><input class="regular-text" type="text" name="truck_number" value="' . esc_attr( $location['truck_number'] ) . '"></label></p>';
		echo '<p><label>Notes<br><textarea class="large-text" rows="4" name="notes">' . esc_textarea( $location['notes'] ) . '</textarea></label></p>';
		echo '<p><label><input type="checkbox" name="active" value="1" ' . checked( (int) $location['active'], 1, false ) . '> Active</label></p>';
		submit_button( $id ? 'Save Inventory Location' : 'Add Inventory Location' );
		echo '</form>';
	}

	public function save(): void {
		check_admin_referer( 'servsys_save_inventory_location' );
		if ( ! current_user_can( Capabilities::MANAGE_INVENTORY ) ) {
			wp_die( esc_html__( 'Permission denied.', 'servsys' ) );
		}
		$id = isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0;
		$data = wp_unslash( $_POST );
		$data['active'] = isset( $_POST['active'] );
		try {
			( new InventoryLocationService() )->save( $data, $id );
			$url = admin_url( 'admin.php?page=servsys-inventory-locations' );
		} catch ( \InvalidArgumentException | \RuntimeException $exception ) {
			$url = add_query_arg( 'servsys_error', $exception->getMessage(), admin_url( 'admin.php?page=servsys-inventory-locations' ) );
		}
		wp_safe_redirect( $url );
		exit;
	}

	private function type_label( string $type ): string {
		$labels = array( 'warehouse'=>'Warehouse', 'shop'=>'Shop', 'truck'=>'Truck', 'technician_vehicle'=>'Technician Vehicle', 'other'=>'Other' );
		return $labels[ $type ] ?? ucfirst( str_replace( '_', ' ', $type ) );
	}
}
