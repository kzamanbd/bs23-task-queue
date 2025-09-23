import React from 'react';
import Chart from 'react-apexcharts';

interface PerformanceDataPoint {
    time: string;
    total: number;
    pending: number;
    processing: number;
}

interface PerformanceChartProps {
    data: PerformanceDataPoint[];
}

const PerformanceChart: React.FC<PerformanceChartProps> = ({ data }) => {
    const categories = data.map(d => d.time);

    const series = [
        {
            name: 'Total Jobs',
            data: data.map(d => d.total)
        },
        {
            name: 'Pending',
            data: data.map(d => d.pending)
        },
        {
            name: 'Processing',
            data: data.map(d => d.processing)
        }
    ];

    const options: ApexCharts.ApexOptions = {
        chart: {
            type: 'line',
            toolbar: { show: false }
        },
        stroke: { curve: 'smooth' },
        colors: ['#6366f1', '#f59e0b', '#3b82f6'],
        dataLabels: { enabled: false },
        xaxis: {
            categories,
            labels: { rotateAlways: false }
        },
        yaxis: {
            min: 0
        },
        legend: { position: 'top' },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.2,
                opacityTo: 0.0,
                stops: [0, 90, 100]
            }
        }
    };

    return (
        <div className="bg-white rounded-xl p-6 shadow-lg">
            <h3 className="text-lg font-semibold text-gray-800 mb-4">Queue Performance Over Time</h3>
            <Chart options={options} series={series} type="line" width="100%" />
        </div>
    );
};

export default PerformanceChart;
