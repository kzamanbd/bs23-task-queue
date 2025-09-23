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
            }, 2000);
            return () => clearTimeout(timer);
        }
    }, [show]);

    return (
        <div className={`fixed top-5 right-5 bg-green-500 text-white px-4 py-2 rounded-full text-sm transition-opacity duration-300 ${isVisible ? 'opacity-100' : 'opacity-0'}`}>
            Data refreshed
        </div>
    );
};

export default RefreshIndicator;
