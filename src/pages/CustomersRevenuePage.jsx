import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { API } from '../utils/api';
import StatCard from '../components/StatCard';
import ChartWrapper from '../components/ChartWrapper';

function CustomersRevenuePage({ dateRange }) {
	// Fetch data
	const { data: customersData, isLoading: customersLoading } = useQuery({
		queryKey: ['customers-by-month', dateRange],
		queryFn: () => API.getCustomersByMonth(dateRange),
	});

	const { data: yoyData, isLoading: yoyLoading } = useQuery({
		queryKey: ['customers-yoy'],
		queryFn: () => API.getCustomersYoY(),
	});

	const { data: revenueData, isLoading: revenueLoading } = useQuery({
		queryKey: ['revenue-by-month', dateRange],
		queryFn: () => API.getRevenueByMonth(dateRange),
	});

	// Chart options
	const customersChartOption = {
		title: {
			text: '',
		},
		tooltip: {
			trigger: 'axis',
			formatter: (params) => {
				const point = params[0];
				return `${point.name}<br/>${point.marker} ${point.seriesName}: ${point.value}`;
			},
		},
		xAxis: {
			type: 'category',
			data: customersData?.map((d) => d.label) || [],
		},
		yAxis: {
			type: 'value',
		},
		series: [
			{
				name: 'New Customers',
				data: customersData?.map((d) => d.value) || [],
				type: 'line',
				smooth: true,
				itemStyle: {
					color: '#0ea5e9',
				},
				areaStyle: {
					color: {
						type: 'linear',
						x: 0,
						y: 0,
						x2: 0,
						y2: 1,
						colorStops: [
							{ offset: 0, color: 'rgba(14, 165, 233, 0.3)' },
							{ offset: 1, color: 'rgba(14, 165, 233, 0.05)' },
						],
					},
				},
			},
		],
	};

	const revenueChartOption = {
		tooltip: {
			trigger: 'axis',
			formatter: (params) => {
				let result = `${params[0].name}<br/>`;
				params.forEach((param) => {
					const value = new Intl.NumberFormat('en-US', {
						style: 'currency',
						currency: 'USD',
					}).format(param.value);
					result += `${param.marker} ${param.seriesName}: ${value}<br/>`;
				});
				return result;
			},
		},
		legend: {
			data: ['New Revenue', 'Recurring Revenue'],
			bottom: 0,
		},
		xAxis: {
			type: 'category',
			data: revenueData?.map((d) => d.label) || [],
		},
		yAxis: {
			type: 'value',
			axisLabel: {
				formatter: (value) => `$${(value / 1000).toFixed(0)}k`,
			},
		},
		series: [
			{
				name: 'New Revenue',
				data: revenueData?.map((d) => parseFloat(d.new_revenue)) || [],
				type: 'bar',
				stack: 'total',
				itemStyle: {
					color: '#10b981',
				},
			},
			{
				name: 'Recurring Revenue',
				data: revenueData?.map((d) => parseFloat(d.recurring_revenue)) || [],
				type: 'bar',
				stack: 'total',
				itemStyle: {
					color: '#0ea5e9',
				},
			},
		],
	};

	return (
		<div className="space-y-6">
			{/* Stats Row */}
			<div className="grid grid-cols-1 md:grid-cols-3 gap-6">
				<StatCard
					title="New Customers This Year"
					value={yoyData?.current_year}
					type="number"
					loading={yoyLoading}
				/>
				<StatCard
					title="New Customers Last Year"
					value={yoyData?.last_year}
					type="number"
					loading={yoyLoading}
				/>
				<StatCard
					title="Year-over-Year Change"
					value={yoyData?.current_year}
					type="number"
					change={yoyData?.change}
					loading={yoyLoading}
				/>
			</div>

			{/* Charts */}
			<ChartWrapper
				title="New Customers by Month"
				subtitle="Number of new customers acquired each month"
				option={customersChartOption}
				loading={customersLoading}
				height={400}
			/>

			<ChartWrapper
				title="Revenue: New vs. Recurring"
				subtitle="Monthly revenue breakdown by source"
				option={revenueChartOption}
				loading={revenueLoading}
				height={400}
			/>
		</div>
	);
}

export default CustomersRevenuePage;
