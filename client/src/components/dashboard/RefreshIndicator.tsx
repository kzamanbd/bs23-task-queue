import { Check } from 'lucide-react';
import React, { useEffect, useState } from 'react';

interface RefreshIndicatorProps {
    show: boolean;
}

const RefreshIndicator: React.FC<RefreshIndicatorProps> = ({ show }) => {
    const [isVisible, setIsVisible] = useState(false);

    useEffect(() => {
        if (show) {
            setIsVisible(true);
            const timer = setTimeout(() => {
                setIsVisible(false);
            }, 3000);
            return () => clearTimeout(timer);
        }
    }, [show]);

    return (
        <div
            className={`fixed top-6 right-6 z-50 transform transition-all duration-500 ${isVisible ? 'translate-y-0 opacity-100' : '-translate-y-2 opacity-0'}`}>
            <div className="flex items-center gap-3 rounded-2xl border border-white/20 bg-green-600 px-6 py-3 text-white shadow-lg backdrop-blur-sm">
                <div className="flex h-6 w-6 items-center justify-center rounded-full bg-white/20">
                    <Check className="h-4 w-4" />
                </div>
                <div>
                    <div className="text-sm font-semibold">Data Updated</div>
                    <div className="text-xs opacity-80">Dashboard refreshed successfully</div>
                </div>
            </div>
        </div>
    );
};

export default RefreshIndicator;

