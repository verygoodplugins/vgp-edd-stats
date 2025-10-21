import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { API } from '../utils/api';
import ChartWrapper from '../components/ChartWrapper';

function RefundsPage({ dateRange }) {
	const { data: refundData, isLoading: refundLoading } = useQuery({
		queryKey: ['refund-rates', dateRange],
		queryFn: () => API.getRefundRates(dateRange),
	});

	const refundChartOption = {
		tooltip: {
			trigger: 'axis',
			formatter: (params) => {
				const point = params[0];
				return `${point.name}<br/>${point.marker} ${point.seriesName}: ${point.value.toFixed(2)}%`;
			},
		},
		xAxis: {
			type: 'category',
			data: refundData?.map((d) => d.label) || [],
		},
		yAxis: {
			type: 'value',
			axisLabel: {
				formatter: '{value}%',
			},
		},
		series: [
			{
				name: 'Refund Rate',
				data: refundData?.map((d) => parseFloat(d.refund_rate)) || [],
				type: 'line',
				smooth: true,
				itemStyle: {
					color: '#ef4444',
				},
			},
		],
	};

	return (
		<div className="space-y-6">
			<ChartWrapper
				title="Refund Rates by Month"
				subtitle="Percentage of orders refunded each month"
				option={refundChartOption}
				loading={refundLoading}
				height={400}
			/>
		</div>
	);
}

export default RefundsPage;
