<?php

namespace ServSys\Admin;

use ServSys\Inventory\InventoryItemRepository;
use ServSys\Inventory\InventoryLocationRepository;
use ServSys\Inventory\InventoryService;
use ServSys\Inventory\InventoryTransactionRepository;
use ServSys\Inventory\ServiceCallInventoryRepository;
use ServSys\Users\Capabilities;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ServiceCallInventoryPage {
	public function register(): void {
		add_action( 'admin_post_servsys_service_call_use_inventory', array( $this, 'use_inventory' ) );
		add_action( 'admin_post_servsys_service_call_return_inventory', array( $this, 'return_inventory' ) );
	}

	public function render( int $service_call_id ): void {
		$usage_repository=new ServiceCallInventoryRepository();
		$transaction_repository=new InventoryTransactionRepository();
		$rows=$usage_repository->for_service_call($service_call_id);
		echo '<h3>Inventory Used</h3><table class="widefat striped"><thead><tr><th>SKU</th><th>Description</th><th>Quantity</th><th>Unit Cost</th><th>Markup</th><th>Unit Price</th><th>Extended</th><th>Action</th></tr></thead><tbody>';
		if ( ! $rows ) { echo '<tr><td colspan="8">No inventory has been recorded for this service call.</td></tr>'; }
		foreach ( $rows as $usage ) {
			$is_reversal=(float)$usage['quantity'] < 0;
			$returned=!$is_reversal && $transaction_repository->has_related_transaction((int)$usage['inventory_transaction_id'],'service_call_return');
			$description=$usage['item_description_snapshot'] . ($is_reversal?' (Return)':'');
			echo '<tr><td>' . esc_html($usage['item_sku_snapshot']) . '</td><td>' . esc_html($description) . '</td><td>' . esc_html($this->quantity($usage['quantity'])) . '</td>';
			echo '<td>$' . esc_html(number_format((float)$usage['unit_cost_snapshot'],2)) . '</td><td>' . esc_html(number_format((float)$usage['markup_percent_snapshot'],3)) . '%</td>';
			echo '<td>$' . esc_html(number_format((float)$usage['unit_selling_price_snapshot'],2)) . '</td><td>$' . esc_html(number_format((float)$usage['extended_selling_price'],2)) . '</td><td>';
			if ( current_user_can(Capabilities::USE_INVENTORY) && !$is_reversal && !$returned ) {
				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
				wp_nonce_field('servsys_service_call_return_inventory_' . (int)$usage['id']);
				echo '<input type="hidden" name="action" value="servsys_service_call_return_inventory"><input type="hidden" name="usage_id" value="' . esc_attr((string)$usage['id']) . '"><input type="hidden" name="service_call_id" value="' . esc_attr((string)$service_call_id) . '">';
				submit_button('Return / Reverse','secondary','',false);
				echo '</form>';
			} elseif ( $returned ) { echo 'Returned'; }
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		if ( ! current_user_can(Capabilities::USE_INVENTORY) ) { return; }
		$locations=(new InventoryLocationRepository())->all(false);
		$items=(new InventoryItemRepository())->search('',false);
		echo '<h4>Add Inventory Used</h4><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('servsys_service_call_use_inventory_' . $service_call_id);
		echo '<input type="hidden" name="action" value="servsys_service_call_use_inventory"><input type="hidden" name="service_call_id" value="' . esc_attr((string)$service_call_id) . '">';
		echo '<p><label>Inventory Location<br><select name="location_id" required><option value="">Select location</option>';
		foreach($locations as $location){echo '<option value="' . esc_attr((string)$location['id']) . '">' . esc_html($location['name']) . '</option>';}
		echo '</select></label></p><p><label>Inventory Item<br><select name="item_id" required><option value="">Select item</option>';
		foreach($items as $item){echo '<option value="' . esc_attr((string)$item['id']) . '">' . esc_html($item['sku'] . ' — ' . $item['description']) . '</option>';}
		echo '</select></label></p><p><label>Quantity Used<br><input type="number" min="0.0001" step="0.0001" name="quantity" required></label></p>';
		echo '<p><label>Note<br><textarea class="large-text" rows="2" name="note"></textarea></label></p>';
		submit_button('Add Inventory Used','secondary');
		echo '</form>';
	}

	public function use_inventory(): void {
		$service_call_id=isset($_POST['service_call_id'])?absint(wp_unslash($_POST['service_call_id'])):0;
		check_admin_referer('servsys_service_call_use_inventory_' . $service_call_id);
		if(!current_user_can(Capabilities::USE_INVENTORY)){wp_die(esc_html__('Permission denied.','servsys'));}
		$item_id=isset($_POST['item_id'])?absint(wp_unslash($_POST['item_id'])):0;
		$location_id=isset($_POST['location_id'])?absint(wp_unslash($_POST['location_id'])):0;
		$quantity=isset($_POST['quantity'])?(float)wp_unslash($_POST['quantity']):0.0;
		$note=isset($_POST['note'])?sanitize_textarea_field(wp_unslash($_POST['note'])):'';
		$url=admin_url('admin.php?page=servsys-service-calls&service_call_id=' . $service_call_id);
		try{(new InventoryService())->consume_for_service_call($service_call_id,$item_id,$location_id,$quantity,$note);}
		catch(\InvalidArgumentException|\RuntimeException $exception){$url=add_query_arg('servsys_error',$exception->getMessage(),$url);}
		wp_safe_redirect($url); exit;
	}

	public function return_inventory(): void {
		$service_call_id=isset($_POST['service_call_id'])?absint(wp_unslash($_POST['service_call_id'])):0;
		$usage_id=isset($_POST['usage_id'])?absint(wp_unslash($_POST['usage_id'])):0;
		check_admin_referer('servsys_service_call_return_inventory_' . $usage_id);
		if(!current_user_can(Capabilities::USE_INVENTORY)){wp_die(esc_html__('Permission denied.','servsys'));}
		$url=admin_url('admin.php?page=servsys-service-calls&service_call_id=' . $service_call_id);
		try{
			$usage=(new ServiceCallInventoryRepository())->find($usage_id);
			if(!$usage || (int)$usage['service_call_id']!==$service_call_id){throw new \InvalidArgumentException('Inventory usage does not belong to this Service Call.');}
			(new InventoryService())->return_service_call_usage($usage_id);
		}catch(\InvalidArgumentException|\RuntimeException $exception){$url=add_query_arg('servsys_error',$exception->getMessage(),$url);}
		wp_safe_redirect($url); exit;
	}

	private function quantity( $quantity ): string {
		return rtrim(rtrim(number_format((float)$quantity,4,'.',''),'0'),'.');
	}
}
