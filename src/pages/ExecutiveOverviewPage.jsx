import React, { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import ReactECharts from 'echarts-for-react';
import StatCard from '../components/StatCard';
import ChartWrapper from '../components/ChartWrapper';
import { API } from '../utils/api';

/**
 * Executive Overview Dashboard - Main business health and metrics overview
 *
 * Features:
 * - Business Health Score gauge
 * - Real-time revenue ticker
 * - Key business metrics grid
 * - Revenue trend sparklines
 * - Quick wins and opportunities
 * - At-risk customers alerts
 * - 30-day forecast
 * - Recent activity feed
 */
function ExecutiveOverviewPage({ dateRange }) {
    // Fetch all necessary data with React Query
    const { data: summaryData, isLoading: summaryLoading } = useQuery({
        queryKey: ['summary', dateRange],
        queryFn: () => API.getSummary(dateRange),
    });

    const { data: revenueData, isLoading: revenueLoading } = useQuery({
        queryKey: ['revenue-overview', dateRange],
        queryFn: () => API.getRevenueOverview(dateRange),
    });

    const { data: mrrData, isLoading: mrrLoading } = useQuery({
        queryKey: ['mrr', dateRange],
        queryFn: () => API.getMRR(dateRange),
    });

    const { data: churnData, isLoading: churnLoading } = useQuery({
        queryKey: ['churn', dateRange],
        queryFn: () => API.getChurnRate(dateRange),
    });

    const { data: customerData, isLoading: customerLoading } = useQuery({
        queryKey: ['customers-revenue', dateRange],
        queryFn: () => API.getCustomersRevenue(dateRange),
    });

    // Calculate Business Health Score (0-100)
    const healthScore = useMemo(() => {
        if (!summaryData?.data || !churnData?.data || !mrrData?.data) return null;

        // Health factors (each 0-100)
        const revenueHealth = summaryData.data.total_revenue > 0 ?
            Math.min((summaryData.data.revenue_change || 0) + 50, 100) : 50;

        const churnHealth = churnData.data.churn_rate ?
            Math.max(100 - (parseFloat(churnData.data.churn_rate) * 10), 0) : 100;

        const mrrHealth = mrrData.data.current_mrr > 0 ?
            Math.min((mrrData.data.mrr_growth || 0) + 50, 100) : 50;

        const customerHealth = summaryData.data.new_customers > 0 ?
            Math.min((summaryData.data.customer_change || 0) + 50, 100) : 50;

        // Weighted average
        const score = (
            revenueHealth * 0.35 +
            churnHealth * 0.25 +
            mrrHealth * 0.25 +
            customerHealth * 0.15
        );

        return Math.round(score);
    }, [summaryData, churnData, mrrData]);

    // Health score color and status
    const getHealthStatus = (score) => {
        if (!score) return { color: '#94a3b8', status: 'Loading...', gradient: 'from-slate-500 to-slate-600' };
        if (score >= 80) return { color: '#10b981', status: 'Excellent', gradient: 'from-green-500 to-emerald-600' };
        if (score >= 60) return { color: '#f59e0b', status: 'Good', gradient: 'from-yellow-500 to-orange-600' };
        if (score >= 40) return { color: '#ef4444', status: 'Needs Attention', gradient: 'from-orange-500 to-red-600' };
        return { color: '#dc2626', status: 'Critical', gradient: 'from-red-600 to-red-700' };
    };

    const healthStatus = getHealthStatus(healthScore);

    // Business Health Gauge Chart
    const gaugeOption = {
        series: [
            {
                type: 'gauge',
                startAngle: 180,
                endAngle: 0,
                min: 0,
                max: 100,
                splitNumber: 10,
                axisLine: {
                    lineStyle: {
                        width: 30,
                        color: [
                            [0.4, '#dc2626'],
                            [0.6, '#f59e0b'],
                            [0.8, '#3b82f6'],
                            [1, '#10b981']
                        ]
                    }
                },
                pointer: {
                    itemStyle: {
                        color: 'auto'
                    }
                },
                axisTick: {
                    distance: -30,
                    length: 8,
                    lineStyle: {
                        color: '#fff',
                        width: 2
                    }
                },
                splitLine: {
                    distance: -30,
                    length: 30,
                    lineStyle: {
                        color: '#fff',
                        width: 4
                    }
                },
                axisLabel: {
                    color: 'auto',
                    distance: 40,
                    fontSize: 14
                },
                detail: {
                    valueAnimation: true,
                    formatter: '{value}',
                    color: 'auto',
                    fontSize: 40,
                    offsetCenter: [0, '70%']
                },
                data: [
                    {
                        value: healthScore || 0,
                        name: healthStatus.status
                    }
                ],
                title: {
                    offsetCenter: [0, '90%'],
                    fontSize: 16,
                    color: '#64748b'
                }
            }
        ]
    };

    // Revenue Sparkline Chart
    const sparklineOption = useMemo(() => {
        if (!revenueData?.data?.daily_revenue) return null;

        const dates = revenueData.data.daily_revenue.map(item => item.date);
        const values = revenueData.data.daily_revenue.map(item => parseFloat(item.revenue));

        return {
            grid: { left: 0, right: 0, top: 5, bottom: 5 },
            xAxis: {
                type: 'category',
                data: dates,
                show: false
            },
            yAxis: {
                type: 'value',
                show: false
            },
            series: [
                {
                    data: values,
                    type: 'line',
                    smooth: true,
                    symbol: 'none',
                    lineStyle: {
                        color: '#0ea5e9',
                        width: 3
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
                                { offset: 1, color: 'rgba(14, 165, 233, 0.05)' }
                            ]
                        }
                    }
                }
            ]
        };
    }, [revenueData]);

    // Today's revenue (last data point)
    const todayRevenue = useMemo(() => {
        if (!revenueData?.data?.daily_revenue?.length) return 0;
        const last = revenueData.data.daily_revenue[revenueData.data.daily_revenue.length - 1];
        return parseFloat(last.revenue);
    }, [revenueData]);

    // Quick Wins calculation
    const quickWins = useMemo(() => {
        const wins = [];

        if (summaryData?.data?.revenue_change > 10) {
            wins.push({
                icon: '📈',
                title: 'Strong Revenue Growth',
                description: `Revenue up ${summaryData.data.revenue_change.toFixed(1)}% vs previous period`
            });
        }

        if (mrrData?.data?.mrr_growth > 5) {
            wins.push({
                icon: '💰',
                title: 'MRR Expanding',
                description: `MRR growing ${mrrData.data.mrr_growth.toFixed(1)}% month-over-month`
            });
        }

        if (churnData?.data?.churn_rate < 5) {
            wins.push({
                icon: '🎯',
                title: 'Low Churn Rate',
                description: `Customer retention at ${(100 - parseFloat(churnData.data.churn_rate || 0)).toFixed(1)}%`
            });
        }

        if (summaryData?.data?.new_customers > 50) {
            wins.push({
                icon: '🚀',
                title: 'Customer Acquisition',
                description: `${summaryData.data.new_customers} new customers this period`
            });
        }

        return wins.length > 0 ? wins : [
            {
                icon: '💼',
                title: 'Steady Performance',
                description: 'Continue monitoring key metrics for opportunities'
            }
        ];
    }, [summaryData, mrrData, churnData]);

    // At-risk indicators
    const risks = useMemo(() => {
        const alerts = [];

        if (churnData?.data?.churn_rate > 10) {
            alerts.push({
                severity: 'high',
                title: 'High Churn Rate',
                description: `${churnData.data.churn_rate}% monthly churn needs immediate attention`,
                action: 'Review customer feedback and renewal campaigns'
            });
        }

        if (summaryData?.data?.revenue_change < -10) {
            alerts.push({
                severity: 'high',
                title: 'Revenue Decline',
                description: `Revenue down ${Math.abs(summaryData.data.revenue_change).toFixed(1)}% from previous period`,
                action: 'Analyze sales pipeline and pricing strategy'
            });
        }

        if (mrrData?.data?.mrr_growth < -5) {
            alerts.push({
                severity: 'medium',
                title: 'MRR Contraction',
                description: `MRR declining ${Math.abs(mrrData.data.mrr_growth).toFixed(1)}%`,
                action: 'Focus on upsells and renewals'
            });
        }

        return alerts;
    }, [summaryData, churnData, mrrData]);

    // Loading state - check all data sources
    if (summaryLoading || revenueLoading || mrrLoading || churnLoading || customerLoading) {
        return (
            <div className="flex items-center justify-center min-h-screen">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-sky-500 mx-auto mb-4"></div>
                    <p className="text-slate-600">Loading executive dashboard...</p>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-6 animate-fadeIn">
            {/* Header Section */}
            <div className="bg-gradient-to-r from-slate-800 to-slate-900 rounded-lg shadow-lg p-8 text-white">
                <h1 className="text-3xl font-bold mb-2">Executive Overview</h1>
                <p className="text-slate-300">Real-time business health and performance metrics</p>
            </div>

            {/* Top Row: Health Score + Today's Revenue */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Business Health Gauge */}
                <div className="stat-card bg-white">
                    <div className="mb-4">
                        <h3 className="text-lg font-semibold text-slate-800">Business Health Score</h3>
                        <p className="text-sm text-slate-500">Composite score across key metrics</p>
                    </div>
                    <ChartWrapper
                        option={gaugeOption}
                        loading={summaryLoading || mrrLoading || churnLoading}
                        style={{ height: '300px' }}
                    />
                    <div className={`mt-4 p-4 rounded-lg bg-gradient-to-r ${healthStatus.gradient} text-white text-center`}>
                        <div className="text-2xl font-bold">{healthStatus.status}</div>
                        <div className="text-sm opacity-90">Overall Business Health</div>
                    </div>
                </div>

                {/* Today's Revenue Ticker */}
                <div className="stat-card bg-gradient-to-br from-sky-500 to-blue-600 text-white">
                    <div className="mb-4">
                        <h3 className="text-lg font-semibold">Today's Revenue</h3>
                        <p className="text-sm opacity-90">Real-time daily performance</p>
                    </div>
                    <div className="text-center py-8">
                        <div className="text-5xl font-bold mb-2 animate-pulse">
                            ${todayRevenue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </div>
                        <div className="text-sm opacity-90 mb-6">Current Day Total</div>
                        {sparklineOption && (
                            <div className="mt-4 bg-white bg-opacity-20 rounded-lg p-4">
                                <ReactECharts option={sparklineOption} style={{ height: '100px' }} />
                                <p className="text-xs mt-2 opacity-75">Revenue Trend ({dateRange?.preset === 'all' ? 'All Time' : `Last ${revenueData?.data?.daily_revenue?.length || 0} days`})</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Key Metrics Grid */}
            <div>
                <h2 className="text-xl font-bold text-slate-800 mb-4">Key Performance Indicators</h2>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard
                        title="Monthly Recurring Revenue"
                        value={mrrData?.data?.current_mrr}
                        type="currency"
                        change={mrrData?.data?.mrr_growth}
                        loading={mrrLoading}
                        className="bg-gradient-to-br from-green-50 to-emerald-50 border-l-4 border-green-500"
                    />
                    <StatCard
                        title="Total Revenue"
                        value={summaryData?.data?.total_revenue}
                        type="currency"
                        change={summaryData?.data?.revenue_change}
                        loading={summaryLoading}
                        className="bg-gradient-to-br from-blue-50 to-sky-50 border-l-4 border-blue-500"
                    />
                    <StatCard
                        title="Active Customers"
                        value={customerData?.data?.total_customers}
                        type="number"
                        change={summaryData?.data?.customer_change}
                        loading={customerLoading}
                        className="bg-gradient-to-br from-purple-50 to-violet-50 border-l-4 border-purple-500"
                    />
                    <StatCard
                        title="Churn Rate"
                        value={churnData?.data?.churn_rate}
                        type="percentage"
                        change={churnData?.data?.churn_change}
                        invertColors={true}
                        loading={churnLoading}
                        className="bg-gradient-to-br from-orange-50 to-amber-50 border-l-4 border-orange-500"
                    />
                </div>
            </div>

            {/* Quick Wins & Opportunities */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Quick Wins */}
                <div className="stat-card bg-gradient-to-br from-green-50 to-emerald-50">
                    <h3 className="text-lg font-semibold text-slate-800 mb-4 flex items-center">
                        <span className="text-2xl mr-2">🎉</span>
                        Quick Wins
                    </h3>
                    <div className="space-y-3">
                        {quickWins.map((win, index) => (
                            <div key={index} className="bg-white rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                                <div className="flex items-start">
                                    <span className="text-3xl mr-3">{win.icon}</span>
                                    <div>
                                        <h4 className="font-semibold text-slate-800">{win.title}</h4>
                                        <p className="text-sm text-slate-600 mt-1">{win.description}</p>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* At-Risk Alerts */}
                <div className="stat-card bg-gradient-to-br from-red-50 to-rose-50">
                    <h3 className="text-lg font-semibold text-slate-800 mb-4 flex items-center">
                        <span className="text-2xl mr-2">⚠️</span>
                        Areas Needing Attention
                    </h3>
                    {risks.length > 0 ? (
                        <div className="space-y-3">
                            {risks.map((risk, index) => (
                                <div key={index} className={`bg-white rounded-lg p-4 border-l-4 ${
                                    risk.severity === 'high' ? 'border-red-500' : 'border-orange-500'
                                } shadow-sm`}>
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <h4 className="font-semibold text-slate-800 flex items-center">
                                                {risk.severity === 'high' ? '🔴' : '🟡'} {risk.title}
                                            </h4>
                                            <p className="text-sm text-slate-600 mt-1">{risk.description}</p>
                                            <p className="text-xs text-slate-500 mt-2 italic">
                                                Action: {risk.action}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="bg-white rounded-lg p-6 text-center">
                            <span className="text-5xl mb-3 block">✅</span>
                            <h4 className="font-semibold text-slate-800">All Clear!</h4>
                            <p className="text-sm text-slate-600 mt-2">No critical issues detected at this time</p>
                        </div>
                    )}
                </div>
            </div>

            {/* Bottom Row: Forecast & Activity */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* 30-Day Forecast */}
                <div className="stat-card">
                    <h3 className="text-lg font-semibold text-slate-800 mb-4 flex items-center">
                        <span className="text-2xl mr-2">🔮</span>
                        30-Day Revenue Forecast
                    </h3>
                    <div className="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-lg p-6">
                        <div className="text-center mb-4">
                            <div className="text-3xl font-bold text-indigo-600">
                                ${(todayRevenue * 30).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </div>
                            <p className="text-sm text-slate-600 mt-2">Projected Monthly Revenue</p>
                            <p className="text-xs text-slate-500 mt-1">Based on current daily average</p>
                        </div>
                        <div className="grid grid-cols-2 gap-4 mt-4">
                            <div className="text-center bg-white rounded-lg p-3">
                                <div className="text-lg font-bold text-green-600">
                                    ${((mrrData?.data?.current_mrr || 0) * 1.1).toLocaleString()}
                                </div>
                                <p className="text-xs text-slate-600 mt-1">Best Case MRR</p>
                            </div>
                            <div className="text-center bg-white rounded-lg p-3">
                                <div className="text-lg font-bold text-orange-600">
                                    ${((mrrData?.data?.current_mrr || 0) * 0.9).toLocaleString()}
                                </div>
                                <p className="text-xs text-slate-600 mt-1">Worst Case MRR</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Recent Activity Feed */}
                <div className="stat-card">
                    <h3 className="text-lg font-semibold text-slate-800 mb-4 flex items-center">
                        <span className="text-2xl mr-2">📊</span>
                        Business Insights
                    </h3>
                    <div className="space-y-3">
                        <div className="flex items-center p-3 bg-blue-50 rounded-lg border-l-4 border-blue-500">
                            <span className="text-2xl mr-3">💰</span>
                            <div className="flex-1">
                                <p className="text-sm font-medium text-slate-800">Revenue Performance</p>
                                <p className="text-xs text-slate-600 mt-1">
                                    Total revenue of ${summaryData?.data?.total_revenue?.toLocaleString()} this period
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center p-3 bg-green-50 rounded-lg border-l-4 border-green-500">
                            <span className="text-2xl mr-3">👥</span>
                            <div className="flex-1">
                                <p className="text-sm font-medium text-slate-800">Customer Growth</p>
                                <p className="text-xs text-slate-600 mt-1">
                                    {summaryData?.data?.new_customers} new customers acquired
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center p-3 bg-purple-50 rounded-lg border-l-4 border-purple-500">
                            <span className="text-2xl mr-3">📈</span>
                            <div className="flex-1">
                                <p className="text-sm font-medium text-slate-800">MRR Status</p>
                                <p className="text-xs text-slate-600 mt-1">
                                    Current MRR at ${mrrData?.data?.current_mrr?.toLocaleString()}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center p-3 bg-yellow-50 rounded-lg border-l-4 border-yellow-500">
                            <span className="text-2xl mr-3">🎯</span>
                            <div className="flex-1">
                                <p className="text-sm font-medium text-slate-800">Retention Rate</p>
                                <p className="text-xs text-slate-600 mt-1">
                                    {(100 - parseFloat(churnData?.data?.churn_rate || 0)).toFixed(1)}% of customers retained
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default ExecutiveOverviewPage;
