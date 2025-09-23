import React from 'react';

interface CardProps {
    title?: string;
    children: React.ReactNode;
    className?: string;
}

const Card: React.FC<CardProps> = ({ title, children, className = '' }) => {
    return (
        <div className={`bg-white rounded-xl p-6 shadow-lg ${className}`}>
            {title ? (
                <h3 className="text-lg font-semibold text-gray-800 mb-4">{title}</h3>
            ) : null}
            {children}
        </div>
    );
};

export default Card;
