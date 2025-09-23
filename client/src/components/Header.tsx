import React from 'react';

const Header: React.FC = () => {
    return (
        <div className="bg-gradient-to-br from-indigo-500 to-purple-600 text-white py-8">
            <div className="max-w-7xl mx-auto px-8 text-center md:text-left flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 className="text-3xl md:text-4xl font-bold mb-1">🚀 Task Queue Dashboard</h1>
                    <p className="text-white/90">Real-time monitoring and management</p>
                </div>
                <div className="text-white/80 text-sm">
                    <span className="hidden md:inline">Built with React · </span>
                    <span>Powered by PHP API</span>
                </div>
            </div>
        </div>
    );
};

export default Header;
