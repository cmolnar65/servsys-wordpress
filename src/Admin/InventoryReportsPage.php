<?php

namespace ServSys\Admin;

use ServSys\Inventory\InventoryBalanceRepository;
use ServSys\Inventory\InventoryItemRepository;
use ServSys\Inventory\InventoryLocationRepository;
use ServSys\Inventory\InventoryTransactionRepository;
use ServSys\Users\Capabilities;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class InventoryReportsPage {
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	public function register_menu(): void {
		add_submenu_page( null, 'Inventory History & Reporting', 'Inventory History & Reporting', Capabilities::VIEW_INVENTORY, 'servsys-inventory-reports', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_INVENTORY ) ) { wp_die( esc_html__( 'Permission denied.', 'servsys' ) ); }
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only report filters.
		$item_id=isset($_GET['item_id'])?absint(wp_unslash($_GET['item_id'])):0;
		$location_id=isset($_GET['location_id'])?absint(wp_unslash($_GET['location_id'])):0;
		$type=isset($_GET['transaction_type'])?sanitize_key(wp_unslash($_GET['transaction_type'])):'';
		$date_from=isset($_GET['date_from'])?sanitize_text_field(wp_unslash($_GET['date_from'])):'';
		$date_to=isset($_GET['date_to'])?sanitize_text_field(wp_unslash($_GET['date_to'])):'';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$filters=array('item_id'=>$item_id,'location_id'=>$location_id,'transaction_type'=>$type,'date_from'=>$date_from,'date_to'=>$date_to);
		$transactions=new InventoryTransactionRepository();
		$rows=$transactions->search($filters,500);
		$types=$transactions->transaction_types();
		$items=(new InventoryItemRepository())->search('',true);
		$locations=(new InventoryLocationRepository())->all(true);

		echo '<div class="wrap"><h1>Inventory History &amp; Reporting</h1>';
		echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=servsys-configuration')) . '">&larr; Return to Configuration</a></p>';
		$this->render_filters($items,$locations,$types,$filters);
		$this->render_summary($transactions->totals_by_type($filters));
		$this->render_current_stock();
		$this->render_reconciliation();
		$this->render_transactions($rows);
		echo '</div>';
	}

	private function render_filters( array $items, array $locations, array $types, array $filters ): void {
		echo '<form method="get"><input type="hidden" name="page" value="servsys-inventory-reports"><h2>Filters</h2><p>';
		echo '<label>Item <select name="item_id"><option value="">All items</option>';
		foreach($items as $item){echo '<option value="' . esc_attr((string)$item['id']) . '" ' . selected((int)$filters['item_id'],(int)$item['id'],false) . '>' . esc_html($item['sku'] . ' — ' . $item['description']) . '</option>';}
		echo '</select></label> ';
		echo '<label>Location <select name="location_id"><option value="">All locations</option>';
		foreach($locations as $location){echo '<option value="' . esc_attr((string)$location['id']) . '" ' . selected((int)$filters['location_id'],(int)$location['id'],false) . '>' . esc_html($location['name']) . '</option>';}
		echo '</select></label> ';
		echo '<label>Type <select name="transaction_type"><option value="">All types</option>';
		foreach($types as $value){echo '<option value="' . esc_attr($value) . '" ' . selected($filters['transaction_type'],$value,false) . '>' . esc_html(ucwords(str_replace('_',' ',$value))) . '</option>';}
		echo '</select></label> ';
		echo '<label>From <input type="date" name="date_from" value="' . esc_attr($filters['date_from']) . '"></label> ';
		echo '<label>To <input type="date" name="date_to" value="' . esc_attr($filters['date_to']) . '"></label> ';
		submit_button('Apply Filters','secondary','',false);
		echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=servsys-inventory-reports')) . '">Clear</a></p></form>';
	}

	private function render_summary( array $totals ): void {
		echo '<h2>Filtered Quantity Movement</h2><table class="widefat striped"><thead><tr><th>Transaction Type</th><th>Net Quantity Change</th></tr></thead><tbody>';
		if(!$totals){echo '<tr><td colspan="2">No matching inventory transactions.</td></tr>';}
		foreach($totals as $type=>$quantity){echo '<tr><td>' . esc_html(ucwords(str_replace('_',' ',$type))) . '</td><td>' . esc_html($this->quantity($quantity)) . '</td></tr>';}
		echo '</tbody></table>';
	}

	private function render_current_stock(): void {
		$balances=new InventoryBalanceRepository();
		$totals=$balances->totals_by_item();
		echo '<h2>Current Stock Summary</h2><table class="widefat striped"><thead><tr><th>SKU</th><th>Description</th><th>Quantity on Hand</th><th>Reorder Quantity</th><th>Unit Cost</th><th>Inventory Value</th></tr></thead><tbody>';
		if(!$totals){echo '<tr><td colspan="6">No inventory balances found.</td></tr>';}
		foreach($totals as $row){
			$value=round((float)$row['quantity_on_hand']*(float)$row['unit_cost'],2);
			echo '<tr><td>' . esc_html($row['item_sku']) . '</td><td>' . esc_html($row['item_description']) . '</td><td>' . esc_html($this->quantity($row['quantity_on_hand'])) . '</td><td>' . esc_html($this->quantity($row['reorder_quantity'])) . '</td><td>$' . esc_html(number_format((float)$row['unit_cost'],2)) . '</td><td>$' . esc_html(number_format($value,2)) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function render_reconciliation(): void {
		$balances=(new InventoryBalanceRepository())->report_rows();
		$ledger=(new InventoryTransactionRepository())->ledger_totals_by_item_location();
		echo '<h2>Balance Reconciliation</h2><table class="widefat striped"><thead><tr><th>Item</th><th>Location</th><th>Balance Quantity</th><th>Ledger Quantity</th><th>Difference</th><th>Status</th></tr></thead><tbody>';
		if(!$balances){echo '<tr><td colspan="6">No inventory balances found.</td></tr>';}
		foreach($balances as $row){
			$key=(int)$row['inventory_item_id'] . ':' . (int)$row['inventory_location_id'];
			$balance=round((float)$row['quantity_on_hand'],4);
			$ledger_quantity=isset($ledger[$key])?round((float)$ledger[$key],4):0.0;
			$difference=round($balance-$ledger_quantity,4);
			$status=abs($difference)<0.0001?'OK':'Mismatch';
			echo '<tr><td>' . esc_html($row['item_sku'] . ' — ' . $row['item_description']) . '</td><td>' . esc_html($row['location_name']) . '</td><td>' . esc_html($this->quantity($balance)) . '</td><td>' . esc_html($this->quantity($ledger_quantity)) . '</td><td>' . esc_html($this->quantity($difference)) . '</td><td>' . esc_html($status) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function render_transactions( array $rows ): void {
		echo '<h2>Inventory Transaction History</h2><table class="widefat striped"><thead><tr><th>Date</th><th>Item</th><th>Location</th><th>Type</th><th>Quantity</th><th>Unit Cost</th><th>Service Call</th><th>Note</th><th>User</th></tr></thead><tbody>';
		if(!$rows){echo '<tr><td colspan="9">No matching inventory transactions.</td></tr>';}
		foreach($rows as $row){
			$user=!empty($row['created_by'])?get_userdata((int)$row['created_by']):null;
			$service_call=!empty($row['service_call_id']) ? '<a href="' . esc_url(admin_url('admin.php?page=servsys-service-calls&service_call_id=' . (int)$row['service_call_id'])) . '">' . esc_html((string)$row['service_call_id']) . '</a>' : '';
			echo '<tr><td>' . esc_html($row['transaction_at']) . '</td><td>' . esc_html($row['item_sku'] . ' — ' . $row['item_description']) . '</td><td>' . esc_html($row['location_name']) . '</td><td>' . esc_html(ucwords(str_replace('_',' ',$row['transaction_type']))) . '</td><td>' . esc_html($this->quantity($row['quantity_change'])) . '</td><td>' . (null===$row['unit_cost']?'':'$' . esc_html(number_format((float)$row['unit_cost'],2))) . '</td><td>' . $service_call . '</td><td>' . esc_html($row['note']) . '</td><td>' . esc_html($user?$user->display_name:'') . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function quantity( $quantity ): string {
		return rtrim(rtrim(number_format((float)$quantity,4,'.',''),'0'),'.');
	}
}
