import React from 'react';
import { Button } from './index';

export default function EmptyState({
    icon: Icon,
    title,
    description,
    actionLabel,
    onAction,
    actionHref,
    className = '',
    children,
}) {
    return (
        <div className={`flex flex-col items-center justify-center p-8 sm:p-12 text-center rounded-2xl border-2 border-dashed border-slate-200 dark:border-neutral-800 bg-white/50 dark:bg-neutral-900/30 ${className}`}>
            {Icon && (
                <div className="w-12 h-12 rounded-2xl bg-brand-50 dark:bg-neutral-800 text-brand-700 dark:text-brand-400 flex items-center justify-center mb-4 shadow-sm">
                    <Icon className="w-6 h-6" />
                </div>
            )}
            <h3 className="text-sm font-bold text-slate-900 dark:text-white mb-1">
                {title}
            </h3>
            {description && (
                <p className="text-xs text-slate-500 dark:text-neutral-400 max-w-sm mb-5 leading-relaxed">
                    {description}
                </p>
            )}
            {actionLabel && (
                <div>
                    {actionHref ? (
                        <a href={actionHref}>
                            <Button size="sm" className="bg-brand-900 hover:bg-brand-800 text-white text-xs font-semibold">
                                {actionLabel}
                            </Button>
                        </a>
                    ) : (
                        <Button
                            size="sm"
                            onClick={onAction}
                            className="bg-brand-900 hover:bg-brand-800 text-white text-xs font-semibold"
                        >
                            {actionLabel}
                        </Button>
                    )}
                </div>
            )}
            {children}
        </div>
    );
}
