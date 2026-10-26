<?php
/**
 * Plugin Name: ServSys
 * Description: WordPress-based field service management system.
 * Version: 0.1.0-dev
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author: Christopher Molnar
 * Text Domain: servsys
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SERVSYS_VERSION', '0.1.0-dev' );
define( 'SERVSYS_DB_VERSION', 11 );
define( 'SERVSYS_PLUGIN_FILE', __FILE__ );
define( 'SERVSYS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

$servsys_autoload = SERVSYS_PLUGIN_DIR . 'vendor/autoload.php';

if ( file_exists( $servsys_autoload ) ) {
	require_once $servsys_autoload;
}

if ( class_exists( '\\ServSys\\Database\\Installer' ) ) {
	register_activation_hook( __FILE__, array( '\\ServSys\\Database\\Installer', 'activate' ) );
}

if ( class_exists( '\\ServSys\\Plugin' ) ) {
	\ServSys\Plugin::instance()->register_hooks();
}
