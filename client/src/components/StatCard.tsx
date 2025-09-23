import React from 'react';

interface StatCardProps {
    title: string;
    count: number;
    color: string;
    isLoading?: boolean;
}

const StatCard: React.FC<StatCardProps> = ({ title, count, color, isLoading = false }) => {
    return (
        <div className="bg-white rounded-xl p-6 shadow-lg hover:-translate-y-1 transition-transform duration-200">
            <h3 className="text-gray-500 text-sm font-medium uppercase tracking-wide mb-2">
                {title}
            </h3>
            <div className={`text-4xl font-bold ${color}`}>
                {isLoading ? '-' : count.toLocaleString()}
            </div>
        </div>
    );
};

export default StatCard;
