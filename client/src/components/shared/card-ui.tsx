import React from 'react';

interface CardProps {
    title?: string;
    children: React.ReactNode;
    className?: string;
}

const Card: React.FC<CardProps> = ({ title, children, className = '' }) => {
    return (
        <div className={`rounded-xl border border-gray-100 bg-white p-6 shadow ${className}`}>
            {title ? <h3 className="mb-4 text-lg font-semibold text-gray-800">{title}</h3> : null}
            {children}
        </div>
    );
};

export default Card;

