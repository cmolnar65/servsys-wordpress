<?php

namespace ServSys\Sites;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SiteRepository {
	private $wpdb;
	private $table;

	public function __construct( $wpdb = null ) {
		$this->wpdb  = $wpdb ?: $GLOBALS['wpdb'];
		$this->table = $this->wpdb->prefix . 'servsys_sites';
	}

	public function for_customer( int $customer_id, bool $include_inactive = true ): array {
		$sql = $include_inactive
			? $this->wpdb->prepare( 'SELECT * FROM %i WHERE customer_id = %d ORDER BY name, city, address1', $this->table, $customer_id )
			: $this->wpdb->prepare( 'SELECT * FROM %i WHERE customer_id = %d AND active = 1 ORDER BY name, city, address1', $this->table, $customer_id );
		return $this->wpdb->get_results( $sql, ARRAY_A );
	}

	public function find( int $id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ),
			ARRAY_A
		);
	}

	public function create( array $data ): int {
		$this->wpdb->insert( $this->table, $data );
		return (int) $this->wpdb->insert_id;
	}

	public function update( int $id, array $data ): bool {
		return false !== $this->wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}
}
