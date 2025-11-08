import React from 'react';
import ReactECharts from 'echarts-for-react';
import clsx from 'clsx';

function ChartWrapper({
    title,
    subtitle = null,
    option,
    loading = false,
    height = 400,
    className = '',
}) {
    const isEmpty = !option || (Array.isArray(option.series) && option.series.length > 0 && !option.series.some(s => Array.isArray(s.data) && s.data.length > 0));
    return (
        <div className={clsx('chart-container relative', className)}>
            <div className="mb-4">
                <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
                {subtitle && (
                    <p className="text-sm text-gray-500 mt-1">{subtitle}</p>
                )}
            </div>

            <div style={{ height: `${height}px` }}>
                {loading ? (
                    <div className="flex items-center justify-center h-full">
                        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
                    </div>
                ) : isEmpty ? (
                    <div className="flex items-center justify-center h-full text-gray-500">No data</div>
                ) : (
                    <ReactECharts
                        option={option}
                        style={{ height: '100%', width: '100%' }}
                        opts={{ renderer: 'canvas' }}
                    />
                )}
            </div>
        </div>
    );
}

export default ChartWrapper;
