<?php
/**
 * Stats query functionality.
 *
 * @package VGP_EDD_Stats
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stats query class.
 */
class VGP_EDD_Stats_Query {

	/**
	 * Cache group for transients.
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'vgp_edd_stats_';

	/**
	 * Development database connection.
	 *
	 * @var wpdb|null
	 */
	private static $dev_db = null;

	/**
	 * Check if development mode is enabled.
	 *
	 * @return bool True if dev mode is enabled.
	 */
	private static function is_dev_mode() {
		// Check if dev-config.php exists and has been loaded
		if ( file_exists( VGP_EDD_STATS_PLUGIN_DIR . 'dev-config.php' ) ) {
			require_once VGP_EDD_STATS_PLUGIN_DIR . 'dev-config.php';
		}

		return defined( 'VGP_EDD_STATS_DEV_MODE' ) && VGP_EDD_STATS_DEV_MODE;
	}

	/**
	 * Get database connection (dev or production).
	 *
	 * @return wpdb Database connection.
	 */
	private static function get_db() {
		// Return production database if not in dev mode
		if ( ! self::is_dev_mode() ) {
			global $wpdb;
			return $wpdb;
		}

		// Initialize dev database connection if needed
		if ( null === self::$dev_db ) {
			$db_host = defined( 'VGP_EDD_DEV_DB_HOST' ) ? VGP_EDD_DEV_DB_HOST : 'localhost';
			$db_name = defined( 'VGP_EDD_DEV_DB_NAME' ) ? VGP_EDD_DEV_DB_NAME : 'vgp_edd_dev';
			$db_user = defined( 'VGP_EDD_DEV_DB_USER' ) ? VGP_EDD_DEV_DB_USER : 'root';
			$db_pass = defined( 'VGP_EDD_DEV_DB_PASSWORD' ) ? VGP_EDD_DEV_DB_PASSWORD : 'root';

			// Create new wpdb instance for dev database
			self::$dev_db = new wpdb( $db_user, $db_pass, $db_name, $db_host );

			// Check if connection succeeded
			if ( ! self::$dev_db->dbh ) {
				// Connection failed, fall back to production database
				error_log( 'VGP EDD Stats: Failed to connect to dev database. Falling back to production database.' );
				if ( ! empty( self::$dev_db->error ) ) {
					error_log( 'VGP EDD Stats: Connection error: ' . self::$dev_db->error->get_error_message() );
				}
				global $wpdb;
				self::$dev_db = $wpdb;
			} else {
				// Set charset for dev database
				self::$dev_db->set_charset( self::$dev_db->dbh );
			}
		}

		return self::$dev_db;
	}

	/**
	 * Get cache duration in seconds.
	 *
	 * @return int Cache duration.
	 */
	private static function get_cache_duration() {
		return (int) get_option( 'vgp_edd_stats_cache_duration', 3600 );
	}

	/**
	 * Get cached query result or execute query.
	 *
	 * @param string $cache_key Cache key.
	 * @param string $query     SQL query.
	 * @param string $type      Query type (get_results, get_var, get_col).
	 * @return mixed Query results.
	 */
	private static function get_cached( $cache_key, $query, $type = 'get_results' ) {
		$db = self::get_db();

		$cache_duration = self::get_cache_duration();

		// Add dev mode suffix to cache key to prevent collisions
		if ( self::is_dev_mode() ) {
			$cache_key .= '_dev';
		}

		// Try to get cached data.
		if ( $cache_duration > 0 ) {
			$cached = get_transient( self::CACHE_GROUP . $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		// Execute query.
		switch ( $type ) {
			case 'get_var':
				$results = $db->get_var( $query );
				break;
			case 'get_col':
				$results = $db->get_col( $query );
				break;
			default:
				$results = $db->get_results( $query, ARRAY_A );
				break;
		}

		// Cache the results.
		if ( $cache_duration > 0 ) {
			set_transient( self::CACHE_GROUP . $cache_key, $results, $cache_duration );
		}

		return $results;
	}

	/**
	 * Get table prefix.
	 *
	 * @return string Table prefix.
	 */
	private static function get_table_prefix() {
		if ( self::is_dev_mode() && defined( 'VGP_EDD_DEV_DB_PREFIX' ) ) {
			return VGP_EDD_DEV_DB_PREFIX;
		}

		global $wpdb;
		return $wpdb->prefix;
	}

	// =========================
	// CUSTOMERS AND REVENUE
	// =========================

	/**
	 * Get new customers by month.
	 *
	 * @param string $start_date Start date (Y-m-d format).
	 * @param string $end_date   End date (Y-m-d format).
	 * @return array Query results.
	 */
	public static function get_new_customers_by_month( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = '';
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND date_created <= %s', $end_date );
		}

		$query = "
			SELECT
				DATE_FORMAT(date_created, '%Y-%m-01') AS date,
				DATE_FORMAT(date_created, '%M %Y') AS label,
				COUNT(*) AS value
			FROM {$wpdb->prefix}edd_customers
			WHERE date_created IS NOT NULL
			{$where}
			GROUP BY
				YEAR(date_created),
				MONTH(date_created)
			ORDER BY date
		";

		return self::get_cached( 'customers_by_month_' . md5( $query ), $query );
	}

	/**
	 * Get new customers YoY change.
	 *
	 * @return array Query results with current year, last year, and percent change.
	 */
	public static function get_new_customers_yoy_change() {
		$wpdb = self::get_db();

		$query = "
			SELECT
				SUM(CASE
					WHEN YEAR(date_created) = YEAR(CURDATE()) THEN 1
					ELSE 0
				END) AS current_year,
				SUM(CASE
					WHEN YEAR(date_created) = YEAR(CURDATE()) - 1 THEN 1
					ELSE 0
				END) AS last_year
			FROM {$wpdb->prefix}edd_customers
			WHERE YEAR(date_created) IN (YEAR(CURDATE()), YEAR(CURDATE()) - 1)
		";

		$result = self::get_cached( 'customers_yoy', $query );

		if ( empty( $result ) || ! isset( $result[0] ) ) {
			return array(
				'current_year' => 0,
				'last_year'    => 0,
				'change'       => 0,
			);
		}

		$current = (int) $result[0]['current_year'];
		$last    = (int) $result[0]['last_year'];
		$change  = $last > 0 ? ( ( $current - $last ) / $last ) * 100 : 0;

		return array(
			'current_year' => $current,
			'last_year'    => $last,
			'change'       => round( $change, 2 ),
		);
	}

	/**
	 * Get revenue by month (new vs recurring).
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Query results.
	 */
	public static function get_revenue_by_month( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = " AND o.status IN ('complete', 'edd_subscription')";
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created <= %s', $end_date );
		}

		$query = "
			SELECT
				DATE_FORMAT(o.date_created, '%Y-%m-01') AS date,
				DATE_FORMAT(o.date_created, '%M %Y') AS label,
				SUM(CASE WHEN o.type = 'sale' THEN o.total ELSE 0 END) AS new_revenue,
				SUM(CASE WHEN o.type = 'renewal' THEN o.total ELSE 0 END) AS recurring_revenue,
				SUM(o.total) AS total_revenue
			FROM {$wpdb->prefix}edd_orders o
			WHERE o.type IN ('sale', 'renewal')
			{$where}
			GROUP BY
				YEAR(o.date_created),
				MONTH(o.date_created)
			ORDER BY date
		";

		return self::get_cached( 'revenue_by_month_' . md5( $query ), $query );
	}

	/**
	 * Get refunded revenue by month.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Query results.
	 */
	public static function get_refunded_revenue_by_month( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = '';
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created <= %s', $end_date );
		}

		$query = "
			SELECT
				DATE_FORMAT(o.date_created, '%Y-%m-01') AS date,
				DATE_FORMAT(o.date_created, '%M %Y') AS label,
				ABS(SUM(o.total)) AS value
			FROM {$wpdb->prefix}edd_orders o
			WHERE o.status = 'refunded'
			{$where}
			GROUP BY
				YEAR(o.date_created),
				MONTH(o.date_created)
			ORDER BY date
		";

		return self::get_cached( 'refunded_revenue_' . md5( $query ), $query );
	}

	// =========================
	// MRR AND GROWTH
	// =========================

	/**
	 * Get MRR by month.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Query results.
	 */
	public static function get_mrr_by_month( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = " AND s.status != 'pending'";
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND s.created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND s.created <= %s', $end_date );
		}

		$query = "
			SELECT
				DATE_FORMAT(s.created, '%Y-%m-01') AS date,
				DATE_FORMAT(s.created, '%M %Y') AS label,
				COUNT(DISTINCT s.id) AS subscriptions,
				ROUND(SUM(s.initial_amount / 12), 2) AS mrr
			FROM {$wpdb->prefix}edd_subscriptions s
			WHERE 1=1
			{$where}
			AND NOT EXISTS (
				SELECT 1
				FROM {$wpdb->prefix}edd_orders o
				WHERE ( o.parent = s.parent_payment_id OR o.id = s.parent_payment_id )
				AND o.status = 'refunded'
			)
			GROUP BY
				YEAR(s.created),
				MONTH(s.created)
			ORDER BY date
		";

		return self::get_cached( 'mrr_by_month_' . md5( $query ), $query );
	}

	/**
	 * Get current MRR breakdown (new, existing, churned).
	 *
	 * @return array Current month MRR breakdown.
	 */
	public static function get_current_mrr_breakdown() {
		$wpdb = self::get_db();

		// New MRR (subscriptions started this month).
		$new_mrr_query = "
			SELECT COALESCE(ROUND(SUM(initial_amount / 12), 2), 0) AS new_mrr
			FROM {$wpdb->prefix}edd_subscriptions
			WHERE DATE_FORMAT(created, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
			AND status != 'pending'
		";

		$new_mrr = self::get_cached( 'new_mrr_current', $new_mrr_query, 'get_var' );

		// Churned MRR (subscriptions cancelled/expired this month).
		$churned_mrr_query = "
			SELECT COALESCE(ROUND(SUM(initial_amount / 12), 2), 0) AS churned_mrr
			FROM {$wpdb->prefix}edd_subscriptions
			WHERE DATE_FORMAT(expiration, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
			AND status IN ('cancelled', 'expired')
		";

		$churned_mrr = self::get_cached( 'churned_mrr_current', $churned_mrr_query, 'get_var' );

		// Existing MRR (active subscriptions from previous months).
		$existing_mrr_query = "
			SELECT COALESCE(ROUND(SUM(initial_amount / 12), 2), 0) AS existing_mrr
			FROM {$wpdb->prefix}edd_subscriptions
			WHERE DATE_FORMAT(created, '%Y-%m') < DATE_FORMAT(CURDATE(), '%Y-%m')
			AND status = 'active'
		";

		$existing_mrr = self::get_cached( 'existing_mrr_current', $existing_mrr_query, 'get_var' );

		return array(
			'new_mrr'      => (float) $new_mrr,
			'existing_mrr' => (float) $existing_mrr,
			'churned_mrr'  => (float) $churned_mrr,
			'net_mrr'      => (float) $new_mrr + (float) $existing_mrr - (float) $churned_mrr,
		);
	}

	// =========================
	// RENEWALS AND CANCELLATIONS
	// =========================

	/**
	 * Get renewal rates by month.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Query results.
	 */
	public static function get_renewal_rates_by_month( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = '';
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND c.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND c.date_created <= %s', $end_date . ' 23:59:59' );
		}

		$query = "
			SELECT
				DATE_FORMAT(c.date_created, '%Y-%m-01') AS date,
				DATE_FORMAT(c.date_created, '%M %Y') AS label,
				ROUND(
					100.0 * COUNT(DISTINCT CASE
						WHEN o.status IN ('complete', 'edd_subscription')
						AND o.type = 'renewal'
						AND o.date_created >= DATE_ADD(c.date_created, INTERVAL 1 YEAR)
						AND o.date_created <= DATE_ADD(c.date_created, INTERVAL 13 MONTH)
						THEN o.customer_id
					END) / NULLIF(COUNT(DISTINCT c.id), 0),
					2
				) AS renewal_rate
			FROM {$wpdb->prefix}edd_customers c
			LEFT JOIN {$wpdb->prefix}edd_orders o ON c.id = o.customer_id
			WHERE c.date_created >= '2017-01-01'
			AND c.date_created <= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
			{$where}
			GROUP BY
				YEAR(c.date_created),
				MONTH(c.date_created)
			ORDER BY date
		";

		return self::get_cached( 'renewal_rates_' . md5( $query ), $query );
	}

	/**
	 * Get upcoming renewals.
	 *
	 * @param int $days Number of days to look ahead.
	 * @return array Query results with count and estimated revenue.
	 */
	public static function get_upcoming_renewals( $days = 30 ) {
		$wpdb = self::get_db();

		$query = $wpdb->prepare(
			"
			SELECT
				COUNT(DISTINCT id) AS count,
				ROUND(SUM(recurring_amount), 2) AS estimated_revenue
			FROM {$wpdb->prefix}edd_subscriptions
			WHERE status = 'active'
			AND expiration >= CURDATE()
			AND expiration <= DATE_ADD(CURDATE(), INTERVAL %d DAY)
			",
			$days
		);

		$result = self::get_cached( "upcoming_renewals_{$days}", $query );

		if ( empty( $result ) || ! isset( $result[0] ) ) {
			return array(
				'count'              => 0,
				'estimated_revenue'  => 0,
			);
		}

		return array(
			'count'             => (int) $result[0]['count'],
			'estimated_revenue' => (float) $result[0]['estimated_revenue'],
		);
	}

	// =========================
	// REFUNDS
	// =========================

	/**
	 * Get refund rates by month.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Query results.
	 */
	public static function get_refund_rates_by_month( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = '';
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND DATE_FORMAT(o.date_created, "%%Y-%%m") >= %s', substr( $start_date, 0, 7 ) );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND DATE_FORMAT(o.date_created, "%%Y-%%m") <= %s', substr( $end_date, 0, 7 ) );
		}

		$query = "
			SELECT
				month_year,
				DATE_FORMAT(STR_TO_DATE(CONCAT(month_year, '-01'), '%Y-%m-%d'), '%M %Y') AS label,
				ROUND(
					100.0 * SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) / COUNT(*),
					2
				) AS refund_rate
			FROM (
				SELECT
					DATE_FORMAT(date_created, '%Y-%m') AS month_year,
					status
				FROM {$wpdb->prefix}edd_orders
				WHERE type = 'sale'
				{$where}
			) AS orders
			GROUP BY month_year
			ORDER BY month_year
		";

		return self::get_cached( 'refund_rates_' . md5( $query ), $query );
	}

	// =========================
	// SOFTWARE LICENSING
	// =========================

	/**
	 * Get top licenses by activation count.
	 *
	 * @param int $limit Number of results to return.
	 * @return array Query results.
	 */
	public static function get_top_licenses( $limit = 20 ) {
		$wpdb = self::get_db();

		// Check if EDD Software Licensing tables exist.
		$table_name = $wpdb->prefix . 'edd_licenses';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
			return array();
		}

		$query = $wpdb->prepare(
			"
			SELECT
				l.id AS license_id,
				l.license_key,
				COUNT(la.site_name) AS activation_count,
				d.post_title AS download_name
			FROM {$wpdb->prefix}edd_licenses l
			LEFT JOIN {$wpdb->prefix}edd_license_activations la ON l.id = la.license_id
			LEFT JOIN {$wpdb->posts} d ON l.download_id = d.ID
			WHERE l.status != 'disabled'
			GROUP BY l.id
			ORDER BY activation_count DESC
			LIMIT %d
			",
			$limit
		);

		return self::get_cached( "top_licenses_{$limit}", $query );
	}

	// =========================
	// CUSTOMER LIFETIME VALUE
	// =========================

	/**
	 * Get customer lifetime values.
	 *
	 * @param string $start_date Start date for filtering customer creation.
	 * @param string $end_date   End date for filtering customer creation.
	 * @param int    $limit      Number of results to return (default: 100).
	 * @return array Customer CLV data with purchase_count, total_spent, avg_order_value, days_active.
	 */
	public static function get_customer_lifetime_values( $start_date = null, $end_date = null, $limit = 100 ) {
		$wpdb = self::get_db();

		$where = '';
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND c.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND c.date_created <= %s', $end_date );
		}

		$query = $wpdb->prepare(
			"
			SELECT
				c.id AS customer_id,
				c.email,
				c.name,
				c.date_created AS signup_date,
				COUNT(DISTINCT o.id) AS purchase_count,
				ROUND(SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END), 2) AS total_spent,
				ROUND(AVG(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE NULL END), 2) AS avg_order_value,
				DATEDIFF(CURDATE(), c.date_created) AS days_active,
				MAX(o.date_created) AS last_purchase_date
			FROM {$wpdb->prefix}edd_customers c
			LEFT JOIN {$wpdb->prefix}edd_orders o ON c.id = o.customer_id
			WHERE c.date_created IS NOT NULL
			{$where}
			GROUP BY c.id
			HAVING purchase_count > 0
			ORDER BY total_spent DESC
			LIMIT %d
			",
			$limit
		);

		return self::get_cached( 'customer_clv_' . md5( $query ), $query );
	}

	/**
	 * Get CLV by customer cohort (monthly signup cohorts).
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array CLV data segmented by signup month cohort.
	 */
	public static function get_clv_by_cohort( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = '';
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND c.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND c.date_created <= %s', $end_date );
		}

		$query = "
			SELECT
				DATE_FORMAT(c.date_created, '%Y-%m-01') AS cohort_date,
				DATE_FORMAT(c.date_created, '%M %Y') AS cohort_label,
				COUNT(DISTINCT c.id) AS customer_count,
				ROUND(AVG(customer_totals.total_spent), 2) AS avg_clv,
				ROUND(AVG(customer_totals.purchase_count), 2) AS avg_purchases,
				ROUND(AVG(customer_totals.days_active), 0) AS avg_days_active
			FROM {$wpdb->prefix}edd_customers c
			INNER JOIN (
				SELECT
					c2.id,
					SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END) AS total_spent,
					COUNT(DISTINCT o.id) AS purchase_count,
					DATEDIFF(CURDATE(), c2.date_created) AS days_active
				FROM {$wpdb->prefix}edd_customers c2
				LEFT JOIN {$wpdb->prefix}edd_orders o ON c2.id = o.customer_id
				GROUP BY c2.id
			) AS customer_totals ON c.id = customer_totals.id
			WHERE c.date_created IS NOT NULL
			{$where}
			GROUP BY
				YEAR(c.date_created),
				MONTH(c.date_created)
			ORDER BY cohort_date
		";

		return self::get_cached( 'clv_by_cohort_' . md5( $query ), $query );
	}

	/**
	 * Get CLV distribution across percentiles.
	 *
	 * @return array CLV distribution by percentile segments.
	 */
	public static function get_clv_distribution() {
		$wpdb = self::get_db();

		$query = "
			SELECT
				CASE
					WHEN total_spent >= percentile_90 THEN 'Top 10%'
					WHEN total_spent >= percentile_75 THEN '75-90%'
					WHEN total_spent >= percentile_50 THEN '50-75%'
					WHEN total_spent >= percentile_25 THEN '25-50%'
					ELSE 'Bottom 25%'
				END AS segment,
				COUNT(*) AS customer_count,
				ROUND(AVG(total_spent), 2) AS avg_clv,
				ROUND(SUM(total_spent), 2) AS total_revenue
			FROM (
				SELECT
					c.id,
					SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END) AS total_spent,
					(SELECT SUM(CASE WHEN o2.status IN ('complete', 'edd_subscription') THEN o2.total ELSE 0 END)
					 FROM {$wpdb->prefix}edd_customers c2
					 LEFT JOIN {$wpdb->prefix}edd_orders o2 ON c2.id = o2.customer_id
					 GROUP BY c2.id
					 ORDER BY SUM(CASE WHEN o2.status IN ('complete', 'edd_subscription') THEN o2.total ELSE 0 END)
					 LIMIT 1 OFFSET (SELECT FLOOR(COUNT(*) * 0.9) FROM {$wpdb->prefix}edd_customers)
					) AS percentile_90,
					(SELECT SUM(CASE WHEN o2.status IN ('complete', 'edd_subscription') THEN o2.total ELSE 0 END)
					 FROM {$wpdb->prefix}edd_customers c2
					 LEFT JOIN {$wpdb->prefix}edd_orders o2 ON c2.id = o2.customer_id
					 GROUP BY c2.id
					 ORDER BY SUM(CASE WHEN o2.status IN ('complete', 'edd_subscription') THEN o2.total ELSE 0 END)
					 LIMIT 1 OFFSET (SELECT FLOOR(COUNT(*) * 0.75) FROM {$wpdb->prefix}edd_customers)
					) AS percentile_75,
					(SELECT SUM(CASE WHEN o2.status IN ('complete', 'edd_subscription') THEN o2.total ELSE 0 END)
					 FROM {$wpdb->prefix}edd_customers c2
					 LEFT JOIN {$wpdb->prefix}edd_orders o2 ON c2.id = o2.customer_id
					 GROUP BY c2.id
					 ORDER BY SUM(CASE WHEN o2.status IN ('complete', 'edd_subscription') THEN o2.total ELSE 0 END)
					 LIMIT 1 OFFSET (SELECT FLOOR(COUNT(*) * 0.5) FROM {$wpdb->prefix}edd_customers)
					) AS percentile_50,
					(SELECT SUM(CASE WHEN o2.status IN ('complete', 'edd_subscription') THEN o2.total ELSE 0 END)
					 FROM {$wpdb->prefix}edd_customers c2
					 LEFT JOIN {$wpdb->prefix}edd_orders o2 ON c2.id = o2.customer_id
					 GROUP BY c2.id
					 ORDER BY SUM(CASE WHEN o2.status IN ('complete', 'edd_subscription') THEN o2.total ELSE 0 END)
					 LIMIT 1 OFFSET (SELECT FLOOR(COUNT(*) * 0.25) FROM {$wpdb->prefix}edd_customers)
					) AS percentile_25
				FROM {$wpdb->prefix}edd_customers c
				LEFT JOIN {$wpdb->prefix}edd_orders o ON c.id = o.customer_id
				GROUP BY c.id
				HAVING total_spent > 0
			) AS customer_clv
			GROUP BY segment
			ORDER BY
				CASE segment
					WHEN 'Top 10%' THEN 1
					WHEN '75-90%' THEN 2
					WHEN '50-75%' THEN 3
					WHEN '25-50%' THEN 4
					WHEN 'Bottom 25%' THEN 5
				END
		";

		return self::get_cached( 'clv_distribution', $query );
	}

	// =========================
	// RFM SEGMENTATION
	// =========================

	/**
	 * Get RFM (Recency, Frequency, Monetary) customer segments.
	 *
	 * @return array Customers segmented by RFM scores.
	 */
	public static function get_rfm_segments() {
		$wpdb = self::get_db();

		$query = "
			SELECT
				c.id AS customer_id,
				c.email,
				c.name,
				DATEDIFF(CURDATE(), MAX(o.date_created)) AS recency_days,
				COUNT(DISTINCT o.id) AS frequency,
				ROUND(SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END), 2) AS monetary,
				CASE
					WHEN DATEDIFF(CURDATE(), MAX(o.date_created)) <= 30 THEN 5
					WHEN DATEDIFF(CURDATE(), MAX(o.date_created)) <= 60 THEN 4
					WHEN DATEDIFF(CURDATE(), MAX(o.date_created)) <= 90 THEN 3
					WHEN DATEDIFF(CURDATE(), MAX(o.date_created)) <= 180 THEN 2
					ELSE 1
				END AS recency_score,
				CASE
					WHEN COUNT(DISTINCT o.id) >= 10 THEN 5
					WHEN COUNT(DISTINCT o.id) >= 7 THEN 4
					WHEN COUNT(DISTINCT o.id) >= 4 THEN 3
					WHEN COUNT(DISTINCT o.id) >= 2 THEN 2
					ELSE 1
				END AS frequency_score,
				CASE
					WHEN SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END) >= 5000 THEN 5
					WHEN SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END) >= 2000 THEN 4
					WHEN SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END) >= 1000 THEN 3
					WHEN SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END) >= 500 THEN 2
					ELSE 1
				END AS monetary_score
			FROM {$wpdb->prefix}edd_customers c
			INNER JOIN {$wpdb->prefix}edd_orders o ON c.id = o.customer_id
			WHERE o.status IN ('complete', 'edd_subscription')
			GROUP BY c.id
			HAVING frequency > 0
			ORDER BY recency_score DESC, frequency_score DESC, monetary_score DESC
		";

		return self::get_cached( 'rfm_segments', $query );
	}

	/**
	 * Get segment performance metrics.
	 *
	 * @return array Revenue and customer count by RFM segment.
	 */
	public static function get_segment_performance() {
		$wpdb = self::get_db();

		$query = "
			SELECT
				CASE
					WHEN (recency_score + frequency_score + monetary_score) >= 13 THEN 'Champions'
					WHEN (recency_score + frequency_score + monetary_score) >= 10 AND recency_score >= 4 THEN 'Loyal Customers'
					WHEN monetary_score >= 4 AND (recency_score + frequency_score) >= 6 THEN 'Big Spenders'
					WHEN recency_score >= 4 AND frequency_score <= 2 THEN 'Recent Customers'
					WHEN frequency_score >= 4 AND recency_score <= 2 THEN 'At Risk'
					WHEN recency_score <= 2 AND frequency_score <= 2 THEN 'Lost'
					ELSE 'Potential'
				END AS segment,
				COUNT(*) AS customer_count,
				ROUND(SUM(monetary), 2) AS total_revenue,
				ROUND(AVG(monetary), 2) AS avg_revenue,
				ROUND(AVG(frequency), 2) AS avg_purchases,
				ROUND(AVG(recency_days), 0) AS avg_days_since_purchase
			FROM (
				SELECT
					c.id,
					DATEDIFF(CURDATE(), MAX(o.date_created)) AS recency_days,
					COUNT(DISTINCT o.id) AS frequency,
					SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END) AS monetary,
					CASE
						WHEN DATEDIFF(CURDATE(), MAX(o.date_created)) <= 30 THEN 5
						WHEN DATEDIFF(CURDATE(), MAX(o.date_created)) <= 60 THEN 4
						WHEN DATEDIFF(CURDATE(), MAX(o.date_created)) <= 90 THEN 3
						WHEN DATEDIFF(CURDATE(), MAX(o.date_created)) <= 180 THEN 2
						ELSE 1
					END AS recency_score,
					CASE
						WHEN COUNT(DISTINCT o.id) >= 10 THEN 5
						WHEN COUNT(DISTINCT o.id) >= 7 THEN 4
						WHEN COUNT(DISTINCT o.id) >= 4 THEN 3
						WHEN COUNT(DISTINCT o.id) >= 2 THEN 2
						ELSE 1
					END AS frequency_score,
					CASE
						WHEN SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END) >= 5000 THEN 5
						WHEN SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END) >= 2000 THEN 4
						WHEN SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END) >= 1000 THEN 3
						WHEN SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END) >= 500 THEN 2
						ELSE 1
					END AS monetary_score
				FROM {$wpdb->prefix}edd_customers c
				INNER JOIN {$wpdb->prefix}edd_orders o ON c.id = o.customer_id
				WHERE o.status IN ('complete', 'edd_subscription')
				GROUP BY c.id
			) AS rfm_data
			GROUP BY segment
			ORDER BY total_revenue DESC
		";

		return self::get_cached( 'segment_performance', $query );
	}

	// =========================
	// CUSTOMER HEALTH & ENGAGEMENT
	// =========================

	/**
	 * Get customer health scores.
	 *
	 * @param int $limit Number of results to return (default: 100).
	 * @return array Customers with health scores based on activity, spending, engagement.
	 */
	public static function get_customer_health_scores( $limit = 100 ) {
		$wpdb = self::get_db();

		$query = $wpdb->prepare(
			"
			SELECT
				c.id AS customer_id,
				c.email,
				c.name,
				DATEDIFF(CURDATE(), MAX(o.date_created)) AS days_since_purchase,
				COUNT(DISTINCT o.id) AS total_purchases,
				ROUND(SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END), 2) AS total_spent,
				(SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions s WHERE s.customer_id = c.id AND s.status = 'active') AS active_subscriptions,
				CASE
					WHEN DATEDIFF(CURDATE(), MAX(o.date_created)) <= 30
						AND (SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions s WHERE s.customer_id = c.id AND s.status = 'active') > 0
						THEN 'Excellent'
					WHEN DATEDIFF(CURDATE(), MAX(o.date_created)) <= 60
						AND COUNT(DISTINCT o.id) >= 2
						THEN 'Good'
					WHEN DATEDIFF(CURDATE(), MAX(o.date_created)) <= 90
						THEN 'Fair'
					WHEN DATEDIFF(CURDATE(), MAX(o.date_created)) <= 180
						THEN 'At Risk'
					ELSE 'Poor'
				END AS health_status,
				CASE
					WHEN DATEDIFF(CURDATE(), MAX(o.date_created)) <= 30
						AND (SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions s WHERE s.customer_id = c.id AND s.status = 'active') > 0
						THEN 95
					WHEN DATEDIFF(CURDATE(), MAX(o.date_created)) <= 60
						AND COUNT(DISTINCT o.id) >= 2
						THEN 75
					WHEN DATEDIFF(CURDATE(), MAX(o.date_created)) <= 90
						THEN 50
					WHEN DATEDIFF(CURDATE(), MAX(o.date_created)) <= 180
						THEN 25
					ELSE 10
				END AS health_score
			FROM {$wpdb->prefix}edd_customers c
			INNER JOIN {$wpdb->prefix}edd_orders o ON c.id = o.customer_id
			WHERE o.status IN ('complete', 'edd_subscription')
			GROUP BY c.id
			ORDER BY health_score DESC, total_spent DESC
			LIMIT %d
			",
			$limit
		);

		return self::get_cached( "customer_health_{$limit}", $query );
	}

	/**
	 * Get at-risk customers (likely to churn).
	 *
	 * @param int $limit Number of results to return (default: 50).
	 * @return array Customers identified as at risk of churning.
	 */
	public static function get_at_risk_customers( $limit = 50 ) {
		$wpdb = self::get_db();

		$query = $wpdb->prepare(
			"
			SELECT
				c.id AS customer_id,
				c.email,
				c.name,
				DATEDIFF(CURDATE(), MAX(o.date_created)) AS days_since_purchase,
				COUNT(DISTINCT o.id) AS total_purchases,
				ROUND(SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END), 2) AS total_spent,
				MAX(o.date_created) AS last_purchase_date,
				(SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions s
				 WHERE s.customer_id = c.id
				 AND s.status IN ('cancelled', 'expired')
				 AND s.expiration >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
				) AS recent_cancellations,
				CASE
					WHEN (SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions s
						  WHERE s.customer_id = c.id AND s.status IN ('cancelled', 'expired')
						  AND s.expiration >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)) > 0
						THEN 'High'
					WHEN DATEDIFF(CURDATE(), MAX(o.date_created)) > 120
						AND COUNT(DISTINCT o.id) >= 3
						THEN 'Medium'
					ELSE 'Low'
				END AS churn_risk
			FROM {$wpdb->prefix}edd_customers c
			INNER JOIN {$wpdb->prefix}edd_orders o ON c.id = o.customer_id
			WHERE o.status IN ('complete', 'edd_subscription')
			GROUP BY c.id
			HAVING
				(days_since_purchase > 90 AND total_purchases >= 2)
				OR recent_cancellations > 0
			ORDER BY
				CASE churn_risk
					WHEN 'High' THEN 1
					WHEN 'Medium' THEN 2
					WHEN 'Low' THEN 3
				END,
				total_spent DESC
			LIMIT %d
			",
			$limit
		);

		return self::get_cached( "at_risk_customers_{$limit}", $query );
	}

	/**
	 * Get customer engagement metrics.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Engagement metrics by month.
	 */
	public static function get_customer_engagement_metrics( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = '';
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created <= %s', $end_date );
		}

		$query = "
			SELECT
				DATE_FORMAT(o.date_created, '%Y-%m-01') AS date,
				DATE_FORMAT(o.date_created, '%M %Y') AS label,
				COUNT(DISTINCT o.customer_id) AS active_customers,
				COUNT(DISTINCT o.id) AS total_orders,
				ROUND(AVG(o.total), 2) AS avg_order_value,
				ROUND(COUNT(DISTINCT o.id) / COUNT(DISTINCT o.customer_id), 2) AS orders_per_customer
			FROM {$wpdb->prefix}edd_orders o
			WHERE o.status IN ('complete', 'edd_subscription')
			{$where}
			GROUP BY
				YEAR(o.date_created),
				MONTH(o.date_created)
			ORDER BY date
		";

		return self::get_cached( 'engagement_metrics_' . md5( $query ), $query );
	}

	// =========================
	// CUSTOMER JOURNEY
	// =========================

	/**
	 * Get customer acquisition channels.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Customer counts by acquisition channel.
	 */
	public static function get_customer_acquisition_channels( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = '';
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND c.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND c.date_created <= %s', $end_date );
		}

		// Note: This queries customer notes for UTM parameters or referrer data
		// Adjust based on how your EDD installation tracks acquisition sources
		$query = "
			SELECT
				CASE
					WHEN cn.content LIKE '%utm_source=google%' THEN 'Google'
					WHEN cn.content LIKE '%utm_source=facebook%' THEN 'Facebook'
					WHEN cn.content LIKE '%utm_source=twitter%' THEN 'Twitter'
					WHEN cn.content LIKE '%utm_source=email%' THEN 'Email'
					WHEN cn.content LIKE '%utm_source=affiliate%' THEN 'Affiliate'
					WHEN cn.content LIKE '%referrer%' THEN 'Referral'
					ELSE 'Direct/Unknown'
				END AS channel,
				COUNT(DISTINCT c.id) AS customer_count,
				ROUND(SUM(customer_revenue.total_spent), 2) AS total_revenue,
				ROUND(AVG(customer_revenue.total_spent), 2) AS avg_revenue_per_customer
			FROM {$wpdb->prefix}edd_customers c
			LEFT JOIN {$wpdb->prefix}edd_customermeta cm ON c.id = cm.customer_id
			LEFT JOIN {$wpdb->prefix}edd_customer_email_addresses cea ON c.id = cea.customer_id
			LEFT JOIN {$wpdb->comments} cn ON cn.comment_author_email = c.email
				AND cn.comment_type = 'edd_payment_note'
			LEFT JOIN (
				SELECT
					customer_id,
					SUM(CASE WHEN status IN ('complete', 'edd_subscription') THEN total ELSE 0 END) AS total_spent
				FROM {$wpdb->prefix}edd_orders
				GROUP BY customer_id
			) AS customer_revenue ON c.id = customer_revenue.customer_id
			WHERE c.date_created IS NOT NULL
			{$where}
			GROUP BY channel
			ORDER BY customer_count DESC
		";

		return self::get_cached( 'acquisition_channels_' . md5( $query ), $query );
	}

	/**
	 * Get time to first purchase distribution.
	 *
	 * @param string $start_date Start date for customer creation filter.
	 * @param string $end_date   End date for customer creation filter.
	 * @return array Distribution of days from signup to first purchase.
	 */
	public static function get_time_to_first_purchase( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = '';
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND c.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND c.date_created <= %s', $end_date );
		}

		$query = "
			SELECT
				CASE
					WHEN days_to_first_purchase = 0 THEN 'Same Day'
					WHEN days_to_first_purchase <= 1 THEN '1 Day'
					WHEN days_to_first_purchase <= 7 THEN '2-7 Days'
					WHEN days_to_first_purchase <= 30 THEN '8-30 Days'
					WHEN days_to_first_purchase <= 90 THEN '31-90 Days'
					ELSE '90+ Days'
				END AS time_segment,
				COUNT(*) AS customer_count,
				ROUND(AVG(days_to_first_purchase), 1) AS avg_days
			FROM (
				SELECT
					c.id,
					DATEDIFF(MIN(o.date_created), c.date_created) AS days_to_first_purchase
				FROM {$wpdb->prefix}edd_customers c
				INNER JOIN {$wpdb->prefix}edd_orders o ON c.id = o.customer_id
				WHERE o.status IN ('complete', 'edd_subscription')
				AND c.date_created IS NOT NULL
				{$where}
				GROUP BY c.id
			) AS customer_conversion
			GROUP BY time_segment
			ORDER BY
				CASE time_segment
					WHEN 'Same Day' THEN 1
					WHEN '1 Day' THEN 2
					WHEN '2-7 Days' THEN 3
					WHEN '8-30 Days' THEN 4
					WHEN '31-90 Days' THEN 5
					WHEN '90+ Days' THEN 6
				END
		";

		return self::get_cached( 'time_to_first_purchase_' . md5( $query ), $query );
	}

	/**
	 * Get customer activation funnel.
	 *
	 * @param string $start_date Start date for customer creation filter.
	 * @param string $end_date   End date for customer creation filter.
	 * @return array Funnel stages from signup to subscriber.
	 */
	public static function get_customer_activation_funnel( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = '';
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND c.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND c.date_created <= %s', $end_date );
		}

		$query = "
			SELECT
				COUNT(DISTINCT c.id) AS total_signups,
				COUNT(DISTINCT CASE WHEN o.id IS NOT NULL THEN c.id END) AS made_purchase,
				COUNT(DISTINCT CASE WHEN purchase_counts.purchase_count >= 2 THEN c.id END) AS repeat_purchase,
				COUNT(DISTINCT CASE WHEN s.id IS NOT NULL THEN c.id END) AS became_subscriber,
				ROUND(100.0 * COUNT(DISTINCT CASE WHEN o.id IS NOT NULL THEN c.id END) / COUNT(DISTINCT c.id), 2) AS purchase_rate,
				ROUND(100.0 * COUNT(DISTINCT CASE WHEN purchase_counts.purchase_count >= 2 THEN c.id END) / NULLIF(COUNT(DISTINCT CASE WHEN o.id IS NOT NULL THEN c.id END), 0), 2) AS repeat_rate,
				ROUND(100.0 * COUNT(DISTINCT CASE WHEN s.id IS NOT NULL THEN c.id END) / NULLIF(COUNT(DISTINCT CASE WHEN o.id IS NOT NULL THEN c.id END), 0), 2) AS subscription_rate
			FROM {$wpdb->prefix}edd_customers c
			LEFT JOIN {$wpdb->prefix}edd_orders o ON c.id = o.customer_id
				AND o.status IN ('complete', 'edd_subscription')
			LEFT JOIN {$wpdb->prefix}edd_subscriptions s ON c.id = s.customer_id
				AND s.status != 'pending'
			LEFT JOIN (
				SELECT
					customer_id,
					COUNT(DISTINCT id) AS purchase_count
				FROM {$wpdb->prefix}edd_orders
				WHERE status IN ('complete', 'edd_subscription')
				GROUP BY customer_id
			) AS purchase_counts ON c.id = purchase_counts.customer_id
			WHERE c.date_created IS NOT NULL
			{$where}
		";

		$result = self::get_cached( 'activation_funnel_' . md5( $query ), $query );

		if ( empty( $result ) || ! isset( $result[0] ) ) {
			return array(
				'total_signups'      => 0,
				'made_purchase'      => 0,
				'repeat_purchase'    => 0,
				'became_subscriber'  => 0,
				'purchase_rate'      => 0,
				'repeat_rate'        => 0,
				'subscription_rate'  => 0,
			);
		}

		return $result[0];
	}

	// =========================
	// GEOGRAPHIC ANALYSIS
	// =========================

	/**
	 * Get customers by country.
	 *
	 * @param string $start_date Start date for customer creation filter.
	 * @param string $end_date   End date for customer creation filter.
	 * @param int    $limit      Number of results to return (default: 20).
	 * @return array Customer distribution by country.
	 */
	public static function get_customers_by_country( $start_date = null, $end_date = null, $limit = 20 ) {
		$wpdb = self::get_db();

		$where = '';
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND c.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND c.date_created <= %s', $end_date );
		}

		// Note: Adjust based on how EDD stores customer country (could be in order_addresses or customer meta)
		$query = $wpdb->prepare(
			"
			SELECT
				COALESCE(oa.country, 'Unknown') AS country,
				COUNT(DISTINCT c.id) AS customer_count,
				COUNT(DISTINCT o.id) AS total_orders,
				ROUND(SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END), 2) AS total_revenue
			FROM {$wpdb->prefix}edd_customers c
			LEFT JOIN {$wpdb->prefix}edd_orders o ON c.id = o.customer_id
			LEFT JOIN {$wpdb->prefix}edd_order_addresses oa ON o.id = oa.order_id
				AND oa.type = 'billing'
			WHERE c.date_created IS NOT NULL
			{$where}
			GROUP BY country
			ORDER BY customer_count DESC
			LIMIT %d
			",
			$limit
		);

		return self::get_cached( 'customers_by_country_' . md5( $query ), $query );
	}

	/**
	 * Get revenue by region.
	 *
	 * @param string $start_date Start date for order filter.
	 * @param string $end_date   End date for order filter.
	 * @return array Revenue breakdown by geographic region.
	 */
	public static function get_revenue_by_region( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = " AND o.status IN ('complete', 'edd_subscription')";
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created <= %s', $end_date );
		}

		$query = "
			SELECT
				CASE
					WHEN oa.country IN ('US', 'CA', 'MX') THEN 'North America'
					WHEN oa.country IN ('GB', 'DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'CH', 'SE', 'NO', 'DK', 'FI', 'IE', 'PT', 'PL', 'CZ', 'GR') THEN 'Europe'
					WHEN oa.country IN ('AU', 'NZ', 'SG', 'MY', 'TH', 'ID', 'PH', 'VN') THEN 'Asia Pacific'
					WHEN oa.country IN ('BR', 'AR', 'CL', 'CO', 'PE', 'VE', 'EC', 'UY') THEN 'Latin America'
					WHEN oa.country IN ('IN', 'PK', 'BD', 'LK') THEN 'South Asia'
					WHEN oa.country IN ('CN', 'JP', 'KR', 'TW', 'HK') THEN 'East Asia'
					WHEN oa.country IN ('ZA', 'EG', 'KE', 'NG', 'GH', 'TZ', 'UG') THEN 'Africa'
					WHEN oa.country IN ('AE', 'SA', 'IL', 'TR', 'IQ', 'IR', 'JO', 'LB', 'KW', 'QA') THEN 'Middle East'
					ELSE 'Other'
				END AS region,
				COUNT(DISTINCT o.id) AS order_count,
				COUNT(DISTINCT o.customer_id) AS customer_count,
				ROUND(SUM(o.total), 2) AS total_revenue,
				ROUND(AVG(o.total), 2) AS avg_order_value
			FROM {$wpdb->prefix}edd_orders o
			LEFT JOIN {$wpdb->prefix}edd_order_addresses oa ON o.id = oa.order_id
				AND oa.type = 'billing'
			WHERE 1=1
			{$where}
			GROUP BY region
			ORDER BY total_revenue DESC
		";

		return self::get_cached( 'revenue_by_region_' . md5( $query ), $query );
	}

	// =========================
	// PRODUCT PERFORMANCE ANALYTICS
	// =========================

	/**
	 * Get top products by revenue.
	 *
	 * @param string $start_date Start date for order filter.
	 * @param string $end_date   End date for order filter.
	 * @param int    $limit      Number of results to return (default: 20).
	 * @return array Top products with revenue, units sold, and AOV.
	 */
	public static function get_top_products( $start_date = null, $end_date = null, $limit = 20 ) {
		$wpdb = self::get_db();

		$where = " AND o.status IN ('complete', 'edd_subscription')";
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created <= %s', $end_date );
		}

		$query = $wpdb->prepare(
			"
			SELECT
				oi.product_id,
				p.post_title AS product_name,
				COUNT(DISTINCT o.id) AS order_count,
				SUM(oi.quantity) AS units_sold,
				ROUND(SUM(oi.total), 2) AS total_revenue,
				ROUND(AVG(oi.total / oi.quantity), 2) AS avg_price,
				ROUND(SUM(oi.total) / COUNT(DISTINCT o.id), 2) AS aov
			FROM {$wpdb->prefix}edd_order_items oi
			INNER JOIN {$wpdb->prefix}edd_orders o ON oi.order_id = o.id
			LEFT JOIN {$wpdb->posts} p ON oi.product_id = p.ID
			WHERE 1=1
			{$where}
			GROUP BY oi.product_id
			ORDER BY total_revenue DESC
			LIMIT %d
			",
			$limit
		);

		return self::get_cached( 'top_products_' . md5( $query ), $query );
	}

	/**
	 * Get product growth trends by month.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param int    $limit      Number of top products to analyze (default: 10).
	 * @return array Monthly sales trends per product.
	 */
	public static function get_product_growth_trends( $start_date = null, $end_date = null, $limit = 10 ) {
		$wpdb = self::get_db();

		$where = " AND o.status IN ('complete', 'edd_subscription')";
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created <= %s', $end_date );
		}

		$query = $wpdb->prepare(
			"
			SELECT
				DATE_FORMAT(o.date_created, '%%Y-%%m-01') AS date,
				DATE_FORMAT(o.date_created, '%%M %%Y') AS label,
				oi.product_id,
				p.post_title AS product_name,
				SUM(oi.quantity) AS units_sold,
				ROUND(SUM(oi.total), 2) AS revenue,
				COUNT(DISTINCT o.id) AS order_count
			FROM {$wpdb->prefix}edd_order_items oi
			INNER JOIN {$wpdb->prefix}edd_orders o ON oi.order_id = o.id
			LEFT JOIN {$wpdb->posts} p ON oi.product_id = p.ID
			WHERE oi.product_id IN (
				SELECT product_id
				FROM {$wpdb->prefix}edd_order_items oi2
				INNER JOIN {$wpdb->prefix}edd_orders o2 ON oi2.order_id = o2.id
				WHERE o2.status IN ('complete', 'edd_subscription')
				GROUP BY product_id
				ORDER BY SUM(oi2.total) DESC
				LIMIT %d
			)
			{$where}
			GROUP BY
				YEAR(o.date_created),
				MONTH(o.date_created),
				oi.product_id
			ORDER BY date, revenue DESC
			",
			$limit
		);

		return self::get_cached( 'product_growth_trends_' . md5( $query ), $query );
	}

	/**
	 * Get product performance matrix (quadrant analysis).
	 *
	 * @param string $start_date Start date for comparison period.
	 * @param string $end_date   End date for comparison period.
	 * @return array Products classified by revenue and growth quadrants.
	 */
	public static function get_product_performance_matrix( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = " AND o.status IN ('complete', 'edd_subscription')";
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created <= %s', $end_date );
		}

		$query = "
			SELECT
				product_id,
				product_name,
				current_revenue,
				previous_revenue,
				growth_rate,
				units_sold,
				CASE
					WHEN current_revenue >= avg_revenue AND growth_rate >= avg_growth THEN 'Star'
					WHEN current_revenue >= avg_revenue AND growth_rate < avg_growth THEN 'Cash Cow'
					WHEN current_revenue < avg_revenue AND growth_rate >= avg_growth THEN 'Question Mark'
					ELSE 'Dog'
				END AS quadrant
			FROM (
				SELECT
					oi.product_id,
					p.post_title AS product_name,
					ROUND(SUM(CASE
						WHEN o.date_created >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
						THEN oi.total ELSE 0
					END), 2) AS current_revenue,
					ROUND(SUM(CASE
						WHEN o.date_created >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
						AND o.date_created < DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
						THEN oi.total ELSE 0
					END), 2) AS previous_revenue,
					ROUND(
						100.0 * (
							SUM(CASE WHEN o.date_created >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH) THEN oi.total ELSE 0 END) -
							SUM(CASE WHEN o.date_created >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND o.date_created < DATE_SUB(CURDATE(), INTERVAL 3 MONTH) THEN oi.total ELSE 0 END)
						) / NULLIF(SUM(CASE WHEN o.date_created >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND o.date_created < DATE_SUB(CURDATE(), INTERVAL 3 MONTH) THEN oi.total ELSE 0 END), 0),
						2
					) AS growth_rate,
					SUM(oi.quantity) AS units_sold,
					(SELECT AVG(product_revenue) FROM (
						SELECT SUM(oi2.total) AS product_revenue
						FROM {$wpdb->prefix}edd_order_items oi2
						INNER JOIN {$wpdb->prefix}edd_orders o2 ON oi2.order_id = o2.id
						WHERE o2.status IN ('complete', 'edd_subscription')
						AND o2.date_created >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
						GROUP BY oi2.product_id
					) AS revenue_avg) AS avg_revenue,
					(SELECT AVG(product_growth) FROM (
						SELECT
							100.0 * (
								SUM(CASE WHEN o2.date_created >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH) THEN oi2.total ELSE 0 END) -
								SUM(CASE WHEN o2.date_created >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND o2.date_created < DATE_SUB(CURDATE(), INTERVAL 3 MONTH) THEN oi2.total ELSE 0 END)
							) / NULLIF(SUM(CASE WHEN o2.date_created >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND o2.date_created < DATE_SUB(CURDATE(), INTERVAL 3 MONTH) THEN oi2.total ELSE 0 END), 0) AS product_growth
						FROM {$wpdb->prefix}edd_order_items oi2
						INNER JOIN {$wpdb->prefix}edd_orders o2 ON oi2.order_id = o2.id
						WHERE o2.status IN ('complete', 'edd_subscription')
						GROUP BY oi2.product_id
					) AS growth_avg) AS avg_growth
				FROM {$wpdb->prefix}edd_order_items oi
				INNER JOIN {$wpdb->prefix}edd_orders o ON oi.order_id = o.id
				LEFT JOIN {$wpdb->posts} p ON oi.product_id = p.ID
				WHERE 1=1
				{$where}
				GROUP BY oi.product_id
				HAVING current_revenue > 0
			) AS product_metrics
			ORDER BY current_revenue DESC
		";

		return self::get_cached( 'product_performance_matrix_' . md5( $query ), $query );
	}

	/**
	 * Get bundle performance comparison.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Performance comparison of bundles vs individual products.
	 */
	public static function get_bundle_performance( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = " AND o.status IN ('complete', 'edd_subscription')";
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created <= %s', $end_date );
		}

		// Note: Adjust based on how EDD Bundles are identified (parent_id or post_type)
		$query = "
			SELECT
				CASE
					WHEN p.post_parent > 0 OR pm.meta_value IS NOT NULL THEN 'Bundle'
					ELSE 'Individual'
				END AS product_type,
				COUNT(DISTINCT oi.product_id) AS product_count,
				COUNT(DISTINCT o.id) AS order_count,
				SUM(oi.quantity) AS units_sold,
				ROUND(SUM(oi.total), 2) AS total_revenue,
				ROUND(AVG(oi.total), 2) AS avg_revenue_per_sale,
				ROUND(SUM(oi.total) / COUNT(DISTINCT o.customer_id), 2) AS revenue_per_customer
			FROM {$wpdb->prefix}edd_order_items oi
			INNER JOIN {$wpdb->prefix}edd_orders o ON oi.order_id = o.id
			LEFT JOIN {$wpdb->posts} p ON oi.product_id = p.ID
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				AND pm.meta_key = '_edd_bundled_products'
			WHERE 1=1
			{$where}
			GROUP BY product_type
			ORDER BY total_revenue DESC
		";

		return self::get_cached( 'bundle_performance_' . md5( $query ), $query );
	}

	/**
	 * Get product lifecycle stages.
	 *
	 * @return array Products classified by lifecycle stage (growth/maturity/decline).
	 */
	public static function get_product_lifecycle_stages() {
		$wpdb = self::get_db();

		$query = "
			SELECT
				product_id,
				product_name,
				months_active,
				revenue_trend,
				current_revenue,
				peak_revenue,
				CASE
					WHEN months_active <= 3 AND revenue_trend > 20 THEN 'Introduction'
					WHEN revenue_trend > 10 THEN 'Growth'
					WHEN revenue_trend >= -10 AND revenue_trend <= 10 THEN 'Maturity'
					WHEN revenue_trend < -10 AND revenue_trend >= -30 THEN 'Decline'
					ELSE 'End of Life'
				END AS lifecycle_stage,
				last_sale_date
			FROM (
				SELECT
					oi.product_id,
					p.post_title AS product_name,
					TIMESTAMPDIFF(MONTH, MIN(o.date_created), CURDATE()) AS months_active,
					ROUND(
						100.0 * (
							SUM(CASE WHEN o.date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) THEN oi.total ELSE 0 END) -
							SUM(CASE WHEN o.date_created >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND o.date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH) THEN oi.total ELSE 0 END)
						) / NULLIF(SUM(CASE WHEN o.date_created >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND o.date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH) THEN oi.total ELSE 0 END), 0),
						2
					) AS revenue_trend,
					ROUND(SUM(CASE WHEN o.date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) THEN oi.total ELSE 0 END), 2) AS current_revenue,
					ROUND(MAX(monthly_revenue.revenue), 2) AS peak_revenue,
					MAX(o.date_created) AS last_sale_date
				FROM {$wpdb->prefix}edd_order_items oi
				INNER JOIN {$wpdb->prefix}edd_orders o ON oi.order_id = o.id
				LEFT JOIN {$wpdb->posts} p ON oi.product_id = p.ID
				LEFT JOIN (
					SELECT
						oi2.product_id,
						DATE_FORMAT(o2.date_created, '%Y-%m') AS month,
						SUM(oi2.total) AS revenue
					FROM {$wpdb->prefix}edd_order_items oi2
					INNER JOIN {$wpdb->prefix}edd_orders o2 ON oi2.order_id = o2.id
					WHERE o2.status IN ('complete', 'edd_subscription')
					GROUP BY oi2.product_id, DATE_FORMAT(o2.date_created, '%Y-%m')
				) AS monthly_revenue ON oi.product_id = monthly_revenue.product_id
				WHERE o.status IN ('complete', 'edd_subscription')
				GROUP BY oi.product_id
				HAVING months_active > 0
			) AS product_metrics
			ORDER BY
				CASE lifecycle_stage
					WHEN 'Introduction' THEN 1
					WHEN 'Growth' THEN 2
					WHEN 'Maturity' THEN 3
					WHEN 'Decline' THEN 4
					WHEN 'End of Life' THEN 5
				END,
				current_revenue DESC
		";

		return self::get_cached( 'product_lifecycle_stages', $query );
	}

	// =========================
	// REVENUE ANALYTICS
	// =========================

	/**
	 * Get detailed revenue breakdown by type.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Revenue segmented by new/recurring/upgrade/downgrade.
	 */
	public static function get_revenue_breakdown( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = '';
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created <= %s', $end_date );
		}

		$query = "
			SELECT
				DATE_FORMAT(o.date_created, '%Y-%m-01') AS date,
				DATE_FORMAT(o.date_created, '%M %Y') AS label,
				ROUND(SUM(CASE WHEN o.type = 'sale' AND is_first_purchase = 1 THEN o.total ELSE 0 END), 2) AS new_customer_revenue,
				ROUND(SUM(CASE WHEN o.type = 'sale' AND is_first_purchase = 0 THEN o.total ELSE 0 END), 2) AS existing_customer_revenue,
				ROUND(SUM(CASE WHEN o.type = 'renewal' THEN o.total ELSE 0 END), 2) AS renewal_revenue,
				ROUND(SUM(CASE WHEN o.type = 'sale' AND upgrade_indicator = 1 THEN o.total ELSE 0 END), 2) AS upgrade_revenue,
				ROUND(SUM(o.total), 2) AS total_revenue
			FROM (
				SELECT
					o.*,
					CASE
						WHEN (
							SELECT COUNT(*)
							FROM {$wpdb->prefix}edd_orders o2
							WHERE o2.customer_id = o.customer_id
							AND o2.status IN ('complete', 'edd_subscription')
							AND o2.date_created < o.date_created
						) = 0 THEN 1
						ELSE 0
					END AS is_first_purchase,
					CASE
						WHEN o.total > (
							SELECT AVG(o3.total)
							FROM {$wpdb->prefix}edd_orders o3
							WHERE o3.customer_id = o.customer_id
							AND o3.status IN ('complete', 'edd_subscription')
							AND o3.date_created < o.date_created
						) THEN 1
						ELSE 0
					END AS upgrade_indicator
				FROM {$wpdb->prefix}edd_orders o
				WHERE o.status IN ('complete', 'edd_subscription')
				{$where}
			) AS o
			GROUP BY
				YEAR(o.date_created),
				MONTH(o.date_created)
			ORDER BY date
		";

		return self::get_cached( 'revenue_breakdown_' . md5( $query ), $query );
	}

	/**
	 * Get revenue concentration (80/20 analysis).
	 *
	 * @return array Distribution showing revenue concentration by customer/product segments.
	 */
	public static function get_revenue_concentration() {
		$wpdb = self::get_db();

		$query = "
			SELECT
				'Top 10% Customers' AS segment,
				COUNT(*) AS count,
				ROUND(SUM(total_spent), 2) AS revenue,
				ROUND(100.0 * SUM(total_spent) / (SELECT SUM(total) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription')), 2) AS revenue_percentage
			FROM (
				SELECT
					c.id,
					SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END) AS total_spent
				FROM {$wpdb->prefix}edd_customers c
				INNER JOIN {$wpdb->prefix}edd_orders o ON c.id = o.customer_id
				GROUP BY c.id
				ORDER BY total_spent DESC
				LIMIT (SELECT FLOOR(COUNT(DISTINCT id) * 0.1) FROM {$wpdb->prefix}edd_customers)
			) AS top_customers

			UNION ALL

			SELECT
				'Top 20% Customers' AS segment,
				COUNT(*) AS count,
				ROUND(SUM(total_spent), 2) AS revenue,
				ROUND(100.0 * SUM(total_spent) / (SELECT SUM(total) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription')), 2) AS revenue_percentage
			FROM (
				SELECT
					c.id,
					SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END) AS total_spent
				FROM {$wpdb->prefix}edd_customers c
				INNER JOIN {$wpdb->prefix}edd_orders o ON c.id = o.customer_id
				GROUP BY c.id
				ORDER BY total_spent DESC
				LIMIT (SELECT FLOOR(COUNT(DISTINCT id) * 0.2) FROM {$wpdb->prefix}edd_customers)
			) AS top_20_customers

			UNION ALL

			SELECT
				'Top 10% Products' AS segment,
				COUNT(*) AS count,
				ROUND(SUM(product_revenue), 2) AS revenue,
				ROUND(100.0 * SUM(product_revenue) / (SELECT SUM(total) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription')), 2) AS revenue_percentage
			FROM (
				SELECT
					oi.product_id,
					SUM(oi.total) AS product_revenue
				FROM {$wpdb->prefix}edd_order_items oi
				INNER JOIN {$wpdb->prefix}edd_orders o ON oi.order_id = o.id
				WHERE o.status IN ('complete', 'edd_subscription')
				GROUP BY oi.product_id
				ORDER BY product_revenue DESC
				LIMIT (SELECT FLOOR(COUNT(DISTINCT product_id) * 0.1) FROM {$wpdb->prefix}edd_order_items)
			) AS top_products
		";

		return self::get_cached( 'revenue_concentration', $query );
	}

	/**
	 * Get payment method performance.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Success rates and revenue by payment gateway.
	 */
	public static function get_payment_method_performance( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = '';
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created <= %s', $end_date );
		}

		$query = "
			SELECT
				COALESCE(o.gateway, 'Unknown') AS payment_method,
				COUNT(*) AS total_attempts,
				COUNT(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN 1 END) AS successful_payments,
				COUNT(CASE WHEN o.status = 'failed' THEN 1 END) AS failed_payments,
				ROUND(100.0 * COUNT(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN 1 END) / COUNT(*), 2) AS success_rate,
				ROUND(SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END), 2) AS total_revenue,
				ROUND(AVG(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE NULL END), 2) AS avg_transaction_value
			FROM {$wpdb->prefix}edd_orders o
			WHERE 1=1
			{$where}
			GROUP BY payment_method
			ORDER BY total_revenue DESC
		";

		return self::get_cached( 'payment_method_performance_' . md5( $query ), $query );
	}

	/**
	 * Get failed payment recovery rates.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Recovery metrics for failed payments.
	 */
	public static function get_failed_payment_recovery( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = '';
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND failed_orders.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND failed_orders.date_created <= %s', $end_date );
		}

		$query = "
			SELECT
				DATE_FORMAT(failed_orders.date_created, '%Y-%m-01') AS date,
				DATE_FORMAT(failed_orders.date_created, '%M %Y') AS label,
				COUNT(DISTINCT failed_orders.id) AS failed_count,
				COUNT(DISTINCT recovery.id) AS recovered_count,
				ROUND(100.0 * COUNT(DISTINCT recovery.id) / COUNT(DISTINCT failed_orders.id), 2) AS recovery_rate,
				ROUND(SUM(CASE WHEN recovery.id IS NOT NULL THEN recovery.total ELSE 0 END), 2) AS recovered_revenue,
				ROUND(AVG(DATEDIFF(recovery.date_created, failed_orders.date_created)), 1) AS avg_days_to_recovery
			FROM {$wpdb->prefix}edd_orders failed_orders
			LEFT JOIN {$wpdb->prefix}edd_orders recovery
				ON failed_orders.customer_id = recovery.customer_id
				AND recovery.status IN ('complete', 'edd_subscription')
				AND recovery.date_created > failed_orders.date_created
				AND recovery.date_created <= DATE_ADD(failed_orders.date_created, INTERVAL 30 DAY)
			WHERE failed_orders.status = 'failed'
			{$where}
			GROUP BY
				YEAR(failed_orders.date_created),
				MONTH(failed_orders.date_created)
			ORDER BY date
		";

		return self::get_cached( 'failed_payment_recovery_' . md5( $query ), $query );
	}

	/**
	 * Get revenue velocity metrics.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Speed of revenue growth and acceleration metrics.
	 */
	public static function get_revenue_velocity( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = " AND o.status IN ('complete', 'edd_subscription')";
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created <= %s', $end_date );
		}

		$query = "
			SELECT
				date,
				label,
				monthly_revenue,
				LAG(monthly_revenue) OVER (ORDER BY date) AS previous_month_revenue,
				ROUND(monthly_revenue - LAG(monthly_revenue) OVER (ORDER BY date), 2) AS revenue_change,
				ROUND(
					100.0 * (monthly_revenue - LAG(monthly_revenue) OVER (ORDER BY date)) /
					NULLIF(LAG(monthly_revenue) OVER (ORDER BY date), 0),
					2
				) AS growth_rate,
				ROUND(
					(monthly_revenue - LAG(monthly_revenue) OVER (ORDER BY date)) -
					(LAG(monthly_revenue) OVER (ORDER BY date) - LAG(monthly_revenue, 2) OVER (ORDER BY date)),
					2
				) AS acceleration
			FROM (
				SELECT
					DATE_FORMAT(o.date_created, '%Y-%m-01') AS date,
					DATE_FORMAT(o.date_created, '%M %Y') AS label,
					ROUND(SUM(o.total), 2) AS monthly_revenue
				FROM {$wpdb->prefix}edd_orders o
				WHERE 1=1
				{$where}
				GROUP BY
					YEAR(o.date_created),
					MONTH(o.date_created)
			) AS monthly_data
			ORDER BY date
		";

		return self::get_cached( 'revenue_velocity_' . md5( $query ), $query );
	}

	// =========================
	// FINANCIAL METRICS
	// =========================

	/**
	 * Get cash flow projection.
	 *
	 * @param int $days Number of days to project (default: 90).
	 * @return array Projected cash flow for next 30/60/90 days.
	 */
	public static function get_cash_flow_projection( $days = 90 ) {
		$wpdb = self::get_db();

		$query = $wpdb->prepare(
			"
			SELECT
				'Confirmed Revenue' AS category,
				ROUND(SUM(CASE WHEN s.expiration <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN s.recurring_amount ELSE 0 END), 2) AS next_30_days,
				ROUND(SUM(CASE WHEN s.expiration <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN s.recurring_amount ELSE 0 END), 2) AS next_60_days,
				ROUND(SUM(CASE WHEN s.expiration <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN s.recurring_amount ELSE 0 END), 2) AS next_90_days
			FROM {$wpdb->prefix}edd_subscriptions s
			WHERE s.status = 'active'
			AND s.expiration >= CURDATE()
			AND s.expiration <= DATE_ADD(CURDATE(), INTERVAL %d DAY)

			UNION ALL

			SELECT
				'Projected New Sales' AS category,
				ROUND(AVG(CASE WHEN o.date_created >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN daily_revenue ELSE NULL END) * 30, 2) AS next_30_days,
				ROUND(AVG(CASE WHEN o.date_created >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) THEN daily_revenue ELSE NULL END) * 60, 2) AS next_60_days,
				ROUND(AVG(CASE WHEN o.date_created >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN daily_revenue ELSE NULL END) * 90, 2) AS next_90_days
			FROM (
				SELECT
					DATE(date_created) AS sale_date,
					SUM(total) AS daily_revenue
				FROM {$wpdb->prefix}edd_orders o
				WHERE status IN ('complete', 'edd_subscription')
				AND type = 'sale'
				GROUP BY DATE(date_created)
			) AS o

			UNION ALL

			SELECT
				'At-Risk Revenue' AS category,
				ROUND(SUM(CASE
					WHEN s.expiration <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
					AND (
						SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders o
						WHERE o.customer_id = s.customer_id
						AND o.status = 'failed'
						AND o.date_created >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
					) > 0
					THEN s.recurring_amount ELSE 0
				END), 2) AS next_30_days,
				ROUND(SUM(CASE
					WHEN s.expiration <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
					AND (
						SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders o
						WHERE o.customer_id = s.customer_id
						AND o.status = 'failed'
						AND o.date_created >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
					) > 0
					THEN s.recurring_amount ELSE 0
				END), 2) AS next_60_days,
				ROUND(SUM(CASE
					WHEN s.expiration <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
					AND (
						SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders o
						WHERE o.customer_id = s.customer_id
						AND o.status = 'failed'
						AND o.date_created >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
					) > 0
					THEN s.recurring_amount ELSE 0
				END), 2) AS next_90_days
			FROM {$wpdb->prefix}edd_subscriptions s
			WHERE s.status = 'active'
			",
			$days
		);

		return self::get_cached( "cash_flow_projection_{$days}", $query );
	}

	/**
	 * Get profitability by segment.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Profit margins by product/customer segment.
	 */
	public static function get_profitability_by_segment( $start_date = null, $end_date = null ) {
		$wpdb = self::get_db();

		$where = " AND o.status IN ('complete', 'edd_subscription')";
		if ( $start_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created >= %s', $start_date );
		}
		if ( $end_date ) {
			$where .= $wpdb->prepare( ' AND o.date_created <= %s', $end_date );
		}

		// Note: This assumes product cost is stored in postmeta as '_edd_product_cost'
		$query = "
			SELECT
				'By Product Type' AS segment_type,
				CASE
					WHEN pm.meta_value IS NOT NULL THEN 'Bundle'
					ELSE 'Individual'
				END AS segment_name,
				ROUND(SUM(oi.total), 2) AS revenue,
				ROUND(SUM(oi.quantity * COALESCE(cost.meta_value, 0)), 2) AS estimated_cost,
				ROUND(SUM(oi.total) - SUM(oi.quantity * COALESCE(cost.meta_value, 0)), 2) AS gross_profit,
				ROUND(100.0 * (SUM(oi.total) - SUM(oi.quantity * COALESCE(cost.meta_value, 0))) / NULLIF(SUM(oi.total), 0), 2) AS profit_margin
			FROM {$wpdb->prefix}edd_order_items oi
			INNER JOIN {$wpdb->prefix}edd_orders o ON oi.order_id = o.id
			LEFT JOIN {$wpdb->posts} p ON oi.product_id = p.ID
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_edd_bundled_products'
			LEFT JOIN {$wpdb->postmeta} cost ON p.ID = cost.post_id AND cost.meta_key = '_edd_product_cost'
			WHERE 1=1
			{$where}
			GROUP BY segment_name

			UNION ALL

			SELECT
				'By Customer Segment' AS segment_type,
				rfm_segment AS segment_name,
				ROUND(SUM(segment_revenue), 2) AS revenue,
				0 AS estimated_cost,
				ROUND(SUM(segment_revenue), 2) AS gross_profit,
				100.0 AS profit_margin
			FROM (
				SELECT
					o.customer_id,
					SUM(o.total) AS segment_revenue,
					CASE
						WHEN (
							SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders o2
							WHERE o2.customer_id = o.customer_id
							AND o2.status IN ('complete', 'edd_subscription')
						) >= 5 THEN 'Loyal'
						WHEN (
							SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders o2
							WHERE o2.customer_id = o.customer_id
							AND o2.status IN ('complete', 'edd_subscription')
						) >= 2 THEN 'Repeat'
						ELSE 'One-Time'
					END AS rfm_segment
				FROM {$wpdb->prefix}edd_orders o
				WHERE o.status IN ('complete', 'edd_subscription')
				{$where}
				GROUP BY o.customer_id
			) AS customer_segments
			GROUP BY rfm_segment

			ORDER BY segment_type, revenue DESC
		";

		return self::get_cached( 'profitability_by_segment_' . md5( $query ), $query );
	}

	/**
	 * Get LTV:CAC ratio.
	 *
	 * @return array Customer acquisition cost vs lifetime value ratio.
	 */
	public static function get_ltv_cac_ratio() {
		$wpdb = self::get_db();

		$query = "
			SELECT
				ROUND(AVG(customer_ltv), 2) AS avg_ltv,
				ROUND(AVG(customer_ltv) / NULLIF((
					SELECT SUM(o.total) FROM {$wpdb->prefix}edd_orders o
					WHERE o.status IN ('complete', 'edd_subscription')
					AND o.date_created >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
				) / NULLIF((
					SELECT COUNT(DISTINCT id) FROM {$wpdb->prefix}edd_customers
					WHERE date_created >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
				), 0), 0), 2) AS ltv_cac_ratio,
				(
					SELECT SUM(o.total) FROM {$wpdb->prefix}edd_orders o
					WHERE o.status IN ('complete', 'edd_subscription')
					AND o.date_created >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
				) / NULLIF((
					SELECT COUNT(DISTINCT id) FROM {$wpdb->prefix}edd_customers
					WHERE date_created >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
				), 0) AS estimated_cac,
				COUNT(*) AS customer_count
			FROM (
				SELECT
					c.id,
					SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END) AS customer_ltv
				FROM {$wpdb->prefix}edd_customers c
				INNER JOIN {$wpdb->prefix}edd_orders o ON c.id = o.customer_id
				GROUP BY c.id
			) AS customer_metrics
		";

		return self::get_cached( 'ltv_cac_ratio', $query );
	}

	/**
	 * Get revenue run rate.
	 *
	 * @return array Annual and monthly run rate calculations.
	 */
	public static function get_revenue_run_rate() {
		$wpdb = self::get_db();

		$query = "
			SELECT
				ROUND(SUM(CASE WHEN o.date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) THEN o.total ELSE 0 END), 2) AS last_month_revenue,
				ROUND(SUM(CASE WHEN o.date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) THEN o.total ELSE 0 END) * 12, 2) AS annual_run_rate,
				ROUND(SUM(CASE WHEN o.date_created >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH) THEN o.total ELSE 0 END) / 3, 2) AS avg_monthly_revenue_3mo,
				ROUND((SUM(CASE WHEN o.date_created >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH) THEN o.total ELSE 0 END) / 3) * 12, 2) AS annual_run_rate_3mo,
				ROUND(
					(SELECT SUM(recurring_amount * 12) FROM {$wpdb->prefix}edd_subscriptions WHERE status = 'active'),
					2
				) AS arr_from_subscriptions,
				ROUND(
					(SELECT SUM(recurring_amount) FROM {$wpdb->prefix}edd_subscriptions WHERE status = 'active'),
					2
				) AS mrr_from_subscriptions
			FROM {$wpdb->prefix}edd_orders o
			WHERE o.status IN ('complete', 'edd_subscription')
		";

		return self::get_cached( 'revenue_run_rate', $query );
	}

	/**
	 * Get financial health indicators.
	 *
	 * @return array Key financial health metrics and benchmarks.
	 */
	public static function get_financial_health_indicators() {
		$wpdb = self::get_db();

		$query = "
			SELECT
				'Revenue Growth' AS metric,
				ROUND(
					100.0 * (
						(SELECT SUM(total) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) -
						(SELECT SUM(total) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
					) / NULLIF((SELECT SUM(total) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 0),
					2
				) AS value,
				'%' AS unit,
				CASE
					WHEN ROUND(
						100.0 * (
							(SELECT SUM(total) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) -
							(SELECT SUM(total) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
						) / NULLIF((SELECT SUM(total) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 0),
						2
					) >= 10 THEN 'Excellent'
					WHEN ROUND(
						100.0 * (
							(SELECT SUM(total) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) -
							(SELECT SUM(total) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
						) / NULLIF((SELECT SUM(total) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 0),
						2
					) >= 5 THEN 'Good'
					WHEN ROUND(
						100.0 * (
							(SELECT SUM(total) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) -
							(SELECT SUM(total) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
						) / NULLIF((SELECT SUM(total) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 0),
						2
					) >= 0 THEN 'Fair'
					ELSE 'Poor'
				END AS health_status

			UNION ALL

			SELECT
				'Churn Rate' AS metric,
				ROUND(
					100.0 * (SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions WHERE status IN ('cancelled', 'expired') AND expiration >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) /
					NULLIF((SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions WHERE created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 0),
					2
				) AS value,
				'%' AS unit,
				CASE
					WHEN ROUND(
						100.0 * (SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions WHERE status IN ('cancelled', 'expired') AND expiration >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) /
						NULLIF((SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions WHERE created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 0),
						2
					) <= 3 THEN 'Excellent'
					WHEN ROUND(
						100.0 * (SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions WHERE status IN ('cancelled', 'expired') AND expiration >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) /
						NULLIF((SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions WHERE created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 0),
						2
					) <= 5 THEN 'Good'
					WHEN ROUND(
						100.0 * (SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions WHERE status IN ('cancelled', 'expired') AND expiration >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) /
						NULLIF((SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions WHERE created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 0),
						2
					) <= 7 THEN 'Fair'
					ELSE 'Poor'
				END AS health_status

			UNION ALL

			SELECT
				'Customer Retention' AS metric,
				ROUND(
					100.0 * (SELECT COUNT(DISTINCT customer_id) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) AND customer_id IN (SELECT DISTINCT customer_id FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH))) /
					NULLIF((SELECT COUNT(DISTINCT customer_id) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 0),
					2
				) AS value,
				'%' AS unit,
				CASE
					WHEN ROUND(
						100.0 * (SELECT COUNT(DISTINCT customer_id) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) AND customer_id IN (SELECT DISTINCT customer_id FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH))) /
						NULLIF((SELECT COUNT(DISTINCT customer_id) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 0),
						2
					) >= 80 THEN 'Excellent'
					WHEN ROUND(
						100.0 * (SELECT COUNT(DISTINCT customer_id) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) AND customer_id IN (SELECT DISTINCT customer_id FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH))) /
						NULLIF((SELECT COUNT(DISTINCT customer_id) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 0),
						2
					) >= 70 THEN 'Good'
					WHEN ROUND(
						100.0 * (SELECT COUNT(DISTINCT customer_id) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) AND customer_id IN (SELECT DISTINCT customer_id FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH))) /
						NULLIF((SELECT COUNT(DISTINCT customer_id) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription') AND date_created < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 0),
						2
					) >= 60 THEN 'Fair'
					ELSE 'Poor'
				END AS health_status

			UNION ALL

			SELECT
				'Refund Rate' AS metric,
				ROUND(
					100.0 * (SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders WHERE status = 'refunded' AND date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) /
					NULLIF((SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription', 'refunded') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 0),
					2
				) AS value,
				'%' AS unit,
				CASE
					WHEN ROUND(
						100.0 * (SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders WHERE status = 'refunded' AND date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) /
						NULLIF((SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription', 'refunded') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 0),
						2
					) <= 2 THEN 'Excellent'
					WHEN ROUND(
						100.0 * (SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders WHERE status = 'refunded' AND date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) /
						NULLIF((SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription', 'refunded') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 0),
						2
					) <= 5 THEN 'Good'
					WHEN ROUND(
						100.0 * (SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders WHERE status = 'refunded' AND date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) /
						NULLIF((SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders WHERE status IN ('complete', 'edd_subscription', 'refunded') AND date_created >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 0),
						2
					) <= 8 THEN 'Fair'
					ELSE 'Poor'
				END AS health_status
		";

		return self::get_cached( 'financial_health_indicators', $query );
	}

	// =========================
	// PREDICTIVE ANALYTICS
	// =========================

	/**
	 * Get churn prediction scores.
	 *
	 * @param int $limit Number of results to return (default: 100).
	 * @return array Customers with churn likelihood scores.
	 */
	public static function get_churn_prediction_scores( $limit = 100 ) {
		$wpdb = self::get_db();

		$query = $wpdb->prepare(
			"
			SELECT
				c.id AS customer_id,
				c.email,
				c.name,
				DATEDIFF(CURDATE(), MAX(o.date_created)) AS days_since_last_order,
				COUNT(DISTINCT o.id) AS total_orders,
				ROUND(SUM(CASE WHEN o.status IN ('complete', 'edd_subscription') THEN o.total ELSE 0 END), 2) AS lifetime_value,
				(SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions s WHERE s.customer_id = c.id AND s.status = 'active') AS active_subscriptions,
				(SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders o2 WHERE o2.customer_id = c.id AND o2.status = 'failed' AND o2.date_created >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) AS recent_failed_payments,
				CASE
					WHEN (
						DATEDIFF(CURDATE(), MAX(o.date_created)) > 180
						OR (SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders o2 WHERE o2.customer_id = c.id AND o2.status = 'failed' AND o2.date_created >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) >= 2
						OR (SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions s WHERE s.customer_id = c.id AND s.status IN ('cancelled', 'expired') AND s.expiration >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) > 0
					) THEN 'High'
					WHEN (
						DATEDIFF(CURDATE(), MAX(o.date_created)) > 90
						OR (SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders o2 WHERE o2.customer_id = c.id AND o2.status = 'failed' AND o2.date_created >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) >= 1
					) THEN 'Medium'
					ELSE 'Low'
				END AS churn_risk,
				CASE
					WHEN (
						DATEDIFF(CURDATE(), MAX(o.date_created)) > 180
						OR (SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders o2 WHERE o2.customer_id = c.id AND o2.status = 'failed' AND o2.date_created >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) >= 2
						OR (SELECT COUNT(*) FROM {$wpdb->prefix}edd_subscriptions s WHERE s.customer_id = c.id AND s.status IN ('cancelled', 'expired') AND s.expiration >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) > 0
					) THEN 85
					WHEN (
						DATEDIFF(CURDATE(), MAX(o.date_created)) > 90
						OR (SELECT COUNT(*) FROM {$wpdb->prefix}edd_orders o2 WHERE o2.customer_id = c.id AND o2.status = 'failed' AND o2.date_created >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) >= 1
					) THEN 55
					ELSE 20
				END AS churn_score
			FROM {$wpdb->prefix}edd_customers c
			INNER JOIN {$wpdb->prefix}edd_orders o ON c.id = o.customer_id
			WHERE o.status IN ('complete', 'edd_subscription', 'failed')
			GROUP BY c.id
			HAVING total_orders > 0
			ORDER BY churn_score DESC, lifetime_value DESC
			LIMIT %d
			",
			$limit
		);

		return self::get_cached( "churn_prediction_scores_{$limit}", $query );
	}

	/**
	 * Get revenue forecast based on historical trends.
	 *
	 * @param int $months Number of months to forecast (default: 6).
	 * @return array Projected revenue for upcoming months.
	 */
	public static function get_revenue_forecast( $months = 6 ) {
		$wpdb = self::get_db();

		$query = $wpdb->prepare(
			"
			SELECT
				forecast_date,
				forecast_label,
				ROUND(
					avg_revenue * (1 + (avg_growth_rate / 100)),
					2
				) AS projected_revenue,
				ROUND(
					avg_revenue * (1 + ((avg_growth_rate - stddev_growth_rate) / 100)),
					2
				) AS conservative_estimate,
				ROUND(
					avg_revenue * (1 + ((avg_growth_rate + stddev_growth_rate) / 100)),
					2
				) AS optimistic_estimate,
				avg_growth_rate AS growth_rate,
				confidence_level
			FROM (
				SELECT
					DATE_ADD(DATE_FORMAT(CURDATE(), '%%Y-%%m-01'), INTERVAL n MONTH) AS forecast_date,
					DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL n MONTH), '%%M %%Y') AS forecast_label,
					AVG(monthly_revenue) AS avg_revenue,
					AVG(growth_rate) AS avg_growth_rate,
					STDDEV(growth_rate) AS stddev_growth_rate,
					CASE
						WHEN COUNT(*) >= 12 THEN 'High'
						WHEN COUNT(*) >= 6 THEN 'Medium'
						ELSE 'Low'
					END AS confidence_level,
					n
				FROM (
					SELECT
						DATE_FORMAT(o.date_created, '%%Y-%%m-01') AS month,
						SUM(o.total) AS monthly_revenue,
						100.0 * (SUM(o.total) - LAG(SUM(o.total)) OVER (ORDER BY DATE_FORMAT(o.date_created, '%%Y-%%m-01'))) /
						NULLIF(LAG(SUM(o.total)) OVER (ORDER BY DATE_FORMAT(o.date_created, '%%Y-%%m-01')), 0) AS growth_rate
					FROM {$wpdb->prefix}edd_orders o
					WHERE o.status IN ('complete', 'edd_subscription')
					AND o.date_created >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
					GROUP BY DATE_FORMAT(o.date_created, '%%Y-%%m-01')
				) AS monthly_data
				CROSS JOIN (
					SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
				) AS forecast_months
				WHERE n <= %d
				GROUP BY n
			) AS forecast_data
			ORDER BY forecast_date
			",
			$months
		);

		return self::get_cached( "revenue_forecast_{$months}", $query );
	}

	/**
	 * Get demand forecast by product.
	 *
	 * @param int $days Number of days to forecast (default: 30).
	 * @param int $limit Number of top products to forecast (default: 10).
	 * @return array Predicted demand for top products.
	 */
	public static function get_demand_forecast( $days = 30, $limit = 10 ) {
		$wpdb = self::get_db();

		$query = $wpdb->prepare(
			"
			SELECT
				product_id,
				product_name,
				ROUND(avg_daily_sales * %d, 0) AS forecasted_units,
				ROUND(avg_daily_revenue * %d, 2) AS forecasted_revenue,
				avg_daily_sales,
				avg_daily_revenue,
				trend_direction,
				CASE
					WHEN data_points >= 90 THEN 'High'
					WHEN data_points >= 30 THEN 'Medium'
					ELSE 'Low'
				END AS forecast_confidence
			FROM (
				SELECT
					oi.product_id,
					p.post_title AS product_name,
					AVG(daily_units) AS avg_daily_sales,
					AVG(daily_revenue) AS avg_daily_revenue,
					COUNT(DISTINCT sale_date) AS data_points,
					CASE
						WHEN AVG(CASE WHEN sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN daily_units END) >
							 AVG(CASE WHEN sale_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND sale_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN daily_units END)
						THEN 'Increasing'
						WHEN AVG(CASE WHEN sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN daily_units END) <
							 AVG(CASE WHEN sale_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND sale_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN daily_units END)
						THEN 'Decreasing'
						ELSE 'Stable'
					END AS trend_direction
				FROM (
					SELECT
						oi.product_id,
						DATE(o.date_created) AS sale_date,
						SUM(oi.quantity) AS daily_units,
						SUM(oi.total) AS daily_revenue
					FROM {$wpdb->prefix}edd_order_items oi
					INNER JOIN {$wpdb->prefix}edd_orders o ON oi.order_id = o.id
					WHERE o.status IN ('complete', 'edd_subscription')
					AND o.date_created >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
					GROUP BY oi.product_id, DATE(o.date_created)
				) AS daily_sales
				INNER JOIN (
					SELECT oi2.product_id, SUM(oi2.total) AS total_revenue
					FROM {$wpdb->prefix}edd_order_items oi2
					INNER JOIN {$wpdb->prefix}edd_orders o2 ON oi2.order_id = o2.id
					WHERE o2.status IN ('complete', 'edd_subscription')
					AND o2.date_created >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
					GROUP BY oi2.product_id
					ORDER BY total_revenue DESC
					LIMIT %d
				) AS top_products ON daily_sales.product_id = top_products.product_id
				LEFT JOIN {$wpdb->posts} p ON daily_sales.product_id = p.ID
				GROUP BY product_id
			) AS product_forecasts
			ORDER BY forecasted_revenue DESC
			",
			$days,
			$days,
			$limit
		);

		return self::get_cached( "demand_forecast_{$days}_{$limit}", $query );
	}

	/**
	 * Get seasonal patterns analysis.
	 *
	 * @return array Seasonal trends and patterns in revenue/sales.
	 */
	public static function get_seasonal_patterns() {
		$wpdb = self::get_db();

		$query = "
			SELECT
				month_number,
				month_name,
				ROUND(AVG(monthly_revenue), 2) AS avg_revenue,
				ROUND(AVG(order_count), 0) AS avg_orders,
				ROUND(AVG(customer_count), 0) AS avg_customers,
				ROUND(STDDEV(monthly_revenue), 2) AS revenue_volatility,
				ROUND(
					100.0 * (AVG(monthly_revenue) - overall_avg.avg_all_months) / NULLIF(overall_avg.avg_all_months, 0),
					2
				) AS deviation_from_average,
				CASE
					WHEN AVG(monthly_revenue) >= overall_avg.avg_all_months * 1.2 THEN 'Peak Season'
					WHEN AVG(monthly_revenue) >= overall_avg.avg_all_months * 1.1 THEN 'High Season'
					WHEN AVG(monthly_revenue) >= overall_avg.avg_all_months * 0.9 THEN 'Normal Season'
					WHEN AVG(monthly_revenue) >= overall_avg.avg_all_months * 0.8 THEN 'Low Season'
					ELSE 'Off Season'
				END AS seasonality_classification
			FROM (
				SELECT
					MONTH(o.date_created) AS month_number,
					DATE_FORMAT(o.date_created, '%M') AS month_name,
					YEAR(o.date_created) AS year,
					SUM(o.total) AS monthly_revenue,
					COUNT(DISTINCT o.id) AS order_count,
					COUNT(DISTINCT o.customer_id) AS customer_count
				FROM {$wpdb->prefix}edd_orders o
				WHERE o.status IN ('complete', 'edd_subscription')
				AND o.date_created >= DATE_SUB(CURDATE(), INTERVAL 2 YEAR)
				GROUP BY YEAR(o.date_created), MONTH(o.date_created)
			) AS monthly_metrics
			CROSS JOIN (
				SELECT AVG(monthly_total) AS avg_all_months
				FROM (
					SELECT SUM(total) AS monthly_total
					FROM {$wpdb->prefix}edd_orders
					WHERE status IN ('complete', 'edd_subscription')
					AND date_created >= DATE_SUB(CURDATE(), INTERVAL 2 YEAR)
					GROUP BY YEAR(date_created), MONTH(date_created)
				) AS all_months
			) AS overall_avg
			GROUP BY month_number, month_name
			ORDER BY month_number
		";

		return self::get_cached( 'seasonal_patterns', $query );
	}
}
