<?php
namespace ServSys\Admin;

use ServSys\ServiceCalls\ServiceCallTotalsService;
use ServSys\Users\Capabilities;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ServiceCallTotalsPage {
 public function render(int $service_call_id): void {
  if(!current_user_can(Capabilities::VIEW_SERVICE_CALLS)){return;}
  try{$totals=(new ServiceCallTotalsService())->totals($service_call_id);}catch(\InvalidArgumentException $e){return;}
  echo '<h3>Operational Totals</h3>';
  echo '<p class="description">Calculated from the Service Call\'s stored inventory usage/return selling-price snapshots and Service Charge snapshots. These are operational totals, not an invoice or accounting ledger. Flat Rate recipe materials are pricing inputs only; inventory is counted here only when separately consumed on the Service Call.</p>';
  echo '<table class="widefat striped"><tbody>';
  echo '<tr><th>Inventory / Materials</th><td>$'.esc_html(number_format($totals['inventory_material_selling_total'],2)).'</td></tr>';
  echo '<tr><th>Service Charges</th><td>$'.esc_html(number_format($totals['service_charge_total'],2)).'</td></tr>';
  echo '<tr><th>Combined Operational Total</th><td><strong>$'.esc_html(number_format($totals['combined_operational_total'],2)).'</strong></td></tr>';
  echo '</tbody></table>';
 }
}
