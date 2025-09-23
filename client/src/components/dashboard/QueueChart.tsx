import { BarChart3 } from 'lucide-react';
import React from 'react';
import Chart from 'react-apexcharts';
import Card from '../Card';

interface QueueChartProps {
    pending: number;
    processing: number;
    completed: number;
    failed: number;
}

const QueueChart: React.FC<QueueChartProps> = ({ pending, processing, completed, failed }) => {
    const series = [pending, processing, completed, failed];
    const total = series.reduce((sum, value) => sum + value, 0);

    const options: ApexCharts.ApexOptions = {
        chart: {
            type: 'donut',
            toolbar: { show: false },
            background: 'transparent',
            fontFamily: 'Inter, system-ui, sans-serif'
        },
        labels: ['Pending', 'Processing', 'Completed', 'Failed'],
        colors: ['#f59e0b', '#3b82f6', '#10b981', '#ef4444'],
        legend: {
            position: 'bottom',
            fontSize: '14px',
            fontFamily: 'Inter, system-ui, sans-serif',
            markers: {
                size: 6,
                strokeWidth: 0
            },
            itemMargin: {
                horizontal: 10,
                vertical: 5
            }
        },
        dataLabels: {
            enabled: true,
            style: {
                fontSize: '14px',
                fontWeight: 'bold',
                colors: ['#fff']
            }
        },
        stroke: {
            width: 3,
            colors: ['#fff']
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Total Jobs',
                            fontSize: '16px',
                            fontWeight: 'bold',
                            color: '#374151',
                            formatter: function () {
                                return total.toLocaleString();
                            }
                        },
                        value: {
                            show: true,
                            fontSize: '20px',
                            fontWeight: 'bold',
                            color: '#374151',
                            formatter: function (val) {
                                return val;
                            }
                        }
                    }
                }
            }
        },
        tooltip: {
            enabled: true,
            fillSeriesColor: false,
            style: {
                fontSize: '14px'
            }
        },
        responsive: [
            {
                breakpoint: 1024,
                options: {
                    chart: { width: '100%' },
                    legend: { position: 'bottom' }
                }
            }
        ]
    };

    return (
        <Card>
            <div className="mb-6 flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-blue-600 shadow">
                    <BarChart3 className="h-5 w-5" />
                </div>
                <div>
                    <h3 className="text-xl font-bold text-gray-800">Queue Status Distribution</h3>
                    <p className="text-sm text-gray-500">
                        Current job distribution across all queues
                    </p>
                </div>
            </div>

            {total === 0 ? (
                <div className="flex flex-col items-center justify-center py-12 text-gray-400">
                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                        <BarChart3 className="h-8 w-8" />
                    </div>
                    <p className="text-lg font-medium">No jobs in queue</p>
                    <p className="text-sm">Create some jobs to see the distribution</p>
                </div>
            ) : (
                <div className="relative">
                    <Chart
                        options={options}
                        series={series}
                        type="donut"
                        width="100%"
                        height={350}
                    />
                </div>
            )}

            <div className="mt-6 border-t border-gray-100 pt-4">
                <div className="grid grid-cols-2 gap-4 text-sm">
                    <div className="flex items-center justify-between">
                        <span className="text-gray-500">Total Jobs</span>
                        <span className="font-semibold text-gray-800">
                            {total.toLocaleString()}
                        </span>
                    </div>
                    <div className="flex items-center justify-between">
                        <span className="text-gray-500">Active Jobs</span>
                        <span className="font-semibold text-gray-800">
                            {(pending + processing).toLocaleString()}
                        </span>
                    </div>
                </div>
            </div>
        </Card>
    );
};

export default QueueChart;

