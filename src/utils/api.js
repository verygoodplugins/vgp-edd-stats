import { format } from 'date-fns';

// Get API configuration from WordPress localized script
const getConfig = () => {
	if (typeof window.vgpEddStats !== 'undefined') {
		return window.vgpEddStats;
	}

	// Fallback for development
	return {
		apiUrl: '/wp-json/vgp-edd-stats/v1',
		nonce: '',
		dateFormat: 'F j, Y',
		currencyCode: 'USD',
	};
};

/**
 * Make API request.
 *
 * @param {string} endpoint API endpoint.
 * @param {Object} params   Query parameters.
 * @return {Promise} Response data.
 */
export async function apiRequest(endpoint, params = {}) {
	const config = getConfig();
	const url = new URL(`${config.apiUrl}${endpoint}`);

	// Add query parameters
	Object.keys(params).forEach(key => {
		if (params[key] !== null && params[key] !== undefined) {
			url.searchParams.append(key, params[key]);
		}
	});

	const response = await fetch(url, {
		method: 'GET',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': config.nonce,
		},
	});

	if (!response.ok) {
		throw new Error(`API request failed: ${response.statusText}`);
	}

	const data = await response.json();

	if (!data.success) {
		throw new Error(data.message || 'API request failed');
	}

	return data.data;
}

/**
 * Format date range for API.
 *
 * @param {Object} dateRange Date range object with startDate and endDate.
 * @return {Object} Formatted date parameters.
 */
export function formatDateRange(dateRange) {
	if (!dateRange || !dateRange.startDate || !dateRange.endDate) {
		return {};
	}

	return {
		start_date: format(dateRange.startDate, 'yyyy-MM-dd'),
		end_date: format(dateRange.endDate, 'yyyy-MM-dd'),
	};
}

/**
 * Get currency symbol.
 *
 * @return {string} Currency symbol.
 */
export function getCurrencySymbol() {
	const config = getConfig();
	const symbols = {
		USD: '$',
		EUR: '€',
		GBP: '£',
		JPY: '¥',
	};

	return symbols[config.currencyCode] || '$';
}

/**
 * Format currency value.
 *
 * @param {number} value Currency value.
 * @return {string} Formatted currency string.
 */
export function formatCurrency(value) {
	const symbol = getCurrencySymbol();
	const formatted = new Intl.NumberFormat('en-US', {
		minimumFractionDigits: 0,
		maximumFractionDigits: 2,
	}).format(Math.abs(value));

	return value < 0 ? `-${symbol}${formatted}` : `${symbol}${formatted}`;
}

/**
 * Format number with commas.
 *
 * @param {number} value Number value.
 * @return {string} Formatted number string.
 */
export function formatNumber(value) {
	return new Intl.NumberFormat('en-US').format(value);
}

/**
 * Format percentage.
 *
 * @param {number} value Percentage value.
 * @param {number} decimals Number of decimal places.
 * @return {string} Formatted percentage string.
 */
export function formatPercentage(value, decimals = 1) {
	return `${value.toFixed(decimals)}%`;
}

// API endpoints
export const API = {
	// Customers and Revenue
	getCustomersByMonth: (dateRange) =>
		apiRequest('/customers/by-month', formatDateRange(dateRange)),

	getCustomersYoY: () =>
		apiRequest('/customers/yoy-change'),

	getRevenueByMonth: (dateRange) =>
		apiRequest('/revenue/by-month', formatDateRange(dateRange)),

	getRefundedRevenue: (dateRange) =>
		apiRequest('/revenue/refunded', formatDateRange(dateRange)),

	// MRR and Growth
	getMRRByMonth: (dateRange) =>
		apiRequest('/mrr/by-month', formatDateRange(dateRange)),

	getCurrentMRR: () =>
		apiRequest('/mrr/current'),

	// Renewals
	getRenewalRates: (dateRange) =>
		apiRequest('/renewals/rates', formatDateRange(dateRange)),

	getUpcomingRenewals: (days = 30) =>
		apiRequest('/renewals/upcoming', { days }),

	// Refunds
	getRefundRates: (dateRange) =>
		apiRequest('/refunds/rates', formatDateRange(dateRange)),

	// Licensing
	getTopLicenses: (limit = 20) =>
		apiRequest('/licenses/top', { limit }),
};
