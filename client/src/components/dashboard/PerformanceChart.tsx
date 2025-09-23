import Card from '@/components/Card';
import { TrendingUp } from 'lucide-react';
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
    const categories = data.map((d) => d.time);

    const series = [
        {
            name: 'Total Jobs',
            data: data.map((d) => d.total)
        },
        {
            name: 'Pending',
            data: data.map((d) => d.pending)
        },
        {
            name: 'Processing',
            data: data.map((d) => d.processing)
        }
    ];

    const options: ApexCharts.ApexOptions = {
        chart: {
            type: 'area',
            toolbar: { show: false },
            background: 'transparent',
            fontFamily: 'Inter, system-ui, sans-serif',
            animations: {
                enabled: true,
                speed: 800
            }
        },
        stroke: {
            curve: 'smooth',
            width: 3
        },
        colors: ['#6366f1', '#f59e0b', '#3b82f6'],
        dataLabels: { enabled: false },
        xaxis: {
            categories,
            labels: {
                rotateAlways: false,
                style: {
                    fontSize: '12px',
                    colors: '#6b7280'
                }
            },
            axisBorder: {
                show: false
            },
            axisTicks: {
                show: false
            }
        },
        yaxis: {
            min: 0,
            labels: {
                style: {
                    fontSize: '12px',
                    colors: '#6b7280'
                }
            }
        },
        legend: {
            position: 'top',
            fontSize: '14px',
            fontFamily: 'Inter, system-ui, sans-serif',
            markers: {
                size: 6,
                strokeWidth: 0
            }
        },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.4,
                opacityTo: 0.1,
                stops: [0, 90, 100]
            }
        },
        grid: {
            borderColor: '#f3f4f6',
            strokeDashArray: 4,
            xaxis: {
                lines: {
                    show: true
                }
            },
            yaxis: {
                lines: {
                    show: true
                }
            }
        },
        tooltip: {
            enabled: true,
            shared: true,
            intersect: false,
            style: {
                fontSize: '14px'
            }
        },
        responsive: [
            {
                breakpoint: 1024,
                options: {
                    chart: { width: '100%' }
                }
            }
        ]
    };

    const hasData = data.length > 0;
    const latestData = hasData ? data[data.length - 1] : null;

    return (
        <Card>
            <div className="mb-6 flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-green-100 text-green-600 shadow">
                    <TrendingUp className="h-5 w-5" />
                </div>
                <div>
                    <h3 className="text-xl font-bold text-gray-800">Performance Over Time</h3>
                    <p className="text-sm text-gray-500">Real-time job processing trends</p>
                </div>
            </div>

            {!hasData ? (
                <div className="flex flex-col items-center justify-center py-12 text-gray-400">
                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                        <TrendingUp className="h-8 w-8" />
                    </div>
                    <p className="text-lg font-medium">No performance data</p>
                    <p className="text-sm">Data will appear as jobs are processed</p>
                </div>
            ) : (
                <>
                    <div className="relative">
                        <Chart
                            options={options}
                            series={series}
                            type="area"
                            width="100%"
                            height={300}
                        />
                    </div>

                    {latestData && (
                        <div className="mt-6 border-t border-gray-100 pt-4">
                            <div className="grid grid-cols-3 gap-4 text-sm">
                                <div className="text-center">
                                    <div className="text-2xl font-bold text-indigo-600">
                                        {latestData.total}
                                    </div>
                                    <div className="text-gray-500">Total Jobs</div>
                                </div>
                                <div className="text-center">
                                    <div className="text-2xl font-bold text-amber-500">
                                        {latestData.pending}
                                    </div>
                                    <div className="text-gray-500">Pending</div>
                                </div>
                                <div className="text-center">
                                    <div className="text-2xl font-bold text-blue-500">
                                        {latestData.processing}
                                    </div>
                                    <div className="text-gray-500">Processing</div>
                                </div>
                            </div>
                        </div>
                    )}
                </>
            )}
        </Card>
    );
};

export default PerformanceChart;

