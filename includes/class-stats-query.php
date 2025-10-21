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

			self::$dev_db = new wpdb( $db_user, $db_pass, $db_name, $db_host );
			self::$dev_db->set_charset( self::$dev_db->dbh, 'utf8mb4', 'utf8mb4_unicode_ci' );
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
}
