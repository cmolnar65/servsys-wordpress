<?php

namespace ServSys\Admin;

use ServSys\Inventory\InventoryBalanceRepository;
use ServSys\Inventory\InventoryItemRepository;
use ServSys\Inventory\InventoryLocationRepository;
use ServSys\Inventory\InventoryService;
use ServSys\Inventory\InventoryTransactionRepository;
use ServSys\Users\Capabilities;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class InventoryStockPage {
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_servsys_adjust_inventory_stock', array( $this, 'save' ) );
		add_action( 'admin_post_servsys_transfer_inventory_stock', array( $this, 'transfer' ) );
	}

	public function register_menu(): void {
		add_submenu_page( null, 'Inventory Stock', 'Inventory Stock', Capabilities::VIEW_INVENTORY, 'servsys-inventory-stock', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_INVENTORY ) ) { wp_die( esc_html__( 'Permission denied.', 'servsys' ) ); }
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation and notices.
		$item_id = isset( $_GET['item_id'] ) ? absint( wp_unslash( $_GET['item_id'] ) ) : 0;
		$location_id = isset( $_GET['location_id'] ) ? absint( wp_unslash( $_GET['location_id'] ) ) : 0;
		$error = isset( $_GET['servsys_error'] ) ? sanitize_text_field( wp_unslash( $_GET['servsys_error'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$item = ( new InventoryItemRepository() )->find( $item_id );
		if ( ! $item ) { wp_die( esc_html__( 'Inventory item not found.', 'servsys' ) ); }
		echo '<div class="wrap"><h1>Inventory Stock: ' . esc_html( $item['sku'] . ' — ' . $item['description'] ) . '</h1>';
		if ( $error ) { echo '<div class="notice notice-error inline"><p>' . esc_html( $error ) . '</p></div>'; }
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=servsys-inventory-items&item_id=' . $item_id ) ) . '">&larr; Return to Inventory Item</a></p>';
		$locations = ( new InventoryLocationRepository() )->all( true );
		$balances = new InventoryBalanceRepository();
		echo '<table class="widefat striped"><thead><tr><th>Location</th><th>Status</th><th>Quantity on Hand</th><th>Action</th></tr></thead><tbody>';
		foreach ( $locations as $location ) {
			echo '<tr><td>' . esc_html( $location['name'] ) . '</td><td>' . ( $location['active'] ? 'Active' : 'Inactive' ) . '</td><td>' . esc_html( $this->quantity( $balances->quantity( $item_id, (int) $location['id'] ) ) ) . '</td><td><a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=servsys-inventory-stock&item_id=' . $item_id . '&location_id=' . (int) $location['id'] ) ) . '">History</a></td></tr>';
		}
		echo '</tbody></table>';
		if ( current_user_can( Capabilities::ADJUST_INVENTORY ) && $item['active'] ) { $this->render_form( $item_id ); $this->render_transfer_form( $item_id ); }
		if ( $location_id ) { $this->render_history( $item_id, $location_id ); }
		echo '</div>';
	}

	private function render_form( int $item_id ): void {
		$locations = ( new InventoryLocationRepository() )->all( false );
		echo '<h2>Receive or Adjust Stock</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'servsys_adjust_inventory_stock' );
		echo '<input type="hidden" name="action" value="servsys_adjust_inventory_stock"><input type="hidden" name="item_id" value="' . esc_attr( (string) $item_id ) . '">';
		echo '<p><label>Location<br><select name="location_id" required><option value="">Select location</option>';
		foreach ( $locations as $location ) { echo '<option value="' . esc_attr( (string) $location['id'] ) . '">' . esc_html( $location['name'] ) . '</option>'; }
		echo '</select></label></p><p><label>Transaction Type<br><select name="transaction_type" required><option value="initial">Initial Quantity</option><option value="receipt">Receipt / Purchase</option><option value="adjustment">Adjustment</option></select></label></p>';
		echo '<p><label>Quantity Change<br><input type="number" step="0.0001" name="quantity_change" required></label><br><span class="description">Use a positive quantity to add stock. Adjustments may be negative, but inventory cannot be reduced below zero.</span></p>';
		echo '<p><label>Note / Reference<br><textarea class="large-text" rows="3" name="note" required></textarea></label></p>';
		submit_button( 'Record Inventory Transaction' );
		echo '</form>';
	}

	private function render_transfer_form( int $item_id ): void {
		$locations = ( new InventoryLocationRepository() )->all( false );
		if ( count( $locations ) < 2 ) { return; }
		echo '<h2>Transfer Stock Between Locations</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'servsys_transfer_inventory_stock' );
		echo '<input type="hidden" name="action" value="servsys_transfer_inventory_stock"><input type="hidden" name="item_id" value="' . esc_attr( (string) $item_id ) . '">';
		foreach ( array( 'from_location_id'=>'From Location', 'to_location_id'=>'To Location' ) as $name=>$label ) {
			echo '<p><label>' . esc_html( $label ) . '<br><select name="' . esc_attr( $name ) . '" required><option value="">Select location</option>';
			foreach ( $locations as $location ) { echo '<option value="' . esc_attr( (string) $location['id'] ) . '">' . esc_html( $location['name'] ) . '</option>'; }
			echo '</select></label></p>';
		}
		echo '<p><label>Quantity<br><input type="number" min="0.0001" step="0.0001" name="quantity" required></label></p>';
		echo '<p><label>Note / Reference<br><textarea class="large-text" rows="3" name="note" required></textarea></label></p>';
		submit_button( 'Transfer Inventory' );
		echo '</form>';
	}

	private function render_history( int $item_id, int $location_id ): void {
		$location = ( new InventoryLocationRepository() )->find( $location_id );
		if ( ! $location ) { wp_die( esc_html__( 'Inventory location not found.', 'servsys' ) ); }
		echo '<h2>Transaction History — ' . esc_html( $location['name'] ) . '</h2><table class="widefat striped"><thead><tr><th>Date</th><th>Type</th><th>Quantity Change</th><th>Unit Cost</th><th>Note</th><th>User</th></tr></thead><tbody>';
		foreach ( ( new InventoryTransactionRepository() )->for_item_location( $item_id, $location_id, 100 ) as $transaction ) {
			$user = ! empty( $transaction['created_by'] ) ? get_userdata( (int) $transaction['created_by'] ) : null;
			echo '<tr><td>' . esc_html( $transaction['transaction_at'] ) . '</td><td>' . esc_html( ucwords( str_replace( '_', ' ', $transaction['transaction_type'] ) ) ) . '</td><td>' . esc_html( $this->quantity( $transaction['quantity_change'] ) ) . '</td><td>' . ( null === $transaction['unit_cost'] ? '' : '$' . esc_html( number_format( (float) $transaction['unit_cost'], 2 ) ) ) . '</td><td>' . esc_html( $transaction['note'] ) . '</td><td>' . esc_html( $user ? $user->display_name : '' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public function save(): void {
		check_admin_referer( 'servsys_adjust_inventory_stock' );
		if ( ! current_user_can( Capabilities::ADJUST_INVENTORY ) ) { wp_die( esc_html__( 'Permission denied.', 'servsys' ) ); }
		$item_id = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;
		$location_id = isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0;
		$type = isset( $_POST['transaction_type'] ) ? sanitize_key( wp_unslash( $_POST['transaction_type'] ) ) : '';
		$quantity = isset( $_POST['quantity_change'] ) ? (float) wp_unslash( $_POST['quantity_change'] ) : 0.0;
		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$url = admin_url( 'admin.php?page=servsys-inventory-stock&item_id=' . $item_id . '&location_id=' . $location_id );
		try {
			if ( ! in_array( $type, array( 'initial', 'receipt', 'adjustment' ), true ) ) { throw new \InvalidArgumentException( 'Invalid stock transaction type.' ); }
			if ( in_array( $type, array( 'initial', 'receipt' ), true ) && $quantity <= 0 ) { throw new \InvalidArgumentException( 'Initial quantities and receipts must add a positive quantity.' ); }
			if ( '' === $note ) { throw new \InvalidArgumentException( 'A note or reference is required for inventory changes.' ); }
			( new InventoryService() )->adjust_stock( $item_id, $location_id, $quantity, $type, $note );
		} catch ( \InvalidArgumentException | \RuntimeException $exception ) {
			$url = add_query_arg( 'servsys_error', $exception->getMessage(), $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	public function transfer(): void {
		check_admin_referer( 'servsys_transfer_inventory_stock' );
		if ( ! current_user_can( Capabilities::ADJUST_INVENTORY ) ) { wp_die( esc_html__( 'Permission denied.', 'servsys' ) ); }
		$item_id = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;
		$from_location_id = isset( $_POST['from_location_id'] ) ? absint( wp_unslash( $_POST['from_location_id'] ) ) : 0;
		$to_location_id = isset( $_POST['to_location_id'] ) ? absint( wp_unslash( $_POST['to_location_id'] ) ) : 0;
		$quantity = isset( $_POST['quantity'] ) ? (float) wp_unslash( $_POST['quantity'] ) : 0.0;
		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$url = admin_url( 'admin.php?page=servsys-inventory-stock&item_id=' . $item_id );
		try {
			( new InventoryService() )->transfer_stock( $item_id, $from_location_id, $to_location_id, $quantity, $note );
		} catch ( \InvalidArgumentException | \RuntimeException $exception ) {
			$url = add_query_arg( 'servsys_error', $exception->getMessage(), $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	private function quantity( $quantity ): string {
		return rtrim( rtrim( number_format( (float) $quantity, 4, '.', '' ), '0' ), '.' );
	}
}
