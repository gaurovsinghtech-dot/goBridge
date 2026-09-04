import React from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    PhoneCall, ArrowLeft, Play, Pause, Square, BarChart3,
    Sparkles, CheckCircle2, Clock, Users, Flame,
    PhoneForwarded, AlertCircle, RefreshCw, FileText
} from 'lucide-react';
import { Card, Button, Badge } from '@/Components/ui';
import { toast } from 'sonner';

export default function VoiceCampaignShow({
    campaign = {},
    recipients = { data: [] },
}) {
    const recipientList = recipients?.data || [];
    const progress = campaign.total_contacts > 0
        ? Math.round((campaign.completed_calls / campaign.total_contacts) * 100)
        : 0;

    const handleStart = () => {
        router.post(route('client.voice.campaigns.start', campaign.uuid), {}, {
            onSuccess: () => toast.success('Campaign started! Calls are now being dispatched.'),
        });
    };

    const handlePause = () => {
        router.post(route('client.voice.campaigns.pause', campaign.uuid), {}, {
            onSuccess: () => toast.success('Campaign paused.'),
        });
    };

    const handleStop = () => {
        if (!confirm('Are you sure you want to stop this campaign? All remaining calls will be cancelled.')) return;
        router.post(route('client.voice.campaigns.stop', campaign.uuid), {}, {
            onSuccess: () => toast.success('Campaign stopped.'),
        });
    };

    return (
        <ClientLayout>
            <Head title={`${campaign.name} — Live Voice Campaign`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div className="flex items-center gap-3.5">
                        <Link href={route('client.voice.campaigns.index')}>
                            <Button variant="ghost" size="sm" className="p-2">
                                <ArrowLeft className="w-4 h-4" />
                            </Button>
                        </Link>
                        <div>
                            <div className="flex items-center gap-2.5">
                                <h1 className="text-xl font-bold text-neutral-900 dark:text-white">{campaign.name}</h1>
                                <Badge variant={
                                    campaign.status === 'running' ? 'success' :
                                    campaign.status === 'scheduled' ? 'brand' :
                                    campaign.status === 'paused' ? 'warning' :
                                    campaign.status === 'completed' ? 'neutral' : 'danger'
                                } className="capitalize text-xs">
                                    ● {campaign.status}
                                </Badge>
                            </div>
                            <p className="text-xs text-neutral-500 mt-0.5">
                                AI Agent: <span className="font-semibold text-neutral-700 dark:text-neutral-300">{campaign.agent?.name || 'Default Voice Assistant'}</span> • Caller ID: <span className="font-semibold text-neutral-700 dark:text-neutral-300">{campaign.caller_id_number || 'Default'}</span>
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {campaign.status === 'running' ? (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={handlePause}
                                className="text-xs text-amber-600 border-amber-300 dark:border-amber-800 gap-1.5"
                            >
                                <Pause className="w-3.5 h-3.5" /> Pause Campaign
                            </Button>
                        ) : campaign.status === 'paused' || campaign.status === 'draft' ? (
                            <Button
                                size="sm"
                                variant="brand"
                                onClick={handleStart}
                                className="text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5"
                            >
                                <Play className="w-3.5 h-3.5" /> Start / Resume Calls
                            </Button>
                        ) : null}

                        {campaign.status !== 'completed' && campaign.status !== 'cancelled' && (
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={handleStop}
                                className="text-xs text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 gap-1.5"
                            >
                                <Square className="w-3.5 h-3.5" /> Stop Campaign
                            </Button>
                        )}
                    </div>
                </div>

                {/* Progress & Real-Time Stats */}
                <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-4">
                    <div className="flex items-center justify-between">
                        <span className="text-sm font-bold text-neutral-900 dark:text-white">Live Campaign Progress</span>
                        <span className="text-xs font-bold text-neutral-600 dark:text-neutral-300">
                            {campaign.completed_calls} / {campaign.total_contacts} Contacts Dialed ({progress}%)
                        </span>
                    </div>

                    <div className="w-full bg-neutral-100 dark:bg-neutral-800 rounded-full h-3 overflow-hidden">
                        <div
                            className="bg-brand-600 h-3 rounded-full transition-all duration-500"
                            style={{ width: `${progress}%` }}
                        />
                    </div>

                    <div className="grid grid-cols-2 sm:grid-cols-5 gap-3 pt-2 text-xs">
                        <div className="p-3 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/30">
                            <span className="text-emerald-700 dark:text-emerald-400 block font-semibold text-[11px]">🔥 Interested / Hot</span>
                            <span className="text-lg font-bold text-emerald-900 dark:text-emerald-300 mt-0.5 block">{campaign.interested_calls ?? 0}</span>
                        </div>
                        <div className="p-3 rounded-xl bg-blue-50/50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/30">
                            <span className="text-blue-700 dark:text-blue-400 block font-semibold text-[11px]">📞 Callbacks</span>
                            <span className="text-lg font-bold text-blue-900 dark:text-blue-300 mt-0.5 block">{campaign.callback_calls ?? 0}</span>
                        </div>
                        <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700/60">
                            <span className="text-neutral-500 block font-semibold text-[11px]">Answer Rate</span>
                            <span className="text-lg font-bold text-neutral-900 dark:text-white mt-0.5 block">{campaign.answer_rate ?? 0}%</span>
                        </div>
                        <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700/60">
                            <span className="text-neutral-500 block font-semibold text-[11px]">⏳ No Answer</span>
                            <span className="text-lg font-bold text-neutral-700 dark:text-neutral-300 mt-0.5 block">{campaign.no_answer_calls ?? 0}</span>
                        </div>
                        <div className="p-3 rounded-xl bg-rose-50/50 dark:bg-rose-950/20 border border-rose-100 dark:border-rose-900/30">
                            <span className="text-rose-700 dark:text-rose-400 block font-semibold text-[11px]">⚠️ Failed / Unreachable</span>
                            <span className="text-lg font-bold text-rose-900 dark:text-rose-300 mt-0.5 block">{campaign.failed_calls ?? 0}</span>
                        </div>
                    </div>
                </Card>

                {/* Call Recipients Table */}
                <Card className="border-neutral-200 dark:border-neutral-800 overflow-hidden">
                    <div className="p-5 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                        <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Target Contacts & Live Call Logs</h3>
                        <span className="text-xs text-neutral-500">{recipientList.length} shown</span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-neutral-50 dark:bg-neutral-800/50 border-b border-neutral-200 dark:border-neutral-800 text-neutral-500 font-semibold uppercase text-[10px]">
                                <tr>
                                    <th className="px-5 py-3">Contact</th>
                                    <th className="px-5 py-3">Phone</th>
                                    <th className="px-5 py-3">Attempts</th>
                                    <th className="px-5 py-3">Call Status</th>
                                    <th className="px-5 py-3">Outcome</th>
                                    <th className="px-5 py-3">Last Attempt</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                                {recipientList.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-5 py-8 text-center text-neutral-400">
                                            No recipients found in this campaign.
                                        </td>
                                    </tr>
                                ) : (
                                    recipientList.map((r) => (
                                        <tr key={r.id} className="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/30">
                                            <td className="px-5 py-3.5 font-bold text-neutral-900 dark:text-white">
                                                {r.contact_name || (r.contact ? `${r.contact.first_name} ${r.contact.last_name || ''}` : 'Contact')}
                                            </td>
                                            <td className="px-5 py-3.5 text-neutral-600 dark:text-neutral-300 font-mono">
                                                {r.phone_e164}
                                            </td>
                                            <td className="px-5 py-3.5 text-neutral-500">
                                                {r.attempts_count} / {r.max_attempts}
                                            </td>
                                            <td className="px-5 py-3.5">
                                                <Badge variant={
                                                    r.status === 'completed' ? 'success' :
                                                    r.status === 'calling' ? 'brand' :
                                                    r.status === 'pending' ? 'neutral' : 'danger'
                                                } className="capitalize text-[10px]">
                                                    {r.status}
                                                </Badge>
                                            </td>
                                            <td className="px-5 py-3.5">
                                                {r.call_outcome ? (
                                                    <span className={`inline-flex items-center gap-1 font-bold ${
                                                        r.call_outcome === 'interested' ? 'text-emerald-600' :
                                                        r.call_outcome === 'callback_requested' ? 'text-blue-600' :
                                                        r.call_outcome === 'not_interested' ? 'text-neutral-500' : 'text-amber-600'
                                                    }`}>
                                                        {r.call_outcome === 'interested' ? '🔥 Interested' :
                                                         r.call_outcome === 'callback_requested' ? '📞 Callback' :
                                                         r.call_outcome === 'not_interested' ? '🚫 Not Interested' : r.call_outcome}
                                                    </span>
                                                ) : (
                                                    <span className="text-neutral-400">—</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 text-neutral-400 text-[11px]">
                                                {r.last_attempt_at ? new Date(r.last_attempt_at).toLocaleString() : 'Not yet dialed'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </ClientLayout>
    );
}
