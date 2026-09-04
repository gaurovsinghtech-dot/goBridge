import React, { useState } from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    PhoneCall, Play, Clock, Users, Flame,
    PhoneForwarded, AlertCircle, RefreshCw, Filter,
    Search, Calendar, ShieldAlert, CheckCircle2,
    Sliders, ArrowRight, Ban, Zap, Check
} from 'lucide-react';
import { Card, Button, Badge, Modal } from '@/Components/ui';
import { toast } from 'sonner';

export default function SmartVoiceQueueIndex({
    queueItems = { data: [] },
    stats = {},
    campaigns = [],
    filters = {},
}) {
    const items = queueItems?.data || [];

    const [callbackModal, setCallbackModal] = useState(false);
    const [excludeModal, setExcludeModal] = useState(false);
    const [selectedItem, setSelectedItem] = useState(null);
    const [callbackTime, setCallbackTime] = useState('');
    const [callbackNotes, setCallbackNotes] = useState('');
    const [excludeReason, setExcludeReason] = useState('opted_out');

    const handleFilterChange = (key, value) => {
        const query = { ...filters, [key]: value };
        if (value === 'all' || !value) delete query[key];
        router.get(route('client.voice.queue.index'), query, { preserveState: true, replace: true });
    };

    const handleDialNow = (id) => {
        router.post(route('client.voice.queue.dial', id), {}, {
            onSuccess: () => toast.success('Call queued for immediate dialing.'),
        });
    };

    const handleRequeue = (id) => {
        router.post(route('client.voice.queue.requeue', id), {}, {
            onSuccess: () => toast.success('Contact re-enqueued for calling.'),
        });
    };

    const handleSaveCallback = (e) => {
        e.preventDefault();
        if (!selectedItem || !callbackTime) return;

        router.post(route('client.voice.queue.callback', selectedItem.id), {
            callback_time: callbackTime,
            notes: callbackNotes,
        }, {
            onSuccess: () => {
                toast.success('Priority callback scheduled successfully.');
                setCallbackModal(false);
            },
        });
    };

    const handleSaveExclude = (e) => {
        e.preventDefault();
        if (!selectedItem) return;

        router.post(route('client.voice.queue.exclude', selectedItem.id), {
            reason: excludeReason,
        }, {
            onSuccess: () => {
                toast.success('Contact excluded from calling queue.');
                setExcludeModal(false);
            },
        });
    };

    const getReasonLabel = (reason) => {
        const map = {
            callback_requested: '📞 Callback Requested',
            hot_lead: '🔥 Hot Lead (Score 80+)',
            appointment_reminder: '📅 Appointment Reminder',
            warm_lead: '🟡 Warm Lead',
            routine_followup: 'Routine Follow-up',
            opted_out: '🚫 Opted Out / DNC',
            invalid_phone: '⚠️ Invalid Phone Number',
            outside_calling_hours: '⏳ Outside Calling Hours',
            max_attempts_reached: '🛑 Max Attempts Reached',
            workspace_mismatch: '❌ Workspace Mismatch',
        };
        return map[reason] || reason;
    };

    return (
        <ClientLayout>
            <Head title="Smart Calling Queue — Growbridge Connect" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div className="flex items-center gap-3.5">
                        <div className="h-11 w-11 rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                            <Zap className="w-6 h-6" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold text-neutral-900 dark:text-white">Smart Calling Queue</h1>
                                <Badge variant="brand" className="text-xs">
                                    Intelligent Dispatch
                                </Badge>
                            </div>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                Dynamic priority scoring, callback scheduling, compliance checks, and duplicate-call prevention.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2.5">
                        <Link href={route('client.voice.campaigns.index')}>
                            <Button variant="outline" size="sm" className="text-xs font-bold gap-1.5">
                                <PhoneCall className="w-3.5 h-3.5" /> Campaigns
                            </Button>
                        </Link>
                        <Link href={route('client.ai.voice-studio.index')}>
                            <Button variant="outline" size="sm" className="text-xs font-bold gap-1.5">
                                <Sliders className="w-3.5 h-3.5" /> Voice Studio
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* 7 KPI Metric Cards */}
                <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 text-xs">
                    {[
                        { key: 'ready', label: 'Ready', count: stats.ready ?? 0, color: 'text-emerald-600' },
                        { key: 'calling', label: 'Calling', count: stats.calling ?? 0, color: 'text-blue-600 animate-pulse' },
                        { key: 'scheduled', label: 'Scheduled', count: stats.scheduled ?? 0, color: 'text-purple-600' },
                        { key: 'callback', label: 'Callback', count: stats.callback ?? 0, color: 'text-amber-600 font-bold' },
                        { key: 'excluded', label: 'Excluded', count: stats.excluded ?? 0, color: 'text-neutral-400' },
                        { key: 'completed', label: 'Completed', count: stats.completed ?? 0, color: 'text-neutral-700 dark:text-neutral-300' },
                        { key: 'failed', label: 'Failed', count: stats.failed ?? 0, color: 'text-rose-600' },
                    ].map((tab) => {
                        const active = (filters.status || 'all') === tab.key;
                        return (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => handleFilterChange('status', active ? 'all' : tab.key)}
                                className={`p-3 rounded-xl border text-left transition ${
                                    active
                                        ? 'border-brand-500 bg-brand-50/50 dark:bg-neutral-800 shadow-xs'
                                        : 'border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 hover:border-neutral-300'
                                }`}
                            >
                                <span className="text-[11px] font-medium text-neutral-500 block">{tab.label}</span>
                                <span className={`text-xl font-bold ${tab.color} block mt-0.5`}>{tab.count}</span>
                            </button>
                        );
                    })}
                </div>

                {/* Filters Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-neutral-900 p-4 rounded-xl border border-neutral-200 dark:border-neutral-800">
                    <div className="flex flex-wrap items-center gap-2.5">
                        {/* Campaign Filter */}
                        <select
                            value={filters.campaign_id || ''}
                            onChange={(e) => handleFilterChange('campaign_id', e.target.value)}
                            className="text-xs font-semibold rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-1.5 text-neutral-800 dark:text-neutral-200"
                        >
                            <option value="">All Campaigns</option>
                            {campaigns.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>

                        {/* Priority Filter */}
                        <select
                            value={filters.priority || 'all'}
                            onChange={(e) => handleFilterChange('priority', e.target.value)}
                            className="text-xs font-semibold rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-1.5 text-neutral-800 dark:text-neutral-200"
                        >
                            <option value="all">All Priorities</option>
                            <option value="high">🔥 High Priority</option>
                            <option value="medium">🟡 Medium Priority</option>
                            <option value="low">⚪ Low Priority</option>
                        </select>
                    </div>

                    {/* Search Input */}
                    <div className="relative w-full sm:w-64">
                        <Search className="w-3.5 h-3.5 absolute left-3 top-2.5 text-neutral-400" />
                        <input
                            type="text"
                            placeholder="Search contact or phone..."
                            value={filters.search || ''}
                            onChange={(e) => handleFilterChange('search', e.target.value)}
                            className="w-full text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 pl-8 pr-3 py-1.5 text-neutral-900 dark:text-white"
                        />
                    </div>
                </div>

                {/* Queue Table */}
                <Card className="border-neutral-200 dark:border-neutral-800 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-neutral-50 dark:bg-neutral-800/50 border-b border-neutral-200 dark:border-neutral-800 text-neutral-500 font-semibold uppercase text-[10px]">
                                <tr>
                                    <th className="px-5 py-3">Priority</th>
                                    <th className="px-5 py-3">Contact</th>
                                    <th className="px-5 py-3">Campaign</th>
                                    <th className="px-5 py-3">Queue Reason</th>
                                    <th className="px-5 py-3">Next Call Time</th>
                                    <th className="px-5 py-3">Attempts</th>
                                    <th className="px-5 py-3">Status</th>
                                    <th className="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                                {items.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="px-5 py-10 text-center text-neutral-400">
                                            No queue items match the selected filters.
                                        </td>
                                    </tr>
                                ) : (
                                    items.map((item) => {
                                        const isHigh = item.priority_level === 'high';
                                        const isMedium = item.priority_level === 'medium';

                                        return (
                                            <tr key={item.id} className="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/30 transition">
                                                {/* Priority */}
                                                <td className="px-5 py-3.5">
                                                    <span className={`inline-flex items-center gap-1 font-bold text-xs ${
                                                        isHigh ? 'text-rose-600' : isMedium ? 'text-amber-600' : 'text-neutral-400'
                                                    }`}>
                                                        {isHigh ? '🔥 High' : isMedium ? '🟡 Medium' : '⚪ Low'}
                                                    </span>
                                                </td>

                                                {/* Contact */}
                                                <td className="px-5 py-3.5">
                                                    <div className="font-bold text-neutral-900 dark:text-white">
                                                        {item.contact_name || (item.contact ? `${item.contact.first_name} ${item.contact.last_name || ''}` : 'Contact')}
                                                    </div>
                                                    <div className="text-[11px] text-neutral-500 font-mono mt-0.5">
                                                        {item.phone_e164}
                                                    </div>
                                                </td>

                                                {/* Campaign */}
                                                <td className="px-5 py-3.5 font-semibold text-neutral-800 dark:text-neutral-200">
                                                    {item.campaign?.name || 'Voice Campaign'}
                                                </td>

                                                {/* Queue Reason */}
                                                <td className="px-5 py-3.5">
                                                    <span className="text-xs text-neutral-700 dark:text-neutral-300 font-medium">
                                                        {item.exclusion_reason
                                                            ? <span className="text-rose-600 font-bold">🚫 Excluded: {getReasonLabel(item.exclusion_reason)}</span>
                                                            : getReasonLabel(item.queue_reason)}
                                                    </span>
                                                </td>

                                                {/* Next Call Time */}
                                                <td className="px-5 py-3.5 text-neutral-600 dark:text-neutral-300">
                                                    {item.callback_scheduled_at ? (
                                                        <span className="font-bold text-amber-600">
                                                            📞 {new Date(item.callback_scheduled_at).toLocaleString()}
                                                        </span>
                                                    ) : item.next_attempt_at ? (
                                                        <span>{new Date(item.next_attempt_at).toLocaleString()}</span>
                                                    ) : (
                                                        <span className="text-emerald-600 font-semibold">Immediate</span>
                                                    )}
                                                </td>

                                                {/* Attempts */}
                                                <td className="px-5 py-3.5 text-neutral-500">
                                                    {item.attempts_count} / {item.max_attempts}
                                                </td>

                                                {/* Status */}
                                                <td className="px-5 py-3.5">
                                                    <Badge variant={
                                                        item.status === 'completed' ? 'success' :
                                                        item.status === 'calling' ? 'brand' :
                                                        item.status === 'scheduled' ? 'warning' :
                                                        item.status === 'pending' || item.status === 'queued' ? 'neutral' : 'danger'
                                                    } className="capitalize text-[10px]">
                                                        {item.is_callback ? 'Callback' : item.status}
                                                    </Badge>
                                                </td>

                                                {/* Actions */}
                                                <td className="px-5 py-3.5 text-right">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        {item.status !== 'completed' && !item.exclusion_reason && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => handleDialNow(item.id)}
                                                                className="text-xs gap-1 text-emerald-600 hover:bg-emerald-50 border-emerald-200"
                                                            >
                                                                <Zap className="w-3 h-3" /> Dial Now
                                                            </Button>
                                                        )}

                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => {
                                                                setSelectedItem(item);
                                                                setCallbackTime('');
                                                                setCallbackNotes('');
                                                                setCallbackModal(true);
                                                            }}
                                                            className="text-xs text-neutral-600 hover:text-neutral-900"
                                                        >
                                                            <Calendar className="w-3.5 h-3.5" />
                                                        </Button>

                                                        {item.exclusion_reason ? (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => handleRequeue(item.id)}
                                                                className="text-xs text-brand-600 gap-1"
                                                            >
                                                                <RefreshCw className="w-3 h-3" /> Re-enqueue
                                                            </Button>
                                                        ) : (
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() => {
                                                                    setSelectedItem(item);
                                                                    setExcludeReason('opted_out');
                                                                    setExcludeModal(true);
                                                                }}
                                                                className="text-xs text-rose-500 hover:text-rose-700"
                                                            >
                                                                <Ban className="w-3.5 h-3.5" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

            {/* Callback Reschedule Modal */}
            <Modal
                show={callbackModal}
                onClose={() => setCallbackModal(false)}
                title={`Schedule Priority Callback — ${selectedItem?.contact_name || selectedItem?.phone_e164}`}
            >
                <form onSubmit={handleSaveCallback} className="space-y-4">
                    <p className="text-xs text-neutral-500">
                        Scheduling a callback automatically promotes this contact to <strong>High Priority (100)</strong> in the calling queue.
                    </p>

                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Callback Date & Time</label>
                        <input
                            type="datetime-local"
                            required
                            value={callbackTime}
                            onChange={(e) => setCallbackTime(e.target.value)}
                            className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3.5 py-2 text-neutral-900 dark:text-white"
                        />
                    </div>

                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Agent Notes / Context</label>
                        <textarea
                            rows={2}
                            value={callbackNotes}
                            onChange={(e) => setCallbackNotes(e.target.value)}
                            placeholder="Customer requested pricing discussion..."
                            className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3.5 py-2 text-neutral-900 dark:text-white"
                        />
                    </div>

                    <div className="flex justify-end gap-2 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                        <Button type="button" variant="outline" size="sm" onClick={() => setCallbackModal(false)}>Cancel</Button>
                        <Button type="submit" variant="brand" size="sm" className="font-bold bg-emerald-600 hover:bg-emerald-700 text-white">
                            Schedule High-Priority Callback
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Exclusion Modal */}
            <Modal
                show={excludeModal}
                onClose={() => setExcludeModal(false)}
                title="Exclude Contact from Queue"
            >
                <form onSubmit={handleSaveExclude} className="space-y-4">
                    <p className="text-xs text-neutral-500">
                        Select why this contact should be excluded from calling campaigns.
                    </p>

                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Exclusion Reason</label>
                        <select
                            value={excludeReason}
                            onChange={(e) => setExcludeReason(e.target.value)}
                            className="w-full text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3.5 py-2 text-neutral-900 dark:text-white"
                        >
                            <option value="opted_out">Customer Requested Opt-Out / DNC</option>
                            <option value="invalid_phone">Invalid / Disconnected Phone</option>
                            <option value="wrong_number">Wrong Number / Unreachable</option>
                            <option value="blocked">Blocked / Spam</option>
                            <option value="manual_exclusion">Manual User Exclusion</option>
                        </select>
                    </div>

                    <div className="flex justify-end gap-2 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                        <Button type="button" variant="outline" size="sm" onClick={() => setExcludeModal(false)}>Cancel</Button>
                        <Button type="submit" variant="brand" size="sm" className="font-bold bg-rose-600 hover:bg-rose-700 text-white">
                            Confirm Exclusion
                        </Button>
                    </div>
                </form>
            </Modal>
        </ClientLayout>
    );
}
