import React from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    PhoneCall, Plus, Play, Pause, Square, BarChart3,
    Sparkles, CheckCircle2, Clock, Users, ArrowRight,
    Flame, PhoneForwarded, AlertCircle, RefreshCw, Layers
} from 'lucide-react';
import { Card, Button, Badge } from '@/Components/ui';
import { toast } from 'sonner';

export default function VoiceCampaignsIndex({ campaigns = { data: [] }, stats = {} }) {
    const campaignList = campaigns?.data || [];

    const handleStart = (uuid) => {
        router.post(route('client.voice.campaigns.start', uuid), {}, {
            onSuccess: () => toast.success('Campaign started! Calls are now being placed.'),
        });
    };

    const handlePause = (uuid) => {
        router.post(route('client.voice.campaigns.pause', uuid), {}, {
            onSuccess: () => toast.success('Campaign paused.'),
        });
    };

    const handleStop = (uuid) => {
        if (!confirm('Are you sure you want to stop this campaign? Pending calls will be cancelled.')) return;
        router.post(route('client.voice.campaigns.stop', uuid), {}, {
            onSuccess: () => toast.success('Campaign stopped.'),
        });
    };

    const getTypeLabel = (type) => {
        return {
            lead_followup: 'Lead Follow-up',
            appointment_reminder: 'Appointment Reminder',
            survey: 'Customer Survey',
            reengagement: 'Customer Re-engagement',
            payment_reminder: 'Payment Reminder',
            custom: 'Custom Campaign',
        }[type] || type;
    };

    return (
        <ClientLayout>
            <Head title="AI Voice Campaigns — Growbridge Connect" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* Header Banner */}
                <div className="bg-gradient-to-r from-brand-900 via-brand-800 to-brand-950 text-white rounded-2xl p-6 sm:p-8 shadow-lg relative overflow-hidden">
                    <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div className="max-w-2xl">
                            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-accent-500/20 text-accent-400 text-xs font-semibold uppercase tracking-wider mb-3">
                                <Sparkles className="w-3.5 h-3.5" />
                                Outbound AI Telephony
                            </div>
                            <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-white mb-2">
                                Outbound AI Voice Campaigns
                            </h1>
                            <p className="text-slate-300 text-sm sm:text-base leading-relaxed">
                                Automate permission-based lead follow-ups, appointment reminders, and qualification calls with your conversational AI Voice Agents.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <Link href={route('client.voice.campaigns.create')}>
                                <Button className="bg-accent-500 hover:bg-accent-600 text-slate-950 font-bold gap-2 shadow-md">
                                    <Plus className="w-4 h-4" /> Create Voice Campaign
                                </Button>
                            </Link>
                            <Link href={route('client.ai.voice-studio.index')}>
                                <Button variant="outline" className="bg-white/10 hover:bg-white/20 text-white border-white/20 gap-2">
                                    <Sparkles className="w-4 h-4" /> Voice Studio
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>

                {/* KPI Metrics */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card className="p-5 border-neutral-200 dark:border-neutral-800">
                        <span className="text-xs font-medium text-neutral-500">Active Campaigns</span>
                        <div className="flex items-baseline justify-between mt-1">
                            <span className="text-2xl font-bold text-neutral-900 dark:text-white">{stats.running_campaigns ?? 0}</span>
                            <span className="text-xs text-neutral-400">/ {stats.total_campaigns ?? 0} total</span>
                        </div>
                    </Card>
                    <Card className="p-5 border-neutral-200 dark:border-neutral-800">
                        <span className="text-xs font-medium text-neutral-500">Total Contacts Queued</span>
                        <p className="text-2xl font-bold text-neutral-900 dark:text-white mt-1">{stats.total_contacts_queued ?? 0}</p>
                    </Card>
                    <Card className="p-5 border-neutral-200 dark:border-neutral-800">
                        <span className="text-xs font-medium text-neutral-500">Calls Completed</span>
                        <p className="text-2xl font-bold text-neutral-900 dark:text-white mt-1">{stats.total_calls_completed ?? 0}</p>
                    </Card>
                    <Card className="p-5 border-neutral-200 dark:border-neutral-800">
                        <span className="text-xs font-medium text-neutral-500">Qualified Leads</span>
                        <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{stats.total_qualified ?? 0}</p>
                    </Card>
                </div>

                {/* Campaigns List */}
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-base font-bold text-neutral-900 dark:text-white">All Voice Campaigns</h2>
                    </div>

                    {campaignList.length === 0 ? (
                        <Card className="p-12 text-center border-neutral-200 dark:border-neutral-800 space-y-4">
                            <div className="h-12 w-12 rounded-full bg-brand-50 text-brand-600 dark:bg-neutral-800 dark:text-brand-400 flex items-center justify-center mx-auto">
                                <PhoneCall className="w-6 h-6" />
                            </div>
                            <div>
                                <h3 className="text-base font-bold text-neutral-900 dark:text-white">No Voice Campaigns Yet</h3>
                                <p className="text-xs text-neutral-500 max-w-sm mx-auto mt-1">
                                    Launch your first automated outbound voice campaign to follow up with leads and schedule appointments.
                                </p>
                            </div>
                            <Link href={route('client.voice.campaigns.create')}>
                                <Button variant="brand" size="sm" className="font-bold gap-2">
                                    <Plus className="w-4 h-4" /> Create First Campaign
                                </Button>
                            </Link>
                        </Card>
                    ) : (
                        <div className="grid grid-cols-1 gap-4">
                            {campaignList.map((c) => {
                                const progress = c.total_contacts > 0
                                    ? Math.round((c.completed_calls / c.total_contacts) * 100)
                                    : 0;

                                return (
                                    <Card key={c.id} className="p-6 border-neutral-200 dark:border-neutral-800 space-y-4 hover:shadow-xs transition">
                                        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2.5">
                                                    <Link
                                                        href={route('client.voice.campaigns.show', c.uuid)}
                                                        className="text-base font-bold text-neutral-900 dark:text-white hover:text-brand-600"
                                                    >
                                                        {c.name}
                                                    </Link>
                                                    <Badge variant={
                                                        c.status === 'running' ? 'success' :
                                                        c.status === 'scheduled' ? 'brand' :
                                                        c.status === 'paused' ? 'warning' :
                                                        c.status === 'completed' ? 'neutral' : 'danger'
                                                    } className="capitalize text-[11px]">
                                                        ● {c.status}
                                                    </Badge>
                                                    <span className="text-xs px-2.5 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 font-semibold">
                                                        {getTypeLabel(c.type)}
                                                    </span>
                                                </div>
                                                <p className="text-xs text-neutral-500">
                                                    AI Agent: <span className="font-semibold text-neutral-700 dark:text-neutral-300">{c.agent?.name || 'Default Voice Assistant'}</span> • Caller ID: <span className="font-semibold text-neutral-700 dark:text-neutral-300">{c.caller_id_number || 'Default Number'}</span>
                                                </p>
                                            </div>

                                            {/* Action Buttons */}
                                            <div className="flex items-center gap-2">
                                                {c.status === 'running' ? (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handlePause(c.uuid)}
                                                        className="text-xs text-amber-600 border-amber-300 gap-1.5"
                                                    >
                                                        <Pause className="w-3.5 h-3.5" /> Pause
                                                    </Button>
                                                ) : c.status === 'paused' || c.status === 'draft' ? (
                                                    <Button
                                                        size="sm"
                                                        variant="brand"
                                                        onClick={() => handleStart(c.uuid)}
                                                        className="text-xs font-bold gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white"
                                                    >
                                                        <Play className="w-3.5 h-3.5" /> Launch
                                                    </Button>
                                                ) : null}

                                                <Link href={route('client.voice.campaigns.show', c.uuid)}>
                                                    <Button size="sm" variant="outline" className="text-xs font-bold gap-1.5">
                                                        <BarChart3 className="w-3.5 h-3.5" /> Live Dashboard
                                                    </Button>
                                                </Link>
                                            </div>
                                        </div>

                                        {/* Progress Bar & Outcome Metrics */}
                                        <div className="space-y-2 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                                            <div className="flex items-center justify-between text-xs font-semibold">
                                                <span className="text-neutral-500">Progress</span>
                                                <span className="text-neutral-800 dark:text-neutral-200">
                                                    {c.completed_calls} / {c.total_contacts} Calls ({progress}%)
                                                </span>
                                            </div>
                                            <div className="w-full bg-neutral-100 dark:bg-neutral-800 rounded-full h-2 overflow-hidden">
                                                <div
                                                    className="bg-brand-600 h-2 rounded-full transition-all duration-500"
                                                    style={{ width: `${progress}%` }}
                                                />
                                            </div>

                                            <div className="flex flex-wrap items-center gap-3 pt-2 text-xs">
                                                <span className="inline-flex items-center gap-1 text-emerald-600 font-bold">
                                                    🔥 {c.interested_calls} Interested
                                                </span>
                                                <span className="inline-flex items-center gap-1 text-blue-600 font-semibold">
                                                    📞 {c.callback_calls} Callbacks
                                                </span>
                                                <span className="inline-flex items-center gap-1 text-neutral-500">
                                                    🚫 {c.not_interested_calls} Not Interested
                                                </span>
                                                <span className="inline-flex items-center gap-1 text-neutral-400">
                                                    ⏳ {c.no_answer_calls} No Answer
                                                </span>
                                            </div>
                                        </div>
                                    </Card>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </ClientLayout>
    );
}
