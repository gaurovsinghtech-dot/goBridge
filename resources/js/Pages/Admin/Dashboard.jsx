import React, { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    Users,
    CreditCard,
    MessageSquare,
    Bot,
    PhoneCall,
    IndianRupee,
    Calendar,
    ChevronDown,
    ChevronRight,
    Bell,
    Sun,
    Moon,
    ExternalLink,
    Store,
    Workflow,
    Folder,
    Database,
    Clock,
    Share2,
    Mail,
    Sliders,
    Sparkles,
} from 'lucide-react';

function safeRoute(name, fallback = '#') {
    try {
        return route(name);
    } catch {
        return fallback;
    }
}

// ── Multi-wave SVG Area Chart for Conversations Overview ──
function ConversationsWaveChart() {
    return (
        <div className="relative w-full h-[220px] select-none">
            {/* Y-Axis Grid Lines */}
            <div className="absolute inset-0 flex flex-col justify-between text-[10px] text-neutral-400 font-medium pointer-events-none">
                <div className="flex items-center gap-3">
                    <span className="w-6 text-right">20K</span>
                    <div className="flex-1 border-b border-neutral-200/60 dark:border-neutral-800/60" />
                </div>
                <div className="flex items-center gap-3">
                    <span className="w-6 text-right">15K</span>
                    <div className="flex-1 border-b border-neutral-200/60 dark:border-neutral-800/60" />
                </div>
                <div className="flex items-center gap-3">
                    <span className="w-6 text-right">10K</span>
                    <div className="flex-1 border-b border-neutral-200/60 dark:border-neutral-800/60" />
                </div>
                <div className="flex items-center gap-3">
                    <span className="w-6 text-right">5K</span>
                    <div className="flex-1 border-b border-neutral-200/60 dark:border-neutral-800/60" />
                </div>
                <div className="flex items-center gap-3">
                    <span className="w-6 text-right">0</span>
                    <div className="flex-1 border-b border-neutral-200/60 dark:border-neutral-800/60" />
                </div>
            </div>

            {/* SVG Splines Container */}
            <div className="absolute inset-0 pl-9 pr-2 pb-6 pt-2">
                <svg className="w-full h-full overflow-visible" preserveAspectRatio="none" viewBox="0 0 600 160">
                    <defs>
                        {/* Gradients for smooth glow / fill */}
                        <linearGradient id="gradWhatsApp" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="#10b981" stopOpacity="0.25" />
                            <stop offset="100%" stopColor="#10b981" stopOpacity="0.0" />
                        </linearGradient>
                        <linearGradient id="gradMessenger" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="#3b82f6" stopOpacity="0.2" />
                            <stop offset="100%" stopColor="#3b82f6" stopOpacity="0.0" />
                        </linearGradient>
                        <linearGradient id="gradInstagram" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="#ec4899" stopOpacity="0.15" />
                            <stop offset="100%" stopColor="#ec4899" stopOpacity="0.0" />
                        </linearGradient>
                        <linearGradient id="gradEmail" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="#8b5cf6" stopOpacity="0.15" />
                            <stop offset="100%" stopColor="#8b5cf6" stopOpacity="0.0" />
                        </linearGradient>
                    </defs>

                    {/* Email Wave (Purple - Bottom) */}
                    <path
                        d="M0,140 C100,145 200,135 300,138 C400,142 500,130 600,135 L600,160 L0,160 Z"
                        fill="url(#gradEmail)"
                    />
                    <path
                        d="M0,140 C100,145 200,135 300,138 C400,142 500,130 600,135"
                        fill="none"
                        stroke="#8b5cf6"
                        strokeWidth="2.5"
                    />

                    {/* Instagram Wave (Pink) */}
                    <path
                        d="M0,120 C100,110 200,118 300,112 C400,95 500,105 600,115 L600,160 L0,160 Z"
                        fill="url(#gradInstagram)"
                    />
                    <path
                        d="M0,120 C100,110 200,118 300,112 C400,95 500,105 600,115"
                        fill="none"
                        stroke="#ec4899"
                        strokeWidth="2.5"
                    />

                    {/* Messenger Wave (Blue) */}
                    <path
                        d="M0,95 C100,80 200,92 300,85 C400,65 500,72 600,88 L600,160 L0,160 Z"
                        fill="url(#gradMessenger)"
                    />
                    <path
                        d="M0,95 C100,80 200,92 300,85 C400,65 500,72 600,88"
                        fill="none"
                        stroke="#3b82f6"
                        strokeWidth="2.5"
                    />

                    {/* WhatsApp Wave (Emerald - Top Leader) */}
                    <path
                        d="M0,60 C100,35 200,55 300,30 C400,15 500,32 600,45 L600,160 L0,160 Z"
                        fill="url(#gradWhatsApp)"
                    />
                    <path
                        d="M0,60 C100,35 200,55 300,30 C400,15 500,32 600,45"
                        fill="none"
                        stroke="#10b981"
                        strokeWidth="3"
                    />
                </svg>
            </div>

            {/* X-Axis Date Labels */}
            <div className="absolute bottom-0 left-9 right-2 flex justify-between text-[10px] text-neutral-400 font-medium pt-1">
                <span>May 20</span>
                <span>May 21</span>
                <span>May 22</span>
                <span>May 23</span>
                <span>May 24</span>
                <span>May 25</span>
                <span>May 26</span>
            </div>
        </div>
    );
}

// ── Donut Chart for Automation Status ──
function AutomationDonut() {
    return (
        <div className="relative w-36 h-36 mx-auto flex items-center justify-center">
            <svg viewBox="0 0 100 100" className="w-full h-full transform -rotate-90">
                {/* Background track */}
                <circle cx="50" cy="50" r="38" fill="none" stroke="#f1f5f9" strokeWidth="12" className="dark:stroke-neutral-800" />
                {/* Completed (Blue ~60.6%) */}
                <circle
                    cx="50" cy="50" r="38" fill="none" stroke="#3b82f6" strokeWidth="12"
                    strokeDasharray="145 238.7" strokeDashoffset="0"
                />
                {/* Running (Green ~27.3%) */}
                <circle
                    cx="50" cy="50" r="38" fill="none" stroke="#10b981" strokeWidth="12"
                    strokeDasharray="65 238.7" strokeDashoffset="-145"
                />
                {/* Scheduled (Orange ~6.7%) */}
                <circle
                    cx="50" cy="50" r="38" fill="none" stroke="#f97316" strokeWidth="12"
                    strokeDasharray="16 238.7" strokeDashoffset="-210"
                />
                {/* Failed (Pink ~3.5%) */}
                <circle
                    cx="50" cy="50" r="38" fill="none" stroke="#ec4899" strokeWidth="12"
                    strokeDasharray="12 238.7" strokeDashoffset="-226"
                />
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                <span className="text-xl font-extrabold text-neutral-900 dark:text-white leading-tight">1,187</span>
                <span className="text-[10px] text-neutral-400 font-semibold uppercase">Total</span>
            </div>
        </div>
    );
}

export default function Dashboard() {
    const { t } = useTranslation();
    const [rangeDropdownOpen, setRangeDropdownOpen] = useState(false);
    const [notificationsOpen, setNotificationsOpen] = useState(false);
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

    return (
        <AdminLayout
            title="Dashboard"
            subtitle="Overview of your platform performance and activity"
        >
            <Head title="Admin Dashboard · Growbridge Connect" />

            <div className="space-y-6 max-w-[1600px] mx-auto">
                {/* ── Top Header Controls Bar ── */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-black text-neutral-900 dark:text-white tracking-tight">
                            Dashboard
                        </h1>
                        <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                            Overview of your platform performance and activity
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        {/* Date Range Picker */}
                        <div className="relative">
                            <button
                                type="button"
                                onClick={() => setRangeDropdownOpen(!rangeDropdownOpen)}
                                className="flex items-center gap-2 px-3.5 py-2 bg-white dark:bg-[#041d15] border border-neutral-200 dark:border-emerald-900/50 rounded-xl text-xs font-semibold text-neutral-700 dark:text-neutral-200 shadow-sm hover:bg-neutral-50 dark:hover:bg-emerald-950/40 transition"
                            >
                                <Calendar className="h-3.5 w-3.5 text-neutral-500 dark:text-emerald-400" />
                                <span>May 20, 2025 - May 26, 2025</span>
                                <ChevronDown className="h-3 w-3 text-neutral-400" />
                            </button>

                            {rangeDropdownOpen && (
                                <div className="absolute right-0 mt-1.5 w-52 rounded-xl bg-white dark:bg-[#051f17] border border-neutral-200 dark:border-emerald-800/60 shadow-xl p-1.5 z-50 text-xs text-neutral-700 dark:text-neutral-300">
                                    <button className="w-full text-left px-3 py-2 rounded-lg hover:bg-emerald-500/10 font-semibold text-emerald-600 dark:text-emerald-400">
                                        May 20, 2025 - May 26, 2025
                                    </button>
                                    <button className="w-full text-left px-3 py-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-white/5">
                                        Last 30 Days
                                    </button>
                                    <button className="w-full text-left px-3 py-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-white/5">
                                        This Quarter
                                    </button>
                                </div>
                            )}
                        </div>

                        {/* Notification Bell */}
                        <div className="relative">
                            <button
                                type="button"
                                onClick={() => setNotificationsOpen(!notificationsOpen)}
                                className="relative p-2 bg-white dark:bg-[#041d15] border border-neutral-200 dark:border-emerald-900/50 rounded-xl text-neutral-600 dark:text-neutral-300 shadow-sm hover:bg-neutral-50 dark:hover:bg-emerald-950/40 transition"
                                title="Notifications"
                            >
                                <Bell className="h-4 w-4" />
                                <span className="absolute -top-1 -right-1 h-4 min-w-[16px] px-1 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center shadow">
                                    12
                                </span>
                            </button>

                            {notificationsOpen && (
                                <div className="absolute right-0 mt-2 w-80 rounded-2xl bg-white dark:bg-[#051f17] border border-neutral-200 dark:border-emerald-800/60 shadow-2xl p-4 z-50 text-xs text-neutral-800 dark:text-neutral-200">
                                    <div className="flex items-center justify-between border-b border-neutral-200/60 dark:border-emerald-900/50 pb-2.5 mb-2">
                                        <span className="font-bold text-neutral-900 dark:text-white text-sm">Notifications</span>
                                        <span className="text-[10px] bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 font-extrabold px-2 py-0.5 rounded-full">12 New</span>
                                    </div>
                                    <div className="space-y-2 max-h-64 overflow-y-auto">
                                        <div className="p-2.5 rounded-xl bg-neutral-50 dark:bg-emerald-950/40 border border-neutral-200/50 dark:border-emerald-900/30">
                                            <p className="font-semibold text-neutral-900 dark:text-white">New Client Onboarded</p>
                                            <p className="text-[10px] text-neutral-500 dark:text-neutral-400 mt-0.5">ABC Retail registered for Enterprise Plan</p>
                                        </div>
                                        <div className="p-2.5 rounded-xl bg-neutral-50 dark:bg-emerald-950/40 border border-neutral-200/50 dark:border-emerald-900/30">
                                            <p className="font-semibold text-neutral-900 dark:text-white">WhatsApp Webhook Active</p>
                                            <p className="text-[10px] text-neutral-500 dark:text-neutral-400 mt-0.5">Connected Meta Cloud API successfully</p>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Theme Toggle */}
                        <button
                            type="button"
                            onClick={toggleTheme}
                            className="p-2 bg-white dark:bg-[#041d15] border border-neutral-200 dark:border-emerald-900/50 rounded-xl text-neutral-600 dark:text-neutral-300 shadow-sm hover:bg-neutral-50 dark:hover:bg-emerald-950/40 transition cursor-pointer"
                            title="Toggle Theme"
                        >
                            {isDark ? <Sun className="h-4 w-4 text-amber-400" /> : <Moon className="h-4 w-4 text-indigo-500" />}
                        </button>
                    </div>
                </div>

                {/* ── Row 1: Top 6 KPI Metric Cards ── */}
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    {/* 1. Total Clients */}
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm hover:shadow-md transition">
                        <div className="flex items-center gap-2 text-xs text-neutral-600 dark:text-neutral-300 font-semibold mb-2">
                            <div className="h-7 w-7 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                <Users className="h-4 w-4" />
                            </div>
                            <span>Total Clients</span>
                        </div>
                        <div className="text-2xl font-black text-neutral-900 dark:text-white tracking-tight">
                            1,248
                        </div>
                        <div className="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold mt-1 flex items-center">
                            <span>↑ 18.5% from last week</span>
                        </div>
                    </div>

                    {/* 2. Active Subscriptions */}
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm hover:shadow-md transition">
                        <div className="flex items-center gap-2 text-xs text-neutral-600 dark:text-neutral-300 font-semibold mb-2">
                            <div className="h-7 w-7 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <CreditCard className="h-4 w-4" />
                            </div>
                            <span>Active Subscriptions</span>
                        </div>
                        <div className="text-2xl font-black text-neutral-900 dark:text-white tracking-tight">
                            986
                        </div>
                        <div className="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold mt-1 flex items-center">
                            <span>↑ 14.3% from last week</span>
                        </div>
                    </div>

                    {/* 3. Conversations */}
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm hover:shadow-md transition">
                        <div className="flex items-center gap-2 text-xs text-neutral-600 dark:text-neutral-300 font-semibold mb-2">
                            <div className="h-7 w-7 rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                                <MessageSquare className="h-4 w-4" />
                            </div>
                            <span>Conversations</span>
                        </div>
                        <div className="text-2xl font-black text-neutral-900 dark:text-white tracking-tight">
                            84,521
                        </div>
                        <div className="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold mt-1 flex items-center">
                            <span>↑ 21.6% from last week</span>
                        </div>
                    </div>

                    {/* 4. AI Conversations */}
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm hover:shadow-md transition">
                        <div className="flex items-center gap-2 text-xs text-neutral-600 dark:text-neutral-300 font-semibold mb-2">
                            <div className="h-7 w-7 rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400 flex items-center justify-center">
                                <Bot className="h-4 w-4" />
                            </div>
                            <span>AI Conversations</span>
                        </div>
                        <div className="text-2xl font-black text-neutral-900 dark:text-white tracking-tight">
                            27,842
                        </div>
                        <div className="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold mt-1 flex items-center">
                            <span>↑ 23.7% from last week</span>
                        </div>
                    </div>

                    {/* 5. Voice Calls (Minutes) */}
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm hover:shadow-md transition">
                        <div className="flex items-center gap-2 text-xs text-neutral-600 dark:text-neutral-300 font-semibold mb-2">
                            <div className="h-7 w-7 rounded-lg bg-orange-500/10 text-orange-600 dark:text-orange-400 flex items-center justify-center">
                                <PhoneCall className="h-4 w-4" />
                            </div>
                            <span>Voice Calls (Minutes)</span>
                        </div>
                        <div className="text-2xl font-black text-neutral-900 dark:text-white tracking-tight">
                            182,450
                        </div>
                        <div className="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold mt-1 flex items-center">
                            <span>↑ 16.4% from last week</span>
                        </div>
                    </div>

                    {/* 6. Revenue (This Month) */}
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm hover:shadow-md transition">
                        <div className="flex items-center gap-2 text-xs text-neutral-600 dark:text-neutral-300 font-semibold mb-2">
                            <div className="h-7 w-7 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                <IndianRupee className="h-4 w-4" />
                            </div>
                            <span>Revenue (This Month)</span>
                        </div>
                        <div className="text-2xl font-black text-neutral-900 dark:text-white tracking-tight">
                            ₹12,84,320
                        </div>
                        <div className="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold mt-1 flex items-center">
                            <span>↑ 19.8% from last week</span>
                        </div>
                    </div>
                </div>

                {/* ── Row 2: Charts & Channel Health (2 Columns) ── */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    {/* Conversations Overview Wave Chart (7 cols) */}
                    <div className="lg:col-span-8 p-5 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                            <div>
                                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                                    Conversations Overview
                                </h3>
                                {/* Legend row */}
                                <div className="flex flex-wrap items-center gap-4 mt-1.5 text-xs text-neutral-600 dark:text-neutral-300">
                                    <div className="flex items-center gap-1.5">
                                        <span className="h-2 w-2 rounded-full bg-emerald-500" />
                                        <span>WhatsApp</span>
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <span className="h-2 w-2 rounded-full bg-pink-500" />
                                        <span>Instagram</span>
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <span className="h-2 w-2 rounded-full bg-blue-500" />
                                        <span>Messenger</span>
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <span className="h-2 w-2 rounded-full bg-purple-500" />
                                        <span>Email</span>
                                    </div>
                                </div>
                            </div>

                            <button className="self-start sm:self-auto flex items-center gap-1.5 px-3 py-1.5 bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 rounded-lg text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 transition">
                                <span>Last 7 Days</span>
                                <ChevronDown className="h-3 w-3" />
                            </button>
                        </div>

                        {/* Render SVG Wave Chart */}
                        <ConversationsWaveChart />
                    </div>

                    {/* Channel Health (4 cols) */}
                    <div className="lg:col-span-4 p-5 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                                Channel Health
                            </h3>
                            <Link href={safeRoute('admin.integrations.index', '/admin/integrations')} className="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                                View all
                            </Link>
                        </div>

                        <div className="space-y-3 text-xs">
                            {/* WhatsApp */}
                            <div className="flex items-center justify-between p-2.5 rounded-xl hover:bg-neutral-50 dark:hover:bg-white/5 transition">
                                <div className="flex items-center gap-3">
                                    <div className="h-7 w-7 rounded-lg bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                                        <MessageSquare className="h-4 w-4" />
                                    </div>
                                    <span className="font-bold text-neutral-900 dark:text-white">WhatsApp</span>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-400">
                                        ● Connected
                                    </span>
                                    <span className="text-neutral-500 dark:text-neutral-400 font-medium">248 Accounts</span>
                                    <ChevronRight className="h-3.5 w-3.5 text-neutral-400" />
                                </div>
                            </div>

                            {/* Instagram */}
                            <div className="flex items-center justify-between p-2.5 rounded-xl hover:bg-neutral-50 dark:hover:bg-white/5 transition">
                                <div className="flex items-center gap-3">
                                    <div className="h-7 w-7 rounded-lg bg-pink-500/10 text-pink-500 flex items-center justify-center">
                                        <Sparkles className="h-4 w-4" />
                                    </div>
                                    <span className="font-bold text-neutral-900 dark:text-white">Instagram</span>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-400">
                                        ● Connected
                                    </span>
                                    <span className="text-neutral-500 dark:text-neutral-400 font-medium">173 Accounts</span>
                                    <ChevronRight className="h-3.5 w-3.5 text-neutral-400" />
                                </div>
                            </div>

                            {/* Messenger */}
                            <div className="flex items-center justify-between p-2.5 rounded-xl hover:bg-neutral-50 dark:hover:bg-white/5 transition">
                                <div className="flex items-center gap-3">
                                    <div className="h-7 w-7 rounded-lg bg-blue-500/10 text-blue-500 flex items-center justify-center">
                                        <MessageSquare className="h-4 w-4" />
                                    </div>
                                    <span className="font-bold text-neutral-900 dark:text-white">Messenger</span>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-400">
                                        ● Connected
                                    </span>
                                    <span className="text-neutral-500 dark:text-neutral-400 font-medium">121 Accounts</span>
                                    <ChevronRight className="h-3.5 w-3.5 text-neutral-400" />
                                </div>
                            </div>

                            {/* Email */}
                            <div className="flex items-center justify-between p-2.5 rounded-xl hover:bg-neutral-50 dark:hover:bg-white/5 transition">
                                <div className="flex items-center gap-3">
                                    <div className="h-7 w-7 rounded-lg bg-purple-500/10 text-purple-500 flex items-center justify-center">
                                        <Mail className="h-4 w-4" />
                                    </div>
                                    <span className="font-bold text-neutral-900 dark:text-white">Email</span>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-400">
                                        ● Connected
                                    </span>
                                    <span className="text-neutral-500 dark:text-neutral-400 font-medium">304 Accounts</span>
                                    <ChevronRight className="h-3.5 w-3.5 text-neutral-400" />
                                </div>
                            </div>

                            {/* AI Provider (OpenAI) */}
                            <div className="flex items-center justify-between p-2.5 rounded-xl hover:bg-neutral-50 dark:hover:bg-white/5 transition">
                                <div className="flex items-center gap-3">
                                    <div className="h-7 w-7 rounded-lg bg-neutral-500/10 text-neutral-700 dark:text-neutral-300 flex items-center justify-center">
                                        <Bot className="h-4 w-4" />
                                    </div>
                                    <span className="font-bold text-neutral-900 dark:text-white">AI Provider (OpenAI)</span>
                                </div>
                                <div>
                                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-400">
                                        ● Connected
                                    </span>
                                </div>
                            </div>

                            {/* Twilio Phone Gateway */}
                            <div className="flex items-center justify-between p-2.5 rounded-xl hover:bg-neutral-50 dark:hover:bg-white/5 transition">
                                <div className="flex items-center gap-3">
                                    <div className="h-7 w-7 rounded-lg bg-orange-500/10 text-orange-500 flex items-center justify-center">
                                        <PhoneCall className="h-4 w-4" />
                                    </div>
                                    <span className="font-bold text-neutral-900 dark:text-white">Twilio Phone Gateway</span>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-400">
                                        ● Connected
                                    </span>
                                    <span className="text-neutral-500 dark:text-neutral-400 font-medium">284 Numbers</span>
                                    <ChevronRight className="h-3.5 w-3.5 text-neutral-400" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* ── Row 3: Three Cards Grid (Recent Activity, Top Clients, Automation Status) ── */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-6">
                    {/* 1. Recent Activity (4 cols) */}
                    <div className="lg:col-span-4 p-5 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                                Recent Activity
                            </h3>
                            <Link href={safeRoute('admin.audit-log.index', '/admin/audit-log')} className="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                                View all
                            </Link>
                        </div>

                        <div className="space-y-3.5 text-xs">
                            {/* Item 1 */}
                            <div className="flex items-start gap-3">
                                <div className="h-8 w-8 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center shrink-0 mt-0.5">
                                    <Store className="h-4 w-4" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold text-neutral-900 dark:text-white leading-tight">
                                        New client "ABC Retail" has been created
                                    </p>
                                    <p className="text-[11px] text-neutral-400 mt-0.5">by Admin User</p>
                                </div>
                                <span className="text-[10px] text-neutral-400 shrink-0">2 min ago</span>
                            </div>

                            {/* Item 2 */}
                            <div className="flex items-start gap-3">
                                <div className="h-8 w-8 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center shrink-0 mt-0.5">
                                    <MessageSquare className="h-4 w-4" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold text-neutral-900 dark:text-white leading-tight">
                                        WhatsApp account connected for client "XYZ Company"
                                    </p>
                                    <p className="text-[11px] text-neutral-400 mt-0.5">by System</p>
                                </div>
                                <span className="text-[10px] text-neutral-400 shrink-0">15 min ago</span>
                            </div>

                            {/* Item 3 */}
                            <div className="flex items-start gap-3">
                                <div className="h-8 w-8 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center shrink-0 mt-0.5">
                                    <Bot className="h-4 w-4" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold text-neutral-900 dark:text-white leading-tight">
                                        AI agent "Sales Assistant" created by client "Digital Solutions"
                                    </p>
                                    <p className="text-[11px] text-neutral-400 mt-0.5">by Client User</p>
                                </div>
                                <span className="text-[10px] text-neutral-400 shrink-0">32 min ago</span>
                            </div>

                            {/* Item 4 */}
                            <div className="flex items-start gap-3">
                                <div className="h-8 w-8 rounded-xl bg-orange-500/10 text-orange-500 flex items-center justify-center shrink-0 mt-0.5">
                                    <PhoneCall className="h-4 w-4" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold text-neutral-900 dark:text-white leading-tight">
                                        Voice call completed from +91 987 654 3210
                                    </p>
                                    <p className="text-[11px] text-neutral-400 mt-0.5">Duration: 02:45</p>
                                </div>
                                <span className="text-[10px] text-neutral-400 shrink-0">1 hour ago</span>
                            </div>

                            {/* Item 5 */}
                            <div className="flex items-start gap-3">
                                <div className="h-8 w-8 rounded-xl bg-pink-500/10 text-pink-500 flex items-center justify-center shrink-0 mt-0.5">
                                    <Mail className="h-4 w-4" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold text-neutral-900 dark:text-white leading-tight">
                                        Email campaign "Summer Offer" sent by "Fashion Store"
                                    </p>
                                    <p className="text-[11px] text-neutral-400 mt-0.5">Total Recipients: 4,521</p>
                                </div>
                                <span className="text-[10px] text-neutral-400 shrink-0">2 hours ago</span>
                            </div>
                        </div>
                    </div>

                    {/* 2. Top Clients by Conversations (4 cols) */}
                    <div className="lg:col-span-4 p-5 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                                Top Clients by Conversations
                            </h3>
                            <Link href={safeRoute('admin.clients.index', '/admin/clients')} className="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                                View all
                            </Link>
                        </div>

                        <div className="space-y-4 text-xs">
                            {[
                                { rank: 1, name: 'Digital Solutions', count: '25,842', width: 'w-full', bg: 'bg-pink-500' },
                                { rank: 2, name: 'ABC Retail', count: '18,765', width: 'w-[75%]', bg: 'bg-amber-500' },
                                { rank: 3, name: 'Marketing Minds', count: '12,453', width: 'w-[52%]', bg: 'bg-teal-600' },
                                { rank: 4, name: 'Tech Innovators', count: '8,921', width: 'w-[38%]', bg: 'bg-neutral-800' },
                                { rank: 5, name: 'Fashion Store', count: '6,540', width: 'w-[28%]', bg: 'bg-purple-600' },
                            ].map((client) => (
                                <div key={client.rank} className="flex items-center gap-3">
                                    <span className="w-3 text-neutral-400 font-bold text-xs">{client.rank}</span>
                                    <div className={`h-7 w-7 rounded-lg ${client.bg} text-white font-bold text-xs flex items-center justify-center shrink-0`}>
                                        {client.name.charAt(0)}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center justify-between mb-1">
                                            <span className="font-semibold text-neutral-900 dark:text-white truncate">{client.name}</span>
                                            <span className="font-bold text-neutral-900 dark:text-white">{client.count}</span>
                                        </div>
                                        <div className="h-1.5 w-full bg-neutral-100 dark:bg-neutral-800 rounded-full overflow-hidden">
                                            <div className={`h-full bg-emerald-500 rounded-full ${client.width}`} />
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* 3. Automation Status Donut (4 cols) */}
                    <div className="lg:col-span-4 p-5 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                                Automation Status
                            </h3>
                            <Link href={safeRoute('admin.queue.index', '/admin/queue')} className="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                                View all
                            </Link>
                        </div>

                        {/* Donut Graphic */}
                        <div className="my-2">
                            <AutomationDonut />
                        </div>

                        {/* Breakdown Legend List */}
                        <div className="grid grid-cols-2 gap-2 mt-4 text-xs">
                            <div className="flex items-center gap-2">
                                <span className="h-2.5 w-2.5 rounded-full bg-emerald-500" />
                                <div>
                                    <span className="font-medium text-neutral-600 dark:text-neutral-400">Running: </span>
                                    <span className="font-bold text-neutral-900 dark:text-white">324 (27.3%)</span>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="h-2.5 w-2.5 rounded-full bg-blue-500" />
                                <div>
                                    <span className="font-medium text-neutral-600 dark:text-neutral-400">Completed: </span>
                                    <span className="font-bold text-neutral-900 dark:text-white">18,521 (60.6%)</span>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="h-2.5 w-2.5 rounded-full bg-pink-500" />
                                <div>
                                    <span className="font-medium text-neutral-600 dark:text-neutral-400">Failed: </span>
                                    <span className="font-bold text-neutral-900 dark:text-white">42 (3.5%)</span>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="h-2.5 w-2.5 rounded-full bg-orange-500" />
                                <div>
                                    <span className="font-medium text-neutral-600 dark:text-neutral-400">Scheduled: </span>
                                    <span className="font-bold text-neutral-900 dark:text-white">821 (6.7%)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* ── Row 4: System Health Horizontal Bar ── */}
                <div className="p-5 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                            System Health
                        </h3>
                        <Link href={safeRoute('admin.settings.index', '/admin/settings')} className="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                            View Details
                        </Link>
                    </div>

                    <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
                        {[
                            { name: 'Application', status: 'Healthy', icon: Store },
                            { name: 'Database', status: 'Healthy', icon: Database },
                            { name: 'Queue', status: 'Healthy', icon: Sliders },
                            { name: 'Cron Jobs', status: 'Healthy', icon: Clock },
                            { name: 'Storage', status: 'Healthy', icon: Folder },
                            { name: 'Webhooks', status: 'Healthy', icon: Share2 },
                            { name: 'Mail Service', status: 'Healthy', icon: Mail },
                        ].map((sys, idx) => (
                            <div key={idx} className="flex items-center gap-2.5 p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/60 dark:border-neutral-700/60">
                                <div className="h-7 w-7 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                    <sys.icon className="h-4 w-4" />
                                </div>
                                <div className="min-w-0">
                                    <div className="text-[11px] font-semibold text-neutral-800 dark:text-neutral-200 truncate">{sys.name}</div>
                                    <div className="text-[10px] font-bold text-emerald-600 dark:text-emerald-400">{sys.status}</div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* ── Page Bottom Footer ── */}
                <div className="flex flex-col sm:flex-row items-center justify-between gap-2 pt-2 text-xs text-neutral-400 border-t border-neutral-200/80 dark:border-neutral-800">
                    <div>© 2025 Growbridge Connect. All rights reserved.</div>
                    <div>Version 1.0.0</div>
                </div>
            </div>
        </AdminLayout>
    );
}
