import React, { useState, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { API, formatCurrency, formatNumber, formatPercentage } from '../utils/api';
import StatCard from '../components/StatCard';
import ChartWrapper from '../components/ChartWrapper';
import clsx from 'clsx';

function CustomerAnalyticsPage({ dateRange }) {
	const [activeSegment, setActiveSegment] = useState(null);
	const [selectedCohort, setSelectedCohort] = useState('all');

	// Mock data for demonstration - In production, these would come from API endpoints
	const { data: clvData, isLoading: clvLoading } = useQuery({
		queryKey: ['customer-clv', dateRange],
		queryFn: () => generateMockCLVData(),
	});

	const { data: rfmData, isLoading: rfmLoading } = useQuery({
		queryKey: ['rfm-segments', dateRange],
		queryFn: () => generateMockRFMData(),
	});

	const { data: healthData, isLoading: healthLoading } = useQuery({
		queryKey: ['customer-health', dateRange],
		queryFn: () => generateMockHealthData(),
	});

	const { data: funnelData, isLoading: funnelLoading } = useQuery({
		queryKey: ['customer-funnel', dateRange],
		queryFn: () => generateMockFunnelData(),
	});

	const { data: geoData, isLoading: geoLoading } = useQuery({
		queryKey: ['customer-geography', dateRange],
		queryFn: () => generateMockGeoData(),
	});

	// Calculate summary metrics
	const summaryMetrics = useMemo(() => {
		if (!clvData || !rfmData || !healthData) return null;

		return {
			avgCLV: clvData.topCustomers.reduce((sum, c) => sum + c.clv, 0) / clvData.topCustomers.length,
			totalCustomers: rfmData.segments.reduce((sum, s) => sum + s.count, 0),
			atRiskCount: healthData.atRiskCustomers.length,
			healthyPercentage: (healthData.distribution.find(d => d.name === 'Healthy')?.value || 0),
		};
	}, [clvData, rfmData, healthData]);

	// Chart Options
	const clvDistributionOption = {
		tooltip: {
			trigger: 'axis',
			axisPointer: { type: 'shadow' },
			formatter: (params) => {
				const point = params[0];
				return `${point.name}<br/>${point.marker} Customers: ${point.value}<br/>Avg CLV: ${formatCurrency(point.data.avgClv)}`;
			},
		},
		grid: { top: 40, right: 60, bottom: 60, left: 80 },
		xAxis: {
			type: 'category',
			data: clvData?.distribution.map(d => d.range) || [],
			axisLabel: { rotate: 45, interval: 0, fontSize: 11 },
		},
		yAxis: {
			type: 'value',
			name: 'Customer Count',
		},
		series: [{
			data: clvData?.distribution.map(d => ({ value: d.count, avgClv: d.avgClv })) || [],
			type: 'bar',
			itemStyle: {
				color: {
					type: 'linear',
					x: 0, y: 0, x2: 0, y2: 1,
					colorStops: [
						{ offset: 0, color: '#0ea5e9' },
						{ offset: 1, color: '#0369a1' },
					],
				},
			},
			label: {
				show: true,
				position: 'top',
				formatter: '{c}',
				fontSize: 10,
			},
		}],
	};

	const clvCohortOption = {
		tooltip: {
			trigger: 'axis',
			formatter: (params) => {
				let result = `${params[0].name}<br/>`;
				params.forEach(p => {
					result += `${p.marker} ${p.seriesName}: ${formatCurrency(p.value)}<br/>`;
				});
				return result;
			},
		},
		legend: {
			data: clvData?.cohorts.map(c => c.name) || [],
			bottom: 0,
			type: 'scroll',
		},
		grid: { top: 40, right: 40, bottom: 80, left: 80 },
		xAxis: {
			type: 'category',
			data: clvData?.cohorts[0]?.months || [],
			axisLabel: { rotate: 45 },
		},
		yAxis: {
			type: 'value',
			name: 'Average CLV',
			axisLabel: {
				formatter: (value) => formatCurrency(value),
			},
		},
		series: clvData?.cohorts.map(cohort => ({
			name: cohort.name,
			type: 'line',
			smooth: true,
			data: cohort.values,
			emphasis: { focus: 'series' },
		})) || [],
	};

	const rfmScatterOption = {
		tooltip: {
			formatter: (params) => {
				const data = params.data;
				return `<strong>${data.segment}</strong><br/>
					Recency: ${data.value[0]}<br/>
					Frequency: ${data.value[1]}<br/>
					Customers: ${data.count}<br/>
					Revenue: ${formatCurrency(data.revenue)}`;
			},
		},
		grid: { top: 60, right: 60, bottom: 60, left: 60 },
		xAxis: {
			name: 'Recency Score',
			nameLocation: 'middle',
			nameGap: 30,
			min: 0,
			max: 5,
			splitLine: { show: true, lineStyle: { type: 'dashed', color: '#e5e7eb' } },
		},
		yAxis: {
			name: 'Frequency Score',
			nameLocation: 'middle',
			nameGap: 40,
			min: 0,
			max: 5,
			splitLine: { show: true, lineStyle: { type: 'dashed', color: '#e5e7eb' } },
		},
		series: [{
			type: 'scatter',
			symbolSize: (data) => Math.sqrt(data[2]) * 3,
			data: rfmData?.scatterData || [],
			itemStyle: {
				color: (params) => {
					const segment = params.data.segment;
					const colors = {
						'Champions': '#10b981',
						'Loyal': '#0ea5e9',
						'Potential': '#f59e0b',
						'At Risk': '#ef4444',
						'Lost': '#6b7280',
					};
					return colors[segment] || '#94a3b8';
				},
				opacity: 0.7,
			},
			emphasis: {
				itemStyle: { opacity: 1 },
			},
		}],
	};

	const healthDistributionOption = {
		tooltip: {
			trigger: 'item',
			formatter: (params) => {
				return `${params.marker} ${params.name}<br/>Count: ${params.value} (${params.percent}%)`;
			},
		},
		legend: {
			orient: 'vertical',
			left: 'left',
			top: 'middle',
		},
		series: [{
			type: 'pie',
			radius: ['40%', '70%'],
			center: ['60%', '50%'],
			avoidLabelOverlap: true,
			itemStyle: {
				borderRadius: 10,
				borderColor: '#fff',
				borderWidth: 2,
			},
			label: {
				show: true,
				formatter: '{b}\n{d}%',
			},
			emphasis: {
				label: { show: true, fontSize: 16, fontWeight: 'bold' },
				itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0, 0, 0, 0.5)' },
			},
			data: healthData?.distribution.map(d => ({
				name: d.name,
				value: d.value,
				itemStyle: {
					color: d.name === 'Healthy' ? '#10b981' :
						d.name === 'Needs Attention' ? '#f59e0b' :
						d.name === 'At Risk' ? '#ef4444' : '#6b7280',
				},
			})) || [],
		}],
	};

	const engagementTrendOption = {
		tooltip: {
			trigger: 'axis',
			formatter: (params) => {
				let result = `${params[0].name}<br/>`;
				params.forEach(p => {
					result += `${p.marker} ${p.seriesName}: ${p.value.toFixed(1)}<br/>`;
				});
				return result;
			},
		},
		legend: {
			data: ['Health Score', 'Purchase Frequency', 'Support Tickets'],
			bottom: 0,
		},
		grid: { top: 40, right: 40, bottom: 80, left: 60 },
		xAxis: {
			type: 'category',
			data: healthData?.trends.map(t => t.month) || [],
			axisLabel: { rotate: 45 },
		},
		yAxis: [
			{
				type: 'value',
				name: 'Score',
				min: 0,
				max: 100,
				position: 'left',
			},
			{
				type: 'value',
				name: 'Count',
				position: 'right',
			},
		],
		series: [
			{
				name: 'Health Score',
				type: 'line',
				smooth: true,
				yAxisIndex: 0,
				data: healthData?.trends.map(t => t.healthScore) || [],
				itemStyle: { color: '#10b981' },
				areaStyle: {
					color: {
						type: 'linear',
						x: 0, y: 0, x2: 0, y2: 1,
						colorStops: [
							{ offset: 0, color: 'rgba(16, 185, 129, 0.3)' },
							{ offset: 1, color: 'rgba(16, 185, 129, 0.05)' },
						],
					},
				},
			},
			{
				name: 'Purchase Frequency',
				type: 'line',
				smooth: true,
				yAxisIndex: 1,
				data: healthData?.trends.map(t => t.purchases) || [],
				itemStyle: { color: '#0ea5e9' },
			},
			{
				name: 'Support Tickets',
				type: 'line',
				smooth: true,
				yAxisIndex: 1,
				data: healthData?.trends.map(t => t.tickets) || [],
				itemStyle: { color: '#f59e0b' },
			},
		],
	};

	const funnelChartOption = {
		tooltip: {
			trigger: 'item',
			formatter: (params) => {
				const stage = params.data;
				return `<strong>${stage.name}</strong><br/>
					Count: ${formatNumber(stage.value)}<br/>
					Conversion: ${stage.conversion}%<br/>
					Avg Days: ${stage.avgDays}`;
			},
		},
		series: [{
			type: 'funnel',
			left: '10%',
			top: 60,
			bottom: 60,
			width: '80%',
			min: 0,
			max: 100,
			minSize: '0%',
			maxSize: '100%',
			sort: 'descending',
			gap: 2,
			label: {
				show: true,
				position: 'inside',
				formatter: '{b}\n{c}',
				fontSize: 14,
			},
			labelLine: {
				length: 10,
				lineStyle: { width: 1, type: 'solid' },
			},
			itemStyle: {
				borderColor: '#fff',
				borderWidth: 1,
			},
			emphasis: {
				label: { fontSize: 18 },
			},
			data: funnelData?.stages.map((stage, idx) => ({
				value: stage.count,
				name: stage.name,
				conversion: stage.conversion,
				avgDays: stage.avgDays,
				itemStyle: {
					color: ['#10b981', '#0ea5e9', '#8b5cf6', '#f59e0b', '#ef4444'][idx],
				},
			})) || [],
		}],
	};

	const geoRevenueOption = {
		tooltip: {
			trigger: 'axis',
			axisPointer: { type: 'shadow' },
			formatter: (params) => {
				const point = params[0];
				return `${point.name}<br/>
					${point.marker} Revenue: ${formatCurrency(point.data.revenue)}<br/>
					Customers: ${point.data.customers}`;
			},
		},
		grid: { top: 40, right: 40, bottom: 60, left: 100 },
		xAxis: {
			type: 'value',
			axisLabel: {
				formatter: (value) => formatCurrency(value),
			},
		},
		yAxis: {
			type: 'category',
			data: geoData?.topCountries.map(c => c.country) || [],
			axisLabel: { fontSize: 12 },
		},
		series: [{
			data: geoData?.topCountries.map(c => ({
				value: c.revenue,
				revenue: c.revenue,
				customers: c.customers,
			})) || [],
			type: 'bar',
			itemStyle: {
				color: {
					type: 'linear',
					x: 0, y: 0, x2: 1, y2: 0,
					colorStops: [
						{ offset: 0, color: '#0ea5e9' },
						{ offset: 1, color: '#06b6d4' },
					],
				},
			},
			label: {
				show: true,
				position: 'right',
				formatter: (params) => formatCurrency(params.value),
				fontSize: 11,
			},
		}],
	};

	return (
		<div className="space-y-8">
			{/* Header */}
			<div>
				<h2 className="text-2xl font-bold text-gray-900">Customer Analytics Dashboard</h2>
				<p className="text-gray-600 mt-1">Deep insights into customer behavior, value, and engagement</p>
			</div>

			{/* Summary Metrics */}
			<div className="grid grid-cols-1 md:grid-cols-4 gap-6">
				<StatCard
					title="Average Customer Lifetime Value"
					value={summaryMetrics?.avgCLV}
					type="currency"
					loading={clvLoading}
					className="bg-gradient-to-br from-blue-50 to-blue-100"
				/>
				<StatCard
					title="Total Active Customers"
					value={summaryMetrics?.totalCustomers}
					type="number"
					loading={rfmLoading}
					className="bg-gradient-to-br from-green-50 to-green-100"
				/>
				<StatCard
					title="At Risk Customers"
					value={summaryMetrics?.atRiskCount}
					type="number"
					loading={healthLoading}
					className="bg-gradient-to-br from-red-50 to-red-100"
				/>
				<StatCard
					title="Healthy Customers"
					value={summaryMetrics?.healthyPercentage}
					type="percentage"
					loading={healthLoading}
					className="bg-gradient-to-br from-purple-50 to-purple-100"
				/>
			</div>

			{/* CLV Analysis Section */}
			<div className="space-y-6">
				<div className="border-b border-gray-200 pb-2">
					<h3 className="text-xl font-semibold text-gray-900">Customer Lifetime Value Analysis</h3>
					<p className="text-sm text-gray-600 mt-1">Understanding customer value distribution and trends</p>
				</div>

				<div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
					<ChartWrapper
						title="CLV Distribution"
						subtitle="Number of customers by lifetime value range"
						option={clvDistributionOption}
						loading={clvLoading}
						height={350}
					/>

					<ChartWrapper
						title="CLV by Cohort Over Time"
						subtitle="Average CLV trends for different customer cohorts"
						option={clvCohortOption}
						loading={clvLoading}
						height={350}
					/>
				</div>

				{/* Top Customers Table */}
				<div className="stat-card">
					<h4 className="text-lg font-semibold text-gray-900 mb-4">Top 10 Customers by CLV</h4>
					<div className="overflow-x-auto">
						<table className="min-w-full divide-y divide-gray-200">
							<thead className="bg-gray-50">
								<tr>
									<th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer ID</th>
									<th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lifetime Value</th>
									<th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchases</th>
									<th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Active</th>
									<th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Order</th>
								</tr>
							</thead>
							<tbody className="bg-white divide-y divide-gray-200">
								{clvLoading ? (
									<tr><td colSpan="5" className="px-4 py-8 text-center text-gray-500">Loading...</td></tr>
								) : (
									clvData?.topCustomers.slice(0, 10).map((customer, idx) => (
										<tr key={idx} className="hover:bg-gray-50 transition-colors">
											<td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">#{customer.id}</td>
											<td className="px-4 py-3 whitespace-nowrap text-sm text-gray-900 font-semibold">{formatCurrency(customer.clv)}</td>
											<td className="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{customer.purchases}</td>
											<td className="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{customer.daysActive}</td>
											<td className="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{formatCurrency(customer.avgOrder)}</td>
										</tr>
									))
								)}
							</tbody>
						</table>
					</div>
				</div>
			</div>

			{/* RFM Segmentation Section */}
			<div className="space-y-6">
				<div className="border-b border-gray-200 pb-2">
					<h3 className="text-xl font-semibold text-gray-900">RFM Customer Segmentation</h3>
					<p className="text-sm text-gray-600 mt-1">Recency, Frequency, Monetary analysis for targeted engagement</p>
				</div>

				<div className="grid grid-cols-2 md:grid-cols-5 gap-4">
					{rfmData?.segments.map((segment, idx) => (
						<div
							key={idx}
							className={clsx(
								'stat-card cursor-pointer transition-all',
								activeSegment === segment.name ? 'ring-2 ring-blue-500 shadow-lg' : 'hover:shadow-md'
							)}
							onClick={() => setActiveSegment(activeSegment === segment.name ? null : segment.name)}
						>
							<div className={clsx(
								'w-12 h-12 rounded-full flex items-center justify-center mb-3',
								segment.name === 'Champions' ? 'bg-green-100 text-green-600' :
								segment.name === 'Loyal' ? 'bg-blue-100 text-blue-600' :
								segment.name === 'Potential' ? 'bg-yellow-100 text-yellow-600' :
								segment.name === 'At Risk' ? 'bg-red-100 text-red-600' :
								'bg-gray-100 text-gray-600'
							)}>
								{segment.icon}
							</div>
							<h5 className="text-sm font-semibold text-gray-900">{segment.name}</h5>
							<p className="text-2xl font-bold text-gray-900 mt-1">{formatNumber(segment.count)}</p>
							<p className="text-sm text-gray-600">{formatCurrency(segment.revenue)}</p>
							<div className="mt-2 text-xs text-gray-500">
								Avg CLV: {formatCurrency(segment.avgClv)}
							</div>
						</div>
					))}
				</div>

				<ChartWrapper
					title="RFM Segmentation Scatter Plot"
					subtitle="Customer distribution by recency and frequency scores (size = monetary value)"
					option={rfmScatterOption}
					loading={rfmLoading}
					height={400}
				/>
			</div>

			{/* Customer Health Section */}
			<div className="space-y-6">
				<div className="border-b border-gray-200 pb-2">
					<h3 className="text-xl font-semibold text-gray-900">Customer Health Dashboard</h3>
					<p className="text-sm text-gray-600 mt-1">Monitor customer engagement and identify churn risks</p>
				</div>

				<div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
					<ChartWrapper
						title="Health Score Distribution"
						subtitle="Current health status of customer base"
						option={healthDistributionOption}
						loading={healthLoading}
						height={350}
					/>

					<ChartWrapper
						title="Customer Engagement Trends"
						subtitle="Health score, purchase frequency, and support interactions over time"
						option={engagementTrendOption}
						loading={healthLoading}
						height={350}
					/>
				</div>

				{/* At Risk Customers Table */}
				<div className="stat-card">
					<div className="flex items-center justify-between mb-4">
						<h4 className="text-lg font-semibold text-gray-900">At-Risk Customers</h4>
						<span className="px-3 py-1 text-xs font-medium text-red-700 bg-red-100 rounded-full">
							Immediate Attention Required
						</span>
					</div>
					<div className="overflow-x-auto">
						<table className="min-w-full divide-y divide-gray-200">
							<thead className="bg-gray-50">
								<tr>
									<th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer ID</th>
									<th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Health Score</th>
									<th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Churn Probability</th>
									<th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Purchase</th>
									<th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CLV at Risk</th>
									<th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
								</tr>
							</thead>
							<tbody className="bg-white divide-y divide-gray-200">
								{healthLoading ? (
									<tr><td colSpan="6" className="px-4 py-8 text-center text-gray-500">Loading...</td></tr>
								) : (
									healthData?.atRiskCustomers.slice(0, 8).map((customer, idx) => (
										<tr key={idx} className="hover:bg-gray-50 transition-colors">
											<td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">#{customer.id}</td>
											<td className="px-4 py-3 whitespace-nowrap">
												<div className="flex items-center">
													<div className="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden mr-2">
														<div
															className={clsx(
																'h-full',
																customer.healthScore >= 60 ? 'bg-green-500' :
																customer.healthScore >= 30 ? 'bg-yellow-500' : 'bg-red-500'
															)}
															style={{ width: `${customer.healthScore}%` }}
														></div>
													</div>
													<span className="text-xs text-gray-600">{customer.healthScore}%</span>
												</div>
											</td>
											<td className="px-4 py-3 whitespace-nowrap">
												<span className={clsx(
													'px-2 py-1 text-xs font-medium rounded-full',
													customer.churnProb >= 70 ? 'bg-red-100 text-red-700' :
													customer.churnProb >= 40 ? 'bg-yellow-100 text-yellow-700' :
													'bg-green-100 text-green-700'
												)}>
													{customer.churnProb}%
												</span>
											</td>
											<td className="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{customer.lastPurchase}</td>
											<td className="px-4 py-3 whitespace-nowrap text-sm font-semibold text-red-600">{formatCurrency(customer.clvAtRisk)}</td>
											<td className="px-4 py-3 whitespace-nowrap text-sm">
												<button className="text-blue-600 hover:text-blue-800 font-medium">Engage</button>
											</td>
										</tr>
									))
								)}
							</tbody>
						</table>
					</div>
				</div>
			</div>

			{/* Customer Journey Funnel Section */}
			<div className="space-y-6">
				<div className="border-b border-gray-200 pb-2">
					<h3 className="text-xl font-semibold text-gray-900">Customer Journey Funnel</h3>
					<p className="text-sm text-gray-600 mt-1">Conversion rates and time-to-conversion across lifecycle stages</p>
				</div>

				<div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
					<div className="lg:col-span-2">
						<ChartWrapper
							title="Lifecycle Stage Funnel"
							subtitle="Customer progression from signup to loyal subscriber"
							option={funnelChartOption}
							loading={funnelLoading}
							height={400}
						/>
					</div>

					<div className="space-y-4">
						<div className="stat-card bg-gradient-to-br from-green-50 to-green-100">
							<h5 className="text-sm font-medium text-gray-600 mb-2">Signup → First Purchase</h5>
							<p className="text-3xl font-bold text-gray-900">{funnelData?.conversionRates.signupToFirstPurchase}%</p>
							<p className="text-sm text-gray-600 mt-1">Avg: {funnelData?.avgDays.signupToFirstPurchase} days</p>
						</div>

						<div className="stat-card bg-gradient-to-br from-blue-50 to-blue-100">
							<h5 className="text-sm font-medium text-gray-600 mb-2">First → Repeat Purchase</h5>
							<p className="text-3xl font-bold text-gray-900">{funnelData?.conversionRates.firstToRepeat}%</p>
							<p className="text-sm text-gray-600 mt-1">Avg: {funnelData?.avgDays.firstToRepeat} days</p>
						</div>

						<div className="stat-card bg-gradient-to-br from-purple-50 to-purple-100">
							<h5 className="text-sm font-medium text-gray-600 mb-2">Repeat → Subscriber</h5>
							<p className="text-3xl font-bold text-gray-900">{funnelData?.conversionRates.repeatToSubscriber}%</p>
							<p className="text-sm text-gray-600 mt-1">Avg: {funnelData?.avgDays.repeatToSubscriber} days</p>
						</div>

						<div className="stat-card bg-gradient-to-br from-yellow-50 to-yellow-100">
							<h5 className="text-sm font-medium text-gray-600 mb-2">Overall Conversion</h5>
							<p className="text-3xl font-bold text-gray-900">{funnelData?.conversionRates.overall}%</p>
							<p className="text-sm text-gray-600 mt-1">Signup to subscriber</p>
						</div>
					</div>
				</div>
			</div>

			{/* Geographic Analysis Section */}
			<div className="space-y-6">
				<div className="border-b border-gray-200 pb-2">
					<h3 className="text-xl font-semibold text-gray-900">Geographic Distribution</h3>
					<p className="text-sm text-gray-600 mt-1">Customer and revenue analysis by region</p>
				</div>

				<ChartWrapper
					title="Revenue by Country"
					subtitle="Top 10 countries by total customer revenue"
					option={geoRevenueOption}
					loading={geoLoading}
					height={400}
				/>

				<div className="grid grid-cols-1 md:grid-cols-3 gap-6">
					<div className="stat-card">
						<h5 className="text-sm font-medium text-gray-600 mb-4">Top Countries</h5>
						<div className="space-y-3">
							{geoData?.topCountries.slice(0, 5).map((country, idx) => (
								<div key={idx} className="flex items-center justify-between">
									<div className="flex items-center space-x-2">
										<span className="text-2xl">{country.flag}</span>
										<span className="text-sm font-medium text-gray-900">{country.country}</span>
									</div>
									<div className="text-right">
										<p className="text-sm font-semibold text-gray-900">{formatCurrency(country.revenue)}</p>
										<p className="text-xs text-gray-600">{formatNumber(country.customers)} customers</p>
									</div>
								</div>
							))}
						</div>
					</div>

					<StatCard
						title="Total Countries"
						value={geoData?.totalCountries}
						type="number"
						subtitle="Active customer locations"
						loading={geoLoading}
					/>

					<StatCard
						title="International Revenue"
						value={geoData?.internationalPercentage}
						type="percentage"
						subtitle="Revenue from outside primary market"
						loading={geoLoading}
					/>
				</div>
			</div>
		</div>
	);
}

// Mock Data Generation Functions (Replace with real API calls in production)

function generateMockCLVData() {
	return new Promise((resolve) => {
		setTimeout(() => {
			resolve({
				distribution: [
					{ range: '$0-100', count: 234, avgClv: 67 },
					{ range: '$100-250', count: 189, avgClv: 178 },
					{ range: '$250-500', count: 156, avgClv: 376 },
					{ range: '$500-1k', count: 98, avgClv: 743 },
					{ range: '$1k-2.5k', count: 67, avgClv: 1654 },
					{ range: '$2.5k-5k', count: 34, avgClv: 3521 },
					{ range: '$5k+', count: 22, avgClv: 8934 },
				],
				topCustomers: Array.from({ length: 20 }, (_, i) => ({
					id: 10000 + i,
					clv: 10000 - i * 300,
					purchases: 45 - i * 2,
					daysActive: 730 - i * 20,
					avgOrder: (10000 - i * 300) / (45 - i * 2),
				})),
				cohorts: [
					{
						name: '2023 Q1',
						months: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
						values: [450, 678, 823, 945, 1089, 1234, 1456, 1567, 1678, 1789, 1890, 2001],
					},
					{
						name: '2023 Q2',
						months: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
						values: [523, 734, 889, 1023, 1178, 1334, 1489, 1645, 1778, 1890, 2012, 2145],
					},
					{
						name: '2023 Q3',
						months: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
						values: [612, 823, 978, 1145, 1312, 1478, 1634, 1789, 1934, 2089, 2234, 2389],
					},
					{
						name: '2023 Q4',
						months: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
						values: [734, 934, 1123, 1312, 1489, 1667, 1845, 2023, 2189, 2356, 2523, 2689],
					},
				],
			});
		}, 500);
	});
}

function generateMockRFMData() {
	return new Promise((resolve) => {
		setTimeout(() => {
			resolve({
				segments: [
					{ name: 'Champions', count: 156, revenue: 487650, avgClv: 3125, icon: '🏆' },
					{ name: 'Loyal', count: 234, revenue: 398730, avgClv: 1704, icon: '⭐' },
					{ name: 'Potential', count: 189, revenue: 245670, avgClv: 1300, icon: '🌱' },
					{ name: 'At Risk', count: 98, revenue: 156890, avgClv: 1601, icon: '⚠️' },
					{ name: 'Lost', count: 67, revenue: 89340, avgClv: 1333, icon: '❌' },
				],
				scatterData: [
					// Champions
					...Array.from({ length: 20 }, () => ({
						value: [4 + Math.random(), 4 + Math.random(), 3000 + Math.random() * 2000],
						segment: 'Champions',
						count: Math.floor(5 + Math.random() * 10),
						revenue: 15000 + Math.random() * 10000,
					})),
					// Loyal
					...Array.from({ length: 25 }, () => ({
						value: [3 + Math.random(), 4 + Math.random(), 1500 + Math.random() * 1000],
						segment: 'Loyal',
						count: Math.floor(8 + Math.random() * 12),
						revenue: 12000 + Math.random() * 8000,
					})),
					// Potential
					...Array.from({ length: 20 }, () => ({
						value: [3 + Math.random() * 2, 2 + Math.random(), 1000 + Math.random() * 800],
						segment: 'Potential',
						count: Math.floor(6 + Math.random() * 10),
						revenue: 8000 + Math.random() * 6000,
					})),
					// At Risk
					...Array.from({ length: 15 }, () => ({
						value: [1 + Math.random(), 3 + Math.random(), 1500 + Math.random() * 1000],
						segment: 'At Risk',
						count: Math.floor(4 + Math.random() * 8),
						revenue: 6000 + Math.random() * 5000,
					})),
					// Lost
					...Array.from({ length: 10 }, () => ({
						value: [Math.random(), 1 + Math.random(), 800 + Math.random() * 700],
						segment: 'Lost',
						count: Math.floor(2 + Math.random() * 6),
						revenue: 3000 + Math.random() * 3000,
					})),
				],
			});
		}, 500);
	});
}

function generateMockHealthData() {
	return new Promise((resolve) => {
		setTimeout(() => {
			resolve({
				distribution: [
					{ name: 'Healthy', value: 423 },
					{ name: 'Needs Attention', value: 189 },
					{ name: 'At Risk', value: 98 },
					{ name: 'Critical', value: 34 },
				],
				atRiskCustomers: Array.from({ length: 15 }, (_, i) => ({
					id: 20000 + i,
					healthScore: 45 - i * 2,
					churnProb: 65 + i * 2,
					lastPurchase: `${30 + i * 5} days ago`,
					clvAtRisk: 5000 - i * 200,
				})),
				trends: [
					{ month: 'Jan', healthScore: 72, purchases: 234, tickets: 45 },
					{ month: 'Feb', healthScore: 75, purchases: 256, tickets: 38 },
					{ month: 'Mar', healthScore: 73, purchases: 243, tickets: 42 },
					{ month: 'Apr', healthScore: 78, purchases: 278, tickets: 35 },
					{ month: 'May', healthScore: 80, purchases: 289, tickets: 32 },
					{ month: 'Jun', healthScore: 77, purchases: 267, tickets: 39 },
					{ month: 'Jul', healthScore: 82, purchases: 301, tickets: 28 },
					{ month: 'Aug', healthScore: 79, purchases: 283, tickets: 34 },
					{ month: 'Sep', healthScore: 81, purchases: 295, tickets: 31 },
					{ month: 'Oct', healthScore: 84, purchases: 312, tickets: 26 },
					{ month: 'Nov', healthScore: 83, purchases: 306, tickets: 29 },
					{ month: 'Dec', healthScore: 85, purchases: 325, tickets: 24 },
				],
			});
		}, 500);
	});
}

function generateMockFunnelData() {
	return new Promise((resolve) => {
		setTimeout(() => {
			resolve({
				stages: [
					{ name: 'Signups', count: 5420, conversion: 100, avgDays: 0 },
					{ name: 'First Purchase', count: 2847, conversion: 52.5, avgDays: 7 },
					{ name: 'Repeat Purchase', count: 1423, conversion: 50.0, avgDays: 28 },
					{ name: 'Regular Customer', count: 712, conversion: 50.0, avgDays: 45 },
					{ name: 'Loyal Subscriber', count: 356, conversion: 50.0, avgDays: 67 },
				],
				conversionRates: {
					signupToFirstPurchase: 52.5,
					firstToRepeat: 50.0,
					repeatToSubscriber: 50.0,
					overall: 6.6,
				},
				avgDays: {
					signupToFirstPurchase: 7,
					firstToRepeat: 28,
					repeatToSubscriber: 67,
				},
			});
		}, 500);
	});
}

function generateMockGeoData() {
	return new Promise((resolve) => {
		setTimeout(() => {
			resolve({
				topCountries: [
					{ country: 'United States', revenue: 1245670, customers: 523, flag: '🇺🇸' },
					{ country: 'United Kingdom', revenue: 567890, customers: 234, flag: '🇬🇧' },
					{ country: 'Canada', revenue: 398450, customers: 167, flag: '🇨🇦' },
					{ country: 'Australia', revenue: 287650, customers: 123, flag: '🇦🇺' },
					{ country: 'Germany', revenue: 234560, customers: 98, flag: '🇩🇪' },
					{ country: 'France', revenue: 189340, customers: 87, flag: '🇫🇷' },
					{ country: 'Netherlands', revenue: 156780, customers: 76, flag: '🇳🇱' },
					{ country: 'Spain', revenue: 134560, customers: 65, flag: '🇪🇸' },
					{ country: 'Italy', revenue: 112340, customers: 54, flag: '🇮🇹' },
					{ country: 'Japan', revenue: 98760, customers: 43, flag: '🇯🇵' },
				],
				totalCountries: 47,
				internationalPercentage: 42.5,
			});
		}, 500);
	});
}

export default CustomerAnalyticsPage;
