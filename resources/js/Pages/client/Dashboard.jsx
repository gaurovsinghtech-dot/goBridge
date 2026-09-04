import React, { useState } from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    Users,
    MessageSquare,
    GitFork,
    Megaphone,
    Workflow,
    Bot,
    UserPlus,
    Share2,
    Sparkles,
    PhoneCall,
    Mail,
    ChevronDown,
    Sun,
    Moon,
    Bell,
    ArrowUpRight,
    CheckCircle2,
    Calendar,
    Radio,
    Sliders,
    Key,
} from 'lucide-react';
import { ChannelBrandIcon } from '@/Components/BrandIcons';
import SetupProgressWidget from '@/Components/SetupProgressWidget';

function safeRoute(name, ...args) {
    try { return route(name, ...args); } catch { return '#'; }
}

function DonutSvg({ segments = [], size = 150, strokeWidth = 24 }) {
    const center = size / 2;
    const radius = center - strokeWidth;
    const circumference = 2 * Math.PI * radius;

    const total = segments.reduce((sum, s) => sum + (Number(s.count) || 0), 0) || 1;
    let accumulatedAngle = 0;

    return (
        <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="transform -rotate-90">
            <circle
                cx={center}
                cy={center}
                r={radius}
                fill="transparent"
                stroke="currentColor"
                strokeWidth={strokeWidth}
                className="text-neutral-100 dark:text-neutral-800"
            />
            {segments.map((seg, i) => {
                const fraction = (Number(seg.count) || 0) / total;
                const strokeDasharray = `${fraction * circumference} ${circumference}`;
                const strokeDashoffset = -accumulatedAngle * circumference;
                accumulatedAngle += fraction;

                return (
                    <circle
                        key={seg.key || i}
                        cx={center}
                        cy={center}
                        r={radius}
                        fill="transparent"
                        stroke={seg.color || '#10b981'}
                        strokeWidth={strokeWidth}
                        strokeDasharray={strokeDasharray}
                        strokeDashoffset={strokeDashoffset}
                        strokeLinecap="round"
                        className="transition-all duration-500 hover:opacity-85"
                    />
                );
            })}
        </svg>
    );
}

export default function Dashboard({
    range = 30,
    hasWorkspace = false,
    currentPlan = null,
    stats = null,
    messagesByChannel = [],
    topChannels = [],
    recentActivity = [],
    aiAgentStatus = null,
    automationOverview = null,
    onboardingProgress = null,
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const user = auth?.user;
    const [rangeDropdownOpen, setRangeDropdownOpen] = useState(false);

    const s = stats || {
        contacts_total: 12540,
        contacts_delta: 12.5,
        messages_total: 8921,
        messages_delta: 15.3,
        conversations_active: 86,
        conversations_delta: 8.7,
        campaigns_total: 12,
        campaigns_delta: 20.0,
        automations_total: 24,
        automations_delta: 14.3,
        ai_conversations_total: 43,
        ai_conversations_delta: 18.6,
    };

    const defaultChannels = [
        { name: 'WhatsApp', key: 'whatsapp', count: 5216, percent: 58, color: '#22c55e', bar_percent: 100 },
        { name: 'Messenger', key: 'messenger', count: 1872, percent: 21, color: '#3b82f6', bar_percent: 36 },
        { name: 'Instagram', key: 'instagram', count: 1023, percent: 11, color: '#ec4899', bar_percent: 20 },
        { name: 'Email', key: 'email', count: 810, percent: 9, color: '#8b5cf6', bar_percent: 16 },
    ];

    const channelData = messagesByChannel && messagesByChannel.length > 0 ? messagesByChannel : defaultChannels;
    const topChannelData = topChannels && topChannels.length > 0 ? topChannels : defaultChannels;

    const defaultActivity = [
        { type: 'whatsapp', title: 'New WhatsApp message from +1 234 567 890', time: '2 min ago' },
        { type: 'ai', title: 'AI Agent completed a conversation', time: '5 min ago' },
        { type: 'contact', title: 'New contact added: Sarah Johnson', time: '1 hour ago' },
        { type: 'campaign', title: 'Campaign Summer Sale 2025 sent', time: '2 hours ago' },
        { type: 'voice', title: 'Voice call received from +1 987 654 321', time: '3 hours ago' },
    ];

    const activityData = recentActivity && recentActivity.length > 0 ? recentActivity : defaultActivity;

    const aiAgent = aiAgentStatus || {
        name: 'Sales Assistant',
        status: 'active',
        conversations: 32,
        resolution_rate: '92%',
    };

    const autoOverview = automationOverview || {
        active: 24,
        completed_today: 128,
        failed_today: 6,
    };

    const initials = (() => {
        if (user?.name) return user.name.split(' ').filter(Boolean).map((n) => n[0]).join('').slice(0, 2).toUpperCase();
        if (user?.email) return user.email[0].toUpperCase();
        return 'J';
    })();

    const handleRangeSelect = (r) => {
        setRangeDropdownOpen(false);
        router.get(safeRoute('client.dashboard'), { range: r }, { preserveState: true, preserveScroll: true });
    };

    const rangeLabel = range === 7 ? 'May 20, 2025 - May 26, 2025' : range === 90 ? 'Last 90 Days' : 'May 20, 2025 - May 26, 2025';

    return (
        <ClientLayout title="Dashboard">
            <Head title="Dashboard - Growbridge Connect" />

            <div className="space-y-6 max-w-7xl mx-auto pb-8">
                {/* ── TOP HERO HEADER BAR ────────────────────────────────── */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-neutral-900 p-5 sm:p-6 rounded-2xl border border-neutral-200/80 dark:border-neutral-800 shadow-xs">
                    <div>
                        <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-neutral-900 dark:text-white flex items-center gap-2">
                            Welcome back, <span className="text-brand-600 dark:text-brand-400">{user?.name ? user.name.split(' ')[0] : 'User'}</span> 👋
                        </h1>
                        <p className="text-xs sm:text-sm text-neutral-500 dark:text-neutral-400 mt-1">
                            Here's what's happening with your omnichannel messaging, AI agents, and campaigns today.
                        </p>
                    </div>

                    <div className="flex items-center gap-2.5 flex-wrap sm:flex-nowrap">
                        {/* Date Range Picker */}
                        <div className="relative">
                            <button
                                type="button"
                                onClick={() => setRangeDropdownOpen(!rangeDropdownOpen)}
                                className="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-medium text-neutral-700 dark:text-neutral-300 bg-neutral-50 hover:bg-neutral-100 dark:bg-neutral-800 dark:hover:bg-neutral-750 border border-neutral-200 dark:border-neutral-700 transition shadow-2xs"
                            >
                                <Calendar className="h-3.5 w-3.5 text-neutral-500" />
                                <span>{rangeLabel}</span>
                                <ChevronDown className="h-3.5 w-3.5 text-neutral-400" />
                            </button>

                            {rangeDropdownOpen && (
                                <div className="absolute right-0 mt-1.5 w-48 rounded-xl bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 shadow-lg py-1 z-30">
                                    <button
                                        onClick={() => handleRangeSelect(7)}
                                        className="w-full text-left px-4 py-2 text-xs text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700"
                                    >
                                        Last 7 Days (May 20 - May 26)
                                    </button>
                                    <button
                                        onClick={() => handleRangeSelect(30)}
                                        className="w-full text-left px-4 py-2 text-xs text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700"
                                    >
                                        Last 30 Days
                                    </button>
                                    <button
                                        onClick={() => handleRangeSelect(90)}
                                        className="w-full text-left px-4 py-2 text-xs text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700"
                                    >
                                        Last 90 Days
                                    </button>
                                </div>
                            )}
                        </div>

                        {/* Quick Action: New Campaign */}
                        <Link
                            href={safeRoute('client.campaigns.create')}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-brand-600 hover:bg-brand-500 text-white shadow-xs transition"
                        >
                            <Megaphone className="h-3.5 w-3.5" />
                            <span>New Campaign</span>
                        </Link>

                        {/* Quick Action: Inbox */}
                        <Link
                            href={safeRoute('client.inbox')}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-neutral-800 dark:text-neutral-200 border border-neutral-200 dark:border-neutral-700 transition"
                        >
                            <MessageSquare className="h-3.5 w-3.5" />
                            <span>Inbox</span>
                        </Link>
                    </div>
                </div>

                {/* ── ONBOARDING SETUP PROGRESS WIDGET ──────────────────── */}
                {onboardingProgress && (
                    <SetupProgressWidget progress={onboardingProgress} />
                )}

                {/* ── ROW 1: 6-CARD KPI GRID ────────────────────────────── */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5">
                    {/* 1. Contacts */}
                    <div className="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200/80 dark:border-neutral-800 shadow-xs flex flex-col justify-between hover:border-emerald-500/40 transition duration-200">
                        <div className="flex items-center gap-2">
                            <div className="h-7 w-7 rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                <Users className="h-3.5 w-3.5" />
                            </div>
                            <span className="text-xs font-semibold text-neutral-600 dark:text-neutral-400">Contacts</span>
                        </div>
                        <div className="mt-3">
                            <div className="text-xl font-bold text-neutral-900 dark:text-white tracking-tight">
                                {Number(s.contacts_total || 0).toLocaleString()}
                            </div>
                            <div className="flex items-center gap-1 mt-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                <span>↑ {s.contacts_delta || 12.5}%</span>
                            </div>
                        </div>
                    </div>

                    {/* 2. Messages */}
                    <div className="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200/80 dark:border-neutral-800 shadow-xs flex flex-col justify-between hover:border-blue-500/40 transition duration-200">
                        <div className="flex items-center gap-2">
                            <div className="h-7 w-7 rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400 flex items-center justify-center shrink-0">
                                <MessageSquare className="h-3.5 w-3.5" />
                            </div>
                            <span className="text-xs font-semibold text-neutral-600 dark:text-neutral-400">Messages</span>
                        </div>
                        <div className="mt-3">
                            <div className="text-xl font-bold text-neutral-900 dark:text-white tracking-tight">
                                {Number(s.messages_total || 0).toLocaleString()}
                            </div>
                            <div className="flex items-center gap-1 mt-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                <span>↑ {s.messages_delta || 15.3}%</span>
                            </div>
                        </div>
                    </div>

                    {/* 3. Active Conversations */}
                    <div className="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200/80 dark:border-neutral-800 shadow-xs flex flex-col justify-between hover:border-emerald-500/40 transition duration-200">
                        <div className="flex items-center gap-2">
                            <div className="h-7 w-7 rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                <GitFork className="h-3.5 w-3.5" />
                            </div>
                            <span className="text-xs font-semibold text-neutral-600 dark:text-neutral-400">Active Chats</span>
                        </div>
                        <div className="mt-3">
                            <div className="text-xl font-bold text-neutral-900 dark:text-white tracking-tight">
                                {Number(s.conversations_active || 0).toLocaleString()}
                            </div>
                            <div className="flex items-center gap-1 mt-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                <span>↑ {s.conversations_delta || 8.7}%</span>
                            </div>
                        </div>
                    </div>

                    {/* 4. Campaigns */}
                    <div className="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200/80 dark:border-neutral-800 shadow-xs flex flex-col justify-between hover:border-rose-500/40 transition duration-200">
                        <div className="flex items-center gap-2">
                            <div className="h-7 w-7 rounded-lg bg-rose-50 text-rose-500 dark:bg-rose-950/40 dark:text-rose-400 flex items-center justify-center shrink-0">
                                <Megaphone className="h-3.5 w-3.5" />
                            </div>
                            <span className="text-xs font-semibold text-neutral-600 dark:text-neutral-400">Campaigns</span>
                        </div>
                        <div className="mt-3">
                            <div className="text-xl font-bold text-neutral-900 dark:text-white tracking-tight">
                                {Number(s.campaigns_total || 0).toLocaleString()}
                            </div>
                            <div className="flex items-center gap-1 mt-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                <span>↑ {s.campaigns_delta || 20.0}%</span>
                            </div>
                        </div>
                    </div>

                    {/* 5. Automations */}
                    <div className="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200/80 dark:border-neutral-800 shadow-xs flex flex-col justify-between hover:border-sky-500/40 transition duration-200">
                        <div className="flex items-center gap-2">
                            <div className="h-7 w-7 rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-950/40 dark:text-sky-400 flex items-center justify-center shrink-0">
                                <Workflow className="h-3.5 w-3.5" />
                            </div>
                            <span className="text-xs font-semibold text-neutral-600 dark:text-neutral-400">Automations</span>
                        </div>
                        <div className="mt-3">
                            <div className="text-xl font-bold text-neutral-900 dark:text-white tracking-tight">
                                {Number(s.automations_total || 0).toLocaleString()}
                            </div>
                            <div className="flex items-center gap-1 mt-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                <span>↑ {s.automations_delta || 14.3}%</span>
                            </div>
                        </div>
                    </div>

                    {/* 6. AI Conversations */}
                    <div className="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200/80 dark:border-neutral-800 shadow-xs flex flex-col justify-between hover:border-teal-500/40 transition duration-200">
                        <div className="flex items-center gap-2">
                            <div className="h-7 w-7 rounded-lg bg-teal-50 text-teal-600 dark:bg-teal-950/40 dark:text-teal-400 flex items-center justify-center shrink-0">
                                <Bot className="h-3.5 w-3.5" />
                            </div>
                            <span className="text-xs font-semibold text-neutral-600 dark:text-neutral-400">AI Chats</span>
                        </div>
                        <div className="mt-3">
                            <div className="text-xl font-bold text-neutral-900 dark:text-white tracking-tight">
                                {Number(s.ai_conversations_total || 0).toLocaleString()}
                            </div>
                            <div className="flex items-center gap-1 mt-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                <span>↑ {s.ai_conversations_delta || 18.6}%</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* ── ROW 2: 3-WIDGET MIDDLE SECTION ────────────────────── */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    {/* Card 1: Messages by Channel */}
                    <div className="bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200/80 dark:border-neutral-800 shadow-xs flex flex-col">
                        <h2 className="text-sm font-bold text-neutral-900 dark:text-white mb-4">
                            Messages by Channel
                        </h2>

                        <div className="flex items-center justify-between gap-4 flex-1">
                            <div className="flex-shrink-0 flex items-center justify-center">
                                <DonutSvg segments={channelData} size={145} strokeWidth={22} />
                            </div>

                            <div className="flex-1 space-y-2.5 min-w-0">
                                {channelData.map((c) => (
                                    <div key={c.key} className="flex items-center justify-between text-xs">
                                        <div className="flex items-center gap-2 truncate">
                                            <span className="h-2.5 w-2.5 rounded-full shrink-0" style={{ backgroundColor: c.color }} />
                                            <span className="font-medium text-neutral-700 dark:text-neutral-300 truncate">{c.name}</span>
                                        </div>
                                        <span className="text-neutral-500 dark:text-neutral-400 font-semibold shrink-0 ml-2">
                                            {Number(c.count).toLocaleString()} ({c.percent}%)
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Card 2: Recent Activity */}
                    <div className="bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200/80 dark:border-neutral-800 shadow-xs flex flex-col">
                        <h2 className="text-sm font-bold text-neutral-900 dark:text-white mb-3">
                            Recent Activity
                        </h2>

                        <div className="space-y-3.5 flex-1">
                            {activityData.map((act, i) => (
                                <div key={i} className="flex items-start justify-between gap-2.5 text-xs">
                                    <div className="flex items-center gap-2.5 min-w-0">
                                        {act.type === 'whatsapp' && (
                                            <div className="h-6 w-6 rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 flex items-center justify-center shrink-0">
                                                <ChannelBrandIcon channel="whatsapp" className="h-3.5 w-3.5" />
                                            </div>
                                        )}
                                        {act.type === 'ai' && (
                                            <div className="h-6 w-6 rounded-full bg-teal-50 text-teal-600 dark:bg-teal-950/50 flex items-center justify-center shrink-0">
                                                <Bot className="h-3.5 w-3.5" />
                                            </div>
                                        )}
                                        {act.type === 'contact' && (
                                            <div className="h-6 w-6 rounded-full bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300 flex items-center justify-center shrink-0">
                                                <Users className="h-3.5 w-3.5" />
                                            </div>
                                        )}
                                        {act.type === 'campaign' && (
                                            <div className="h-6 w-6 rounded-full bg-rose-50 text-rose-500 dark:bg-rose-950/50 flex items-center justify-center shrink-0">
                                                <Megaphone className="h-3.5 w-3.5" />
                                            </div>
                                        )}
                                        {act.type === 'voice' && (
                                            <div className="h-6 w-6 rounded-full bg-purple-50 text-purple-600 dark:bg-purple-950/50 flex items-center justify-center shrink-0">
                                                <PhoneCall className="h-3.5 w-3.5" />
                                            </div>
                                        )}
                                        <span className="font-medium text-neutral-800 dark:text-neutral-200 truncate" title={act.title}>
                                            {act.title}
                                        </span>
                                    </div>
                                    <span className="text-neutral-400 dark:text-neutral-500 text-[11px] shrink-0 whitespace-nowrap">
                                        {act.time}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Card 3: Top Channels */}
                    <div className="bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200/80 dark:border-neutral-800 shadow-xs flex flex-col">
                        <h2 className="text-sm font-bold text-neutral-900 dark:text-white mb-4">
                            Top Channels
                        </h2>

                        <div className="space-y-4 flex-1 justify-center flex flex-col">
                            {topChannelData.map((c) => (
                                <div key={c.key} className="space-y-1.5">
                                    <div className="flex items-center justify-between text-xs">
                                        <span className="font-medium text-neutral-800 dark:text-neutral-200">{c.name}</span>
                                        <span className="font-semibold text-neutral-900 dark:text-white">{Number(c.count).toLocaleString()}</span>
                                    </div>
                                    <div className="w-full h-2 rounded-full bg-neutral-100 dark:bg-neutral-800 overflow-hidden">
                                        <div
                                            className="h-full rounded-full transition-all duration-500"
                                            style={{
                                                width: `${c.bar_percent || c.percent}%`,
                                                backgroundColor: c.color,
                                            }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* ── ROW 3: 3-WIDGET BOTTOM SECTION ────────────────────── */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    {/* Card 1: Quick Actions */}
                    <div className="bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200/80 dark:border-neutral-800 shadow-xs flex flex-col">
                        <h2 className="text-sm font-bold text-neutral-900 dark:text-white mb-3">
                            Quick Actions
                        </h2>

                        <div className="grid grid-cols-2 gap-2.5 flex-1">
                            <Link
                                href={safeRoute('client.campaigns.index')}
                                className="flex flex-col items-center justify-center p-2.5 rounded-xl border border-neutral-200/70 dark:border-neutral-800 hover:border-emerald-500/50 hover:bg-emerald-50/20 dark:hover:bg-emerald-950/20 transition group text-center"
                            >
                                <div className="h-7 w-7 rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 flex items-center justify-center mb-1 group-hover:scale-110 transition">
                                    <Megaphone className="h-3.5 w-3.5" />
                                </div>
                                <span className="text-[11px] font-medium text-neutral-700 dark:text-neutral-300">New Campaign</span>
                            </Link>

                            <Link
                                href={safeRoute('client.automations.index')}
                                className="flex flex-col items-center justify-center p-2.5 rounded-xl border border-neutral-200/70 dark:border-neutral-800 hover:border-emerald-500/50 hover:bg-emerald-50/20 dark:hover:bg-emerald-950/20 transition group text-center"
                            >
                                <div className="h-7 w-7 rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 flex items-center justify-center mb-1 group-hover:scale-110 transition">
                                    <Workflow className="h-3.5 w-3.5" />
                                </div>
                                <span className="text-[11px] font-medium text-neutral-700 dark:text-neutral-300">New Automation</span>
                            </Link>

                            <Link
                                href={safeRoute('client.contacts.index')}
                                className="flex flex-col items-center justify-center p-2.5 rounded-xl border border-neutral-200/70 dark:border-neutral-800 hover:border-emerald-500/50 hover:bg-emerald-50/20 dark:hover:bg-emerald-950/20 transition group text-center"
                            >
                                <div className="h-7 w-7 rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 flex items-center justify-center mb-1 group-hover:scale-110 transition">
                                    <UserPlus className="h-3.5 w-3.5" />
                                </div>
                                <span className="text-[11px] font-medium text-neutral-700 dark:text-neutral-300">Add Contact</span>
                            </Link>

                            <Link
                                href={safeRoute('client.inbox.setup')}
                                className="flex flex-col items-center justify-center p-2.5 rounded-xl border border-neutral-200/70 dark:border-neutral-800 hover:border-emerald-500/50 hover:bg-emerald-50/20 dark:hover:bg-emerald-950/20 transition group text-center"
                            >
                                <div className="h-7 w-7 rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 flex items-center justify-center mb-1 group-hover:scale-110 transition">
                                    <Share2 className="h-3.5 w-3.5" />
                                </div>
                                <span className="text-[11px] font-medium text-neutral-700 dark:text-neutral-300">Connect Channel</span>
                            </Link>

                            <Link
                                href={safeRoute('client.ai.chatbots.index')}
                                className="flex flex-col items-center justify-center p-2.5 rounded-xl border border-neutral-200/70 dark:border-neutral-800 hover:border-emerald-500/50 hover:bg-emerald-50/20 dark:hover:bg-emerald-950/20 transition group text-center"
                            >
                                <div className="h-7 w-7 rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 flex items-center justify-center mb-1 group-hover:scale-110 transition">
                                    <Bot className="h-3.5 w-3.5" />
                                </div>
                                <span className="text-[11px] font-medium text-neutral-700 dark:text-neutral-300">Create AI Agent</span>
                            </Link>

                            <Link
                                href={safeRoute('client.api-tokens.index', '/api-tokens')}
                                className="flex flex-col items-center justify-center p-2.5 rounded-xl border border-neutral-200/70 dark:border-neutral-800 hover:border-emerald-500/50 hover:bg-emerald-50/20 dark:hover:bg-emerald-950/20 transition group text-center"
                            >
                                <div className="h-7 w-7 rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 flex items-center justify-center mb-1 group-hover:scale-110 transition">
                                    <Sliders className="h-3.5 w-3.5" />
                                </div>
                                <span className="text-[11px] font-medium text-neutral-700 dark:text-neutral-300">API & Connections</span>
                            </Link>
                        </div>
                    </div>

                    {/* Card 2: AI Agent Status */}
                    <div className="bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200/80 dark:border-neutral-800 shadow-xs flex flex-col justify-between">
                        <div>
                            <h2 className="text-sm font-bold text-neutral-900 dark:text-white mb-4">
                                AI Agent Status
                            </h2>

                            <div className="flex items-center gap-3 p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200/70 dark:border-neutral-700/60">
                                <div className="h-10 w-10 rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                    <Bot className="h-6 w-6" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center justify-between gap-2">
                                        <h3 className="text-xs font-bold text-neutral-900 dark:text-white truncate">
                                            {aiAgent.name}
                                        </h3>
                                        <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300">
                                            Active
                                        </span>
                                    </div>
                                    <p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5">Omnichannel Smart Responder</p>
                                </div>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3 pt-3 border-t border-neutral-100 dark:border-neutral-800 mt-3">
                            <div>
                                <span className="text-[11px] text-neutral-400 dark:text-neutral-500">Conversations</span>
                                <div className="text-lg font-bold text-neutral-900 dark:text-white mt-0.5">
                                    {aiAgent.conversations}
                                </div>
                            </div>
                            <div>
                                <span className="text-[11px] text-neutral-400 dark:text-neutral-500">Resolution Rate</span>
                                <div className="text-lg font-bold text-neutral-900 dark:text-white mt-0.5">
                                    {aiAgent.resolution_rate}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Card 3: Automation Overview */}
                    <div className="bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200/80 dark:border-neutral-800 shadow-xs flex flex-col justify-between">
                        <h2 className="text-sm font-bold text-neutral-900 dark:text-white mb-2">
                            Automation Overview
                        </h2>

                        <div className="grid grid-cols-3 gap-2 text-center py-3">
                            <div className="p-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/50">
                                <div className="text-2xl font-black text-neutral-900 dark:text-white">
                                    {autoOverview.active}
                                </div>
                                <div className="text-[10px] font-medium text-neutral-500 dark:text-neutral-400 mt-1 leading-tight">
                                    Active Automations
                                </div>
                            </div>

                            <div className="p-2.5 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20">
                                <div className="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                                    {autoOverview.completed_today}
                                </div>
                                <div className="text-[10px] font-medium text-neutral-500 dark:text-neutral-400 mt-1 leading-tight">
                                    Completed Today
                                </div>
                            </div>

                            <div className="p-2.5 rounded-xl bg-rose-50/50 dark:bg-rose-950/20">
                                <div className="text-2xl font-black text-rose-600 dark:text-rose-400">
                                    {autoOverview.failed_today}
                                </div>
                                <div className="text-[10px] font-medium text-neutral-500 dark:text-neutral-400 mt-1 leading-tight">
                                    Failed Today
                                </div>
                            </div>
                        </div>

                        <div className="pt-2 text-center">
                            <Link
                                href={safeRoute('client.automations.index')}
                                className="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline"
                            >
                                <span>Manage All Automations</span>
                                <ArrowUpRight className="h-3.5 w-3.5" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </ClientLayout>
    );
}
