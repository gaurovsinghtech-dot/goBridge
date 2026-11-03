import { Link, usePage, router } from '@inertiajs/react';
import { useOneSignal, isOnInboxOrDashboard, showBrowserNotification } from '@/hooks/useOneSignal';
import { useState, useEffect, useCallback, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Toaster, toast } from 'sonner';
import Topbar from '@/Components/Topbar';
import Sidebar from '@/Components/Sidebar';
import UpgradeModal from '@/Components/UpgradeModal';
import DashboardTour from '@/Components/ProductTour/DashboardTour';
import useClientNav from '@/Layouts/useClientNav';
import { ChannelBrandIcon } from '@/Components/BrandIcons';
import {
    LayoutDashboard,
    CreditCard,
    Package,
    FileText,
    Users,
    Settings,
    Layers,
    Webhook,
    Key,
    BookOpen,
    Image,
    Radio,
    Inbox,
    Bot,
    Database,
    Zap,
    Share2,
    MapPin,
    Tag,
    LifeBuoy,
    ExternalLink,
    Mail,
    MessageSquare,
    ChevronDown,
} from 'lucide-react';

const iconClass = 'h-4 w-4';
const whatsappNavIcon = <ChannelBrandIcon channel="whatsapp" className={iconClass} />;

function safeRoute(name, ...args) {
    try { return route(name, ...args); } catch { return '#'; }
}

function UsageBanner({ usage }) {
    const { t } = useTranslation();
    const [dismissed, setDismissed] = useState(false);

    const overThreshold = Object.entries(usage ?? {}).filter(([, v]) => v.percent >= 80);

    if (dismissed || overThreshold.length === 0) return null;

    const worst = overThreshold.sort((a, b) => b[1].percent - a[1].percent)[0];
    const [key, data] = worst;
    const label = key.replace(/_per_month$/, '').replace(/_/g, ' ');

    return (
        <div className="flex items-center justify-between gap-4 bg-amber-50 dark:bg-amber-900/20 border-b border-amber-200 dark:border-amber-800 px-4 py-2 text-sm">
            <div className="flex items-center gap-2 text-amber-800 dark:text-amber-300">
                <svg className="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
                <span>
                    {t('ui.usage_banner', { percent: data.percent, label, current: data.current, limit: data.limit })}{' '}
                    <Link href={safeRoute('client.pricing')} className="font-semibold underline hover:no-underline">
                        {t('ui.usage_banner_upgrade')}
                    </Link>{' '}
                    {t('ui.usage_banner_suffix')}
                </span>
            </div>
            <button
                onClick={() => setDismissed(true)}
                className="flex-shrink-0 text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-200"
                aria-label={t('common.dismiss')}
            >
                <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                </svg>
            </button>
        </div>
    );
}

function ClientLayoutFooter() {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const user = auth?.user;
    const [menuOpen, setMenuOpen] = useState(false);
    const menuRef = useRef(null);
    const initials = (() => {
        if (user?.name) return user.name.split(' ').filter(Boolean).map((n) => n[0]).join('').slice(0, 2).toUpperCase();
        if (user?.email) return user.email[0].toUpperCase();
        return 'U';
    })();

    const handleSignOut = () => {
        router.post(route('logout'), {}, {
            onError: () => {
                window.location.href = route('login');
            },
            onSuccess: () => {
                window.location.replace(route('home'));
            },
        });
    };

    const accountLinks = [
        { label: t('nav.profile') || 'Profile', href: safeRoute('client.profile.edit') },
        { label: t('nav.twoFactor') || 'Two-Factor Auth', href: safeRoute('client.profile.2fa') },
        { label: t('nav.sessions') || 'Sessions', href: safeRoute('client.profile.sessions') },
    ];

    useEffect(() => {
        if (!menuOpen) return;
        const handler = (e) => {
            if (menuRef.current && !menuRef.current.contains(e.target)) setMenuOpen(false);
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [menuOpen]);

    if (!user) return null;

    return (
        <div ref={menuRef} className="relative border-t border-neutral-200 dark:border-neutral-800 pt-3">
            <button
                type="button"
                onClick={() => setMenuOpen((o) => !o)}
                className="flex w-full items-center gap-3 px-2.5 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
            >
                <div className="flex-shrink-0 h-9 w-9 rounded-full bg-brand-50 text-brand-600 dark:bg-brand-950/40 dark:text-brand-400 border border-brand-200 dark:border-brand-800 flex items-center justify-center text-xs font-bold shadow-inner">
                    {initials}
                </div>
                <div className="min-w-0 flex-1 text-left">
                    <p className="text-xs font-semibold text-neutral-900 dark:text-neutral-100 truncate">{user.name || 'User'}</p>
                    <p className="text-[10px] font-medium text-brand-600 dark:text-brand-400 capitalize truncate">{user.client_role || 'Admin'}</p>
                </div>
                <ChevronDown className={`h-3.5 w-3.5 text-neutral-400 shrink-0 transition-transform ${menuOpen ? 'rotate-180' : ''}`} />
            </button>

            {menuOpen && (
                <div className="absolute bottom-full left-0 right-0 mb-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-lg overflow-hidden">
                    <div className="px-3.5 py-2.5 border-b border-neutral-100 dark:border-neutral-800">
                        <p className="text-sm font-semibold text-neutral-900 dark:text-neutral-100 truncate">{user.name}</p>
                        <p className="text-xs text-neutral-500 dark:text-neutral-400 truncate">{user.email}</p>
                    </div>
                    {accountLinks.map((link) => (
                        <Link
                            key={link.href}
                            href={link.href}
                            onClick={() => setMenuOpen(false)}
                            className="block px-3.5 py-2 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition"
                        >
                            {link.label}
                        </Link>
                    ))}
                    <div className="border-t border-neutral-100 dark:border-neutral-800">
                        <button
                            type="button"
                            onClick={handleSignOut}
                            className="flex w-full items-center gap-2 px-3.5 py-2 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-red-50 dark:hover:bg-red-950/30 hover:text-red-600 dark:hover:text-red-400 transition"
                        >
                            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            {t('nav.logout') || 'Log Out'}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

export default function ClientLayout({ header, children, title }) {
    const { t } = useTranslation();
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const { auth, impersonation, current_workspace_usage, unreadNotificationsCount, branding, onesignal } = usePage().props;
    const logoUrl = branding?.logo_url;
    const [unreadCount, setUnreadCount] = useState(unreadNotificationsCount ?? 0);
    const clientNavGroups = useClientNav();

    // Initialise OneSignal SDK (registers SW, requests push permission)
    useOneSignal({ appId: onesignal?.app_id, enabled: onesignal?.enabled });

    // Sync unread count from server on page changes
    useEffect(() => {
        setUnreadCount(unreadNotificationsCount ?? 0);
    }, [unreadNotificationsCount]);

    // Subscribe to broadcast notifications for this user
    useEffect(() => {
        if (! window.Echo || ! auth?.user?.id) return;

        window.Echo.private(`App.Models.User.${auth.user.id}`)
            .notification((notification) => {
                setUnreadCount(prev => prev + 1);
                const msg = notification.snippet ?? notification.name ?? notification.automation ?? notification.error ?? t('ui.notif_new');
                const title = {
                    new_message:          t('ui.notif_new_message'),
                    mention:              t('ui.notif_mention'),
                    conversation_assigned:t('ui.notif_conversation_assigned'),
                    campaign_completed:   t('ui.notif_campaign_completed'),
                    automation_failed:    t('ui.notif_automation_failed'),
                    billing_failed:       t('ui.notif_billing_failed'),
                }[notification.type] ?? t('ui.notif_default');

                toast(title, {
                    description: msg,
                    action: notification.url ? { label: t('common.view'), onClick: () => router.visit(notification.url) } : undefined,
                });

                // Show a browser push notification when the user is not on inbox or dashboard
                if (!isOnInboxOrDashboard()) {
                    showBrowserNotification(title, msg, notification.url);
                }
            });

        return () => {
            window.Echo.leave(`App.Models.User.${auth.user.id}`);
        };
    }, [auth?.user?.id]);

    const returnToAdmin = () => {
        router.post(impersonation?.returnUrl ?? route('admin.impersonation.stop'));
    };

    const demoMode = usePage().props.demo_mode === true;

    const userNavItems = [
        { label: t('nav.profile') || 'Profile',           href: safeRoute('client.profile.edit'),    as: 'link' },
        { label: t('nav.twoFactor') || 'Two-Factor Auth', href: safeRoute('client.profile.2fa'),     as: 'link' },
        { label: t('nav.sessions') || 'Sessions',         href: safeRoute('client.profile.sessions'),as: 'link' },
        { type: 'divider' },
        { label: t('nav.logout') || 'Log Out',            href: route('logout'), method: 'post', as: 'link' },
    ];

    return (
        <div className="min-h-screen bg-white dark:bg-neutral-950">
            {impersonation?.active && (
                <div className="flex items-center justify-between gap-4 bg-amber-500/90 text-white px-4 py-2 text-sm font-medium">
                    <span>{t('impersonation.impersonating', { name: impersonation.clientName })}</span>
                    <button
                        type="button"
                        onClick={returnToAdmin}
                        className="rounded-soft bg-white/20 px-3 py-1.5 font-medium hover:bg-white/30 transition"
                    >
                        {t('impersonation.return_to_admin')}
                    </button>
                </div>
            )}

            <Sidebar
                open={sidebarOpen}
                onClose={() => setSidebarOpen(false)}
                showCreateButton={false}
                navGroups={clientNavGroups.map(group => ({
                    ...group,
                    items: group.items.map(item => ({
                        ...item,
                        key: item.activePattern || item.label,
                        active: () => item.activePattern ? route().current(item.activePattern) : false,
                    }))
                }))}
footer={<ClientLayoutFooter />}
            />

            <div className="lg:pl-64 rtl:lg:pl-0 rtl:lg:pr-64">
                <Topbar
                    showLogo={false}
                    title={title}
                    userNavItems={userNavItems}
                    unreadCount={unreadCount}
                    showGlobalSearch
                />
                <UsageBanner usage={current_workspace_usage} />

                <main className={`p-4 sm:p-6 lg:p-8 ${demoMode ? 'pb-16' : ''}`}>
                    {header && typeof header === 'object' && (
                        <div className="mb-6">
                            {header}
                        </div>
                    )}
                    {children}
                </main>
            </div>

            {/* Mobile menu button */}
            <button
                type="button"
                onClick={() => setSidebarOpen(true)}
                className="fixed bottom-4 right-4 z-30 flex h-12 w-12 items-center justify-center rounded-soft-lg bg-white dark:bg-neutral-900 border border-soft border-gray-200 dark:border-neutral-800 shadow-soft-lg text-neutral-600 hover:bg-neutral-50 dark:text-neutral-400 dark:hover:bg-neutral-800 lg:hidden rtl:right-auto rtl:left-4"
                aria-label={t('open_menu')}
            >
                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <UpgradeModal />
            <DashboardTour />
            <Toaster richColors position="top-right" />
            {/* Demo notice — pinned to the bottom of the viewport */}
            {demoMode && (
                <div className="fixed inset-x-0 bottom-0 z-20 flex items-center justify-center gap-2 bg-amber-500/90 text-amber-950 px-4 py-2 text-sm font-medium pointer-events-none">
                    <span>{t('demo.banner') || 'Demo mode: changes are disabled.'}</span>
                </div>
            )}
        </div>
    );
}
