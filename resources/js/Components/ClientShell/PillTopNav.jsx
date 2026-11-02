import { useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { checkIsActive } from '@/utils/navActive';

/**
 * Buckets the flat client nav (from useClientNav) into the handful of
 * category pills shown in the header. Items not explicitly placed fall back
 * to "Manage" so nothing from the full nav list is ever unreachable.
 */
const CATEGORY_KEYS = {
    overview: ['dashboard'],
    activity: ['inbox', 'contacts', 'campaigns'],
    manage: ['automations', 'ai-agents', 'playground', 'knowledge-base', 'channels', 'templates', 'integrations'],
    program: ['voice-studio', 'call-center', 'voice-campaigns', 'smart-queue', 'voice-follow-ups', 'voice-agents', 'phone-numbers'],
    account: ['wallet', 'plans-billing', 'settings'],
    reports: ['analytics', 'ai-analytics'],
};

const CATEGORY_LABELS = {
    overview: ['nav_groups.overview', 'Overview'],
    activity: ['nav_groups.activity', 'Activity'],
    manage: ['nav_groups.manage', 'Manage'],
    program: ['nav_groups.program', 'Program'],
    account: ['nav_groups.account', 'Account'],
    reports: ['nav_groups.reports', 'Reports'],
};

export default function PillTopNav({ items = [] }) {
    const { t } = useTranslation();
    const [openKey, setOpenKey] = useState(null);
    const ref = useRef(null);

    useEffect(() => {
        const handler = (e) => {
            if (ref.current && !ref.current.contains(e.target)) setOpenKey(null);
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const byKey = Object.fromEntries(items.filter(i => !i.locked).map(i => [i.key, i]));
    const placed = new Set(Object.values(CATEGORY_KEYS).flat());
    const leftover = items.filter(i => !i.locked && !placed.has(i.key));

    const categories = Object.entries(CATEGORY_KEYS)
        .map(([catKey, itemKeys]) => {
            const catItems = itemKeys.map(k => byKey[k]).filter(Boolean);
            return { key: catKey, items: catKey === 'manage' ? [...catItems, ...leftover] : catItems };
        })
        .filter(cat => cat.items.length > 0);

    return (
        <div ref={ref} className="hidden md:flex items-center gap-0.5 rounded-full bg-neutral-100/80 dark:bg-neutral-800/60 p-1">
            {categories.map((cat) => {
                const isOpen = openKey === cat.key;
                const isActive = cat.items.some(checkIsActive);
                const [labelKey, fallback] = CATEGORY_LABELS[cat.key];

                // A single-item category (just "Overview" → Dashboard) is a direct link, not a dropdown.
                if (cat.items.length === 1) {
                    const item = cat.items[0];
                    return (
                        <Link
                            key={cat.key}
                            href={item.href}
                            className={[
                                'rounded-full px-4 py-1.5 text-sm font-medium transition-colors duration-150',
                                isActive
                                    ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                                    : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-300 dark:hover:text-white',
                            ].join(' ')}
                        >
                            {t(labelKey, fallback)}
                        </Link>
                    );
                }

                return (
                    <div key={cat.key} className="relative">
                        <button
                            type="button"
                            onClick={() => setOpenKey(isOpen ? null : cat.key)}
                            className={[
                                'rounded-full px-4 py-1.5 text-sm font-medium transition-colors duration-150',
                                isActive || isOpen
                                    ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                                    : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-300 dark:hover:text-white',
                            ].join(' ')}
                        >
                            {t(labelKey, fallback)}
                        </button>

                        {isOpen && (
                            <div className="absolute left-1/2 z-40 mt-2 w-60 -translate-x-1/2 rounded-xl border border-neutral-200 bg-white py-1.5 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                                {cat.items.map((item) => (
                                    <Link
                                        key={item.key}
                                        href={item.href}
                                        onClick={() => setOpenKey(null)}
                                        className="flex items-center gap-2.5 px-3.5 py-2 text-sm text-neutral-700 hover:bg-neutral-50 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                    >
                                        <span className="text-neutral-400 dark:text-neutral-500">{item.icon}</span>
                                        <span className="truncate">{item.label}</span>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
