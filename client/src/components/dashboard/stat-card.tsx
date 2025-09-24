import React from 'react';
import Card from '../shared/card-ui';

interface StatCardProps {
    title: string;
    count: number;
    color: string;
    isLoading?: boolean;
    icon?: React.ReactNode;
}

const StatCard: React.FC<StatCardProps> = ({ title, count, color, isLoading = false, icon }) => {
    return (
        <Card className="group cursor-pointer">
            <div className="mb-4 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-600 shadow transition-transform duration-300 group-hover:scale-110">
                        {icon}
                    </div>
                    <div>
                        <h3 className="text-sm font-semibold tracking-wide text-gray-600 uppercase">
                            {title}
                        </h3>
                        <div className="mt-1 text-xs text-gray-400">Current count</div>
                    </div>
                </div>

                {!isLoading && (
                    <div className="flex items-center gap-1">
                        <div
                            className={`h-2 w-2 rounded-full ${color.replace('text-', 'bg-')} animate-pulse`}></div>
                    </div>
                )}
            </div>

            <div className="flex items-end justify-between">
                <div
                    className={`text-4xl font-bold ${color} transition-transform duration-300 group-hover:scale-105`}>
                    {isLoading ? (
                        <div className="skeleton h-10 w-24 rounded"></div>
                    ) : (
                        <span className="bg-gradient-to-r from-gray-800 to-gray-600 bg-clip-text text-transparent">
                            {count.toLocaleString()}
                        </span>
                    )}
                </div>

                {!isLoading && count > 0 && (
                    <div className="text-right">
                        <div className="text-xs text-gray-400">Jobs</div>
                    </div>
                )}
            </div>

            {!isLoading && (
                <div className="mt-4 border-t border-gray-100 pt-4">
                    <div className="flex items-center justify-between text-xs text-gray-500">
                        <span>Status</span>
                        <span className="font-medium capitalize">
                            {count > 0 ? 'Active' : 'Idle'}
                        </span>
                    </div>
                </div>
            )}
        </Card>
    );
};

export default StatCard;

