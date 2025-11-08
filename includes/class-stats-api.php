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

        register_rest_route(
            self::NAMESPACE,
            '/refunds/new-customers-yearly',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_new_customer_refunds_yearly' ),
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

		// Customer Analytics endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/customers/clv',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_customer_clv' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'limit' => array(
						'default'           => 100,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/customers/rfm',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_customer_rfm' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/customers/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_customer_health' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/customers/acquisition-cost',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_acquisition_cost' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/customers/retention',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_customer_retention' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		// Product Performance endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/products/top',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_top_products' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array_merge(
					$this->get_date_range_args(),
					array(
						'limit' => array(
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
					)
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products/growth',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_product_growth' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products/matrix',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_product_matrix' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products/lifecycle',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_product_lifecycle' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Revenue Analytics endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/revenue/breakdown',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_revenue_breakdown' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/revenue/concentration',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_revenue_concentration' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/revenue/segments',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_revenue_segments' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/revenue/per-customer',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_revenue_per_customer' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		// Financial Metrics endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/financial/cash-flow',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_cash_flow' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/financial/profitability',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_profitability' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/financial/burn-rate',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_burn_rate' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/financial/runway',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_runway' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		// Subscription Analytics endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/subscriptions/churn',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_subscription_churn' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/subscriptions/cohort',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_cohort_analysis' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'months' => array(
						'default'           => 12,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/subscriptions/reactivation',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_reactivation_rates' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/subscriptions/upgrade-downgrade',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_upgrade_downgrade' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		// Predictive Analytics endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/predictive/churn',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_churn_prediction' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/predictive/revenue-forecast',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_revenue_forecast' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'months' => array(
						'default'           => 6,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Payment Analytics endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/payments/gateways',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_payment_gateways' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/payments/failures',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_payment_failures' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => $this->get_date_range_args(),
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
        if ( ! is_array( $data ) ) {
            $data = array();
        }

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
     * Get new customer refund rates by year.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function get_new_customer_refunds_yearly( $request ) {
        $start_date = $request->get_param( 'start_date' );
        $end_date   = $request->get_param( 'end_date' );

        $data = VGP_EDD_Stats_Query::get_new_customer_refund_rates_by_year( $start_date, $end_date );

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
	 * Get customer lifetime value.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
    public function get_customer_clv( $request ) {
        $limit = $request->get_param( 'limit' );
        $data  = VGP_EDD_Stats_Query::get_customer_lifetime_values( null, null, $limit );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get customer RFM analysis.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
    public function get_customer_rfm( $request ) {
        $data = VGP_EDD_Stats_Query::get_rfm_segments();

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get customer health metrics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
    public function get_customer_health( $request ) {
        $data = VGP_EDD_Stats_Query::get_customer_health_scores();

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get customer acquisition cost.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_acquisition_cost( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$data = VGP_EDD_Stats_Query::get_customer_acquisition_cost( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get customer retention cohorts.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_customer_retention( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$data = VGP_EDD_Stats_Query::get_customer_retention_cohorts( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get top products by revenue.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
    public function get_top_products( $request ) {
        $start_date = $request->get_param( 'start_date' );
        $end_date   = $request->get_param( 'end_date' );
        $limit      = $request->get_param( 'limit' );

        $data = VGP_EDD_Stats_Query::get_top_products( $start_date, $end_date, $limit );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get product growth metrics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
    public function get_product_growth( $request ) {
        $start_date = $request->get_param( 'start_date' );
        $end_date   = $request->get_param( 'end_date' );

        $data = VGP_EDD_Stats_Query::get_product_growth_trends( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get product performance matrix.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_product_matrix( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$data = VGP_EDD_Stats_Query::get_product_performance_matrix( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get product lifecycle analysis.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
    public function get_product_lifecycle( $request ) {
        $data = VGP_EDD_Stats_Query::get_product_lifecycle_stages();

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get revenue breakdown.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_revenue_breakdown( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$data = VGP_EDD_Stats_Query::get_revenue_breakdown( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get revenue concentration analysis.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_revenue_concentration( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$data = VGP_EDD_Stats_Query::get_revenue_concentration( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get revenue by customer segments.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_revenue_segments( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$data = VGP_EDD_Stats_Query::get_revenue_by_customer_segments( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get average revenue per customer.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
    public function get_revenue_per_customer( $request ) {
        $start_date = $request->get_param( 'start_date' );
        $end_date   = $request->get_param( 'end_date' );

        $data = VGP_EDD_Stats_Query::get_average_revenue_per_customer( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get cash flow analysis.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
    public function get_cash_flow( $request ) {
        // Use projection for next 90 days as a placeholder implementation
        $data = VGP_EDD_Stats_Query::get_cash_flow_projection( 90 );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get profitability metrics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
    public function get_profitability( $request ) {
        $start_date = $request->get_param( 'start_date' );
        $end_date   = $request->get_param( 'end_date' );

        $data = VGP_EDD_Stats_Query::get_profitability_by_segment( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get burn rate analysis.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_burn_rate( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$data = VGP_EDD_Stats_Query::get_burn_rate_analysis( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get runway calculation.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_runway( $request ) {
		$data = VGP_EDD_Stats_Query::get_runway_calculation();

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get subscription churn analysis.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_subscription_churn( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$data = VGP_EDD_Stats_Query::get_subscription_churn_analysis( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get cohort retention analysis.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_cohort_analysis( $request ) {
		$months = $request->get_param( 'months' );
		$data   = VGP_EDD_Stats_Query::get_cohort_retention_analysis( $months );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get reactivation rates.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_reactivation_rates( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$data = VGP_EDD_Stats_Query::get_reactivation_rates( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get upgrade and downgrade trends.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_upgrade_downgrade( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$data = VGP_EDD_Stats_Query::get_upgrade_downgrade_trends( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get churn prediction.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
    public function get_churn_prediction( $request ) {
        // Return top 100 customers with churn risk scores
        $data = VGP_EDD_Stats_Query::get_churn_prediction_scores( 100 );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get revenue forecast.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_revenue_forecast( $request ) {
		$months = $request->get_param( 'months' );
		$data   = VGP_EDD_Stats_Query::get_revenue_forecast( $months );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get payment gateway analysis.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
    public function get_payment_gateways( $request ) {
        $start_date = $request->get_param( 'start_date' );
        $end_date   = $request->get_param( 'end_date' );

        $data = VGP_EDD_Stats_Query::get_payment_method_performance( $start_date, $end_date );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get payment failure analysis.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
    public function get_payment_failures( $request ) {
        $start_date = $request->get_param( 'start_date' );
        $end_date   = $request->get_param( 'end_date' );

        $data = VGP_EDD_Stats_Query::get_failed_payment_recovery( $start_date, $end_date );

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
