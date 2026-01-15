<?php
/**
 * Admin page functionality.
 *
 * @package VGP_EDD_Stats
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page class.
 */
class VGP_EDD_Stats_Admin_Page {

	/**
	 * Singleton instance.
	 *
	 * @var VGP_EDD_Stats_Admin_Page
	 */
	private static $instance;

	/**
	 * Get singleton instance.
	 *
	 * @return VGP_EDD_Stats_Admin_Page
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register plugin settings so they can be saved via options.php.
	 */
	public function register_settings() {
		register_setting(
			'vgp_edd_stats_settings',
			'vgp_edd_stats_cache_duration',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 3600,
			)
		);

		register_setting(
			'vgp_edd_stats_settings',
			'vgp_edd_stats_default_range',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_default_range' ),
				'default'           => '365',
			)
		);
	}

	/**
	 * Sanitize default date range setting.
	 *
	 * @param string $value Raw value.
	 * @return string Sanitized value.
	 */
	public function sanitize_default_range( $value ) {
		$value   = sanitize_text_field( $value );
		$allowed = array( '30', '90', '365', 'all' );

		return in_array( $value, $allowed, true ) ? $value : '365';
	}

	/**
	 * Add admin menu pages.
	 */
	public function add_menu_pages() {
		// Main dashboard page.
		add_menu_page(
			__( 'EDD Stats', 'vgp-edd-stats' ),
			__( 'EDD Stats', 'vgp-edd-stats' ),
			'manage_shop_settings',
			'vgp-edd-stats',
			array( $this, 'render_dashboard' ),
			'dashicons-chart-area',
			58
		);

		// Subpages for each stats section.
		// Keep only the pages that existed in the original Appsmith dashboards.
		$subpages = array(
			'customers-revenue' => __( 'Customers & Revenue', 'vgp-edd-stats' ),
			'mrr-growth'        => __( 'MRR & Growth', 'vgp-edd-stats' ),
			'renewals'          => __( 'Renewals & Cancellations', 'vgp-edd-stats' ),
			'refunds'           => __( 'Refunds', 'vgp-edd-stats' ),
			'licensing'         => __( 'Software Licensing', 'vgp-edd-stats' ),
			'sites'             => __( 'Sites Stats', 'vgp-edd-stats' ),
			'support'           => __( 'Support', 'vgp-edd-stats' ),
		);

		foreach ( $subpages as $slug => $title ) {
			add_submenu_page(
				'vgp-edd-stats',
				$title,
				$title,
				'manage_shop_settings',
				'vgp-edd-stats-' . $slug,
				array( $this, 'render_dashboard' )
			);
		}

		// Settings page.
		add_submenu_page(
			'vgp-edd-stats',
			__( 'Settings', 'vgp-edd-stats' ),
			__( 'Settings', 'vgp-edd-stats' ),
			'manage_shop_settings',
			'vgp-edd-stats-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Render dashboard page.
	 */
	public function render_dashboard() {
		// Get current page slug.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'vgp-edd-stats';

		// Determine active section.
		$section = str_replace( 'vgp-edd-stats-', '', $page );
		if ( $section === 'vgp-edd-stats' ) {
			$section = 'executive-overview'; // Default to executive overview.
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="vgp-edd-stats-root" data-section="<?php echo esc_attr( $section ); ?>"></div>
		</div>
		<?php
	}

	/**
	 * Render settings page.
	 */
	public function render_settings() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'vgp_edd_stats_settings' );
				do_settings_sections( 'vgp-edd-stats-settings' );
				?>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="vgp_edd_stats_cache_duration">
								<?php esc_html_e( 'Cache Duration', 'vgp-edd-stats' ); ?>
							</label>
						</th>
						<td>
							<input
								type="number"
								id="vgp_edd_stats_cache_duration"
								name="vgp_edd_stats_cache_duration"
								value="<?php echo esc_attr( get_option( 'vgp_edd_stats_cache_duration', 3600 ) ); ?>"
								class="regular-text"
								min="0"
							/>
							<p class="description">
								<?php esc_html_e( 'Cache duration in seconds (default: 3600 = 1 hour). Set to 0 to disable caching.', 'vgp-edd-stats' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="vgp_edd_stats_default_range">
								<?php esc_html_e( 'Default Date Range', 'vgp-edd-stats' ); ?>
							</label>
						</th>
						<td>
							<select id="vgp_edd_stats_default_range" name="vgp_edd_stats_default_range">
								<?php
								$ranges   = array(
									'30'  => __( 'Last 30 Days', 'vgp-edd-stats' ),
									'90'  => __( 'Last 90 Days', 'vgp-edd-stats' ),
									'365' => __( 'Last 12 Months', 'vgp-edd-stats' ),
									'all' => __( 'All Time', 'vgp-edd-stats' ),
								);
								$selected = get_option( 'vgp_edd_stats_default_range', '365' );

								foreach ( $ranges as $value => $label ) {
									printf(
										'<option value="%s"%s>%s</option>',
										esc_attr( $value ),
										selected( $selected, $value, false ),
										esc_html( $label )
									);
								}
								?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Default date range for dashboard charts.', 'vgp-edd-stats' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Clear Cache', 'vgp-edd-stats' ); ?>
						</th>
						<td>
							<button type="button" class="button" id="vgp-edd-stats-clear-cache">
								<?php esc_html_e( 'Clear All Cached Stats', 'vgp-edd-stats' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Clear all cached statistics data. Charts will reload fresh data on next view.', 'vgp-edd-stats' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<script>
		document.getElementById('vgp-edd-stats-clear-cache').addEventListener('click', function() {
			const confirmText = <?php echo wp_json_encode( __( 'Are you sure you want to clear all cached stats data?', 'vgp-edd-stats' ) ); ?>;
			const successText = <?php echo wp_json_encode( __( 'Cache cleared successfully!', 'vgp-edd-stats' ) ); ?>;
			const errorText = <?php echo wp_json_encode( __( 'Error clearing cache.', 'vgp-edd-stats' ) ); ?>;
			const endpointUrl = <?php echo wp_json_encode( rest_url( 'vgp-edd-stats/v1/cache/clear' ) ); ?>;
			const nonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;

			if (confirm(confirmText)) {
				fetch(endpointUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'X-WP-Nonce': nonce
					}
				})
				.then(response => response.json())
				.then(data => {
					alert(data.message || successText);
				})
				.catch(error => {
					alert(errorText);
				});
			}
		});
		</script>
		<?php
	}
}
