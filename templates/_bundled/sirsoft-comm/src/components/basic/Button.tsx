import React, { forwardRef } from 'react';


const G7Core = () => (window as any).G7Core;

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'danger' | 'success' | 'ghost' | 'outline';
  size?: 'sm' | 'md' | 'lg';
}

const variantClasses: Record<NonNullable<ButtonProps['variant']>, string> = {
  primary: 'btn-primary',
  secondary: 'btn-secondary',
  danger: 'btn-danger',
  success: 'btn-success',
  ghost: 'btn-ghost',
  outline: 'btn-outline',
};

const sizeClasses: Record<NonNullable<ButtonProps['size']>, string> = {
  sm: 'px-3 py-1.5 text-sm',
  md: 'px-4 py-2 text-sm',
  lg: 'px-5 py-3 text-sm',
};


export const Button = forwardRef<HTMLButtonElement, ButtonProps>(({
  children,
  variant = 'primary',
  size = 'md',
  className = '',
  ...props
}, ref) => {
  const baseClasses = `btn ${variantClasses[variant]} ${sizeClasses[size]}`;

  
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
