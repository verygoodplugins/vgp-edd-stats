import React, { useState } from 'react';
import ExecutiveOverviewPage from './pages/ExecutiveOverviewPage';
import CustomerAnalyticsPage from './pages/CustomerAnalyticsPage';
import RevenueIntelligencePage from './pages/RevenueIntelligencePage';
import ProductPerformancePage from './pages/ProductPerformancePage';
import SubscriptionAnalyticsPage from './pages/SubscriptionAnalyticsPage';
import CustomersRevenuePage from './pages/CustomersRevenuePage';
import MRRGrowthPage from './pages/MRRGrowthPage';
import RenewalsPage from './pages/RenewalsPage';
import RefundsPage from './pages/RefundsPage';
import LicensingPage from './pages/LicensingPage';
import SitesPage from './pages/SitesPage';
import SupportPage from './pages/SupportPage';
import DateRangeFilter from './components/DateRangeFilter';
import { subDays, subMonths } from 'date-fns';

function App({ section }) {
	// Global date range state
	const [dateRange, setDateRange] = useState(() => getInitialDateRange());
	// Comparison mode state: 'previous' (default), 'yoy', or 'none'
	const [comparisonMode, setComparisonMode] = useState('previous');

	// Render appropriate page based on section
	const renderPage = () => {
		switch (section) {
			case 'executive-overview':
				return <ExecutiveOverviewPage dateRange={dateRange} comparisonMode={comparisonMode} />;
			case 'customer-analytics':
				return <CustomerAnalyticsPage dateRange={dateRange} comparisonMode={comparisonMode} />;
			case 'revenue-intelligence':
				return <RevenueIntelligencePage dateRange={dateRange} comparisonMode={comparisonMode} />;
			case 'product-performance':
				return <ProductPerformancePage dateRange={dateRange} comparisonMode={comparisonMode} />;
			case 'subscription-analytics':
				return <SubscriptionAnalyticsPage dateRange={dateRange} comparisonMode={comparisonMode} />;
			case 'customers-revenue':
				return <CustomersRevenuePage dateRange={dateRange} comparisonMode={comparisonMode} />;
			case 'mrr-growth':
				return <MRRGrowthPage dateRange={dateRange} comparisonMode={comparisonMode} />;
			case 'renewals':
				return <RenewalsPage dateRange={dateRange} comparisonMode={comparisonMode} />;
			case 'refunds':
				return <RefundsPage dateRange={dateRange} comparisonMode={comparisonMode} />;
			case 'licensing':
				return <LicensingPage dateRange={dateRange} comparisonMode={comparisonMode} />;
			case 'sites':
				return <SitesPage dateRange={dateRange} comparisonMode={comparisonMode} />;
			case 'support':
				return <SupportPage dateRange={dateRange} comparisonMode={comparisonMode} />;
			default:
				return <ExecutiveOverviewPage dateRange={dateRange} comparisonMode={comparisonMode} />;
		}
	};

	return (
		<div className="vgp-edd-stats-app">
			{/* Global controls */}
			<div className="mb-6 flex items-center justify-between bg-white p-4 rounded-lg shadow-sm border border-gray-200">
				<div>
					<h2 className="text-xl font-semibold text-gray-900">
						{getSectionTitle(section)}
					</h2>
					<p className="text-sm text-gray-500 mt-1">
						Analytics and insights for your EDD store
					</p>
				</div>
				<DateRangeFilter
					dateRange={dateRange}
					onChange={setDateRange}
					comparisonMode={comparisonMode}
					onComparisonChange={setComparisonMode}
				/>
			</div>

			{/* Page content */}
			<div className="space-y-6">
				{renderPage()}
			</div>
		</div>
	);
}

function getSectionTitle(section) {
	const titles = {
		'executive-overview': 'Executive Overview',
		'customer-analytics': 'Customer Analytics',
		'revenue-intelligence': 'Revenue Intelligence',
		'product-performance': 'Product Performance',
		'subscription-analytics': 'Subscription Analytics',
		'customers-revenue': 'Customers & Revenue',
		'mrr-growth': 'MRR & Growth',
		'renewals': 'Renewals & Cancellations',
		'refunds': 'Refunds',
		'licensing': 'Software Licensing',
		'sites': 'Sites Stats',
		'support': 'Support',
	};

	return titles[section] || 'Dashboard';
}

export default App;

function getInitialDateRange() {
	const configuredPreset =
		typeof window !== 'undefined' &&
		window.vgpEddStats &&
		typeof window.vgpEddStats.defaultRange === 'string'
			? window.vgpEddStats.defaultRange
			: null;

	const preset = ['30', '90', '365', 'all'].includes(configuredPreset)
		? configuredPreset
		: '365';

	if (preset === 'all') {
		return { startDate: null, endDate: null, preset: 'all' };
	}

	if (preset === '30') {
		return { startDate: subDays(new Date(), 30), endDate: new Date(), preset: '30' };
	}

	if (preset === '90') {
		return { startDate: subDays(new Date(), 90), endDate: new Date(), preset: '90' };
	}

	return { startDate: subMonths(new Date(), 12), endDate: new Date(), preset: '365' };
}
