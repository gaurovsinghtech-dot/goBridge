import React, { useState, useEffect } from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    PhoneCall, PhoneIncoming, PhoneOutgoing, PhoneForwarded,
    Users, Bot, Flame, Sparkles, Sliders, AlertCircle,
    CheckCircle2, Clock, Zap, ArrowRight, Play, Pause,
    RefreshCw, ShieldAlert, Activity, FileText, Check,
    Radio, ExternalLink, Headset, Volume2, PhoneMissed
} from 'lucide-react';
import { Card, Button, Badge, Modal } from '@/Components/ui';
import { toast } from 'sonner';

export default function VoiceCallCenterIndex({
    activeCalls: initialActiveCalls = [],
    activeHandoffs: initialActiveHandoffs = [],
    agents: initialAgents = [],
    queueSummary: initialQueueSummary = {},
    activeCampaigns: initialActiveCampaigns = [],
    todayStats: initialTodayStats = {},
    outcomes: initialOutcomes = {},
    providers: initialProviders = [],
    recentActivity: initialRecentActivity = [],
    alerts: initialAlerts = [],
}) {
    const [activeCalls, setActiveCalls] = useState(initialActiveCalls);
    const [activeHandoffs, setActiveHandoffs] = useState(initialActiveHandoffs);
    const [agents, setAgents] = useState(initialAgents);
    const [queueSummary, setQueueSummary] = useState(initialQueueSummary);
    const [activeCampaigns, setActiveCampaigns] = useState(initialActiveCampaigns);
    const [todayStats, setTodayStats] = useState(initialTodayStats);
    const [outcomes, setOutcomes] = useState(initialOutcomes);
    const [providers, setProviders] = useState(initialProviders);
    const [recentActivity, setRecentActivity] = useState(initialRecentActivity);
    const [alerts, setAlerts] = useState(initialAlerts);

    const [isLivePolling, setIsLivePolling] = useState(true);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [selectedCall, setSelectedCall] = useState(null);
    const [callModal, setCallModal] = useState(false);

    // Polling effect every 4 seconds for live telemetry
    useEffect(() => {
        if (!isLivePolling) return;

        const interval = setInterval(async () => {
            try {
                const res = await fetch(route('client.voice.call-center.live-feed'), {
                    headers: { credentials: 'same-origin', Accept: 'application/json' },
                });
                if (res.ok) {
                    const data = await res.json();
                    setActiveCalls(data.activeCalls || []);
                    setActiveHandoffs(data.activeHandoffs || []);
                    setAgents(data.agents || []);
                    setQueueSummary(data.queueSummary || {});
                    setActiveCampaigns(data.activeCampaigns || []);
                    setTodayStats(data.todayStats || {});
                    setOutcomes(data.outcomes || {});
                    setProviders(data.providers || []);
                    setRecentActivity(data.recentActivity || []);
                    setAlerts(data.alerts || []);
                }
            } catch (err) {
                // Silently ignore background polling transient errors
            }
        }, 4000);

        return () => clearInterval(interval);
    }, [isLivePolling]);

    const handleManualRefresh = async () => {
        setIsRefreshing(true);
        try {
            const res = await fetch(route('client.voice.call-center.live-feed'), {
                headers: { credentials: 'same-origin', Accept: 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                setActiveCalls(data.activeCalls || []);
                setActiveHandoffs(data.activeHandoffs || []);
                setAgents(data.agents || []);
                setQueueSummary(data.queueSummary || {});
                setActiveCampaigns(data.activeCampaigns || []);
                setTodayStats(data.todayStats || {});
                setOutcomes(data.outcomes || {});
                setProviders(data.providers || []);
                setRecentActivity(data.recentActivity || []);
                setAlerts(data.alerts || []);
                toast.success('Live operations telemetry updated.');
            }
        } finally {
            setIsRefreshing(false);
        }
    };

    const handleToggleCampaign = (uuid, currentStatus) => {
        const action = currentStatus === 'running' ? 'pause' : 'start';
        router.post(route(`client.voice.campaigns.${action}`, uuid), {}, {
            onSuccess: () => {
                toast.success(`Campaign ${action === 'pause' ? 'paused' : 'resumed'}.`);
                handleManualRefresh();
            },
        });
    };

    return (
        <ClientLayout>
            <Head title="AI Voice Call Center & Real-Time Monitor" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* Header & Quick Action Bar */}
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div className="flex items-center gap-3.5">
                        <div className="h-11 w-11 rounded-2xl bg-brand-500/10 text-brand-600 dark:text-brand-400 flex items-center justify-center">
                            <Headset className="w-6 h-6" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2.5">
                                <h1 className="text-xl font-bold text-neutral-900 dark:text-white">AI Voice Call Center</h1>
                                <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    <span className="h-2 w-2 rounded-full bg-emerald-500 animate-ping" />
                                    System Online
                                </span>
                            </div>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                                Live operational cockpit for inbound calls, outbound campaigns, human handoffs, and AI agents.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        <button
                            type="button"
                            onClick={() => setIsLivePolling(!isLivePolling)}
                            className={`px-2.5 py-1.5 rounded-lg border text-xs font-semibold flex items-center gap-1.5 transition ${
                                isLivePolling
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300'
                                    : 'bg-neutral-100 text-neutral-600 border-neutral-200 dark:bg-neutral-800 dark:text-neutral-400'
                            }`}
                        >
                            <Radio className={`w-3.5 h-3.5 ${isLivePolling ? 'text-emerald-600 animate-pulse' : ''}`} />
                            {isLivePolling ? 'Live Telemetry Active' : 'Telemetry Paused'}
                        </button>

                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleManualRefresh}
                            disabled={isRefreshing}
                            className="text-xs gap-1"
                        >
                            <RefreshCw className={`w-3.5 h-3.5 ${isRefreshing ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>

                        <Link href={route('client.voice.campaigns.create')}>
                            <Button size="sm" variant="brand" className="text-xs font-bold gap-1 bg-brand-600 text-white">
                                <Zap className="w-3.5 h-3.5" /> + New Campaign
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Alerts Banner if any */}
                {alerts.length > 0 && (
                    <div className="space-y-2">
                        {alerts.map((alert, idx) => (
                            <div
                                key={idx}
                                className={`p-3.5 rounded-xl border flex items-center gap-3 text-xs font-medium ${
                                    alert.type === 'danger'
                                        ? 'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-950/30 dark:border-rose-900/50 dark:text-rose-300'
                                        : 'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-950/30 dark:border-amber-900/50 dark:text-amber-300'
                                }`}
                            >
                                <AlertCircle className="w-4 h-4 shrink-0" />
                                <span>{alert.message}</span>
                            </div>
                        ))}
                    </div>
                )}

                {/* Top 4 Primary KPI Cards */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    {/* Active Calls */}
                    <Card className="p-5 border-neutral-200 dark:border-neutral-800 relative overflow-hidden">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold text-neutral-500 uppercase tracking-wider">Active Calls</span>
                            <div className="p-2 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <PhoneCall className="w-4 h-4 animate-bounce" />
                            </div>
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <span className="text-3xl font-extrabold text-neutral-900 dark:text-white">
                                {activeCalls.length}
                            </span>
                            <span className="text-xs font-semibold text-blue-600">● Live Now</span>
                        </div>
                    </Card>

                    {/* AI Agents */}
                    <Card className="p-5 border-neutral-200 dark:border-neutral-800">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold text-neutral-500 uppercase tracking-wider">AI Voice Agents</span>
                            <div className="p-2 rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400">
                                <Bot className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <span className="text-3xl font-extrabold text-neutral-900 dark:text-white">
                                {agents.filter(a => a.status === 'active').length}
                            </span>
                            <span className="text-xs text-neutral-400">/ {agents.length} Total</span>
                        </div>
                    </Card>

                    {/* Smart Queue Backlog */}
                    <Card className="p-5 border-neutral-200 dark:border-neutral-800">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold text-neutral-500 uppercase tracking-wider">Queue Backlog</span>
                            <div className="p-2 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                <Zap className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <span className="text-3xl font-extrabold text-neutral-900 dark:text-white">
                                {(queueSummary.ready || 0) + (queueSummary.scheduled || 0)}
                            </span>
                            <span className="text-xs font-semibold text-amber-600">{queueSummary.callback || 0} Callbacks</span>
                        </div>
                    </Card>

                    {/* Today's Calls */}
                    <Card className="p-5 border-neutral-200 dark:border-neutral-800">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold text-neutral-500 uppercase tracking-wider">Today's Calls</span>
                            <div className="p-2 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                <Activity className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <span className="text-3xl font-extrabold text-neutral-900 dark:text-white">
                                {todayStats.total_calls || 0}
                            </span>
                            <span className="text-xs font-semibold text-emerald-600">{todayStats.ai_resolved || 0} Resolved</span>
                        </div>
                    </Card>
                </div>

                {/* Main 2-Column Operational Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left & Center Column (2 cols) */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* 1. Live Calls Monitor */}
                        <Card className="border-neutral-200 dark:border-neutral-800 overflow-hidden">
                            <div className="p-4 sm:p-5 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50/50 dark:bg-neutral-800/30">
                                <div className="flex items-center gap-2">
                                    <span className="h-2.5 w-2.5 rounded-full bg-blue-500 animate-ping" />
                                    <h2 className="text-sm font-bold text-neutral-900 dark:text-white">Live Active Calls</h2>
                                </div>
                                <span className="text-xs font-semibold text-neutral-500">{activeCalls.length} in progress</span>
                            </div>

                            <div className="divide-y divide-neutral-200 dark:divide-neutral-800">
                                {activeCalls.length === 0 ? (
                                    <div className="p-8 text-center text-neutral-400 text-xs">
                                        <PhoneCall className="w-8 h-8 mx-auto mb-2 opacity-30" />
                                        No live calls in progress right now. Outbound campaigns and incoming calls will stream here automatically.
                                    </div>
                                ) : (
                                    activeCalls.map((call) => (
                                        <div
                                            key={call.id}
                                            onClick={() => {
                                                setSelectedCall(call);
                                                setCallModal(true);
                                            }}
                                            className="p-4 hover:bg-neutral-50/80 dark:hover:bg-neutral-800/40 cursor-pointer transition flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                                        >
                                            <div className="flex items-center gap-3">
                                                <div className="h-9 w-9 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 flex items-center justify-center shrink-0">
                                                    {call.direction === 'outbound' ? <PhoneOutgoing className="w-4 h-4" /> : <PhoneIncoming className="w-4 h-4" />}
                                                </div>
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-bold text-xs text-neutral-900 dark:text-white">{call.contact_name}</span>
                                                        <span className="text-[11px] font-mono text-neutral-500">{call.phone}</span>
                                                    </div>
                                                    <div className="text-[11px] text-neutral-500 mt-0.5">
                                                        Agent: <span className="font-semibold text-neutral-700 dark:text-neutral-300">{call.agent_name}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-3 self-end sm:self-center">
                                                <div className="text-right">
                                                    <span className="text-xs font-mono font-bold text-neutral-900 dark:text-white block">
                                                        ⏱ {call.duration_formatted}
                                                    </span>
                                                    <span className="text-[10px] text-neutral-400 capitalize">{call.provider}</span>
                                                </div>

                                                <Badge variant={call.status === 'in_progress' ? 'brand' : 'warning'} className="capitalize text-[10px]">
                                                    ● {call.status.replace('_', ' ')}
                                                </Badge>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </Card>

                        {/* 2. Active Human Handoffs */}
                        {activeHandoffs.length > 0 && (
                            <Card className="border-amber-200 dark:border-amber-900/50 bg-amber-50/30 dark:bg-amber-950/10 overflow-hidden">
                                <div className="p-4 border-b border-amber-200/60 dark:border-amber-900/40 flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <PhoneForwarded className="w-4 h-4 text-amber-600" />
                                        <h3 className="text-xs font-bold text-amber-900 dark:text-amber-300 uppercase tracking-wider">
                                            Active Human Handoffs ({activeHandoffs.length})
                                        </h3>
                                    </div>
                                </div>

                                <div className="p-4 space-y-3">
                                    {activeHandoffs.map((h) => (
                                        <div key={h.id} className="p-3 bg-white dark:bg-neutral-900 rounded-xl border border-amber-200/80 dark:border-amber-800/40 flex items-center justify-between text-xs">
                                            <div>
                                                <span className="font-bold text-neutral-900 dark:text-white">{h.customer_name}</span>
                                                <p className="text-[11px] text-neutral-500 mt-0.5">{h.reason} → Transferred to <span className="font-semibold text-amber-600">{h.destination}</span></p>
                                            </div>
                                            <Badge variant="warning" className="text-[10px]">
                                                ● Connected
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            </Card>
                        )}

                        {/* 3. Active Voice Campaigns Monitor */}
                        <Card className="border-neutral-200 dark:border-neutral-800 p-5 space-y-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Zap className="w-4 h-4 text-brand-600" />
                                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Active Campaigns</h3>
                                </div>
                                <Link href={route('client.voice.campaigns.index')} className="text-xs font-semibold text-brand-600 hover:underline flex items-center gap-1">
                                    All Campaigns <ArrowRight className="w-3 h-3" />
                                </Link>
                            </div>

                            <div className="space-y-3">
                                {activeCampaigns.length === 0 ? (
                                    <p className="text-xs text-neutral-400 py-3 text-center">No active voice campaigns running.</p>
                                ) : (
                                    activeCampaigns.map((camp) => (
                                        <div key={camp.id} className="p-3.5 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                                            <div className="space-y-1.5 flex-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-bold text-neutral-900 dark:text-white">{camp.name}</span>
                                                    <Badge variant={camp.status === 'running' ? 'success' : 'warning'} className="text-[10px] capitalize">
                                                        ● {camp.status}
                                                    </Badge>
                                                </div>
                                                <div className="w-full bg-neutral-200 dark:bg-neutral-700 rounded-full h-2 max-w-xs overflow-hidden">
                                                    <div className="bg-brand-600 h-2 rounded-full transition-all" style={{ width: `${camp.progress_percent || 0}%` }} />
                                                </div>
                                                <p className="text-[11px] text-neutral-500">
                                                    {camp.completed_calls} / {camp.total_contacts} Dialed • 🔥 {camp.interested || 0} Interested
                                                </p>
                                            </div>

                                            <div className="flex items-center gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleToggleCampaign(camp.uuid, camp.status)}
                                                    className="text-xs gap-1"
                                                >
                                                    {camp.status === 'running' ? <Pause className="w-3 h-3" /> : <Play className="w-3 h-3" />}
                                                    {camp.status === 'running' ? 'Pause' : 'Resume'}
                                                </Button>
                                                <Link href={route('client.voice.campaigns.show', camp.uuid)}>
                                                    <Button size="sm" variant="ghost" className="text-xs">
                                                        View
                                                    </Button>
                                                </Link>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </Card>

                        {/* 4. Today's Performance & Outcomes */}
                        <Card className="border-neutral-200 dark:border-neutral-800 p-5 space-y-4">
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Today's Call Performance & Outcomes</h3>

                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                                <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700/60">
                                    <span className="text-neutral-500 font-semibold text-[11px] block">Answered</span>
                                    <span className="text-lg font-bold text-neutral-900 dark:text-white mt-0.5 block">{todayStats.answered || 0}</span>
                                </div>
                                <div className="p-3 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/30">
                                    <span className="text-emerald-700 dark:text-emerald-400 font-semibold text-[11px] block">AI Resolved</span>
                                    <span className="text-lg font-bold text-emerald-900 dark:text-emerald-300 mt-0.5 block">{todayStats.ai_resolved || 0}</span>
                                </div>
                                <div className="p-3 rounded-xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/30">
                                    <span className="text-amber-700 dark:text-amber-400 font-semibold text-[11px] block">Human Handoff</span>
                                    <span className="text-lg font-bold text-amber-900 dark:text-amber-300 mt-0.5 block">{todayStats.human_handoff || 0}</span>
                                </div>
                                <div className="p-3 rounded-xl bg-rose-50/50 dark:bg-rose-950/20 border border-rose-100 dark:border-rose-900/30">
                                    <span className="text-rose-700 dark:text-rose-400 font-semibold text-[11px] block">Failed / No Answer</span>
                                    <span className="text-lg font-bold text-rose-900 dark:text-rose-300 mt-0.5 block">{(todayStats.failed || 0) + (todayStats.no_answer || 0)}</span>
                                </div>
                            </div>
                        </Card>
                    </div>

                    {/* Right Column / Operational Sidebar */}
                    <div className="space-y-6">
                        {/* 1. Voice Providers Status */}
                        <Card className="border-neutral-200 dark:border-neutral-800 p-5 space-y-3">
                            <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider">Voice Providers</h3>
                            <div className="space-y-2">
                                {providers.map((p) => (
                                    <div key={p.provider} className="flex items-center justify-between text-xs p-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-800">
                                        <span className="font-semibold text-neutral-900 dark:text-white">{p.name}</span>
                                        <span className={`inline-flex items-center gap-1 font-bold text-[11px] ${p.status === 'connected' ? 'text-emerald-600' : 'text-rose-600'}`}>
                                            ● {p.status === 'connected' ? 'Connected' : 'Disconnected'}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </Card>

                        {/* 2. AI Voice Agents Status */}
                        <Card className="border-neutral-200 dark:border-neutral-800 p-5 space-y-3">
                            <div className="flex items-center justify-between">
                                <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider">AI Voice Agents</h3>
                                <Link href={route('client.ai.voice-studio.index')} className="text-xs text-brand-600 hover:underline">
                                    Studio
                                </Link>
                            </div>

                            <div className="space-y-2.5">
                                {agents.map((ag) => (
                                    <div key={ag.id} className="p-3 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/30 text-xs space-y-1">
                                        <div className="flex items-center justify-between">
                                            <span className="font-bold text-neutral-900 dark:text-white">{ag.name}</span>
                                            <span className={`text-[10px] font-bold ${ag.status === 'active' ? 'text-emerald-600' : 'text-neutral-400'}`}>
                                                ● {ag.status}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-[11px] text-neutral-500 pt-1 border-t border-neutral-200/50 dark:border-neutral-700/50">
                                            <span>{ag.calls_today} calls today</span>
                                            <span className="font-semibold text-emerald-600">{ag.resolution_rate}% resolved</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Card>

                        {/* 3. Calling Queue Monitor */}
                        <Card className="border-neutral-200 dark:border-neutral-800 p-5 space-y-3">
                            <div className="flex items-center justify-between">
                                <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider">Calling Queue</h3>
                                <Link href={route('client.voice.queue.index')}>
                                    <Button size="sm" variant="outline" className="text-xs gap-1 py-1">
                                        View Queue <ArrowRight className="w-3 h-3" />
                                    </Button>
                                </Link>
                            </div>

                            <div className="grid grid-cols-2 gap-2 text-xs">
                                <div className="p-2.5 rounded-lg bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/30">
                                    <span className="text-[11px] text-emerald-700 dark:text-emerald-400 block font-medium">Ready to Call</span>
                                    <span className="text-base font-bold text-emerald-900 dark:text-emerald-300">{queueSummary.ready || 0}</span>
                                </div>
                                <div className="p-2.5 rounded-lg bg-amber-50/50 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/30">
                                    <span className="text-[11px] text-amber-700 dark:text-amber-400 block font-medium">Callbacks</span>
                                    <span className="text-base font-bold text-amber-900 dark:text-amber-300">{queueSummary.callback || 0}</span>
                                </div>
                                <div className="p-2.5 rounded-lg bg-blue-50/50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/30">
                                    <span className="text-[11px] text-blue-700 dark:text-blue-400 block font-medium">Scheduled</span>
                                    <span className="text-base font-bold text-blue-900 dark:text-blue-300">{queueSummary.scheduled || 0}</span>
                                </div>
                                <div className="p-2.5 rounded-lg bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700/60">
                                    <span className="text-[11px] text-neutral-500 block font-medium">Excluded</span>
                                    <span className="text-base font-bold text-neutral-800 dark:text-neutral-200">{queueSummary.excluded || 0}</span>
                                </div>
                            </div>
                        </Card>

                        {/* 4. Recent Real-Time Activity */}
                        <Card className="border-neutral-200 dark:border-neutral-800 p-5 space-y-3">
                            <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider">Recent Activity</h3>
                            <div className="space-y-2 text-xs">
                                {recentActivity.length === 0 ? (
                                    <p className="text-neutral-400 text-center py-2">No activity recorded today.</p>
                                ) : (
                                    recentActivity.map((act) => (
                                        <div key={act.id} className="flex items-start gap-2.5 pb-2 border-b border-neutral-100 dark:border-neutral-800 last:border-0 last:pb-0">
                                            <span className="text-[10px] font-mono text-neutral-400 shrink-0 mt-0.5">{act.time}</span>
                                            <span className="text-neutral-700 dark:text-neutral-300 leading-snug">{act.message}</span>
                                        </div>
                                    ))
                                )}
                            </div>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Live Call Detail Modal */}
            <Modal
                show={callModal}
                onClose={() => setCallModal(false)}
                title={`Live Call — ${selectedCall?.contact_name || selectedCall?.phone}`}
            >
                {selectedCall && (
                    <div className="space-y-4 text-xs">
                        <div className="grid grid-cols-2 gap-3 p-3 bg-neutral-50 dark:bg-neutral-800/50 rounded-xl border border-neutral-200 dark:border-neutral-700">
                            <div>
                                <span className="text-neutral-500 block text-[11px]">Caller Phone</span>
                                <span className="font-mono font-bold text-neutral-900 dark:text-white">{selectedCall.phone}</span>
                            </div>
                            <div>
                                <span className="text-neutral-500 block text-[11px]">AI Voice Agent</span>
                                <span className="font-bold text-neutral-900 dark:text-white">{selectedCall.agent_name}</span>
                            </div>
                            <div>
                                <span className="text-neutral-500 block text-[11px]">Duration</span>
                                <span className="font-mono font-bold text-blue-600">⏱ {selectedCall.duration_formatted}</span>
                            </div>
                            <div>
                                <span className="text-neutral-500 block text-[11px]">Telephony Provider</span>
                                <span className="font-bold text-neutral-900 dark:text-white capitalize">{selectedCall.provider}</span>
                            </div>
                        </div>

                        {selectedCall.transcript ? (
                            <div className="space-y-1.5">
                                <span className="font-bold text-neutral-900 dark:text-white uppercase tracking-wider text-[10px]">
                                    Live Transcript
                                </span>
                                <div className="p-3 bg-neutral-950 text-neutral-200 rounded-xl font-mono text-[11px] max-h-48 overflow-y-auto whitespace-pre-wrap leading-relaxed">
                                    {selectedCall.transcript}
                                </div>
                            </div>
                        ) : (
                            <p className="text-neutral-400 text-[11px] italic">
                                Live audio stream connected via {selectedCall.provider}. Spoken conversation is currently in progress.
                            </p>
                        )}

                        <div className="flex justify-end pt-2 border-t border-neutral-100 dark:border-neutral-800">
                            <Button size="sm" variant="outline" onClick={() => setCallModal(false)}>Close</Button>
                        </div>
                    </div>
                )}
            </Modal>
        </ClientLayout>
    );
}
