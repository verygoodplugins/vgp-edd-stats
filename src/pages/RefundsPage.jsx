import React, { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { API, formatCurrency } from '../utils/api';
import ChartWrapper from '../components/ChartWrapper';
import StatCard from '../components/StatCard';

function RefundsPage({ dateRange }) {
    const { data: refundData, isLoading: refundLoading } = useQuery({
        queryKey: ['refund-rates', dateRange],
        queryFn: () => API.getRefundRates(dateRange),
    });

    // Unfiltered monthly dataset to mirror Appsmith "last 12 months" stat regardless of UI selection
    const { data: refundAllData } = useQuery({
        queryKey: ['refund-rates-all'],
        queryFn: () => API.getRefundRates(),
    });

    // New customer refunds by year
    const { data: newCustomerRefunds, isLoading: newCustomerLoading } = useQuery({
        queryKey: ['refunds-new-customers-yearly'],
        queryFn: () => API.getNewCustomerRefundsByYear(),
    });

    // Stat: average refund rate last 12 months (from unfiltered series)
    const avg12 = useMemo(() => {
        if (!refundAllData || refundAllData.length === 0) return null;
        const last12 = refundAllData.slice(-12);
        if (last12.length === 0) return null;
        const vals = last12.map((d) => parseFloat(d.refund_rate)).filter((n) => !Number.isNaN(n));
        if (vals.length === 0) return null;
        return vals.reduce((a, b) => a + b, 0) / vals.length;
    }, [refundAllData]);

    // Stat: last month refund rate (from unfiltered series)
    const lastMonthRate = useMemo(() => {
        if (!refundAllData || refundAllData.length === 0) return null;
        const last = refundAllData[refundAllData.length - 1];
        return last ? parseFloat(last.refund_rate) : null;
    }, [refundAllData]);

    // Refunded revenue stats (aggregate from monthly refunded revenue series)
    const { data: refundedRevenueAll } = useQuery({
        queryKey: ['refunded-revenue-all'],
        queryFn: () => API.getRefundedRevenue(),
    });

    const refunded12 = useMemo(() => {
        if (!refundedRevenueAll || refundedRevenueAll.length === 0) return null;
        const last12 = refundedRevenueAll.slice(-12);
        if (last12.length === 0) return null;
        const total = last12.reduce((sum, r) => sum + (parseFloat(r.value) || 0), 0);
        return total;
    }, [refundedRevenueAll]);

    const refundedLastMonth = useMemo(() => {
        if (!refundedRevenueAll || refundedRevenueAll.length === 0) return null;
        const last = refundedRevenueAll[refundedRevenueAll.length - 1];
        return last ? (parseFloat(last.value) || 0) : null;
    }, [refundedRevenueAll]);

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

    const newCustomerChartOption = {
        tooltip: {
            trigger: 'axis',
            formatter: (params) => {
                const p = params[0];
                return `${p.name}<br/>${p.marker} ${p.seriesName}: ${p.value.toFixed(2)}%`;
            },
        },
        xAxis: {
            type: 'category',
            data: newCustomerRefunds?.map((d) => d.year) || [],
        },
        yAxis: {
            type: 'value',
            axisLabel: { formatter: '{value}%' },
        },
        series: [
            {
                name: 'New Customer Refund Rate',
                data: newCustomerRefunds?.map((d) => parseFloat(d.refund_rate)) || [],
                type: 'line',
                smooth: true,
                itemStyle: { color: '#6366f1' },
            },
        ],
    };

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <StatCard
                    title="Refund Rate Avg. 12 mo."
                    value={avg12}
                    type="percentage"
                    loading={refundLoading}
                    subtitle={refunded12 !== null ? `Refunded: ${formatCurrency(refunded12)}` : undefined}
                />
                <StatCard
                    title="Refund Rate Last Month"
                    value={lastMonthRate}
                    type="percentage"
                    loading={refundLoading}
                    subtitle={refundedLastMonth !== null ? `Refunded: ${formatCurrency(refundedLastMonth)}` : undefined}
                />
            </div>

            <ChartWrapper
                title="Refund Rates by Month"
                subtitle="Percentage of orders refunded each month"
                option={refundChartOption}
                loading={refundLoading}
                height={400}
            />

            <ChartWrapper
                title="New Customer Refunds by Year"
                subtitle="Refund rate for first-time orders (parent = 0)"
                option={newCustomerChartOption}
                loading={newCustomerLoading}
                height={420}
            />
        </div>
    );
}

export default RefundsPage;
