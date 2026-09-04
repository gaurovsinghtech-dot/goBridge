import { Link, usePage, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Toaster } from 'sonner';
import CommandPalette from '@/Components/CommandPalette';
import {
    LayoutDashboard,
    Users,
    CreditCard,
    Megaphone,
    Bot,
    PhoneCall,
    GitFork,
    Settings,
    FileText,
    Activity,
    ChevronDown,
    ChevronRight,
    MessageSquare,
    Sparkles,
    Mail,
    Clock,
    Moon,
    Sun,
    ChevronLeft,
    LogOut,
    CheckCircle2,
    Sliders,
    IndianRupee,
    Smartphone,
} from 'lucide-react';

function safeRoute(name, fallback = '#') {
    try {
        return route(name);
    } catch {
        return fallback;
    }
}

export default function AdminLayout({ title, subtitle, actions, children }) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
    const [channelsOpen, setChannelsOpen] = useState(true);
    const [integrationsOpen, setIntegrationsOpen] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const [isDark, setIsDark] = useState(() => document.documentElement.classList.contains('dark'));

    const toggleTheme = () => {
        const next = !isDark;
        setIsDark(next);
        if (next) {
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        }
    };

    const handleLogout = () => {
        router.post(safeRoute('admin.logout', '/admin/logout'), {}, {
            onError: () => { window.location.href = safeRoute('login'); },
            onSuccess: () => { window.location.replace(safeRoute('home')); },
        });
    };

    const adminUser = auth?.adminUser;

    return (
        <div className="min-h-screen bg-[#f4f6f8] dark:bg-[#07130f] text-neutral-900 dark:text-neutral-100 font-sans selection:bg-emerald-500 selection:text-black">
            <Toaster position="top-right" richColors />

            {/* ── Fixed Sidebar ── */}
            <aside
                className={`fixed inset-y-0 left-0 z-30 flex flex-col bg-[#031a14] text-white transition-all duration-300 border-r border-[#072d23] ${
                    sidebarCollapsed ? 'w-20' : 'w-64'
                }`}
            >
                {/* Brand Header */}
                <div className="p-4 pb-3 border-b border-white/5">
                    <Link href={safeRoute('admin.dashboard', '/admin/dashboard')} className="flex items-center gap-3 group">
                        <div className="h-9 w-9 rounded-full bg-white flex items-center justify-center shrink-0 shadow-lg shadow-emerald-500/20 group-hover:scale-105 transition-transform">
                            <span className="text-xl font-black text-black leading-none">G</span>
                        </div>
                        {!sidebarCollapsed && (
                            <div className="min-w-0 flex-1">
                                <div className="text-base font-bold text-white tracking-tight leading-tight">
                                    Growbridge <span className="text-emerald-400 font-semibold">Connect</span>
                                </div>
                                <div className="mt-1">
                                    <span className="inline-block text-[9px] font-extrabold uppercase tracking-wider text-emerald-400 bg-emerald-950/80 border border-emerald-500/40 px-2 py-0.5 rounded-full">
                                        Admin Panel
                                    </span>
                                </div>
                            </div>
                        )}
                    </Link>
                </div>

                {/* Navigation Items */}
                <nav className="flex-1 overflow-y-auto px-3 py-4 space-y-1 scrollbar-thin scrollbar-track-transparent scrollbar-thumb-white/10">
                    {/* 1. Dashboard (Active Pill) */}
                    <Link
                        href={safeRoute('admin.dashboard', '/admin/dashboard')}
                        className={`flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all ${
                            route().current('admin.dashboard')
                                ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/30'
                                : 'text-neutral-300 hover:text-white hover:bg-white/5'
                        }`}
                    >
                        <LayoutDashboard className="h-4 w-4 shrink-0 text-white" />
                        {!sidebarCollapsed && <span>Dashboard</span>}
                    </Link>

                    {/* 2. Clients */}
                    <Link
                        href={safeRoute('admin.clients.index', '/admin/clients')}
                        className={`flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${
                            route().current('admin.clients.*')
                                ? 'bg-emerald-600 text-white shadow-md'
                                : 'text-neutral-300 hover:text-white hover:bg-white/5'
                        }`}
                    >
                        <Users className="h-4 w-4 shrink-0" />
                        {!sidebarCollapsed && <span>Clients</span>}
                    </Link>

                    {/* 3. Plans & Billing */}
                    <Link
                        href={safeRoute('admin.plans.index', '/admin/plans')}
                        className={`flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${
                            route().current('admin.plans.*') || route().current('admin.subscriptions.*') || route().current('admin.payment-gateways.*')
                                ? 'bg-emerald-600 text-white shadow-md'
                                : 'text-neutral-300 hover:text-white hover:bg-white/5'
                        }`}
                    >
                        <CreditCard className="h-4 w-4 shrink-0" />
                        {!sidebarCollapsed && <span>Plans & Billing</span>}
                    </Link>

                    {/* Provider Cost Ledger & Margin Management */}
                    <Link
                        href={safeRoute('admin.billing.provider-costs.index', '/admin/billing/provider-costs')}
                        className={`flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${
                            route().current('admin.billing.provider-costs.*')
                                ? 'bg-emerald-600 text-white shadow-md'
                                : 'text-neutral-300 hover:text-white hover:bg-white/5'
                        }`}
                    >
                        <IndianRupee className="h-4 w-4 shrink-0 text-emerald-400" />
                        {!sidebarCollapsed && <span>Provider Costs & Margin</span>}
                    </Link>

                    {/* 4. Channels (Expandable) */}
                    <div>
                        <button
                            type="button"
                            onClick={() => setChannelsOpen(!channelsOpen)}
                            className="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium text-neutral-300 hover:text-white hover:bg-white/5 transition-all"
                        >
                            <div className="flex items-center gap-3">
                                <Megaphone className="h-4 w-4 shrink-0 text-neutral-300" />
                                {!sidebarCollapsed && <span>Channels</span>}
                            </div>
                            {!sidebarCollapsed && (
                                <ChevronDown className={`h-3.5 w-3.5 transition-transform duration-200 ${channelsOpen ? 'rotate-0' : '-rotate-90'}`} />
                            )}
                        </button>
                        {channelsOpen && !sidebarCollapsed && (
                            <div className="pl-6 pr-1 py-1 space-y-1 text-xs">
                                <Link
                                    href={safeRoute('admin.integrations.index', '/admin/integrations')}
                                    className="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-neutral-300 hover:text-emerald-400 hover:bg-white/5 font-medium transition"
                                >
                                    <MessageSquare className="h-3.5 w-3.5 text-emerald-400" />
                                    <span>WhatsApp</span>
                                </Link>
                                <Link
                                    href={safeRoute('admin.integrations.index', '/admin/integrations')}
                                    className="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-neutral-300 hover:text-pink-400 hover:bg-white/5 font-medium transition"
                                >
                                    <Sparkles className="h-3.5 w-3.5 text-pink-400" />
                                    <span>Instagram</span>
                                </Link>
                                <Link
                                    href={safeRoute('admin.integrations.index', '/admin/integrations')}
                                    className="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-neutral-300 hover:text-blue-400 hover:bg-white/5 font-medium transition"
                                >
                                    <MessageSquare className="h-3.5 w-3.5 text-blue-400" />
                                    <span>Messenger</span>
                                </Link>
                                <Link
                                    href={safeRoute('admin.email-system.index', '/admin/email-system')}
                                    className="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-neutral-300 hover:text-purple-400 hover:bg-white/5 font-medium transition"
                                >
                                    <Mail className="h-3.5 w-3.5 text-purple-400" />
                                    <span>Email</span>
                                </Link>
                            </div>
                        )}
                    </div>

                    {/* 5. AI */}
                    <Link
                        href={safeRoute('admin.ai.index', '/admin/ai')}
                        className={`flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${
                            route().current('admin.ai.*')
                                ? 'bg-emerald-600 text-white shadow-md'
                                : 'text-neutral-300 hover:text-white hover:bg-white/5'
                        }`}
                    >
                        <div className="flex items-center gap-3">
                            <Bot className="h-4 w-4 shrink-0" />
                            {!sidebarCollapsed && <span>AI</span>}
                        </div>
                        {!sidebarCollapsed && <ChevronRight className="h-3.5 w-3.5 text-neutral-500" />}
                    </Link>

                    {/* 6. Twilio & Voice */}
                    <Link
                        href={safeRoute('admin.twilio.index', '/admin/twilio')}
                        className={`flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${
                            route().current('admin.twilio.*') || route().current('admin.phone-numbers.*')
                                ? 'bg-emerald-600 text-white shadow-md'
                                : 'text-neutral-300 hover:text-white hover:bg-white/5'
                        }`}
                    >
                        <div className="flex items-center gap-3">
                            <PhoneCall className="h-4 w-4 shrink-0" />
                            {!sidebarCollapsed && <span>Twilio & Numbers</span>}
                        </div>
                        {!sidebarCollapsed && <ChevronRight className="h-3.5 w-3.5 text-neutral-500" />}
                    </Link>

                    {/* 7. Automations */}
                    <Link
                        href={safeRoute('admin.queue.index', '/admin/queue')}
                        className="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium text-neutral-300 hover:text-white hover:bg-white/5 transition-all"
                    >
                        <div className="flex items-center gap-3">
                            <GitFork className="h-4 w-4 shrink-0" />
                            {!sidebarCollapsed && <span>Automations</span>}
                        </div>
                        {!sidebarCollapsed && <ChevronRight className="h-3.5 w-3.5 text-neutral-500" />}
                    </Link>

                    {/* 8. Integrations */}
                    <Link
                        href={safeRoute('admin.integrations.index', '/admin/integrations')}
                        className={`flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${
                            route().current('admin.integrations.*')
                                ? 'bg-emerald-600 text-white shadow-md'
                                : 'text-neutral-300 hover:text-white hover:bg-white/5'
                        }`}
                    >
                        <div className="flex items-center gap-3">
                            <Sliders className="h-4 w-4 shrink-0" />
                            {!sidebarCollapsed && <span>Integrations</span>}
                        </div>
                        {!sidebarCollapsed && <ChevronDown className="h-3.5 w-3.5 text-neutral-500" />}
                    </Link>

                    {/* 9. Analytics */}
                    <Link
                        href={safeRoute('admin.dashboard', '/admin/dashboard')}
                        className="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-neutral-300 hover:text-white hover:bg-white/5 transition-all"
                    >
                        <Activity className="h-4 w-4 shrink-0" />
                        {!sidebarCollapsed && <span>Analytics</span>}
                    </Link>

                    {/* 10. Audit Logs */}
                    <Link
                        href={safeRoute('admin.audit-log.index', '/admin/audit-log')}
                        className={`flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${
                            route().current('admin.audit-log.*')
                                ? 'bg-emerald-600 text-white shadow-md'
                                : 'text-neutral-300 hover:text-white hover:bg-white/5'
                        }`}
                    >
                        <FileText className="h-4 w-4 shrink-0" />
                        {!sidebarCollapsed && <span>Audit Logs</span>}
                    </Link>

                    {/* 11. Android App Management */}
                    <Link
                        href={safeRoute('admin.app-management.android.index', '/admin/app-management/android')}
                        className={`flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${
                            route().current('admin.app-management.*')
                                ? 'bg-emerald-600 text-white shadow-md'
                                : 'text-neutral-300 hover:text-white hover:bg-white/5'
                        }`}
                    >
                        <Smartphone className="h-4 w-4 shrink-0 text-emerald-400" />
                        {!sidebarCollapsed && <span>Android App</span>}
                    </Link>

                    {/* 12. System Settings */}
                    <Link
                        href={safeRoute('admin.settings.index', '/admin/settings')}
                        className={`flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${
                            route().current('admin.settings.*')
                                ? 'bg-emerald-600 text-white shadow-md'
                                : 'text-neutral-300 hover:text-white hover:bg-white/5'
                        }`}
                    >
                        <Settings className="h-4 w-4 shrink-0" />
                        {!sidebarCollapsed && <span>System Settings</span>}
                    </Link>
                </nav>

                {/* Sidebar Bottom Profile & Utility Bar */}
                <div className="p-3 border-t border-white/10 space-y-2">
                    {/* Admin User Profile Card */}
                    <div className="flex items-center justify-between p-2 rounded-xl bg-white/5 border border-white/5">
                        <div className="flex items-center gap-2.5 min-w-0">
                            <div className="h-8 w-8 rounded-full bg-emerald-500 flex items-center justify-center text-black font-bold text-xs shrink-0">
                                <span>A</span>
                            </div>
                            {!sidebarCollapsed && (
                                <div className="min-w-0 flex-1">
                                    <p className="text-xs font-bold text-white truncate">Admin User</p>
                                    <p className="text-[10px] text-neutral-400 truncate">Super Administrator</p>
                                </div>
                            )}
                        </div>
                        {!sidebarCollapsed && (
                            <button
                                type="button"
                                onClick={handleLogout}
                                title="Log out"
                                className="text-neutral-400 hover:text-rose-400 p-1 transition"
                            >
                                <LogOut className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>

                    {/* Bottom Action Icons Bar */}
                    <div className="flex items-center justify-around pt-1 text-neutral-400 text-xs">
                        <button type="button" className="p-1.5 hover:text-white hover:bg-white/5 rounded-lg transition" title="Activity Clock">
                            <Clock className="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            onClick={toggleTheme}
                            className="p-1.5 hover:text-white hover:bg-white/5 rounded-lg transition"
                            title="Toggle Theme"
                        >
                            {isDark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
                        </button>
                        <button
                            type="button"
                            onClick={() => setSidebarCollapsed(!sidebarCollapsed)}
                            className="p-1.5 hover:text-white hover:bg-white/5 rounded-lg transition"
                            title={sidebarCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar'}
                        >
                            <ChevronLeft className={`h-4 w-4 transition-transform ${sidebarCollapsed ? 'rotate-180' : ''}`} />
                        </button>
                    </div>
                </div>
            </aside>

            {/* ── Main Content Area ── */}
            <div
                className={`flex flex-col min-h-screen transition-all duration-300 ${
                    sidebarCollapsed ? 'pl-20' : 'pl-64'
                }`}
            >
                <main className="flex-1 p-4 sm:p-6 lg:p-8">
                    {children}
                </main>
            </div>
        </div>
    );
}
