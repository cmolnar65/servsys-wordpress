<?php
namespace ServSys\FlatRate;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class FlatRatePricingEngine {
 public const PRICE_STANDARD='standard';
 public const PRICE_ADDITIONAL='additional';
 public const PRICE_AGREEMENT='agreement';
 public const PRICE_AGREEMENT_ADDITIONAL='agreement_additional';
 public const PRICE_COMMERCIAL='commercial';
 public static function calculate(float $labor_hours,float $labor_rate,float $material_cost,float $part_markup_multiplier,float $sales_tax_percent,string $price_type='standard',float $additional_multiplier=1.0,float $agreement_multiplier=1.0,float $commercial_multiplier=1.0,bool $no_discount=false): array {
  foreach(array($labor_hours,$labor_rate,$material_cost,$part_markup_multiplier,$sales_tax_percent,$additional_multiplier,$agreement_multiplier,$commercial_multiplier) as $value){if($value<0){throw new \InvalidArgumentException('Flat Rate pricing inputs cannot be negative.');}}
  $allowed=array(self::PRICE_STANDARD,self::PRICE_ADDITIONAL,self::PRICE_AGREEMENT,self::PRICE_AGREEMENT_ADDITIONAL,self::PRICE_COMMERCIAL);if(!in_array($price_type,$allowed,true)){throw new \InvalidArgumentException('Invalid Flat Rate price type.');}
  $parts=$no_discount?$material_cost:$material_cost*$part_markup_multiplier;
  $tax=$parts*($sales_tax_percent/100);
  $base=($labor_hours*$labor_rate)+$parts+$tax;
  $multiplier=1.0;
  if(self::PRICE_ADDITIONAL===$price_type){$multiplier=$additional_multiplier;}
  elseif(self::PRICE_AGREEMENT===$price_type){$multiplier=$agreement_multiplier;}
  elseif(self::PRICE_AGREEMENT_ADDITIONAL===$price_type){$multiplier=$agreement_multiplier*$additional_multiplier;}
  elseif(self::PRICE_COMMERCIAL===$price_type){$multiplier=$commercial_multiplier;}
  if($no_discount && self::PRICE_STANDARD!==$price_type){$multiplier=1.0;}
  return array('labor_total'=>round($labor_hours*$labor_rate,4),'material_cost'=>round($material_cost,4),'material_selling_total'=>round($parts,4),'sales_tax'=>round($tax,4),'base_price'=>round($base,4),'price_multiplier'=>round($multiplier,4),'price'=>round($base*$multiplier,4),'price_type'=>$price_type,'no_discount'=>$no_discount);
 }
}
