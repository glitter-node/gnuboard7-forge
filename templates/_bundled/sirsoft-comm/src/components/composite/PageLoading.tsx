import React from 'react';


export interface PageLoadingProps {
    options?: {
        text?: string;
    };
}


const t = (key: string, params?: Record<string, string | number>) =>
    (window as any).G7Core?.t?.(key, params) ?? key;


const PageLoading: React.FC<PageLoadingProps> = ({ options }) => {
    const isDark = document.documentElement.classList.contains('dark');

    const bgColor = isDark ? 'rgb(17,24,39)' : 'rgb(249,250,251)';
    const spinnerColor = isDark ? '#6b7280' : '#9ca3af';
    const textColor = isDark ? '#9ca3af' : '#6b7280';

    return (
        <div
            style={{
                position: 'absolute',
                inset: 0,
                zIndex: 2147483647,
                overflow: 'hidden',
                background: bgColor,
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'flex-start',
                paddingTop: '15%',
                gap: '12px',
            }}
        >
            <div
                style={{
                    width: '32px',
                    height: '32px',
                    border: `3px solid ${spinnerColor}`,
                    borderTopColor: 'transparent',
                    borderRadius: '50%',
                    animation: 'g7-spin 0.8s linear infinite',
                }}
            />
            <span style={{ color: textColor, fontSize: '14px' }}>
                {options?.text || t('nav.loading')}
            </span>
        </div>
    );
};

export default PageLoading;
