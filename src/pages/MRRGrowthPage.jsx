import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { API, formatCurrency } from '../utils/api';
import StatCard from '../components/StatCard';
import ChartWrapper from '../components/ChartWrapper';

function MRRGrowthPage({ dateRange }) {
	// Fetch data
	const { data: mrrData, isLoading: mrrLoading } = useQuery({
		queryKey: ['mrr-by-month', dateRange],
		queryFn: () => API.getMRRByMonth(dateRange),
	});

	const { data: currentMrrData, isLoading: currentMrrLoading } = useQuery({
		queryKey: ['current-mrr'],
		queryFn: () => API.getCurrentMRR(),
	});

	// Calculate growth rate
	const growthRate = currentMrrData
		? ((currentMrrData.new_mrr - currentMrrData.churned_mrr) / currentMrrData.existing_mrr) * 100
		: 0;

	// Chart option
	const mrrChartOption = {
		tooltip: {
			trigger: 'axis',
			formatter: (params) => {
				let result = `${params[0].name}<br/>`;
				params.forEach((param) => {
					result += `${param.marker} ${param.seriesName}: ${formatCurrency(param.value)}<br/>`;
				});
				return result;
			},
		},
		legend: {
			data: ['MRR', 'Subscriptions'],
			bottom: 0,
		},
		xAxis: {
			type: 'category',
			data: mrrData?.map((d) => d.label) || [],
		},
		yAxis: [
			{
				type: 'value',
				name: 'MRR',
				position: 'left',
				axisLabel: {
					formatter: (value) => formatCurrency(value),
				},
			},
			{
				type: 'value',
				name: 'Subscriptions',
				position: 'right',
			},
		],
		series: [
			{
				name: 'MRR',
				data: mrrData?.map((d) => parseFloat(d.mrr)) || [],
				type: 'line',
				smooth: true,
				yAxisIndex: 0,
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
			{
				name: 'Subscriptions',
				data: mrrData?.map((d) => parseInt(d.subscriptions)) || [],
				type: 'bar',
				yAxisIndex: 1,
				itemStyle: {
					color: '#10b981',
				},
			},
		],
	};

	return (
		<div className="space-y-6">
			{/* Current Month MRR Breakdown */}
			<div className="grid grid-cols-1 md:grid-cols-4 gap-6">
				<StatCard
					title="New MRR"
					value={currentMrrData?.new_mrr}
					type="currency"
					subtitle="New subscriptions this month"
					loading={currentMrrLoading}
				/>
				<StatCard
					title="Existing MRR"
					value={currentMrrData?.existing_mrr}
					type="currency"
					subtitle="Active from previous months"
					loading={currentMrrLoading}
				/>
				<StatCard
					title="Churned MRR"
					value={currentMrrData?.churned_mrr}
					type="currency"
					subtitle="Cancelled/expired this month"
					loading={currentMrrLoading}
				/>
				<StatCard
					title="Net MRR"
					value={currentMrrData?.net_mrr}
					type="currency"
					subtitle="Total recurring revenue"
					loading={currentMrrLoading}
				/>
			</div>

			{/* Growth Rate */}
			<div className="grid grid-cols-1 md:grid-cols-3 gap-6">
				<StatCard
					title="Growth Rate"
					value={growthRate}
					type="percentage"
					subtitle="Month-over-month growth"
					loading={currentMrrLoading}
				/>
				<StatCard
					title="Net New MRR"
					value={currentMrrData ? currentMrrData.new_mrr - currentMrrData.churned_mrr : 0}
					type="currency"
					subtitle="Net change this month"
					loading={currentMrrLoading}
				/>
			</div>

			{/* MRR Chart */}
			<ChartWrapper
				title="MRR and Subscriptions Over Time"
				subtitle="Monthly recurring revenue and active subscription count"
				option={mrrChartOption}
				loading={mrrLoading}
				height={450}
			/>
		</div>
	);
}

export default MRRGrowthPage;
