<?php

namespace ServSys\Database;

final class Schema {
	public static function tables( $wpdb ): array {
		$charset = $wpdb->get_charset_collate();

		return array(
			"{$wpdb->prefix}servsys_customer_types" => "CREATE TABLE {$wpdb->prefix}servsys_customer_types (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(100) NOT NULL,
				slug varchar(100) NOT NULL,
				sort_order int(11) NOT NULL DEFAULT 0,
				active tinyint(1) NOT NULL DEFAULT 1,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY slug (slug),
				KEY active_sort (active,sort_order)
			) {$charset};",
			"{$wpdb->prefix}servsys_customers" => "CREATE TABLE {$wpdb->prefix}servsys_customers (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				legacy_custnum bigint(20) unsigned NULL,
				name varchar(255) NOT NULL,
				customer_type varchar(50) NULL,
				customer_type_id bigint(20) unsigned NULL,
				primary_phone varchar(50) NULL,
				alternate_phone varchar(50) NULL,
				email varchar(255) NULL,
				notes longtext NULL,
				active tinyint(1) NOT NULL DEFAULT 1,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				created_by bigint(20) unsigned NULL,
				updated_by bigint(20) unsigned NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY legacy_custnum (legacy_custnum),
				KEY name (name),
				KEY customer_type_id (customer_type_id),
				KEY email (email),
				KEY primary_phone (primary_phone),
				KEY active (active)
			) {$charset};",
			"{$wpdb->prefix}servsys_sites" => "CREATE TABLE {$wpdb->prefix}servsys_sites (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				customer_id bigint(20) unsigned NOT NULL,
				legacy_custsite varchar(100) NULL,
				legacy_sitenum bigint(20) unsigned NULL,
				name varchar(255) NULL,
				address1 varchar(255) NOT NULL,
				address2 varchar(255) NULL,
				city varchar(100) NOT NULL,
				state varchar(50) NOT NULL,
				postal_code varchar(20) NOT NULL,
				phone varchar(50) NULL,
				notes longtext NULL,
				active tinyint(1) NOT NULL DEFAULT 1,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				created_by bigint(20) unsigned NULL,
				updated_by bigint(20) unsigned NULL,
				PRIMARY KEY  (id),
				KEY customer_id (customer_id),
				KEY legacy_site (legacy_custsite,legacy_sitenum),
				KEY city (city),
				KEY postal_code (postal_code),
				KEY active (active)
			) {$charset};",
			"{$wpdb->prefix}servsys_customer_notes" => "CREATE TABLE {$wpdb->prefix}servsys_customer_notes (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				customer_id bigint(20) unsigned NOT NULL,
				site_id bigint(20) unsigned NULL,
				legacy_notenum bigint(20) unsigned NULL,
				subject varchar(255) NULL,
				note longtext NOT NULL,
				note_date datetime NOT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				created_by bigint(20) unsigned NULL,
				updated_by bigint(20) unsigned NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY legacy_notenum (legacy_notenum),
				KEY customer_id (customer_id),
				KEY site_id (site_id),
				KEY note_date (note_date)
			) {$charset};",
		);
	}
}
