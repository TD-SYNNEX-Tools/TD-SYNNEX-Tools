import React, { ReactNode } from 'react';

// SponsorLogos Component
export const SponsorLogos = () => (
  <div className="flex items-center gap-4 select-none opacity-90">
    <div className="flex items-center gap-2">
      <div className="w-6 h-6 rounded bg-blue-700 flex items-center justify-center">
        <span className="text-white text-xs font-bold">M</span>
      </div>
      <span className="text-sm font-medium text-gray-700">Microsoft</span>
    </div>
    <div className="w-px h-4 bg-gray-300" />
    <div className="flex items-center gap-2">
      <div className="w-6 h-6 rounded bg-gray-800 flex items-center justify-center">
        <span className="text-white text-xs font-bold">T</span>
      </div>
      <span className="text-sm font-medium text-gray-700">TD SYNNEX</span>
    </div>
  </div>
);

// Card Component
interface CardProps {
  children: ReactNode;
  className?: string;
  onClick?: (e: React.MouseEvent<HTMLDivElement>) => void;
}

export const Card = ({ children, className = '', onClick }: CardProps) => (
  <div 
    className={`bg-white rounded-lg shadow-sm border border-gray-200 ${className}`}
    onClick={onClick}
  >
    {children}
  </div>
);

// Button Component
interface ButtonProps {
  children: ReactNode;
  variant?: 'primary' | 'ghost' | 'secondary';
  className?: string;
  onClick?: () => void;
  disabled?: boolean;
}

export const Button = ({ 
  children, 
  variant = 'primary', 
  className = '', 
  onClick,
  disabled = false 
}: ButtonProps) => {
  const baseClasses = 'px-4 py-2 rounded-lg font-medium text-sm transition-all flex items-center gap-2 justify-center';
  
  const variantClasses = {
    primary: 'bg-blue-700 text-white hover:bg-blue-800 disabled:bg-gray-300',
    secondary: 'bg-gray-700 text-white hover:bg-gray-800 disabled:bg-gray-300',
    ghost: 'border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:text-gray-400'
  };
  
  return (
    <button
      className={`${baseClasses} ${variantClasses[variant]} ${className}`}
      onClick={onClick}
      disabled={disabled}
    >
      {children}
    </button>
  );
};

// Badge Component
interface BadgeProps {
  children: ReactNode;
  variant?: 'sky' | 'violet' | 'amber' | 'emerald' | 'slate' | 'red';
  className?: string;
}

export const Badge = ({ children, variant = 'slate', className = '' }: BadgeProps) => {
  const variantClasses = {
    sky: 'bg-blue-50 text-blue-800 border-blue-200',
    violet: 'bg-gray-100 text-gray-800 border-gray-300',
    amber: 'bg-orange-50 text-orange-800 border-orange-200',
    emerald: 'bg-green-50 text-green-800 border-green-200',
    slate: 'bg-gray-100 text-gray-800 border-gray-300',
    red: 'bg-red-50 text-red-800 border-red-200'
  };
  
  return (
    <span className={`inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium border ${variantClasses[variant]} ${className}`}>
      {children}
    </span>
  );
};
