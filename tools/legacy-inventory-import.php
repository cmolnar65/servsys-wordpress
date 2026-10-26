<?php
/**
 * One-time ServSys legacy inventory importer.
 *
 * Development utility only. This file must never be included in the plugin ZIP.
 *
 * Usage:
 * php tools/legacy-inventory-import.php --wp-load=/path/to/wp-load.php --user-id=1
 * php tools/legacy-inventory-import.php --wp-load=/path/to/wp-load.php --user-id=1 --apply
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "ERROR: CLI only.\n" );
	exit( 1 );
}

$options = getopt( '', array( 'source::', 'wp-load:', 'user-id:', 'company-id::', 'location::', 'apply', 'help' ) );
if ( isset( $options['help'] ) ) {
	echo "Dry run (default):\n";
	echo "  php tools/legacy-inventory-import.php --wp-load=/path/to/wp-load.php --user-id=1\n\n";
	echo "Apply after reviewing the dry run:\n";
	echo "  php tools/legacy-inventory-import.php --wp-load=/path/to/wp-load.php --user-id=1 --apply\n";
	exit( 0 );
}

$source      = isset( $options['source'] ) ? (string) $options['source'] : '/home/cmolnar/ourworldservices_inventory_dump.sql';
$wp_load     = isset( $options['wp-load'] ) ? (string) $options['wp-load'] : '';
$user_id     = isset( $options['user-id'] ) ? (int) $options['user-id'] : 0;
$company_id  = isset( $options['company-id'] ) ? (int) $options['company-id'] : 1;
$location    = isset( $options['location'] ) ? trim( (string) $options['location'] ) : 'Legacy Inventory';
$apply       = isset( $options['apply'] );

if ( ! is_file( $source ) || ! is_readable( $source ) ) {
	fwrite( STDERR, "ERROR: Legacy SQL dump is not readable: {$source}\n" );
	exit( 1 );
}
if ( '' === $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, "ERROR: --wp-load must point to the WordPress wp-load.php file.\n" );
	exit( 1 );
}
if ( $user_id <= 0 || $company_id <= 0 || '' === $location ) {
	fwrite( STDERR, "ERROR: Valid --user-id, --company-id and location are required.\n" );
	exit( 1 );
}

require_once $wp_load;

if ( ! defined( 'SERVSYS_DB_VERSION' ) ) {
	fwrite( STDERR, "ERROR: ServSys is not active in this WordPress installation.\n" );
	exit( 1 );
}
wp_set_current_user( $user_id );

if ( ! \ServSys\Users\Authorization::is_wordpress_admin( $user_id ) ) {
	fwrite( STDERR, "ERROR: --user-id must identify a WordPress Administrator.\n" );
	exit( 1 );
}
$current_company_id = \ServSys\Companies\CompanyContext::current_company_id();
if ( $current_company_id !== $company_id ) {
	if ( ! $apply ) {
		fwrite( STDERR, "ERROR: Dry run will not change company context. Current company is {$current_company_id}; requested company is {$company_id}.\n" );
		exit( 1 );
	}
	\ServSys\Companies\CompanyContext::set_current_company_id( $company_id );
	if ( \ServSys\Companies\CompanyContext::current_company_id() !== $company_id ) {
		fwrite( STDERR, "ERROR: Unable to select ServSys company {$company_id}.\n" );
		exit( 1 );
	}
}

$category_map = array(
	'FIL'=>'Filters','COP'=>'Plumbing - Copper Fittings','ELE'=>'Electrical','ACR'=>'Air Conditioning',
	'RFG'=>'Refrigeration','GAS'=>'Heating - Gas','CAP'=>'Electrical - Capacitors','CTL'=>'Electrical - Controls',
	'THM'=>'Electrical - Thermostats','MIS'=>'Disposables','HNG'=>'Hangers','PVC'=>'Plumbing - PVC Fittings',
	'HPU'=>'Heat Pumps','BLT'=>'Belts','OIL'=>'Heating - Oil','BLK'=>'Plumbing - Black Pipe',
	'REF'=>'Refrigerants','IAQ'=>'Indoor Air Quality','BBD'=>'Heating - Baseboard','ABS'=>'Plumbing - ABS',
	'AHU'=>'Air Handling Units','BOI'=>'Heating - Boilers','BRA'=>'Plumbing - Brass','BVT'=>'Vent - BVent',
	'CHM'=>'Heating - Complete Heat','COI'=>'Coils','CON'=>'Air Conditioning - Condensors','DAM'=>'Dampers',
	'DIF'=>'Diffusers','DUC'=>'Ductwork','FAN'=>'Fans','FLX'=>'Ductwork - Flex','FUR'=>'Heating - Furnaces',
	'GAL'=>'Ductwork - Galvinized','GRL'=>'Ductwork - Grills','HUM'=>'Humidifiers','HWH'=>'Hot Water Heaters',
	'HYD'=>'Heating - Hydronic','INS'=>'Insulation','MOT'=>'Motors','NOZ'=>'Oil Nozzles','PMP'=>'Pumps',
	'REG'=>'Ductwork - Registers','SWI'=>'Electrical - Switches','TUB'=>'Plumbing - Tubing','UNI'=>'Unico Parts',
	'VEN'=>'Vent','VLV'=>'Plumbing - Valves','WAT'=>'Plumbing - Misc','WIR'=>'Electrical - Wire',
);

function servsys_legacy_sql_unescape( string $value ): string {
	return strtr( $value, array( "\\0"=>"\0", "\\n"=>"\n", "\\r"=>"\r", "\\Z"=>"\x1a", "\\'"=>"'", '\\"'=>'"', "\\\\"=>"\\" ) );
}

function servsys_legacy_parse_tuple( string $tuple ): array {
	$values = array();
	$value = '';
	$quoted = false;
	$escaped = false;
	$length = strlen( $tuple );
	for ( $i = 0; $i < $length; $i++ ) {
		$char = $tuple[$i];
		if ( $quoted ) {
			if ( $escaped ) {
				$value .= '\\' . $char;
				$escaped = false;
			} elseif ( '\\' === $char ) {
				$escaped = true;
			} elseif ( "'" === $char ) {
				$quoted = false;
			} else {
				$value .= $char;
			}
		} elseif ( "'" === $char ) {
			$quoted = true;
		} elseif ( ',' === $char ) {
			$trimmed = trim( $value );
			$values[] = 'NULL' === strtoupper( $trimmed ) ? null : servsys_legacy_sql_unescape( $trimmed );
			$value = '';
		} else {
			$value .= $char;
		}
	}
	$trimmed = trim( $value );
	$values[] = 'NULL' === strtoupper( $trimmed ) ? null : servsys_legacy_sql_unescape( $trimmed );
	return $values;
}

function servsys_legacy_keycode_rows( string $path ): array {
	$handle = fopen( $path, 'rb' );
	if ( false === $handle ) {
		throw new RuntimeException( 'Unable to open legacy SQL dump.' );
	}
	$rows = array();
	$collecting = false;
	$buffer = '';
	while ( false !== ( $line = fgets( $handle ) ) ) {
		if ( ! $collecting ) {
			if ( 0 === strpos( $line, 'INSERT INTO `inv_keycodes` VALUES' ) ) {
				$collecting = true;
			}
			continue;
		}
		$buffer .= $line;
		if ( false !== strpos( $line, ';' ) ) {
			break;
		}
	}
	fclose( $handle );
	if ( '' === $buffer ) {
		throw new RuntimeException( 'No inv_keycodes INSERT data found in legacy SQL dump.' );
	}

	$tuple = '';
	$depth = 0;
	$quoted = false;
	$escaped = false;
	$length = strlen( $buffer );
	for ( $i = 0; $i < $length; $i++ ) {
		$char = $buffer[$i];
		if ( $quoted ) {
			if ( $escaped ) {
				$escaped = false;
			} elseif ( '\\' === $char ) {
				$escaped = true;
			} elseif ( "'" === $char ) {
				$quoted = false;
			}
			if ( $depth > 0 ) {
				$tuple .= $char;
			}
			continue;
		}
		if ( "'" === $char ) {
			$quoted = true;
			if ( $depth > 0 ) {
				$tuple .= $char;
			}
		} elseif ( '(' === $char ) {
			if ( 0 === $depth ) {
				$tuple = '';
			} else {
				$tuple .= $char;
			}
			$depth++;
		} elseif ( ')' === $char ) {
			$depth--;
			if ( 0 === $depth ) {
				$values = servsys_legacy_parse_tuple( $tuple );
				if ( 17 !== count( $values ) ) {
					throw new RuntimeException( 'Unexpected inv_keycodes column count while parsing legacy dump.' );
				}
				$rows[] = $values;
			} elseif ( $depth > 0 ) {
				$tuple .= $char;
			}
		} elseif ( $depth > 0 ) {
			$tuple .= $char;
		}
	}
	if ( 0 !== $depth || $quoted ) {
		throw new RuntimeException( 'Legacy inv_keycodes INSERT data ended unexpectedly.' );
	}
	return $rows;
}

function servsys_legacy_normalize_uom( $value ): string {
	$uom = strtoupper( trim( (string) $value ) );
	if ( '' === $uom ) {
		return 'EACH';
	}
	$map = array( 'EA'=>'EACH', 'ACH'=>'EACH', 'FOOT'=>'FT', 'FT.'=>'FT', 'PKG'=>'PACKAGE', 'GAL'=>'GALLON' );
	return $map[$uom] ?? $uom;
}

function servsys_legacy_category( string $sku, array $category_map ): string {
	if ( preg_match( '/^([A-Za-z]+)/', trim( $sku ), $matches ) ) {
		$prefix = strtoupper( $matches[1] );
		if ( isset( $category_map[$prefix] ) ) {
			return $category_map[$prefix];
		}
	}
	return 'MISC';
}

try {
	$legacy_rows = servsys_legacy_keycode_rows( $source );
} catch ( Throwable $exception ) {
	fwrite( STDERR, 'ERROR: ' . $exception->getMessage() . "\n" );
	exit( 1 );
}

$item_repository = new \ServSys\Inventory\InventoryItemRepository();
$item_service = new \ServSys\Inventory\InventoryItemService( $item_repository );
$location_repository = new \ServSys\Inventory\InventoryLocationRepository();
$location_service = new \ServSys\Inventory\InventoryLocationService( $location_repository );
$inventory_service = new \ServSys\Inventory\InventoryService();

$locations = $location_repository->all( true );
$location_id = 0;
foreach ( $locations as $existing_location ) {
	if ( 0 === strcasecmp( trim( (string) $existing_location['name'] ), $location ) ) {
		$location_id = (int) $existing_location['id'];
		break;
	}
}

$summary = array(
	'legacy_rows'=>count($legacy_rows),'new_items'=>0,'existing_skus'=>0,'blank_descriptions'=>0,
	'misc_categories'=>0,'negative_quantities_zeroed'=>0,'positive_quantity_items'=>0,'initial_quantity'=>0.0,
	'initial_transactions'=>0,'location_exists'=>(bool)$location_id,
);

$prepared = array();
foreach ( $legacy_rows as $row ) {
	list( $legacy_id, $sku, $part_number, $sort_desc, $description, $manufacturer, $legacy_location, $oh_qty, $qty_opt, $part_cost ) = array_slice( $row, 0, 10 );
	$sku = trim( (string) $sku );
	if ( '' === $sku ) {
		throw new RuntimeException( 'Legacy inv_keycodes row ' . (int)$legacy_id . ' has a blank keycode.' );
	}
	$description = trim( (string) $description );
	if ( '' === $description ) {
		$description = 'Legacy item ' . $sku;
		$summary['blank_descriptions']++;
	}
	$quantity = round( (float) $oh_qty, 4 );
	if ( $quantity < 0 ) {
		$quantity = 0.0;
		$summary['negative_quantities_zeroed']++;
	}
	$category = servsys_legacy_category( $sku, $category_map );
	if ( 'MISC' === $category ) {
		$summary['misc_categories']++;
	}
	if ( $quantity > 0 ) {
		$summary['positive_quantity_items']++;
		$summary['initial_quantity'] += $quantity;
	}
	$existing = $item_repository->find_by_sku( $sku );
	if ( $existing ) {
		$summary['existing_skus']++;
	} else {
		$summary['new_items']++;
	}
	$notes = '';
	if ( '' !== trim( (string) $legacy_location ) ) {
		$notes = 'Legacy bin/location: ' . trim( (string) $legacy_location );
	}
	$prepared[] = array(
		'legacy_id'=>(int)$legacy_id,
		'existing_id'=>$existing ? (int)$existing['id'] : 0,
		'quantity'=>$quantity,
		'item'=>array(
			'sku'=>$sku,
			'description'=>$description,
			'manufacturer'=>trim((string)$manufacturer),
			'manufacturer_part_number'=>trim((string)$part_number),
			'category'=>$category,
			'unit_of_measure'=>servsys_legacy_normalize_uom($row[13]),
			'unit_cost'=>max(0.0,round((float)$part_cost,4)),
			'reorder_quantity'=>max(0.0,round((float)$qty_opt,4)),
			'notes'=>$notes,
			'active'=>1,
		),
	);
}

echo "ServSys legacy inventory migration\n";
echo "Mode: " . ( $apply ? 'APPLY' : 'DRY RUN - NO CHANGES' ) . "\n";
echo "Source: {$source}\nCompany: {$company_id}\nLocation: {$location}\n\n";
foreach ( $summary as $key => $value ) {
	echo str_pad( $key, 30 ) . ': ' . ( is_bool($value) ? ($value?'yes':'no') : $value ) . "\n";
}

if ( ! $apply ) {
	echo "\nNo database changes were made. Re-run with --apply only after reviewing this report.\n";
	exit( 0 );
}

if ( ! $location_id ) {
	$location_id = $location_service->save( array(
		'name'=>$location,'location_type'=>'other','notes'=>'One-time legacy inventory import location.','active'=>1,
	) );
	echo "\nCreated inventory location #{$location_id}: {$location}\n";
}

$created_items = 0;
$created_transactions = 0;
$skipped_existing = 0;
foreach ( $prepared as $record ) {
	if ( $record['existing_id'] ) {
		$skipped_existing++;
		continue;
	}
	$item_id = $item_service->save( $record['item'] );
	$created_items++;
	if ( $record['quantity'] > 0 ) {
		$inventory_service->adjust_stock(
			$item_id,
			$location_id,
			(float)$record['quantity'],
			'initial',
			'Initial quantity imported from legacy inv_keycodes; legacy record ID ' . $record['legacy_id'] . '.'
		);
		$created_transactions++;
	}
}

echo "\nImport complete.\n";
echo "Items created: {$created_items}\n";
echo "Existing SKUs skipped: {$skipped_existing}\n";
echo "Initial inventory transactions created: {$created_transactions}\n";
echo "IMPORTANT: Existing ServSys SKUs are never overwritten and receive no imported quantity.\n";
