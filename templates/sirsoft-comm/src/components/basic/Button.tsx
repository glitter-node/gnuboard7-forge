import React, { forwardRef } from 'react';


const G7Core = () => (window as any).G7Core;

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'danger' | 'success';
  size?: 'sm' | 'md' | 'lg';
}


export const Button = forwardRef<HTMLButtonElement, ButtonProps>(({
  children,
  variant = 'primary',
  size = 'md',
  className = '',
  ...props
}, ref) => {
  const baseClasses = 'inline-flex items-center justify-center';

  
  const mergedClassName = G7Core()?.style?.mergeClasses?.(baseClasses, className)
    ?? `${baseClasses} ${className}`;

  return (
    <button
      ref={ref}
      className={mergedClassName}
      {...props}
    >
      {children}
    </button>
  );
});

Button.displayName = 'Button';
