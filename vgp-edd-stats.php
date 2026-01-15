<?php
/**
 * Plugin Name: VGP EDD Stats Dashboard
 * Plugin URI: https://verygoodplugins.com
 * Description: Modern analytics dashboard for Easy Digital Downloads with advanced filtering and comparison tools
 * Version: 1.0.0
 * Author: Very Good Plugins
 * Author URI: https://verygoodplugins.com
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: vgp-edd-stats
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
final class VGP_EDD_Stats {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Singleton instance.
	 *
	 * @var VGP_EDD_Stats
	 */
	private static $instance;

	/**
	 * Get singleton instance.
	 *
	 * @return VGP_EDD_Stats
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof VGP_EDD_Stats ) ) {
			self::$instance = new VGP_EDD_Stats();
			self::$instance->setup_constants();
			self::$instance->includes();
			self::$instance->hooks();
		}

		return self::$instance;
	}

	/**
	 * Setup plugin constants.
	 */
	private function setup_constants() {
		define( 'VGP_EDD_STATS_VERSION', self::VERSION );
		define( 'VGP_EDD_STATS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		define( 'VGP_EDD_STATS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		define( 'VGP_EDD_STATS_PLUGIN_FILE', __FILE__ );
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once VGP_EDD_STATS_PLUGIN_DIR . 'includes/class-admin-page.php';
		require_once VGP_EDD_STATS_PLUGIN_DIR . 'includes/class-stats-query.php';
		require_once VGP_EDD_STATS_PLUGIN_DIR . 'includes/class-stats-api.php';
	}

	/**
	 * Setup hooks and filters.
	 */
	private function hooks() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		// Check if EDD is active.
		if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
			add_action( 'admin_notices', array( $this, 'edd_missing_notice' ) );
			return;
		}

		// Initialize components.
		VGP_EDD_Stats_Admin_Page::instance();
		VGP_EDD_Stats_API::instance();

		// Load translations.
		load_plugin_textdomain( 'vgp-edd-stats', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our stats pages.
		if ( strpos( $hook, 'vgp-edd-stats' ) === false ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		// Don't load the dashboard bundle on the settings screen.
		if ( 'vgp-edd-stats-settings' === $page ) {
			return;
		}

		// If EDD isn't active, avoid calling EDD helpers.
		if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
			return;
		}

		// Enqueue styles.
		wp_enqueue_style(
			'vgp-edd-stats',
			VGP_EDD_STATS_PLUGIN_URL . 'build/dashboard.css',
			array(),
			VGP_EDD_STATS_VERSION
		);

		// Enqueue scripts.
		$asset_file = VGP_EDD_STATS_PLUGIN_DIR . 'build/dashboard.asset.php';
		$asset_data = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => VGP_EDD_STATS_VERSION,
		);

		wp_enqueue_script(
			'vgp-edd-stats',
			VGP_EDD_STATS_PLUGIN_URL . 'build/dashboard.js',
			$asset_data['dependencies'],
			$asset_data['version'],
			true
		);

		// Localize script.
		wp_localize_script(
			'vgp-edd-stats',
			'vgpEddStats',
			array(
				'apiUrl'       => rest_url( 'vgp-edd-stats/v1' ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'dateFormat'   => get_option( 'date_format' ),
				'currencyCode' => edd_get_currency(),
				'defaultRange' => get_option( 'vgp_edd_stats_default_range', '365' ),
				'version'      => VGP_EDD_STATS_VERSION,
			)
		);
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		// Check for minimum requirements.
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( __( 'VGP EDD Stats requires PHP 8.0 or higher.', 'vgp-edd-stats' ) );
		}

		if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( __( 'VGP EDD Stats requires WordPress 6.0 or higher.', 'vgp-edd-stats' ) );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		// Clear cached data.
		$this->clear_cache();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Clear all cached stats data.
	 */
	public function clear_cache() {
		global $wpdb;

		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_vgp_edd_stats_%'
			OR option_name LIKE '_transient_timeout_vgp_edd_stats_%'"
		);
	}

	/**
	 * Display notice if EDD is not active.
	 */
	public function edd_missing_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %s: Easy Digital Downloads */
					esc_html__( 'VGP EDD Stats requires %s to be installed and active.', 'vgp-edd-stats' ),
					'<strong>Easy Digital Downloads</strong>'
				);
				?>
			</p>
		</div>
		<?php
	}
}

/**
 * Initialize the plugin.
 *
 * @return VGP_EDD_Stats
 */
function vgp_edd_stats() {
	return VGP_EDD_Stats::instance();
}

// Kick it off.
vgp_edd_stats();
