<?php
/**
 * Development Mode Configuration (Sample)
 *
 * Copy this file to dev-config.php and customize as needed.
 * The dev-config.php file is gitignored for security.
 *
 * When dev-config.php exists, the plugin will use the development
 * database specified below instead of the WordPress database for
 * all EDD stats queries.
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
 * When true, the plugin uses the development database below.
 * When false or undefined, uses normal WordPress database.
 */
define( 'VGP_EDD_STATS_DEV_MODE', true );

/**
 * Development database connection settings.
 *
 * These should match your local MySQL configuration.
 * Default Local WP settings are shown below.
 */
define( 'VGP_EDD_DEV_DB_HOST', 'localhost' );
define( 'VGP_EDD_DEV_DB_NAME', 'vgp_edd_dev' );
define( 'VGP_EDD_DEV_DB_USER', 'root' );
define( 'VGP_EDD_DEV_DB_PASSWORD', 'root' );

/**
 * Development database table prefix.
 *
 * Should match your live site's table prefix (typically 'wp_').
 */
define( 'VGP_EDD_DEV_DB_PREFIX', 'wp_' );
