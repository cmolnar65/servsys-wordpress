<?php

namespace ServSys\Admin;

use ServSys\Customers\CustomerRepository;
use ServSys\Equipment\EquipmentRepository;
use ServSys\Equipment\EquipmentService;
use ServSys\Sites\SiteRepository;
use ServSys\Users\Capabilities;

final class EquipmentPage {
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_servsys_save_equipment', array( $this, 'save_equipment' ) );
	}

	public function register_menu(): void {
		add_submenu_page( 'servsys', 'Equipment', 'Equipment', Capabilities::VIEW_EQUIPMENT, 'servsys-equipment', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_EQUIPMENT ) ) {
			wp_die( esc_html__( 'You do not have permission to view ServSys equipment.', 'servsys' ) );
		}

		$equipment_id = isset( $_GET['equipment_id'] ) ? absint( $_GET['equipment_id'] ) : 0;
		$site_id      = isset( $_GET['site_id'] ) ? absint( $_GET['site_id'] ) : 0;
		$customer_id  = isset( $_GET['customer_id'] ) ? absint( $_GET['customer_id'] ) : 0;
		$edit         = isset( $_GET['action'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['action'] ) );

		echo '<div class="wrap"><h1>ServSys Equipment</h1>';
		if ( $equipment_id || $edit ) {
			$this->render_equipment( $equipment_id, $customer_id, $site_id );
		} else {
			$this->render_list();
		}
		echo '</div>';
	}

	private function render_list(): void {
		$term      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$equipment = ( new EquipmentRepository() )->search( $term, true );
		$clear_url = admin_url( 'admin.php?page=servsys-equipment' );

		echo '<form method="get"><input type="hidden" name="page" value="servsys-equipment"><p class="search-box">';
		echo '<a class="button" href="' . esc_url( $clear_url ) . '" title="Clear equipment search" aria-label="Clear equipment search"><span class="dashicons dashicons-dismiss"></span><span class="screen-reader-text">Clear equipment search</span></a> ';
		echo '<input type="search" name="s" value="' . esc_attr( $term ) . '"> ';
		submit_button( 'Search Equipment', 'secondary', '', false );
		echo '</p></form>';

		echo '<p>Equipment is added from a customer site so the customer/site relationship is always explicit.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Type</th><th>Brand / Model</th><th>Serial</th><th>Customer</th><th>Site</th><th>Status</th><th>Action</th></tr></thead><tbody>';
		foreach ( $equipment as $item ) {
			$url = admin_url( 'admin.php?page=servsys-equipment&action=edit&equipment_id=' . (int) $item['id'] . '&customer_id=' . (int) $item['customer_id'] . '&site_id=' . (int) $item['site_id'] );
			echo '<tr><td>' . esc_html( $item['equipment_type'] ) . '</td>';
			echo '<td>' . esc_html( trim( $item['brand'] . ' ' . $item['model_number'] ) ) . '</td>';
			echo '<td>' . esc_html( $item['serial_number'] ) . '</td>';
			echo '<td>' . esc_html( $item['customer_name'] ) . '</td>';
			echo '<td>' . esc_html( $item['site_name'] ?: $item['site_address'] ) . '</td>';
			echo '<td>' . ( $item['active'] ? 'Active' : 'Inactive' ) . '</td>';
			echo '<td><a class="button button-small" href="' . esc_url( $url ) . '">Edit</a></td></tr>';
		}
		echo '</tbody></table>';
	}

	private function render_equipment( int $equipment_id, int $customer_id, int $site_id ): void {
		$repository = new EquipmentRepository();
		$item       = $equipment_id ? $repository->find( $equipment_id ) : null;

		if ( $equipment_id && ! $item ) {
			wp_die( esc_html__( 'Equipment not found.', 'servsys' ) );
		}
		if ( ! current_user_can( $equipment_id ? Capabilities::EDIT_EQUIPMENT : Capabilities::CREATE_EQUIPMENT ) ) {
			wp_die( esc_html__( 'You do not have permission to edit equipment.', 'servsys' ) );
		}

		if ( $item ) {
			$customer_id = (int) $item['customer_id'];
			$site_id     = (int) $item['site_id'];
		}
		$customer = ( new CustomerRepository() )->find( $customer_id );
		$site     = ( new SiteRepository() )->find( $site_id );
		if ( ! $customer || ! $site || (int) $site['customer_id'] !== $customer_id ) {
			wp_die( esc_html__( 'A valid customer site is required before equipment can be added.', 'servsys' ) );
		}

		$item = $item ?: array_fill_keys( array( 'equipment_type', 'brand', 'model_number', 'serial_number', 'filter', 'seer', 'btu_output', 'notes' ), '' );
		$item['active'] = isset( $item['active'] ) ? $item['active'] : 1;
		$list_url       = admin_url( 'admin.php?page=servsys-equipment' );
		$customer_url   = admin_url( 'admin.php?page=servsys&customer_id=' . $customer_id );

		echo '<p><a class="button" href="' . esc_url( $list_url ) . '">&larr; Return to Equipment List</a> ';
		echo '<a class="button" href="' . esc_url( $customer_url ) . '">Return to Customer</a></p>';
		echo '<h2>' . ( $equipment_id ? 'Edit Equipment' : 'Add Equipment' ) . '</h2>';
		echo '<p><strong>Customer:</strong> ' . esc_html( $customer['name'] ) . '<br><strong>Site:</strong> ' . esc_html( $site['name'] ?: $site['address1'] ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'servsys_save_equipment_' . $equipment_id );
		echo '<input type="hidden" name="action" value="servsys_save_equipment"><input type="hidden" name="equipment_id" value="' . $equipment_id . '"><input type="hidden" name="customer_id" value="' . $customer_id . '"><input type="hidden" name="site_id" value="' . $site_id . '">';
		$this->input( 'equipment_type', 'Equipment Type', $item['equipment_type'], true );
		$this->input( 'brand', 'Brand', $item['brand'] );
		$this->input( 'model_number', 'Model Number', $item['model_number'] );
		$this->input( 'serial_number', 'Serial Number', $item['serial_number'] );
		$this->input( 'filter', 'Filter', $item['filter'] );
		$this->input( 'seer', 'SEER', $item['seer'] );
		$this->input( 'btu_output', 'BTU Output', $item['btu_output'] );
		echo '<p><label>Notes<br><textarea class="large-text" rows="5" name="notes">' . esc_textarea( $item['notes'] ) . '</textarea></label></p>';
		echo '<p><label><input type="checkbox" name="active" value="1" ' . checked( (int) $item['active'], 1, false ) . '> Active</label></p>';
		submit_button( $equipment_id ? 'Save Equipment' : 'Add Equipment' );
		echo '</form><p><a class="button" href="' . esc_url( $list_url ) . '">&larr; Return to Equipment List</a> <a class="button" href="' . esc_url( $customer_url ) . '">Return to Customer</a></p>';
	}

	private function input( string $name, string $label, $value, bool $required = false ): void {
		echo '<p><label>' . esc_html( $label ) . '<br><input class="regular-text" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $required ? ' required' : '' ) . '></label></p>';
	}

	public function save_equipment(): void {
		$id          = isset( $_POST['equipment_id'] ) ? absint( $_POST['equipment_id'] ) : 0;
		$customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
		$site_id     = isset( $_POST['site_id'] ) ? absint( $_POST['site_id'] ) : 0;
		check_admin_referer( 'servsys_save_equipment_' . $id );
		if ( ! current_user_can( $id ? Capabilities::EDIT_EQUIPMENT : Capabilities::CREATE_EQUIPMENT ) ) {
			wp_die( esc_html__( 'Permission denied.', 'servsys' ) );
		}
		$data = wp_unslash( $_POST );
		$data['active'] = isset( $_POST['active'] );
		$id = ( new EquipmentService() )->save( $customer_id, $site_id, $data, $id );
		wp_safe_redirect( admin_url( 'admin.php?page=servsys-equipment&action=edit&equipment_id=' . $id . '&customer_id=' . $customer_id . '&site_id=' . $site_id ) );
		exit;
	}
}
