import React from 'react';
import { CheckCircle2, AlertTriangle, XCircle, Circle } from 'lucide-react';

export default function ChannelStatusBadge({
    status = 'not_connected',
    label,
    size = 'md',
    showDotOnly = false,
}) {
    const config = {
        connected: {
            text: 'Connected',
            pillClass: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
            dotClass: 'bg-emerald-500',
            icon: CheckCircle2,
        },
        setup_required: {
            text: 'Setup Required',
            pillClass: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30',
            dotClass: 'bg-amber-500',
            icon: AlertTriangle,
        },
        connection_failed: {
            text: 'Connection Failed',
            pillClass: 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/30',
            dotClass: 'bg-red-500',
            icon: XCircle,
        },
        not_connected: {
            text: 'Not Connected',
            pillClass: 'bg-neutral-500/10 text-neutral-500 dark:text-neutral-400 border-neutral-500/20',
            dotClass: 'bg-neutral-400',
            icon: Circle,
        },
    }[status] || {
        text: status,
        pillClass: 'bg-neutral-500/10 text-neutral-500 border-neutral-500/20',
        dotClass: 'bg-neutral-400',
        icon: Circle,
    };

    if (showDotOnly) {
        return (
            <span className="relative flex h-2.5 w-2.5" title={label || config.text}>
                {status === 'connected' && (
                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                )}
                <span className={`relative inline-flex rounded-full h-2.5 w-2.5 ${config.dotClass}`} />
            </span>
        );
    }

    const sizeClass = size === 'sm' ? 'px-2 py-0.5 text-[10px]' : 'px-2.5 py-1 text-xs';

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full font-bold border ${config.pillClass} ${sizeClass}`}
        >
            <span className={`h-1.5 w-1.5 rounded-full ${config.dotClass}`} />
            <span>{label || config.text}</span>
        </span>
    );
}
