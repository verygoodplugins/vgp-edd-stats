import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { API } from '../utils/api';
import StatCard from '../components/StatCard';
import ChartWrapper from '../components/ChartWrapper';

function RenewalsPage({ dateRange }) {
	const { data: renewalData, isLoading: renewalLoading } = useQuery({
		queryKey: ['renewal-rates', dateRange],
		queryFn: () => API.getRenewalRates(dateRange),
	});

	const { data: upcoming30, isLoading: upcoming30Loading } = useQuery({
		queryKey: ['upcoming-renewals-30'],
		queryFn: () => API.getUpcomingRenewals(30),
	});

	const { data: upcoming365, isLoading: upcoming365Loading } = useQuery({
		queryKey: ['upcoming-renewals-365'],
		queryFn: () => API.getUpcomingRenewals(365),
	});

	const renewalChartOption = {
		tooltip: {
			trigger: 'axis',
			formatter: (params) => {
				const point = params[0];
				return `${point.name}<br/>${point.marker} ${point.seriesName}: ${point.value.toFixed(2)}%`;
			},
		},
		xAxis: {
			type: 'category',
			data: renewalData?.map((d) => d.label) || [],
		},
		yAxis: {
			type: 'value',
			axisLabel: {
				formatter: '{value}%',
			},
		},
		series: [
			{
				name: 'Renewal Rate',
				data: renewalData?.map((d) => parseFloat(d.renewal_rate)) || [],
				type: 'line',
				smooth: true,
				itemStyle: {
					color: '#10b981',
				},
			},
		],
	};

	return (
		<div className="space-y-6">
			<div className="grid grid-cols-1 md:grid-cols-3 gap-6">
				<StatCard
					title="Upcoming Renewals (30 Days)"
					value={upcoming30?.count}
					type="number"
					subtitle={`Est. Revenue: $${upcoming30?.estimated_revenue?.toFixed(2) || 0}`}
					loading={upcoming30Loading}
				/>
				<StatCard
					title="Upcoming Renewals (365 Days)"
					value={upcoming365?.count}
					type="number"
					subtitle={`Est. Revenue: $${upcoming365?.estimated_revenue?.toFixed(2) || 0}`}
					loading={upcoming365Loading}
				/>
			</div>

			<ChartWrapper
				title="First Year Renewal Rates"
				subtitle="Percentage of customers who renewed after first year"
				option={renewalChartOption}
				loading={renewalLoading}
				height={400}
			/>
		</div>
	);
}

export default RenewalsPage;
