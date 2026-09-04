import React from 'react';
import { Loader2 } from 'lucide-react';

export default function LoadingState({
    message = 'Loading data...',
    className = '',
}) {
    return (
        <div className={`flex flex-col items-center justify-center p-12 text-center ${className}`}>
            <Loader2 className="w-7 h-7 text-brand-600 animate-spin mb-3" />
            <p className="text-xs font-medium text-slate-500 dark:text-neutral-400">
                {message}
            </p>
        </div>
    );
}
