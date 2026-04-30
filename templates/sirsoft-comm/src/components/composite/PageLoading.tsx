import React from 'react';


export interface PageLoadingProps {
    options?: {
        text?: string;
    };
}


const t = (key: string, params?: Record<string, string | number>) =>
    (window as any).G7Core?.t?.(key, params) ?? key;


const PageLoading: React.FC<PageLoadingProps> = ({ options }) => {
    return (
        <div className="absolute inset-0 z-[2147483647] overflow-hidden bg-gray-50 dark:bg-gray-900 flex flex-col items-center justify-start pt-[15%] gap-3">
            <div className="w-8 h-8 border-[3px] border-gray-400 dark:border-gray-500 border-t-transparent rounded-full animate-[g7-spin_0.8s_linear_infinite]" />
            <span className="text-sm text-gray-500 dark:text-gray-400">
                {options?.text || t('nav.loading')}
            </span>
        </div>
    );
};

export default PageLoading;
