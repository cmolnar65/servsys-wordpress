<?php
namespace ServSys\Companies;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class CompanyRepository {
	private $wpdb; private $table;
	public function __construct( $wpdb = null ) { $this->wpdb = $wpdb ?: $GLOBALS['wpdb']; $this->table = $this->wpdb->prefix . 'servsys_companies'; }
	public function all( bool $include_inactive = true ): array {
		$wpdb=$this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned company configuration.
		if ( $include_inactive ) { return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY name,id', $this->table ), ARRAY_A ); }
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned company configuration.
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE active = 1 ORDER BY name,id', $this->table ), ARRAY_A );
	}
	public function find_unscoped( int $id ) {
		$wpdb=$this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned company configuration.
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ), ARRAY_A );
	}
	public function create( array $data ): int { $this->wpdb->insert( $this->table, $data ); return (int) $this->wpdb->insert_id; }
	public function update( int $id, array $data ): bool { return false !== $this->wpdb->update( $this->table, $data, array( 'id'=>$id ) ); }
}
