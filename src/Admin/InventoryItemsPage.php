<?php

namespace ServSys\Admin;

use ServSys\Inventory\InventoryItemRepository;
use ServSys\Inventory\InventoryItemService;
use ServSys\Inventory\InventoryService;
use ServSys\Users\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InventoryItemsPage {
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_servsys_save_inventory_item', array( $this, 'save' ) );
	}

	public function register_menu(): void {
		add_submenu_page( null, 'Inventory Items', 'Inventory Items', Capabilities::VIEW_INVENTORY, 'servsys-inventory-items', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_INVENTORY ) ) {
			wp_die( esc_html__( 'You do not have permission to view inventory items.', 'servsys' ) );
		}
		$repository = new InventoryItemRepository();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation/search; no state change.
		$item_id = isset( $_GET['item_id'] ) ? absint( wp_unslash( $_GET['item_id'] ) ) : 0;
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$error = isset( $_GET['servsys_error'] ) ? sanitize_text_field( wp_unslash( $_GET['servsys_error'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap"><h1>ServSys Inventory Items</h1>';
		if ( $error ) { echo '<div class="notice notice-error inline"><p>' . esc_html( $error ) . '</p></div>'; }
		if ( $item_id ) {
			$item = $repository->find( $item_id );
			if ( ! $item ) { wp_die( esc_html__( 'Inventory item not found.', 'servsys' ) ); }
			if ( 'edit' === $action ) { $this->render_form( $item ); } else { $this->render_detail( $item ); }
		} elseif ( 'add' === $action ) {
			$this->render_form( array() );
		} else {
			$this->render_list( $repository );
		}
		echo '</div>';
	}

	private function render_list( InventoryItemRepository $repository ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search; no state change.
		$term = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$items = $repository->search( $term, true );
		echo '<p>Inventory items are company-specific. Selling price is calculated from current unit cost and the company Inventory Markup setting.</p>';
		if ( current_user_can( Capabilities::MANAGE_INVENTORY ) ) {
			echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=servsys-inventory-items&action=add' ) ) . '">Add Inventory Item</a></p>';
		}
		echo '<form method="get"><input type="hidden" name="page" value="servsys-inventory-items"><p class="search-box"><input type="search" name="s" value="' . esc_attr( $term ) . '"> ';
		submit_button( 'Search Inventory', 'secondary', '', false );
		echo '</p></form><table class="widefat striped"><thead><tr><th>SKU</th><th>Description</th><th>Manufacturer / Part #</th><th>Category</th><th>Unit Cost</th><th>Selling Price</th><th>Reorder Qty</th><th>Status</th><th>Action</th></tr></thead><tbody>';
		$inventory = new InventoryService();
		foreach ( $items as $item ) {
			$price = InventoryService::selling_price( (float) $item['unit_cost'], $inventory->current_markup_percent() );
			$view_url = admin_url( 'admin.php?page=servsys-inventory-items&item_id=' . (int) $item['id'] );
			echo '<tr><td>' . esc_html( $item['sku'] ) . '</td><td>' . esc_html( $item['description'] ) . '</td><td>' . esc_html( trim( $item['manufacturer'] . ' ' . $item['manufacturer_part_number'] ) ) . '</td><td>' . esc_html( $item['category'] ) . '</td><td>$' . esc_html( number_format( (float) $item['unit_cost'], 2 ) ) . '</td><td>$' . esc_html( number_format( $price, 2 ) ) . '</td><td>' . esc_html( $this->quantity( $item['reorder_quantity'] ) ) . '</td><td>' . ( $item['active'] ? 'Active' : 'Inactive' ) . '</td><td><a class="button button-small" href="' . esc_url( $view_url ) . '">View</a>';
			if ( current_user_can( Capabilities::MANAGE_INVENTORY ) ) { echo ' <a class="button button-small" href="' . esc_url( $view_url . '&action=edit' ) . '">Edit</a>'; }
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function render_detail( array $item ): void {
		$inventory = new InventoryService();
		$markup = $inventory->current_markup_percent();
		$price = InventoryService::selling_price( (float) $item['unit_cost'], $markup );
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=servsys-inventory-items' ) ) . '">&larr; Return to Inventory Items</a></p><h2>' . esc_html( $item['description'] ) . '</h2>';
		echo '<table class="widefat striped"><tbody>';
		foreach ( array(
			'SKU'=>$item['sku'], 'Manufacturer'=>$item['manufacturer'], 'Manufacturer Part Number'=>$item['manufacturer_part_number'],
			'Category'=>$item['category'], 'Unit of Measure'=>$item['unit_of_measure'], 'Unit Cost'=>'$'.number_format( (float) $item['unit_cost'], 2 ),
			'Company Inventory Markup'=>number_format( $markup, 3 ).'%', 'Calculated Selling Price'=>'$'.number_format( $price, 2 ),
			'Reorder Quantity'=>$this->quantity( $item['reorder_quantity'] ), 'Status'=>$item['active']?'Active':'Inactive',
		) as $label=>$value ) { echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>'; }
		echo '<tr><th>Notes</th><td>' . nl2br( esc_html( $item['notes'] ) ) . '</td></tr></tbody></table>';
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=servsys-inventory-stock&item_id=' . (int) $item['id'] ) ) . '">View Stock &amp; Transactions</a></p>';
		if ( current_user_can( Capabilities::MANAGE_INVENTORY ) ) { echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=servsys-inventory-items&item_id=' . (int) $item['id'] . '&action=edit' ) ) . '">Edit Inventory Item</a></p>'; }
	}

	private function render_form( array $item ): void {
		if ( ! current_user_can( Capabilities::MANAGE_INVENTORY ) ) { wp_die( esc_html__( 'Permission denied.', 'servsys' ) ); }
		$item = wp_parse_args( $item, array( 'id'=>0, 'sku'=>'', 'description'=>'', 'manufacturer'=>'', 'manufacturer_part_number'=>'', 'category'=>'', 'unit_of_measure'=>'each', 'unit_cost'=>'0.0000', 'reorder_quantity'=>'0.0000', 'notes'=>'', 'active'=>1 ) );
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=servsys-inventory-items' ) ) . '">&larr; Return to Inventory Items</a></p><h2>' . ( $item['id'] ? 'Edit Inventory Item' : 'Add Inventory Item' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'servsys_save_inventory_item' );
		echo '<input type="hidden" name="action" value="servsys_save_inventory_item"><input type="hidden" name="item_id" value="' . esc_attr( (string) $item['id'] ) . '">';
		$this->input( 'sku', 'Internal SKU / Part Number', $item['sku'], true );
		$this->input( 'description', 'Description', $item['description'], true );
		$this->input( 'manufacturer', 'Manufacturer', $item['manufacturer'] );
		$this->input( 'manufacturer_part_number', 'Manufacturer Part Number', $item['manufacturer_part_number'] );
		$this->input( 'category', 'Category', $item['category'] );
		$this->input( 'unit_of_measure', 'Unit of Measure', $item['unit_of_measure'], true );
		echo '<p><label>Unit Cost<br><input type="number" min="0" step="0.0001" name="unit_cost" value="' . esc_attr( (string) $item['unit_cost'] ) . '" required></label></p>';
		echo '<p><label>Reorder Quantity<br><input type="number" min="0" step="0.0001" name="reorder_quantity" value="' . esc_attr( (string) $item['reorder_quantity'] ) . '" required></label></p>';
		echo '<p><label>Notes<br><textarea class="large-text" rows="5" name="notes">' . esc_textarea( $item['notes'] ) . '</textarea></label></p><p><label><input type="checkbox" name="active" value="1" ' . checked( (int) $item['active'], 1, false ) . '> Active</label></p>';
		submit_button( $item['id'] ? 'Save Inventory Item' : 'Add Inventory Item' );
		echo '</form>';
	}

	public function save(): void {
		check_admin_referer( 'servsys_save_inventory_item' );
		if ( ! current_user_can( Capabilities::MANAGE_INVENTORY ) ) { wp_die( esc_html__( 'Permission denied.', 'servsys' ) ); }
		$id = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;
		$data = wp_unslash( $_POST );
		$data['active'] = isset( $_POST['active'] );
		try {
			$id = ( new InventoryItemService() )->save( $data, $id );
			$url = admin_url( 'admin.php?page=servsys-inventory-items&item_id=' . $id );
		} catch ( \InvalidArgumentException | \RuntimeException $exception ) {
			$url = add_query_arg( 'servsys_error', $exception->getMessage(), admin_url( 'admin.php?page=servsys-inventory-items' . ( $id ? '&item_id=' . $id . '&action=edit' : '&action=add' ) ) );
		}
		wp_safe_redirect( $url );
		exit;
	}

	private function input( string $name, string $label, $value, bool $required = false ): void {
		echo '<p><label>' . esc_html( $label ) . '<br><input class="regular-text" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $required ? ' required' : '' ) . '></label></p>';
	}

	private function quantity( $quantity ): string {
		return rtrim( rtrim( number_format( (float) $quantity, 4, '.', '' ), '0' ), '.' );
	}
}
