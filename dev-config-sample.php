<?php
/**
 * Development Mode Configuration (Sample)
 *
 * Copy this file to dev-config.php and customize as needed.
 * The dev-config.php file is gitignored for security.
 *
 * REMOTE DATABASE MODE (Recommended):
 * Connect directly to the live database via SSH tunnel.
 * No data sync needed - always uses fresh production data.
 *
 * Setup:
 * 1. Start the SSH tunnel: ./scripts/start-tunnel.sh start
 * 2. Copy this file to dev-config.php
 * 3. The plugin will query the live database through the tunnel
 *
 * @package VGP_EDD_Stats
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enable development mode.
 *
 * When true, the plugin uses the remote database connection below.
 * When false or undefined, uses normal WordPress database.
 */
define( 'VGP_EDD_STATS_DEV_MODE', true );

/**
 * Remote database connection via SSH tunnel.
 *
 * The SSH tunnel forwards localhost:3307 to the remote MySQL server.
 * Start the tunnel with: ./scripts/start-tunnel.sh start
 *
 * These credentials should match the LIVE site's database.
 * Get them from the live site's wp-config.php.
 */
define( 'VGP_EDD_DEV_DB_HOST', '127.0.0.1' );
define( 'VGP_EDD_DEV_DB_PORT', 3307 );  // SSH tunnel local port
define( 'VGP_EDD_DEV_DB_NAME', 'your_live_db_name' );  // From live wp-config.php
define( 'VGP_EDD_DEV_DB_USER', 'your_live_db_user' );  // From live wp-config.php
define( 'VGP_EDD_DEV_DB_PASSWORD', 'your_live_db_pass' );  // From live wp-config.php

/**
 * Remote database table prefix.
 *
 * Should match your live site's table prefix (typically 'wp_').
 */
define( 'VGP_EDD_DEV_DB_PREFIX', 'wp_' );
