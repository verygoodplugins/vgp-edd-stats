import React, { useState, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { API, formatCurrency } from '../utils/api';
import StatCard from '../components/StatCard';
import ChartWrapper from '../components/ChartWrapper';

/**
 * Product Performance Dashboard
 *
 * Comprehensive analytics showing:
 * - Revenue ranking and performance metrics
 * - BCG matrix for strategic positioning
 * - Cross-sell/upsell opportunities
 * - Product lifecycle analysis
 * - Profitability analysis
 * - Feature adoption patterns
 */
function ProductPerformancePage({ dateRange }) {
    const [searchTerm, setSearchTerm] = useState('');
    const [sortConfig, setSortConfig] = useState({ key: 'revenue', direction: 'desc' });
    const [selectedQuadrant, setSelectedQuadrant] = useState('all');

    // Use existing endpoints
    const { data: matrixData, isLoading: matrixLoading } = useQuery({
        queryKey: ['product-matrix', dateRange],
        queryFn: () => API.getProductMatrix(dateRange),
    });

    const { data: topProducts, isLoading: topLoading } = useQuery({
        queryKey: ['top-products', dateRange],
        queryFn: () => API.getTopProducts(dateRange, 20),
    });

    // Calculate summary metrics
    const summaryMetrics = useMemo(() => {
        if (!matrixData) return null;

        const products = matrixData || [];
        const totalRevenue = products.reduce((sum, p) => sum + parseFloat(p.current_revenue || 0), 0);
        const totalUnits = products.reduce((sum, p) => sum + parseInt(p.units_sold || 0, 10), 0);
        const avgGrowth = products.length > 0
            ? products.reduce((sum, p) => sum + parseFloat(p.growth_rate || 0), 0) / products.length
            : 0;
        const activeProducts = products.filter(p => parseFloat(p.current_revenue || 0) > 0).length;

        return {
            totalRevenue,
            totalUnits,
            avgGrowth,
            activeProducts,
            avgAOV: totalUnits > 0 ? totalRevenue / totalUnits : 0,
        };
    }, [matrixData]);

    // Filter and sort products
    const filteredProducts = useMemo(() => {
        if (!topProducts) return [];

        let filtered = topProducts.filter(product =>
            (product.product_name || '').toLowerCase().includes(searchTerm.toLowerCase())
        );

        // Apply quadrant filter if needed
        if (selectedQuadrant !== 'all' && filtered.length > 0) {
            const avgRevenue = filtered.reduce((sum, p) => sum + parseFloat(p.total_revenue || 0), 0) / filtered.length;
            const avgGrowth = filtered.reduce((sum, p) => sum + parseFloat(p.growth_rate || 0), 0) / filtered.length;

            filtered = filtered.filter(p => {
                const revenue = parseFloat(p.total_revenue || 0);
                const growth = parseFloat(p.growth_rate || 0);

                switch (selectedQuadrant) {
                    case 'stars':
                        return revenue >= avgRevenue && growth >= avgGrowth;
                    case 'cash-cows':
                        return revenue >= avgRevenue && growth < avgGrowth;
                    case 'question-marks':
                        return revenue < avgRevenue && growth >= avgGrowth;
                    case 'dogs':
                        return revenue < avgRevenue && growth < avgGrowth;
                    default:
                        return true;
                }
            });
        }

        // Sort
        filtered.sort((a, b) => {
            const key = sortConfig.key === 'revenue' ? 'total_revenue' : sortConfig.key;
            const aVal = parseFloat(a[key] || 0);
            const bVal = parseFloat(b[key] || 0);
            return sortConfig.direction === 'asc' ? aVal - bVal : bVal - aVal;
        });

        return filtered;
    }, [topProducts, searchTerm, sortConfig, selectedQuadrant]);

    // BCG Matrix Chart
    const bcgMatrixOption = useMemo(() => {
        if (!matrixData || matrixData.length === 0) return null;

        const products = matrixData;
        const avgRevenue = products.reduce((sum, p) => sum + parseFloat(p.current_revenue || 0), 0) / products.length;
        const avgGrowth = products.reduce((sum, p) => sum + parseFloat(p.growth_rate || 0), 0) / products.length;

        const data = products.map(p => ({
            name: p.product_name,
            value: [
                parseFloat(p.current_revenue || 0),
                parseFloat(p.growth_rate || 0),
                parseFloat(p.current_revenue || 0),
            ],
            itemStyle: {
                color: getQuadrantColor(
                    parseFloat(p.current_revenue || 0),
                    parseFloat(p.growth_rate || 0),
                    avgRevenue,
                    avgGrowth
                ),
            },
        }));

        return {
            title: {
                text: 'Product Portfolio Matrix (BCG)',
                left: 'center',
                textStyle: { fontSize: 16, fontWeight: 600 },
            },
            grid: { left: '10%', right: '10%', top: '15%', bottom: '10%', containLabel: true },
            xAxis: {
                name: 'Revenue →',
                nameLocation: 'middle',
                nameGap: 30,
                type: 'value',
                splitLine: { lineStyle: { type: 'dashed', color: '#e5e7eb' } },
                axisLine: { show: true, lineStyle: { color: '#9ca3af' } },
            },
            yAxis: {
                name: 'Growth Rate (%) →',
                nameLocation: 'middle',
                nameGap: 50,
                type: 'value',
                splitLine: { lineStyle: { type: 'dashed', color: '#e5e7eb' } },
                axisLine: { show: true, lineStyle: { color: '#9ca3af' } },
            },
            series: [
                {
                    type: 'scatter',
                    symbolSize: (val) => Math.max(Math.sqrt(val[2]) / 10, 10),
                    data: data,
                    markLine: {
                        silent: true,
                        lineStyle: { color: '#6b7280', width: 2, type: 'solid' },
                        data: [
                            { xAxis: avgRevenue },
                            { yAxis: avgGrowth },
                        ],
                        label: { show: false },
                    },
                },
            ],
            tooltip: {
                trigger: 'item',
                formatter: (params) => {
                    const [revenue, growth] = params.value;
                    return `<strong>${params.name}</strong><br/>
                            Revenue: ${formatCurrency(revenue)}<br/>
                            Growth: ${growth.toFixed(1)}%`;
                },
            },
        };
    }, [matrixData]);

    // Cross-sell Heatmap
    const crossSellHeatmapOption = useMemo(() => {
        if (!crossSellData?.matrix) return null;

        const matrix = crossSellData.matrix;
        const products = crossSellData.products || [];
        const data = [];

        matrix.forEach((row, i) => {
            row.forEach((count, j) => {
                if (i !== j && count > 0) {
                    data.push([i, j, count]);
                }
            });
        });

        const maxCount = Math.max(...data.map(d => d[2]), 1);

        return {
            title: {
                text: 'Product Cross-sell Matrix',
                left: 'center',
                textStyle: { fontSize: 16, fontWeight: 600 },
            },
            grid: { left: '15%', right: '5%', top: '15%', bottom: '15%', containLabel: true },
            xAxis: {
                type: 'category',
                data: products,
                axisLabel: { rotate: 45, fontSize: 10 },
            },
            yAxis: {
                type: 'category',
                data: products,
                axisLabel: { fontSize: 10 },
            },
            visualMap: {
                min: 0,
                max: maxCount,
                calculable: true,
                orient: 'horizontal',
                left: 'center',
                bottom: '0%',
                inRange: {
                    color: ['#e0f2fe', '#0ea5e9', '#0369a1'],
                },
            },
            series: [
                {
                    type: 'heatmap',
                    data: data,
                    label: { show: false },
                    emphasis: {
                        itemStyle: {
                            shadowBlur: 10,
                            shadowColor: 'rgba(0, 0, 0, 0.5)',
                        },
                    },
                },
            ],
            tooltip: {
                formatter: (params) => {
                    const [x, y, count] = params.value;
                    return `<strong>${products[y]}</strong> → <strong>${products[x]}</strong><br/>
                            Co-purchases: ${count}`;
                },
            },
        };
    }, [crossSellData]);

    // Product Lifecycle Timeline
    const lifecycleTimelineOption = useMemo(() => {
        if (!lifecycleData?.products) return null;

        const products = lifecycleData.products;
        const stages = ['Introduction', 'Growth', 'Maturity', 'Decline'];
        const stageColors = {
            'Introduction': '#10b981',
            'Growth': '#0ea5e9',
            'Maturity': '#f59e0b',
            'Decline': '#ef4444',
        };

        const data = products.map(p => ({
            name: p.name,
            value: [p.name, p.days_since_launch, p.stage],
            itemStyle: { color: stageColors[p.stage] || '#6b7280' },
        }));

        return {
            title: {
                text: 'Product Lifecycle Analysis',
                left: 'center',
                textStyle: { fontSize: 16, fontWeight: 600 },
            },
            grid: { left: '15%', right: '10%', top: '15%', bottom: '10%', containLabel: true },
            xAxis: {
                type: 'value',
                name: 'Days Since Launch →',
                nameLocation: 'middle',
                nameGap: 30,
            },
            yAxis: {
                type: 'category',
                data: products.map(p => p.name),
                axisLabel: { fontSize: 10 },
            },
            series: [
                {
                    type: 'bar',
                    data: data.map(d => ({
                        value: d.value[1],
                        itemStyle: d.itemStyle,
                    })),
                    label: {
                        show: true,
                        position: 'right',
                        formatter: (params) => products[params.dataIndex].stage,
                        fontSize: 10,
                    },
                },
            ],
            tooltip: {
                formatter: (params) => {
                    const product = products[params.dataIndex];
                    return `<strong>${product.name}</strong><br/>
                            Stage: ${product.stage}<br/>
                            Days Active: ${product.days_since_launch}<br/>
                            Revenue Trend: ${product.trend > 0 ? '↑' : '↓'} ${Math.abs(product.trend).toFixed(1)}%`;
                },
            },
        };
    }, [lifecycleData]);

    // Profitability Analysis
    const profitabilityOption = useMemo(() => {
        if (!profitabilityData?.products) return null;

        const products = profitabilityData.products;

        return {
            title: {
                text: 'Product Profitability Analysis',
                left: 'center',
                textStyle: { fontSize: 16, fontWeight: 600 },
            },
            grid: { left: '10%', right: '10%', top: '15%', bottom: '10%', containLabel: true },
            xAxis: {
                type: 'category',
                data: products.map(p => p.name),
                axisLabel: { rotate: 45, fontSize: 10 },
            },
            yAxis: [
                {
                    type: 'value',
                    name: 'Amount ($)',
                    position: 'left',
                    axisLabel: { formatter: (val) => `$${(val / 1000).toFixed(0)}K` },
                },
                {
                    type: 'value',
                    name: 'Margin (%)',
                    position: 'right',
                    axisLabel: { formatter: (val) => `${val.toFixed(0)}%` },
                    min: 0,
                    max: 100,
                },
            ],
            series: [
                {
                    name: 'Revenue',
                    type: 'bar',
                    data: products.map(p => p.revenue),
                    itemStyle: { color: '#0ea5e9' },
                },
                {
                    name: 'Cost',
                    type: 'bar',
                    data: products.map(p => p.cost),
                    itemStyle: { color: '#ef4444' },
                },
                {
                    name: 'Profit Margin',
                    type: 'line',
                    yAxisIndex: 1,
                    data: products.map(p => p.margin),
                    itemStyle: { color: '#10b981' },
                    lineStyle: { width: 3 },
                    symbolSize: 8,
                },
            ],
            legend: {
                data: ['Revenue', 'Cost', 'Profit Margin'],
                bottom: '0%',
            },
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'cross' },
            },
        };
    }, [profitabilityData]);

    // Table sorting handler
    const handleSort = (key) => {
        setSortConfig({
            key,
            direction: sortConfig.key === key && sortConfig.direction === 'desc' ? 'asc' : 'desc',
        });
    };

    // Helper function to get trend icon
    const getTrendIcon = (growthRate) => {
        if (growthRate > 5) return <span className="text-green-500 text-xl">↑</span>;
        if (growthRate < -5) return <span className="text-red-500 text-xl">↓</span>;
        return <span className="text-gray-400 text-xl">→</span>;
    };

    // Helper function to get sparkline data
    const getSparklineOption = (history) => {
        if (!history || history.length === 0) return null;

        return {
            grid: { left: 0, right: 0, top: 0, bottom: 0 },
            xAxis: { type: 'category', show: false, data: history.map((_, i) => i) },
            yAxis: { type: 'value', show: false },
            series: [
                {
                    type: 'line',
                    data: history,
                    smooth: true,
                    symbol: 'none',
                    lineStyle: { width: 2, color: '#0ea5e9' },
                    areaStyle: {
                        color: {
                            type: 'linear',
                            x: 0, y: 0, x2: 0, y2: 1,
                            colorStops: [
                                { offset: 0, color: 'rgba(14, 165, 233, 0.3)' },
                                { offset: 1, color: 'rgba(14, 165, 233, 0.05)' },
                            ],
                        },
                    },
                },
            ],
        };
    };

    // Omitted sections until endpoints exist
    const crossSellLoading = false, lifecycleLoading = false, profitabilityLoading = false;
    const crossSellHeatmapOption = null, lifecycleTimelineOption = null, profitabilityOption = null;

    const isLoading = matrixLoading || topLoading;

    if (isLoading) {
        return (
            <div className="flex items-center justify-center h-64">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <StatCard
                    title="Total Revenue"
                    value={summaryMetrics?.totalRevenue}
                    type="currency"
                    loading={productsLoading}
                />
                <StatCard
                    title="Active Products"
                    value={summaryMetrics?.activeProducts}
                    type="number"
                    loading={productsLoading}
                />
                <StatCard
                    title="Units Sold"
                    value={summaryMetrics?.totalUnits}
                    type="number"
                    loading={productsLoading}
                />
                <StatCard
                    title="Avg AOV"
                    value={summaryMetrics?.avgAOV}
                    type="currency"
                    loading={productsLoading}
                />
                <StatCard
                    title="Avg Growth"
                    value={summaryMetrics?.avgGrowth}
                    type="percentage"
                    loading={productsLoading}
                />
            </div>

            {/* BCG Matrix */}
            <div className="stat-card">
                <div className="mb-4">
                    <h3 className="text-lg font-semibold mb-2">Strategic Portfolio Positioning</h3>
                    <div className="flex flex-wrap gap-2">
                        <button
                            onClick={() => setSelectedQuadrant('all')}
                            className={`px-3 py-1 rounded text-sm font-medium ${
                                selectedQuadrant === 'all'
                                    ? 'bg-blue-500 text-white'
                                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                            }`}
                        >
                            All Products
                        </button>
                        <button
                            onClick={() => setSelectedQuadrant('stars')}
                            className={`px-3 py-1 rounded text-sm font-medium ${
                                selectedQuadrant === 'stars'
                                    ? 'bg-green-500 text-white'
                                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                            }`}
                        >
                            ⭐ Stars
                        </button>
                        <button
                            onClick={() => setSelectedQuadrant('cash-cows')}
                            className={`px-3 py-1 rounded text-sm font-medium ${
                                selectedQuadrant === 'cash-cows'
                                    ? 'bg-blue-500 text-white'
                                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                            }`}
                        >
                            💰 Cash Cows
                        </button>
                        <button
                            onClick={() => setSelectedQuadrant('question-marks')}
                            className={`px-3 py-1 rounded text-sm font-medium ${
                                selectedQuadrant === 'question-marks'
                                    ? 'bg-yellow-500 text-white'
                                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                            }`}
                        >
                            ❓ Question Marks
                        </button>
                        <button
                            onClick={() => setSelectedQuadrant('dogs')}
                            className={`px-3 py-1 rounded text-sm font-medium ${
                                selectedQuadrant === 'dogs'
                                    ? 'bg-red-500 text-white'
                                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                            }`}
                        >
                            🐕 Dogs
                        </button>
                    </div>
                    <div className="mt-2 text-sm text-gray-600">
                        <p className="mb-1"><strong>Stars:</strong> High growth, high revenue - Invest for growth</p>
                        <p className="mb-1"><strong>Cash Cows:</strong> Low growth, high revenue - Maximize profit</p>
                        <p className="mb-1"><strong>Question Marks:</strong> High growth, low revenue - Evaluate potential</p>
                        <p><strong>Dogs:</strong> Low growth, low revenue - Consider discontinuation</p>
                    </div>
                </div>
                <ChartWrapper option={bcgMatrixOption} loading={matrixLoading} height="500px" />
            </div>

            {/* Product Performance Table */}
            <div className="stat-card">
                <div className="mb-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <h3 className="text-lg font-semibold">Product Revenue Ranking</h3>
                    <input
                        type="text"
                        placeholder="Search products..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Product
                                </th>
                                <th
                                    className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                    onClick={() => handleSort('revenue')}
                                >
                                    Revenue {sortConfig.key === 'revenue' && (sortConfig.direction === 'asc' ? '↑' : '↓')}
                                </th>
                                <th
                                    className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                    onClick={() => handleSort('units_sold')}
                                >
                                    Units {sortConfig.key === 'units_sold' && (sortConfig.direction === 'asc' ? '↑' : '↓')}
                                </th>
                                <th
                                    className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                    onClick={() => handleSort('aov')}
                                >
                                    AOV {sortConfig.key === 'aov' && (sortConfig.direction === 'asc' ? '↑' : '↓')}
                                </th>
                                <th
                                    className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                    onClick={() => handleSort('growth_rate')}
                                >
                                    Growth {sortConfig.key === 'growth_rate' && (sortConfig.direction === 'asc' ? '↑' : '↓')}
                                </th>
                                <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Trend
                                </th>
                                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    History
                                </th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {filteredProducts.map((product, index) => (
                                <tr key={index} className="hover:bg-gray-50">
                                    <td className="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {product.product_name || product.name}
                                    </td>
                                    <td className="px-4 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                        {formatCurrency(parseFloat(product.total_revenue || product.revenue || 0))}
                                    </td>
                                    <td className="px-4 py-4 whitespace-nowrap text-sm text-right text-gray-600">
                                        {parseInt(product.units_sold || 0, 10).toLocaleString()}
                                    </td>
                                    <td className="px-4 py-4 whitespace-nowrap text-sm text-right text-gray-600">
                                        {formatCurrency(parseFloat(product.aov || 0))}
                                    </td>
                                    <td className="px-4 py-4 whitespace-nowrap text-sm text-right">
                                        <span
                                            className={`font-semibold ${
                                                parseFloat(product.growth_rate || 0) > 0
                                                    ? 'text-green-600'
                                                    : parseFloat(product.growth_rate || 0) < 0
                                                    ? 'text-red-600'
                                                    : 'text-gray-600'
                                            }`}
                                        >
                                            {parseFloat(product.growth_rate || 0).toFixed(1)}%
                                        </span>
                                    </td>
                                    <td className="px-4 py-4 whitespace-nowrap text-center">
                                        {getTrendIcon(parseFloat(product.growth_rate || 0))}
                                    </td>
                                    <td className="px-4 py-4 whitespace-nowrap">
                                        {product.revenue_history && product.revenue_history.length > 0 && (
                                            <div style={{ width: '100px', height: '40px' }}>
                                                <ReactECharts
                                                    option={getSparklineOption(product.revenue_history)}
                                                    style={{ height: '40px', width: '100px' }}
                                                    opts={{ renderer: 'canvas' }}
                                                />
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {filteredProducts.length === 0 && (
                    <div className="text-center py-8 text-gray-500">
                        No products found matching your criteria
                    </div>
                )}
            </div>

            {/* Cross-sell Matrix */}
            <div className="stat-card">
                <h3 className="text-lg font-semibold mb-4">Cross-sell Opportunities</h3>
                <p className="text-sm text-gray-600 mb-4">
                    Darker cells indicate products frequently purchased together. Identify bundle opportunities and upsell patterns.
                </p>
                <ChartWrapper option={crossSellHeatmapOption} loading={crossSellLoading} height="600px" />
            </div>

            {/* Product Lifecycle */}
            <div className="stat-card">
                <h3 className="text-lg font-semibold mb-4">Product Lifecycle Analysis</h3>
                <p className="text-sm text-gray-600 mb-4">
                    Track products through their lifecycle stages. Focus growth investments on Introduction/Growth stages, maximize profitability in Maturity, and plan transitions for Decline stage products.
                </p>
                <ChartWrapper option={lifecycleTimelineOption} loading={lifecycleLoading} height="500px" />
            </div>

            {/* Profitability Analysis */}
            <div className="stat-card">
                <h3 className="text-lg font-semibold mb-4">Profitability by Product</h3>
                <p className="text-sm text-gray-600 mb-4">
                    Compare revenue, costs, and profit margins. High-margin products are prime candidates for increased marketing investment.
                </p>
                <ChartWrapper option={profitabilityOption} loading={profitabilityLoading} height="400px" />
            </div>

            {/* Top Insights Panel */}
            <div className="stat-card bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-500">
                <h3 className="text-lg font-semibold mb-4 text-blue-900">Strategic Insights</h3>
                <div className="space-y-3">
                    {summaryMetrics && (
                        <>
                            <div className="flex items-start gap-3">
                                <span className="text-2xl">⭐</span>
                                <div>
                                    <p className="font-semibold text-gray-900">Stars to Watch</p>
                                    <p className="text-sm text-gray-700">
                                        Products with both high growth (&gt;{summaryMetrics.avgGrowth.toFixed(0)}%) and high revenue
                                        require continued investment to maintain momentum.
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <span className="text-2xl">💰</span>
                                <div>
                                    <p className="font-semibold text-gray-900">Cash Cow Optimization</p>
                                    <p className="text-sm text-gray-700">
                                        High-revenue, low-growth products should focus on efficiency and profit maximization rather than growth investments.
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <span className="text-2xl">❓</span>
                                <div>
                                    <p className="font-semibold text-gray-900">Question Marks Need Evaluation</p>
                                    <p className="text-sm text-gray-700">
                                        High-growth but low-revenue products require strategic decisions: invest heavily to become Stars, or divest.
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <span className="text-2xl">🔗</span>
                                <div>
                                    <p className="font-semibold text-gray-900">Cross-sell Opportunities</p>
                                    <p className="text-sm text-gray-700">
                                        Review the heatmap for strong product affinities. Create bundles or automated upsell sequences for frequently co-purchased items.
                                    </p>
                                </div>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}

/**
 * Helper: Determine BCG quadrant color
 */
function getQuadrantColor(revenue, growth, avgRevenue, avgGrowth) {
    if (revenue >= avgRevenue && growth >= avgGrowth) return '#10b981'; // Stars - Green
    if (revenue >= avgRevenue && growth < avgGrowth) return '#0ea5e9'; // Cash Cows - Blue
    if (revenue < avgRevenue && growth >= avgGrowth) return '#f59e0b'; // Question Marks - Yellow
    return '#ef4444'; // Dogs - Red
}

export default ProductPerformancePage;
