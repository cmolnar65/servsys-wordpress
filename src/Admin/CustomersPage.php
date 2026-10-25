<?php

namespace ServSys\Admin;

use ServSys\Customers\CustomerRepository;
use ServSys\Customers\CustomerService;
use ServSys\Customers\NoteRepository;
use ServSys\Customers\NoteService;
use ServSys\Sites\SiteRepository;
use ServSys\Sites\SiteService;
use ServSys\Users\Capabilities;

final class CustomersPage {
	public function register(): void {
		add_menu_page( 'ServSys', 'ServSys', Capabilities::ACCESS, 'servsys', array( $this, 'render' ), 'dashicons-admin-tools', 26 );
		add_submenu_page( 'servsys', 'Customers', 'Customers', Capabilities::VIEW_CUSTOMERS, 'servsys', array( $this, 'render' ) );
		add_action( 'admin_post_servsys_save_customer', array( $this, 'save_customer' ) );
		add_action( 'admin_post_servsys_save_site', array( $this, 'save_site' ) );
		add_action( 'admin_post_servsys_add_note', array( $this, 'add_note' ) );
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_CUSTOMERS ) ) {
			wp_die( esc_html__( 'You do not have permission to view ServSys customers.', 'servsys' ) );
		}

		$customer_id = isset( $_GET['customer_id'] ) ? absint( $_GET['customer_id'] ) : 0;
		$edit        = isset( $_GET['action'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['action'] ) );

		echo '<div class="wrap"><h1>ServSys Customers</h1>';

		if ( $customer_id || $edit ) {
			$this->render_customer( $customer_id );
		} else {
			$this->render_list();
		}

		echo '</div>';
	}

	private function render_list(): void {
		$term      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$customers = ( new CustomerRepository() )->search( $term, true );

		echo '<form method="get"><input type="hidden" name="page" value="servsys">';
		echo '<p class="search-box"><input type="search" name="s" value="' . esc_attr( $term ) . '"> ';
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

	private function render_customer( int $customer_id ): void {
		$repository = new CustomerRepository();
		$customer   = $customer_id ? $repository->find( $customer_id ) : null;

		if ( $customer_id && ! $customer ) {
			wp_die( esc_html__( 'Customer not found.', 'servsys' ) );
		}

		if ( ! current_user_can( $customer_id ? Capabilities::EDIT_CUSTOMERS : Capabilities::CREATE_CUSTOMERS ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this customer.', 'servsys' ) );
		}

		$customer = $customer ?: array_fill_keys( array( 'name', 'customer_type', 'primary_phone', 'alternate_phone', 'email', 'notes' ), '' );
		$customer['active'] = isset( $customer['active'] ) ? $customer['active'] : 1;

		echo '<h2>' . ( $customer_id ? esc_html( $customer['name'] ) : 'Add Customer' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'servsys_save_customer_' . $customer_id );
		echo '<input type="hidden" name="action" value="servsys_save_customer"><input type="hidden" name="customer_id" value="' . $customer_id . '">';
		$this->input( 'name', 'Name', $customer['name'], true );
		$this->input( 'customer_type', 'Customer Type', $customer['customer_type'] );
		$this->input( 'primary_phone', 'Primary Phone', $customer['primary_phone'] );
		$this->input( 'alternate_phone', 'Alternate Phone', $customer['alternate_phone'] );
		$this->input( 'email', 'Email', $customer['email'], false, 'email' );
		echo '<p><label>Notes<br><textarea class="large-text" rows="4" name="notes">' . esc_textarea( $customer['notes'] ) . '</textarea></label></p>';
		echo '<p><label><input type="checkbox" name="active" value="1" ' . checked( (int) $customer['active'], 1, false ) . '> Active</label></p>';
		submit_button( $customer_id ? 'Save Customer' : 'Create Customer' );
		echo '</form>';

		if ( ! $customer_id ) {
			return;
		}

		$sites = ( new SiteRepository() )->for_customer( $customer_id, true );
		echo '<hr><h2>Sites</h2>';
		foreach ( $sites as $site ) {
			echo '<p><strong>' . esc_html( $site['name'] ?: 'Site #' . $site['id'] ) . '</strong> — ' . esc_html( $site['address1'] . ', ' . $site['city'] . ', ' . $site['state'] . ' ' . $site['postal_code'] ) . ( $site['active'] ? '' : ' (Inactive)' ) . '</p>';
		}
		$this->render_site_form( $customer_id );

		echo '<hr><h2>Notes</h2>';
		foreach ( ( new NoteRepository() )->for_customer( $customer_id ) as $note ) {
			echo '<div class="notice notice-info inline"><p><strong>' . esc_html( $note['subject'] ?: 'Note' ) . '</strong><br>' . nl2br( esc_html( $note['note'] ) ) . '</p></div>';
		}
		$this->render_note_form( $customer_id, $sites );
	}

	private function render_site_form( int $customer_id ): void {
		echo '<h3>Add Site</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'servsys_save_site_' . $customer_id );
		echo '<input type="hidden" name="action" value="servsys_save_site"><input type="hidden" name="customer_id" value="' . $customer_id . '">';
		foreach ( array( 'name' => 'Site Name', 'address1' => 'Address', 'address2' => 'Address 2', 'city' => 'City', 'state' => 'State', 'postal_code' => 'Postal Code', 'phone' => 'Phone' ) as $name => $label ) {
			$this->input( $name, $label, '', in_array( $name, array( 'address1', 'city', 'state', 'postal_code' ), true ) );
		}
		echo '<p><label><input type="checkbox" name="active" value="1" checked> Active</label></p>';
		submit_button( 'Add Site', 'secondary' );
		echo '</form>';
	}

	private function render_note_form( int $customer_id, array $sites ): void {
		echo '<h3>Add Note</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'servsys_add_note_' . $customer_id );
		echo '<input type="hidden" name="action" value="servsys_add_note"><input type="hidden" name="customer_id" value="' . $customer_id . '">';
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
		$customer_id = absint( $_POST['customer_id'] );
		check_admin_referer( 'servsys_save_site_' . $customer_id );
		if ( ! current_user_can( Capabilities::EDIT_CUSTOMERS ) ) {
			wp_die( esc_html__( 'Permission denied.', 'servsys' ) );
		}
		$data = wp_unslash( $_POST );
		$data['active'] = isset( $_POST['active'] );
		( new SiteService() )->save( $customer_id, $data );
		wp_safe_redirect( admin_url( 'admin.php?page=servsys&customer_id=' . $customer_id ) );
		exit;
	}

	public function add_note(): void {
		$customer_id = absint( $_POST['customer_id'] );
		check_admin_referer( 'servsys_add_note_' . $customer_id );
		if ( ! current_user_can( Capabilities::EDIT_CUSTOMERS ) ) {
			wp_die( esc_html__( 'Permission denied.', 'servsys' ) );
		}
		( new NoteService() )->create( $customer_id, wp_unslash( $_POST ) );
		wp_safe_redirect( admin_url( 'admin.php?page=servsys&customer_id=' . $customer_id ) );
		exit;
	}
}
