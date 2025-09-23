import React from 'react';
import Chart from 'react-apexcharts';

interface QueueChartProps {
    pending: number;
    processing: number;
    completed: number;
    failed: number;
}

const QueueChart: React.FC<QueueChartProps> = ({ pending, processing, completed, failed }) => {
    const series = [pending, processing, completed, failed];

    const options: ApexCharts.ApexOptions = {
        chart: {
            type: 'donut',
            toolbar: { show: false }
        },
        labels: ['Pending', 'Processing', 'Completed', 'Failed'],
        colors: ['#f59e0b', '#3b82f6', '#10b981', '#ef4444'],
        legend: { position: 'bottom' },
        dataLabels: { enabled: true },
        stroke: { width: 2, colors: ['#fff'] },
        responsive: [
            {
                breakpoint: 1024,
                options: { chart: { width: '100%' } }
            }
        ]
    };

    return (
        <div className="bg-white rounded-xl p-6 shadow-lg">
            <h3 className="text-lg font-semibold text-gray-800 mb-4">Queue Status Distribution</h3>
            <Chart options={options} series={series} type="donut" width="100%" />
        </div>
    );
};

export default QueueChart;
