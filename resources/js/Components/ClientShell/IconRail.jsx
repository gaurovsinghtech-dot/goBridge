import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { LayoutDashboard, Inbox, Users, Megaphone, Workflow, Bot, Share2, Settings, LifeBuoy, LogOut } from 'lucide-react';
import { useBranding } from '@/hooks/useBranding';
import { checkIsActive } from '@/utils/navActive';

function safeRoute(name, fallback = '#') {
    try { return route(name); } catch { return fallback; }
}

const RAIL_ITEMS = [
    { key: 'dashboard', href: () => safeRoute('client.dashboard'), activePattern: 'client.dashboard', icon: LayoutDashboard, labelKey: 'nav.dashboard', fallback: 'Dashboard' },
    { key: 'inbox', href: () => safeRoute('client.inbox.index'), activePattern: 'client.inbox.*', icon: Inbox, labelKey: 'nav.inbox', fallback: 'Inbox' },
    { key: 'contacts', href: () => safeRoute('client.contacts.index'), activePattern: 'client.contacts.*', icon: Users, labelKey: 'nav.contacts', fallback: 'Contacts' },
    { key: 'campaigns', href: () => safeRoute('client.campaigns.index'), activePattern: 'client.campaigns.*', icon: Megaphone, labelKey: 'nav.campaigns', fallback: 'Campaigns' },
    { key: 'automations', href: () => safeRoute('client.automations.index'), activePattern: 'client.automations.*', icon: Workflow, labelKey: 'nav.automations', fallback: 'Automations' },
    { key: 'ai-agents', href: () => safeRoute('client.ai.chatbots.index'), activePattern: 'client.ai.chatbots.*', icon: Bot, labelKey: 'nav.ai_agents', fallback: 'AI Agents' },
    { key: 'channels', href: () => safeRoute('client.inbox.setup'), activePattern: 'client.inbox.setup', icon: Share2, labelKey: 'nav.channels', fallback: 'Channels' },
];

/**
 * Floating icon-only rail — the primary desktop nav for the client shell, in
 * place of the old full-width text sidebar. Secondary/long-tail nav items
 * live in PillTopNav's category dropdowns instead of here, so this stays a
 * small, fixed set of the most-used destinations.
 */
export default function IconRail({ onLogout }) {
    const { t } = useTranslation();
    const { logoUrl } = useBranding();

    return (
        <div className="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-20 lg:flex lg:w-24 lg:flex-col lg:items-center lg:py-4 rtl:lg:left-auto rtl:lg:right-0">
            <Link
                href={safeRoute('client.dashboard')}
                className="mb-4 flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-neutral-900 dark:bg-white shadow-sm overflow-hidden"
            >
                <img src={logoUrl || '/images/brand/logo-icon.png'} alt="" className="h-6 w-6 object-contain" />
            </Link>

            <nav className="flex w-16 flex-1 flex-col items-center gap-1 overflow-y-auto rounded-[28px] border border-neutral-200/70 bg-white px-2 py-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 scrollbar-thin scrollbar-track-transparent">
                {RAIL_ITEMS.map((item) => {
                    const isActive = checkIsActive({ activePattern: item.activePattern });
                    const Icon = item.icon;
                    const label = t(item.labelKey, item.fallback);
                    return (
                        <Link
                            key={item.key}
                            href={item.href()}
                            title={label}
                            aria-label={label}
                            className={[
                                'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition-colors duration-150',
                                isActive
                                    ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                                    : 'text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:text-neutral-500 dark:hover:bg-neutral-800 dark:hover:text-neutral-200',
                            ].join(' ')}
                        >
                            <Icon className="h-[18px] w-[18px]" />
                        </Link>
                    );
                })}
            </nav>

            <div className="mt-3 flex w-16 flex-col items-center gap-1 rounded-[28px] border border-neutral-200/70 bg-white px-2 py-3 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <Link
                    href={safeRoute('client.settings.index')}
                    title={t('nav.settings', 'Settings')}
                    aria-label={t('nav.settings', 'Settings')}
                    className={[
                        'flex h-10 w-10 items-center justify-center rounded-xl transition-colors duration-150',
                        checkIsActive({ activePattern: 'client.settings.*' })
                            ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                            : 'text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:text-neutral-500 dark:hover:bg-neutral-800 dark:hover:text-neutral-200',
                    ].join(' ')}
                >
                    <Settings className="h-[18px] w-[18px]" />
                </Link>
                <a
                    href="https://growbridge.co.in/contact"
                    target="_blank"
                    rel="noopener noreferrer"
                    title={t('nav.help', 'Help')}
                    aria-label={t('nav.help', 'Help')}
                    className="flex h-10 w-10 items-center justify-center rounded-xl text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:text-neutral-500 dark:hover:bg-neutral-800 dark:hover:text-neutral-200 transition-colors duration-150"
                >
                    <LifeBuoy className="h-[18px] w-[18px]" />
                </a>
                {onLogout && (
                    <button
                        type="button"
                        onClick={onLogout}
                        title={t('nav.logout', 'Log Out')}
                        aria-label={t('nav.logout', 'Log Out')}
                        className="flex h-10 w-10 items-center justify-center rounded-xl text-neutral-400 hover:bg-red-50 hover:text-red-600 dark:text-neutral-500 dark:hover:bg-red-950/30 dark:hover:text-red-400 transition-colors duration-150"
                    >
                        <LogOut className="h-[18px] w-[18px]" />
                    </button>
                )}
            </div>
        </div>
    );
}
