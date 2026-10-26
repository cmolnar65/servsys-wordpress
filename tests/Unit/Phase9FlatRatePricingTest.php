<?php
use PHPUnit\Framework\TestCase;
use ServSys\FlatRate\FlatRatePricingEngine;
final class Phase9FlatRatePricingTest extends TestCase {
 public function test_standard_price_uses_labor_marked_up_parts_and_tax(): void {
  $p=FlatRatePricingEngine::calculate(2.0,250.0,100.0,1.45,5.0);
  $this->assertSame(500.0,$p['labor_total']);
  $this->assertSame(145.0,$p['material_selling_total']);
  $this->assertSame(7.25,$p['sales_tax']);
  $this->assertSame(652.25,$p['price']);
 }
 public function test_legacy_customer_class_multipliers_are_applied_deterministically(): void {
  $args=array(1.0,250.0,100.0,1.45,5.0);
  $this->assertSame(301.6875,FlatRatePricingEngine::calculate(...array_merge($args,array(FlatRatePricingEngine::PRICE_ADDITIONAL,0.75,0.80,1.05)))['price']);
  $this->assertSame(321.0,FlatRatePricingEngine::calculate(...array_merge($args,array(FlatRatePricingEngine::PRICE_AGREEMENT,0.75,0.80,1.05)))['price']);
  $this->assertSame(240.75,FlatRatePricingEngine::calculate(...array_merge($args,array(FlatRatePricingEngine::PRICE_AGREEMENT_ADDITIONAL,0.75,0.80,1.05)))['price']);
  $this->assertSame(421.3125,FlatRatePricingEngine::calculate(...array_merge($args,array(FlatRatePricingEngine::PRICE_COMMERCIAL,0.75,0.80,1.05)))['price']);
 }
 public function test_no_discount_material_uses_stored_cost_as_selling_amount_and_bypasses_class_multiplier(): void {
  $p=FlatRatePricingEngine::calculate(1.0,250.0,100.0,1.45,5.0,FlatRatePricingEngine::PRICE_AGREEMENT,0.75,0.80,1.05,true);
  $this->assertSame(100.0,$p['material_selling_total']);
  $this->assertSame(5.0,$p['sales_tax']);
  $this->assertSame(355.0,$p['base_price']);
  $this->assertSame(1.0,$p['price_multiplier']);
  $this->assertSame(355.0,$p['price']);
 }
 public function test_invalid_price_type_and_negative_inputs_are_rejected(): void {
  $this->expectException(InvalidArgumentException::class);
  FlatRatePricingEngine::calculate(1,250,100,1.45,5,'unknown');
 }
 public function test_negative_input_is_rejected(): void {
  $this->expectException(InvalidArgumentException::class);
  FlatRatePricingEngine::calculate(-1,250,100,1.45,5);
 }
 public function test_special_item_thresholds_are_not_invented_into_pricing_formula(): void {
  $engine=file_get_contents(__DIR__.'/../../src/FlatRate/FlatRatePricingEngine.php');
  $this->assertStringNotContainsString('special_item_low_price',$engine);
  $this->assertStringNotContainsString('special_item_high_price',$engine);
  $this->assertStringNotContainsString('special_item_high_hours',$engine);
 }
}
