<?php
/**
 * REST API functionality.
 *
 * @package VGP_EDD_Stats
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stats API class.
 */
class VGP_EDD_Stats_API {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'vgp-edd-stats/v1';

	/**
	 * Singleton instance.
	 *
	 * @var VGP_EDD_Stats_API
	 */
	private static $instance;

	/**
	 * Get singleton instance.
	 *
	 * @return VGP_EDD_Stats_API
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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Customers and Revenue endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/customers/by-month',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_customers_by_month' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/customers/yoy-change',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_customers_yoy_change' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/revenue/by-month',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_revenue_by_month' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/revenue/refunded',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_refunded_revenue' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		// MRR and Growth endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/mrr/by-month',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_mrr_by_month' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/mrr/current',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_current_mrr' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Renewals endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/renewals/rates',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_renewal_rates' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/renewals/upcoming',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_upcoming_renewals' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'days' => array(
						'default'           => 30,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Refunds endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/refunds/rates',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_refund_rates' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		// Licensing endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/licenses/top',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_top_licenses' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'limit' => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Cache management endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/cache/clear',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'clear_cache' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Health check endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'health_check' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Get date range arguments for endpoints.
	 *
	 * @return array Arguments array.
	 */
	private function get_date_range_args() {
		return array(
			'start_date' => array(
				'default'           => null,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_date' ),
			),
			'end_date'   => array(
				'default'           => null,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_date' ),
			),
		);
	}

	/**
	 * Validate date format.
	 *
	 * @param string $date Date string.
	 * @return bool True if valid or empty, false otherwise.
	 */
	public function validate_date( $date ) {
		if ( empty( $date ) ) {
			return true;
		}

		$parsed = date_parse( $date );
		return $parsed && ! $parsed['error_count'] && checkdate( $parsed['month'], $parsed['day'], $parsed['year'] );
	}

	/**
	 * Check user permissions.
	 *
	 * @return bool True if user has permission.
	 */
	public function check_permissions() {
		return current_user_can( 'manage_shop_settings' );
	}

	/**
	 * Get customers by month.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_customers_by_month( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$data = VGP_EDD_Stats_Query::get_new_customers_by_month( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get customers YoY change.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_customers_yoy_change( $request ) {
		$data = VGP_EDD_Stats_Query::get_new_customers_yoy_change();

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get revenue by month.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_revenue_by_month( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$data = VGP_EDD_Stats_Query::get_revenue_by_month( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get refunded revenue.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_refunded_revenue( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$data = VGP_EDD_Stats_Query::get_refunded_revenue_by_month( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get MRR by month.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_mrr_by_month( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$data = VGP_EDD_Stats_Query::get_mrr_by_month( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get current MRR breakdown.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_current_mrr( $request ) {
		$data = VGP_EDD_Stats_Query::get_current_mrr_breakdown();

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get renewal rates.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_renewal_rates( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$data = VGP_EDD_Stats_Query::get_renewal_rates_by_month( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get upcoming renewals.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_upcoming_renewals( $request ) {
		$days = $request->get_param( 'days' );
		$data = VGP_EDD_Stats_Query::get_upcoming_renewals( $days );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get refund rates.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_refund_rates( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$data = VGP_EDD_Stats_Query::get_refund_rates_by_month( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get top licenses.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_top_licenses( $request ) {
		$limit = $request->get_param( 'limit' );
		$data  = VGP_EDD_Stats_Query::get_top_licenses( $limit );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Clear all cached data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function clear_cache( $request ) {
		vgp_edd_stats()->clear_cache();

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Cache cleared successfully!', 'vgp-edd-stats' ),
			)
		);
	}

	/**
	 * Health check endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function health_check( $request ) {
		global $wpdb;

		// Check database connection.
		$db_check = $wpdb->query( 'SELECT 1' );

		// Check if EDD is active.
		$edd_active = class_exists( 'Easy_Digital_Downloads' );

		// Check required tables.
		$tables_exist = array(
			'edd_customers'     => $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}edd_customers'" ) === $wpdb->prefix . 'edd_customers',
			'edd_orders'        => $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}edd_orders'" ) === $wpdb->prefix . 'edd_orders',
			'edd_subscriptions' => $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}edd_subscriptions'" ) === $wpdb->prefix . 'edd_subscriptions',
		);

		$all_tables_exist = ! in_array( false, $tables_exist, true );

		return rest_ensure_response(
			array(
				'success'       => $db_check && $edd_active && $all_tables_exist,
				'plugin'        => 'VGP EDD Stats',
				'version'       => VGP_EDD_STATS_VERSION,
				'database'      => $db_check ? 'connected' : 'disconnected',
				'edd_active'    => $edd_active,
				'tables_exist'  => $tables_exist,
			)
		);
	}
}
