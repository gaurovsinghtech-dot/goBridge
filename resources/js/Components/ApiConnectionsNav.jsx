import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Key, Share2, Webhook, BookOpen, Sliders } from 'lucide-react';

function safeRoute(name, fallback = '#', ...args) {
    try {
        if (typeof route === 'function') {
            return route(name, ...args);
        }
        return fallback;
    } catch {
        return fallback;
    }
}

export default function ApiConnectionsNav({ current = 'tokens' }) {
    const { t } = useTranslation();

    const tabs = [
        {
            key: 'tokens',
            label: t('api.nav_tokens', 'API Keys & Tokens'),
            href: safeRoute('client.api-tokens.index', '/api-tokens'),
            icon: Key,
            active: current === 'tokens' || (typeof route === 'function' && (route().current('client.api-tokens.*') || route().current('client.api.tokens'))),
        },
        {
            key: 'crm',
            label: t('api.nav_crm', 'CRM & Business Systems'),
            href: safeRoute('client.crm.integrations.index', '/crm/integrations'),
            icon: Share2,
            active: current === 'crm' || (typeof route === 'function' && route().current('client.crm.integrations.*')),
        },
        {
            key: 'webhooks',
            label: t('api.nav_webhooks', 'Webhooks & Endpoints'),
            href: safeRoute('client.webhooks.index', '/webhooks'),
            icon: Webhook,
            active: current === 'webhooks' || (typeof route === 'function' && route().current('client.webhooks.*')),
        },
        {
            key: 'docs',
            label: t('api.nav_docs', 'REST API Reference'),
            href: safeRoute('client.api-docs', '/api-docs'),
            icon: BookOpen,
            active: current === 'docs' || (typeof route === 'function' && (route().current('client.api-docs') || route().current('client.api.docs'))),
        },
    ];

    return (
        <div className="border-b border-neutral-200 dark:border-neutral-800 mb-6">
            <div className="flex items-center gap-2 overflow-x-auto scrollbar-none py-1">
                {tabs.map((tab) => {
                    const Icon = tab.icon;
                    return (
                        <Link
                            key={tab.key}
                            href={tab.href}
                            className={`inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-sm font-medium transition-all whitespace-nowrap ${
                                tab.active
                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 font-semibold shadow-xs'
                                    : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:text-neutral-100 dark:hover:bg-neutral-800/60'
                            }`}
                        >
                            <Icon className={`h-4 w-4 shrink-0 ${tab.active ? 'text-emerald-600 dark:text-emerald-400' : 'text-neutral-400'}`} />
                            <span>{tab.label}</span>
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}
