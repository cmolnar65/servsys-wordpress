<?php

namespace ServSys\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ServSys\Customers\CustomerRepository;
use ServSys\Customers\CustomerService;
use ServSys\Customers\CustomerTypeRepository;
use ServSys\Customers\NoteRepository;
use ServSys\Customers\NoteService;
use ServSys\Equipment\EquipmentRepository;
use ServSys\Sites\SiteRepository;
use ServSys\Sites\SiteService;
use ServSys\Users\Capabilities;

final class CustomersPage {
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_servsys_save_customer', array( $this, 'save_customer' ) );
		add_action( 'admin_post_servsys_save_site', array( $this, 'save_site' ) );
		add_action( 'admin_post_servsys_add_note', array( $this, 'add_note' ) );
		add_action( 'admin_post_servsys_save_customer_type', array( $this, 'save_customer_type' ) );
	}

	public function register_menu(): void {
		add_menu_page( 'ServSys', 'ServSys', Capabilities::ACCESS, 'servsys', array( $this, 'render' ), 'dashicons-admin-tools', 26 );
		add_submenu_page( 'servsys', 'Customers', 'Customers', Capabilities::VIEW_CUSTOMERS, 'servsys', array( $this, 'render' ) );
		add_submenu_page( 'servsys', 'Customer Types', 'Customer Types', Capabilities::EDIT_CUSTOMERS, 'servsys-customer-types', array( $this, 'render_customer_types' ) );
	}

	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'servsys' ) ) {
			return;
		}
		wp_enqueue_script( 'servsys-admin-customers', plugins_url( 'assets/admin-customers.js', SERVSYS_PLUGIN_FILE ), array(), SERVSYS_VERSION, true );
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_CUSTOMERS ) ) {
			wp_die( esc_html__( 'You do not have permission to view ServSys customers.', 'servsys' ) );
		}

		$customer_id = isset( $_GET['customer_id'] ) ? absint( $_GET['customer_id'] ) : 0;
		$action      = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$edit        = 'edit' === $action;

		echo '<div class="wrap"><h1>ServSys Customers</h1>';
		if ( 'view-site' === $action && $customer_id ) {
			$this->render_site_view( $customer_id, isset( $_GET['site_id'] ) ? absint( $_GET['site_id'] ) : 0 );
		} elseif ( 'edit-site' === $action && $customer_id ) {
			$this->render_customer_view( $customer_id );
		} elseif ( $edit ) {
			$this->render_customer_edit( $customer_id );
		} elseif ( $customer_id ) {
			$this->render_customer_view( $customer_id );
		} else {
			$this->render_list();
		}
		echo '</div>';
	}

	private function render_list(): void {
		$term      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$customers = ( new CustomerRepository() )->search( $term, true );
		$clear_url = admin_url( 'admin.php?page=servsys' );

		echo '<form method="get"><input type="hidden" name="page" value="servsys">';
		echo '<p class="search-box">';
		echo '<a class="button" href="' . esc_url( $clear_url ) . '" title="Clear customer search" aria-label="Clear customer search"><span class="dashicons dashicons-dismiss"></span><span class="screen-reader-text">Clear customer search</span></a> ';
		echo '<input type="search" name="s" value="' . esc_attr( $term ) . '"> ';
		submit_button( 'Search Customers', 'secondary', '', false );
		echo '</p></form>';

		if ( current_user_can( Capabilities::CREATE_CUSTOMERS ) ) {
			echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=servsys&action=edit' ) ) . '">Add Customer</a></p>';
		}

		echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Status</th></tr></thead><tbody>';
		foreach ( $customers as $customer ) {
			$url = admin_url( 'admin.php?page=servsys&customer_id=' . (int) $customer['id'] );
			echo '<tr><td><a href="' . esc_url( $url ) . '">' . esc_html( $customer['name'] ) . '</a></td>';
			echo '<td>' . esc_html( $customer['primary_phone'] ) . '</td><td>' . esc_html( $customer['email'] ) . '</td>';
			echo '<td>' . ( $customer['active'] ? 'Active' : 'Inactive' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function render_customer_view( int $customer_id ): void {
		$customer = ( new CustomerRepository() )->find( $customer_id );
		if ( ! $customer ) {
			wp_die( esc_html__( 'Customer not found.', 'servsys' ) );
		}

		$list_url = admin_url( 'admin.php?page=servsys' );
		echo '<p><a class="button" href="' . esc_url( $list_url ) . '">&larr; Return to Customer List</a>';
		if ( current_user_can( Capabilities::EDIT_CUSTOMERS ) ) {
			echo ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=servsys&action=edit&customer_id=' . $customer_id ) ) . '">Edit Customer</a>';
		}
		echo '</p><h2>' . esc_html( $customer['name'] ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:900px"><tbody>';
		foreach ( array( 'Customer Type' => $customer['customer_type'], 'Primary Phone' => $customer['primary_phone'], 'Alternate Phone' => $customer['alternate_phone'], 'Email' => $customer['email'], 'Status' => ( $customer['active'] ? 'Active' : 'Inactive' ) ) as $label => $value ) {
			echo '<tr><th style="width:180px">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}
		echo '<tr><th>Notes</th><td>' . nl2br( esc_html( $customer['notes'] ) ) . '</td></tr></tbody></table>';

		$sites = ( new SiteRepository() )->for_customer( $customer_id, true );
		echo '<hr><h2>Sites &amp; Equipment</h2>';
		$this->render_sites_table( $customer_id, $sites );
		$edit_site_id = isset( $_GET['site_id'] ) ? absint( $_GET['site_id'] ) : 0;
		$edit_site = ( 'edit-site' === ( isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '' ) && $edit_site_id ) ? ( new SiteRepository() )->find( $edit_site_id ) : null;
		if ( $edit_site && (int) $edit_site['customer_id'] !== $customer_id ) {
			$edit_site = null;
		}
		if ( $edit_site && current_user_can( Capabilities::EDIT_SITES ) ) {
			$this->render_site_form( $customer_id, $edit_site );
		} elseif ( current_user_can( Capabilities::CREATE_SITES ) ) {
			$this->render_site_form( $customer_id );
		}

		echo '<hr><h2>Notes</h2>';
		$site_names = array();
		foreach ( $sites as $site ) {
			$site_names[ (int) $site['id'] ] = $site['name'] ?: $site['address1'];
		}
		foreach ( ( new NoteRepository() )->for_customer( $customer_id ) as $note ) {
			$scope = empty( $note['site_id'] ) ? 'Customer-wide' : 'Site: ' . ( $site_names[ (int) $note['site_id'] ] ?? 'Unknown site' );
			echo '<div class="notice notice-info inline"><p><strong>' . esc_html( $note['subject'] ?: 'Note' ) . '</strong> <em>(' . esc_html( $scope ) . ')</em><br>' . nl2br( esc_html( $note['note'] ) ) . '</p></div>';
		}
		if ( current_user_can( Capabilities::EDIT_CUSTOMERS ) ) {
			$this->render_note_form( $customer_id, $sites );
		}
		echo '<p><a class="button" href="' . esc_url( $list_url ) . '">&larr; Return to Customer List</a></p>';
	}

	private function render_customer_edit( int $customer_id ): void {
		$repository = new CustomerRepository();
		$customer = $customer_id ? $repository->find( $customer_id ) : null;
		if ( $customer_id && ! $customer ) {
			wp_die( esc_html__( 'Customer not found.', 'servsys' ) );
		}
		if ( ! current_user_can( $customer_id ? Capabilities::EDIT_CUSTOMERS : Capabilities::CREATE_CUSTOMERS ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this customer.', 'servsys' ) );
		}
		$list_url = admin_url( 'admin.php?page=servsys' );
		$view_url = $customer_id ? admin_url( 'admin.php?page=servsys&customer_id=' . $customer_id ) : $list_url;
		echo '<p><a class="button" href="' . esc_url( $view_url ) . '">&larr; ' . ( $customer_id ? 'Return to Customer' : 'Return to Customer List' ) . '</a></p>';

		$customer = $customer ?: array_fill_keys( array( 'name', 'customer_type', 'customer_type_id', 'primary_phone', 'alternate_phone', 'email', 'notes' ), '' );
		$customer['active'] = isset( $customer['active'] ) ? $customer['active'] : 1;
		echo '<h2>' . ( $customer_id ? 'Edit ' . esc_html( $customer['name'] ) : 'Add Customer' ) . '</h2>';
		echo '<form id="servsys-customer-form" data-existing-customer="' . ( $customer_id ? '1' : '0' ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'servsys_save_customer_' . $customer_id );
		echo '<input type="hidden" name="action" value="servsys_save_customer"><input type="hidden" name="customer_id" value="' . esc_attr( (string) $customer_id ) . '">';
		$this->input( 'name', 'Name', $customer['name'], true );
		$this->customer_type_select( isset( $customer['customer_type_id'] ) ? (int) $customer['customer_type_id'] : 0 );
		$this->input( 'primary_phone', 'Primary Phone', $customer['primary_phone'] );
		$this->input( 'alternate_phone', 'Alternate Phone', $customer['alternate_phone'] );
		$this->input( 'email', 'Email', $customer['email'], false, 'email' );
		echo '<p><label>Notes<br><textarea class="large-text" rows="4" name="notes">' . esc_textarea( $customer['notes'] ) . '</textarea></label></p>';
		echo '<p><label><input type="checkbox" name="active" value="1" ' . checked( (int) $customer['active'], 1, false ) . '> Active</label></p>';
		submit_button( $customer_id ? 'Save Customer' : 'Create Customer' );
		echo '</form>';
		if ( ! $customer_id ) {
			echo '<div class="notice notice-info inline"><p>Save the customer before adding sites or equipment.</p></div>';
		}
	}

	private function customer_type_select( int $selected ): void {
		$types = ( new CustomerTypeRepository() )->all( true );
		echo '<p><label>Customer Type<br><select name="customer_type_id"><option value="">Select customer type</option>';
		foreach ( $types as $type ) {
			if ( ! $type['active'] && (int) $type['id'] !== $selected ) {
				continue;
			}
			$label = $type['name'] . ( $type['active'] ? '' : ' (Inactive)' );
			echo '<option value="' . (int) $type['id'] . '" ' . selected( $selected, (int) $type['id'], false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label></p>';
	}

	private function render_sites_table( int $customer_id, array $sites ): void {
		if ( ! $sites ) {
			echo '<p>No sites have been added.</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr><th>Site</th><th>Address</th><th>City / State / ZIP</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
		foreach ( $sites as $site ) {
			$site_view_url = admin_url( 'admin.php?page=servsys&action=view-site&customer_id=' . $customer_id . '&site_id=' . (int) $site['id'] );
			$site_url = admin_url( 'admin.php?page=servsys&action=edit-site&customer_id=' . $customer_id . '&site_id=' . (int) $site['id'] );
			$address  = $site['address1'] . ( $site['address2'] ? ', ' . $site['address2'] : '' );
			echo '<tr><td><strong><a href="' . esc_url( $site_view_url ) . '">' . esc_html( $site['name'] ?: 'Site #' . $site['id'] ) . '</a></strong></td>';
			echo '<td>' . esc_html( $address ) . '</td>';
			echo '<td>' . esc_html( $site['city'] . ', ' . $site['state'] . ' ' . $site['postal_code'] ) . '</td>';
			echo '<td>' . esc_html( $site['phone'] ) . '</td>';
			echo '<td>' . ( $site['active'] ? 'Active' : 'Inactive' ) . '</td>';
			$equipment_url = admin_url( 'admin.php?page=servsys-equipment&action=edit&source=site&customer_id=' . $customer_id . '&site_id=' . (int) $site['id'] );
			echo '<td>';
			if ( current_user_can( Capabilities::EDIT_SITES ) ) {
				echo '<a class="button button-small" href="' . esc_url( $site_url ) . '">Edit Site</a> ';
			}
			if ( current_user_can( Capabilities::CREATE_EQUIPMENT ) ) {
				echo '<a class="button button-small" href="' . esc_url( $equipment_url ) . '">Add Equipment</a>';
			}
			echo '</td></tr>';
			$site_equipment = ( new EquipmentRepository() )->for_site( (int) $site['id'], true );
			if ( $site_equipment ) {
				echo '<tr><td colspan="6"><strong>Equipment:</strong><table class="widefat striped" style="margin-top:8px"><thead><tr><th>Type</th><th>Brand / Model</th><th>Serial</th><th>Filter</th><th>Status</th><th>Action</th></tr></thead><tbody>';
				foreach ( $site_equipment as $item ) {
					$item_url = admin_url( 'admin.php?page=servsys-equipment&action=edit&source=site&equipment_id=' . (int) $item['id'] . '&customer_id=' . $customer_id . '&site_id=' . (int) $site['id'] );
					echo '<tr><td>' . esc_html( $item['equipment_type'] ) . '</td><td>' . esc_html( trim( $item['brand'] . ' ' . $item['model_number'] ) ) . '</td><td>' . esc_html( $item['serial_number'] ) . '</td><td>' . esc_html( $item['filter'] ) . '</td><td>' . ( $item['active'] ? 'Active' : 'Inactive' ) . '</td><td><a class="button button-small" href="' . esc_url( $item_url ) . '">Edit</a></td></tr>';
				}
				echo '</tbody></table></td></tr>';
			}
		}
		echo '</tbody></table>';
	}

	private function render_site_view( int $customer_id, int $site_id ): void {
		$customer = ( new CustomerRepository() )->find( $customer_id );
		$site = ( new SiteRepository() )->find( $site_id );
		if ( ! $customer || ! $site || (int) $site['customer_id'] !== $customer_id ) {
			wp_die( esc_html__( 'Site not found for this customer.', 'servsys' ) );
		}

		$customer_url = admin_url( 'admin.php?page=servsys&customer_id=' . $customer_id );
		echo '<p><a class="button" href="' . esc_url( $customer_url ) . '">&larr; Return to Customer</a>';
		if ( current_user_can( Capabilities::EDIT_SITES ) ) {
			echo ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=servsys&action=edit-site&customer_id=' . $customer_id . '&site_id=' . $site_id ) ) . '">Edit Site</a>';
		}
		if ( current_user_can( Capabilities::CREATE_EQUIPMENT ) ) {
			echo ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=servsys-equipment&action=edit&source=site&customer_id=' . $customer_id . '&site_id=' . $site_id ) ) . '">Add Equipment</a>';
		}
		echo '</p><h2>' . esc_html( $site['name'] ?: $site['address1'] ) . '</h2>';
		echo '<p><strong>Customer:</strong> <a href="' . esc_url( $customer_url ) . '">' . esc_html( $customer['name'] ) . '</a></p>';
		echo '<table class="widefat striped" style="max-width:900px"><tbody>';
		foreach ( array(
			'Address' => trim( $site['address1'] . ( $site['address2'] ? ', ' . $site['address2'] : '' ) ),
			'City / State / ZIP' => trim( $site['city'] . ', ' . $site['state'] . ' ' . $site['postal_code'] ),
			'Phone' => $site['phone'],
			'Status' => ( $site['active'] ? 'Active' : 'Inactive' ),
			'Notes' => $site['notes'],
		) as $label => $value ) {
			echo '<tr><th style="width:180px">' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( $value ) ) . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<hr><h2>Equipment</h2>';
		$equipment = ( new EquipmentRepository() )->for_site( $site_id, true );
		if ( ! $equipment ) {
			echo '<p>No equipment has been added to this site.</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Type</th><th>Brand / Model</th><th>Serial</th><th>Filter</th><th>Refrigerant</th><th>Status</th><th>Action</th></tr></thead><tbody>';
			foreach ( $equipment as $item ) {
				echo '<tr><td>' . esc_html( $item['equipment_type'] ) . '</td><td>' . esc_html( trim( $item['brand'] . ' ' . $item['model_number'] ) ) . '</td><td>' . esc_html( $item['serial_number'] ) . '</td><td>' . esc_html( $item['filter'] ) . '</td><td>' . esc_html( $item['refrigerant_type'] ) . '</td><td>' . ( $item['active'] ? 'Active' : 'Inactive' ) . '</td><td>';
				if ( current_user_can( Capabilities::EDIT_EQUIPMENT ) ) {
					$item_url = admin_url( 'admin.php?page=servsys-equipment&action=edit&source=site&equipment_id=' . (int) $item['id'] . '&customer_id=' . $customer_id . '&site_id=' . $site_id );
					echo '<a class="button button-small" href="' . esc_url( $item_url ) . '">Edit</a>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}

		echo '<hr><h2>Site Activity</h2><p>No site activity modules are available yet. Future service calls, inspections, agreements, and other site activity will be shown here.</p>';
		echo '<p><a class="button" href="' . esc_url( $customer_url ) . '">&larr; Return to Customer</a></p>';
	}

	private function render_site_form( int $customer_id, $site = null ): void {
		$site    = $site ?: array_fill_keys( array( 'id', 'name', 'address1', 'address2', 'city', 'state', 'postal_code', 'phone' ), '' );
		$editing = ! empty( $site['id'] );
		$active  = isset( $site['active'] ) ? (int) $site['active'] : 1;
		echo '<h3>' . ( $editing ? 'Edit Site' : 'Add Site' ) . '</h3><form class="servsys-dependent-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'servsys_save_site_' . $customer_id );
		echo '<input type="hidden" name="action" value="servsys_save_site"><input type="hidden" name="customer_id" value="' . esc_attr( (string) $customer_id ) . '"><input type="hidden" name="site_id" value="' . (int) $site['id'] . '">';
		foreach ( array( 'name' => 'Site Name', 'address1' => 'Address', 'address2' => 'Address 2', 'city' => 'City', 'state' => 'State', 'postal_code' => 'Postal Code', 'phone' => 'Phone' ) as $name => $label ) {
			$this->input( $name, $label, $site[ $name ], in_array( $name, array( 'address1', 'city', 'state', 'postal_code' ), true ) );
		}
		echo '<p><label><input type="checkbox" name="active" value="1" ' . checked( $active, 1, false ) . '> Active</label></p>';
		submit_button( $editing ? 'Save Site' : 'Add Site', 'secondary' );
		echo '</form>';
	}

	private function render_note_form( int $customer_id, array $sites ): void {
		echo '<h3>Add Note</h3><form class="servsys-dependent-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'servsys_add_note_' . $customer_id );
		echo '<input type="hidden" name="action" value="servsys_add_note"><input type="hidden" name="customer_id" value="' . esc_attr( (string) $customer_id ) . '">'';
		$this->input( 'subject', 'Subject', '' );
		echo '<p><label>Site<br><select name="site_id"><option value="">Customer-wide</option>';
		foreach ( $sites as $site ) {
			echo '<option value="' . (int) $site['id'] . '">' . esc_html( $site['name'] ?: $site['address1'] ) . '</option>';
		}
		echo '</select></label></p><p><label>Note<br><textarea class="large-text" rows="4" name="note" required></textarea></label></p>';
		submit_button( 'Add Note', 'secondary' );
		echo '</form>';
	}

	private function input( string $name, string $label, $value, bool $required = false, string $type = 'text' ): void {
		echo '<p><label>' . esc_html( $label ) . '<br><input class="regular-text" type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $required ? ' required' : '' ) . '></label></p>';
	}

	public function render_customer_types(): void {
		if ( ! current_user_can( Capabilities::EDIT_CUSTOMERS ) ) {
			wp_die( esc_html__( 'Permission denied.', 'servsys' ) );
		}
		$types = ( new CustomerTypeRepository() )->all( true );
		echo '<div class="wrap"><h1>ServSys Customer Types</h1><p>Configure the customer types available on customer records.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Sort Order</th><th>Active</th><th>Action</th></tr></thead><tbody>';
		foreach ( $types as $type ) {
			$form_id = 'servsys-customer-type-' . (int) $type['id'];
			echo '<tr><td><form id="' . esc_attr( $form_id ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'servsys_save_customer_type_' . (int) $type['id'] );
			echo '<input type="hidden" name="action" value="servsys_save_customer_type"><input type="hidden" name="type_id" value="' . (int) $type['id'] . '">';
			echo '<input type="text" name="name" value="' . esc_attr( $type['name'] ) . '" required></form></td>';
			echo '<td><input form="' . esc_attr( $form_id ) . '" type="number" name="sort_order" value="' . (int) $type['sort_order'] . '" class="small-text"></td>';
			echo '<td><label><input form="' . esc_attr( $form_id ) . '" type="checkbox" name="active" value="1" ' . checked( (int) $type['active'], 1, false ) . '> Active</label></td>';
			echo '<td><button form="' . esc_attr( $form_id ) . '" class="button" type="submit">Save</button></td></tr>';
		}
		echo '</tbody></table>';
		echo '<h2>Add Customer Type</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'servsys_save_customer_type_0' );
		echo '<input type="hidden" name="action" value="servsys_save_customer_type"><input type="hidden" name="type_id" value="0">';
		echo '<p><label>Name<br><input type="text" name="name" class="regular-text" required></label></p>';
		echo '<p><label>Sort Order<br><input type="number" name="sort_order" value="40" class="small-text"></label></p>';
		echo '<p><label><input type="checkbox" name="active" value="1" checked> Active</label></p>';
		submit_button( 'Add Customer Type' );
		echo '</form></div>';
	}

	public function save_customer(): void {
		$id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
		check_admin_referer( 'servsys_save_customer_' . $id );
		if ( ! current_user_can( $id ? Capabilities::EDIT_CUSTOMERS : Capabilities::CREATE_CUSTOMERS ) ) {
			wp_die( esc_html__( 'Permission denied.', 'servsys' ) );
		}
		$data = wp_unslash( $_POST );
		$data['active'] = isset( $_POST['active'] );
		$id = ( new CustomerService() )->save( $data, $id );
		wp_safe_redirect( admin_url( 'admin.php?page=servsys&customer_id=' . $id ) );
		exit;
	}

	public function save_site(): void {
		$customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
		check_admin_referer( 'servsys_save_site_' . $customer_id );
		$site_id = isset( $_POST['site_id'] ) ? absint( $_POST['site_id'] ) : 0;
		if ( ! current_user_can( $site_id ? Capabilities::EDIT_SITES : Capabilities::CREATE_SITES ) ) {
			wp_die( esc_html__( 'Permission denied.', 'servsys' ) );
		}
		$data = wp_unslash( $_POST );
		$data['active'] = isset( $_POST['active'] );
		( new SiteService() )->save( $customer_id, $data, $site_id );
		wp_safe_redirect( admin_url( 'admin.php?page=servsys&customer_id=' . $customer_id ) );
		exit;
	}

	public function add_note(): void {
		$customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
		check_admin_referer( 'servsys_add_note_' . $customer_id );
		if ( ! current_user_can( Capabilities::EDIT_CUSTOMERS ) ) {
			wp_die( esc_html__( 'Permission denied.', 'servsys' ) );
		}
		( new NoteService() )->create( $customer_id, wp_unslash( $_POST ) );
		wp_safe_redirect( admin_url( 'admin.php?page=servsys&customer_id=' . $customer_id ) );
		exit;
	}

	public function save_customer_type(): void {
		$id = isset( $_POST['type_id'] ) ? absint( $_POST['type_id'] ) : 0;
		check_admin_referer( 'servsys_save_customer_type_' . $id );
		if ( ! current_user_can( Capabilities::EDIT_CUSTOMERS ) ) {
			wp_die( esc_html__( 'Permission denied.', 'servsys' ) );
		}
		$data = wp_unslash( $_POST );
		$data['active'] = isset( $_POST['active'] );
		( new CustomerTypeRepository() )->save( $data, $id );
		wp_safe_redirect( admin_url( 'admin.php?page=servsys-customer-types' ) );
		exit;
	}
}
