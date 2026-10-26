<?php

namespace ServSys\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InventoryItemService {
	private $items;

	public function __construct( InventoryItemRepository $items = null ) {
		$this->items = $items ?: new InventoryItemRepository();
	}

	public function save( array $input, int $id = 0 ): int {
		$sku = sanitize_text_field( (string) ( $input['sku'] ?? '' ) );
		$description = sanitize_text_field( (string) ( $input['description'] ?? '' ) );
		if ( '' === $sku ) {
			throw new \InvalidArgumentException( 'Inventory SKU is required.' );
		}
		if ( '' === $description ) {
			throw new \InvalidArgumentException( 'Inventory description is required.' );
		}

		$existing_sku = $this->items->find_by_sku( $sku );
		if ( $existing_sku && (int) $existing_sku['id'] !== $id ) {
			throw new \InvalidArgumentException( 'Inventory SKU must be unique within the current company.' );
		}
		if ( $id && ! $this->items->find( $id ) ) {
			throw new \InvalidArgumentException( 'Inventory item not found for the current company.' );
		}

		$unit_cost = round( (float) ( $input['unit_cost'] ?? 0 ), 4 );
		$reorder_quantity = round( (float) ( $input['reorder_quantity'] ?? 0 ), 4 );
		if ( $unit_cost < 0 || $reorder_quantity < 0 ) {
			throw new \InvalidArgumentException( 'Unit cost and reorder quantity cannot be negative.' );
		}

		$now = current_time( 'mysql', true );
		$data = array(
			'sku'                      => $sku,
			'description'              => $description,
			'manufacturer'             => sanitize_text_field( (string) ( $input['manufacturer'] ?? '' ) ),
			'manufacturer_part_number' => sanitize_text_field( (string) ( $input['manufacturer_part_number'] ?? '' ) ),
			'category'                 => sanitize_text_field( (string) ( $input['category'] ?? '' ) ),
			'unit_of_measure'          => sanitize_text_field( (string) ( $input['unit_of_measure'] ?? 'each' ) ) ?: 'each',
			'unit_cost'                => $unit_cost,
			'reorder_quantity'         => $reorder_quantity,
			'notes'                    => sanitize_textarea_field( (string) ( $input['notes'] ?? '' ) ),
			'active'                   => ! empty( $input['active'] ) ? 1 : 0,
			'updated_at'               => $now,
			'updated_by'               => get_current_user_id(),
		);

		if ( $id ) {
			if ( ! $this->items->update( $id, $data ) ) {
				throw new \RuntimeException( 'Unable to update inventory item.' );
			}
			return $id;
		}

		$data['created_at'] = $now;
		$data['created_by'] = get_current_user_id();
		$id = $this->items->create( $data );
		if ( ! $id ) {
			throw new \RuntimeException( 'Unable to create inventory item.' );
		}
		return $id;
	}
}
