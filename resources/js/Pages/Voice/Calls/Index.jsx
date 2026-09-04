import React from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    PhoneCall, PhoneIncoming, PhoneOutgoing, PhoneForwarded,
    Search, Filter, Calendar, Clock, Headphones, FileText,
    Sparkles, ArrowRight, ShieldAlert, CheckCircle2,
    Users, Bot, Flame, Activity
} from 'lucide-react';
import { Card, Button, Badge } from '@/Components/ui';

export default function VoiceCallsIndex({
    calls = { data: [] },
    stats = {},
    agents = [],
    campaigns = [],
    filters = {},
}) {
    const callList = calls?.data || [];

    const handleFilterChange = (key, value) => {
        const query = { ...filters, [key]: value };
        if (value === 'all' || value === '' || !value) delete query[key];
        router.get(route('client.voice.calls.index'), query, { preserveState: true, replace: true });
    };

    return (
        <ClientLayout>
            <Head title="Voice Call History & Conversation Intelligence" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <h1 className="text-xl font-bold text-neutral-900 dark:text-white">Call History & Intelligence</h1>
                            <Badge variant="brand" className="text-xs">
                                Voice Analytics
                            </Badge>
                        </div>
                        <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                            Search transcripts, listen to recordings, review AI-extracted customer intent, and follow up in CRM.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link href={route('client.voice.call-center')}>
                            <Button variant="outline" size="sm" className="text-xs font-bold gap-1.5">
                                <Activity className="w-3.5 h-3.5" /> Call Center
                            </Button>
                        </Link>
                        <Link href={route('client.voice.queue.index')}>
                            <Button variant="outline" size="sm" className="text-xs font-bold gap-1.5">
                                <Clock className="w-3.5 h-3.5" /> Calling Queue
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* KPI Overview */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <Card className="p-4 border-neutral-200 dark:border-neutral-800">
                        <span className="text-[11px] font-bold text-neutral-500 uppercase tracking-wider block">Total Calls</span>
                        <span className="text-2xl font-extrabold text-neutral-900 dark:text-white mt-1 block">{stats.total_calls || 0}</span>
                    </Card>
                    <Card className="p-4 border-neutral-200 dark:border-neutral-800">
                        <span className="text-[11px] font-bold text-neutral-500 uppercase tracking-wider block">Answered Calls</span>
                        <span className="text-2xl font-extrabold text-neutral-900 dark:text-white mt-1 block">{stats.answered_calls || 0}</span>
                    </Card>
                    <Card className="p-4 border-neutral-200 dark:border-neutral-800">
                        <span className="text-[11px] font-bold text-neutral-500 uppercase tracking-wider block">AI Resolved</span>
                        <span className="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1 block">{stats.resolved_calls || 0}</span>
                    </Card>
                    <Card className="p-4 border-neutral-200 dark:border-neutral-800">
                        <span className="text-[11px] font-bold text-neutral-500 uppercase tracking-wider block">Avg Duration</span>
                        <span className="text-2xl font-extrabold text-blue-600 dark:text-blue-400 mt-1 block">⏱ {stats.avg_duration_formatted || '00:00'}</span>
                    </Card>
                </div>

                {/* Filter Bar */}
                <Card className="p-4 border-neutral-200 dark:border-neutral-800 space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-2">
                            {/* Date Filter */}
                            <select
                                value={filters.date_range || 'all'}
                                onChange={(e) => handleFilterChange('date_range', e.target.value)}
                                className="text-xs font-semibold rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-1.5 text-neutral-800 dark:text-neutral-200"
                            >
                                <option value="all">All Dates</option>
                                <option value="today">Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="7days">Last 7 Days</option>
                                <option value="30days">Last 30 Days</option>
                            </select>

                            {/* Agent Filter */}
                            <select
                                value={filters.agent_id || ''}
                                onChange={(e) => handleFilterChange('agent_id', e.target.value)}
                                className="text-xs font-semibold rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-1.5 text-neutral-800 dark:text-neutral-200"
                            >
                                <option value="">All Voice Agents</option>
                                {agents.map((ag) => (
                                    <option key={ag.id} value={ag.id}>{ag.name}</option>
                                ))}
                            </select>

                            {/* Outcome Filter */}
                            <select
                                value={filters.outcome || 'all'}
                                onChange={(e) => handleFilterChange('outcome', e.target.value)}
                                className="text-xs font-semibold rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-1.5 text-neutral-800 dark:text-neutral-200"
                            >
                                <option value="all">All Outcomes</option>
                                <option value="interested">🔥 Interested</option>
                                <option value="qualified">✓ Qualified</option>
                                <option value="callback_requested">📞 Callback</option>
                                <option value="support_resolved">🤝 Support Resolved</option>
                                <option value="human_handoff">⚡ Human Handoff</option>
                                <option value="not_interested">🚫 Not Interested</option>
                                <option value="no_answer">⏳ No Answer</option>
                            </select>
                        </div>

                        {/* Search Input */}
                        <div className="relative w-full sm:w-72">
                            <Search className="w-3.5 h-3.5 absolute left-3 top-2.5 text-neutral-400" />
                            <input
                                type="text"
                                placeholder="Search customer, phone, summary..."
                                value={filters.search || ''}
                                onChange={(e) => handleFilterChange('search', e.target.value)}
                                className="w-full text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 pl-8 pr-3 py-1.5 text-neutral-900 dark:text-white"
                            />
                        </div>
                    </div>

                    {/* Checkbox Toggles */}
                    <div className="flex flex-wrap items-center gap-4 pt-1 text-xs text-neutral-600 dark:text-neutral-300">
                        <label className="flex items-center gap-1.5 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={filters.has_recording === '1'}
                                onChange={(e) => handleFilterChange('has_recording', e.target.checked ? '1' : '')}
                                className="rounded text-brand-600"
                            />
                            <span>🎧 Has Recording</span>
                        </label>

                        <label className="flex items-center gap-1.5 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={filters.has_transcript === '1'}
                                onChange={(e) => handleFilterChange('has_transcript', e.target.checked ? '1' : '')}
                                className="rounded text-brand-600"
                            />
                            <span>📝 Has Transcript</span>
                        </label>

                        <label className="flex items-center gap-1.5 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={filters.is_handoff === '1'}
                                onChange={(e) => handleFilterChange('is_handoff', e.target.checked ? '1' : '')}
                                className="rounded text-brand-600"
                            />
                            <span>⚡ Human Handoff</span>
                        </label>
                    </div>
                </Card>

                {/* Calls Table */}
                <Card className="border-neutral-200 dark:border-neutral-800 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-neutral-50 dark:bg-neutral-800/50 border-b border-neutral-200 dark:border-neutral-800 text-neutral-500 font-semibold uppercase text-[10px]">
                                <tr>
                                    <th className="px-5 py-3">Customer</th>
                                    <th className="px-5 py-3">AI Agent</th>
                                    <th className="px-5 py-3">Duration</th>
                                    <th className="px-5 py-3">Outcome</th>
                                    <th className="px-5 py-3">Intelligence</th>
                                    <th className="px-5 py-3">Date</th>
                                    <th className="px-5 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                                {callList.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-5 py-10 text-center text-neutral-400">
                                            No call history matches the selected filters.
                                        </td>
                                    </tr>
                                ) : (
                                    callList.map((call) => {
                                        const duration = sprintf('%02d:%02d', Math.floor(call.duration_sec / 60), call.duration_sec % 60);

                                        return (
                                            <tr key={call.id} className="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/30 transition">
                                                {/* Customer */}
                                                <td className="px-5 py-3.5">
                                                    <div className="font-bold text-neutral-900 dark:text-white">
                                                        {call.contact ? `${call.contact.first_name} ${call.contact.last_name || ''}` : (call.to_number || call.from_number)}
                                                    </div>
                                                    <div className="text-[11px] text-neutral-500 font-mono mt-0.5">
                                                        {call.direction === 'outbound' ? '↗ ' : '↙ '}
                                                        {call.to_number || call.from_number}
                                                    </div>
                                                </td>

                                                {/* Agent */}
                                                <td className="px-5 py-3.5 font-semibold text-neutral-800 dark:text-neutral-200">
                                                    {call.agent?.name || 'Voice Assistant'}
                                                </td>

                                                {/* Duration */}
                                                <td className="px-5 py-3.5 font-mono font-bold text-neutral-700 dark:text-neutral-300">
                                                    ⏱ {duration}
                                                </td>

                                                {/* Outcome */}
                                                <td className="px-5 py-3.5">
                                                    <Badge variant={
                                                        call.outcome === 'interested' || call.outcome === 'qualified' ? 'success' :
                                                        call.outcome === 'human_handoff' || call.outcome === 'transferred' ? 'warning' :
                                                        call.outcome === 'callback_requested' ? 'brand' : 'neutral'
                                                    } className="capitalize text-[10px]">
                                                        {call.outcome?.replace('_', ' ') || call.status}
                                                    </Badge>
                                                </td>

                                                {/* Intelligence Icons */}
                                                <td className="px-5 py-3.5">
                                                    <div className="flex items-center gap-2 text-neutral-400">
                                                        {call.recording_url && (
                                                            <span title="Audio Recording Available" className="text-blue-600">
                                                                <Headphones className="w-3.5 h-3.5" />
                                                            </span>
                                                        )}
                                                        {call.transcript && (
                                                            <span title="Transcript Available" className="text-emerald-600">
                                                                <FileText className="w-3.5 h-3.5" />
                                                            </span>
                                                        )}
                                                        {call.summary && (
                                                            <span title="AI Summary Available" className="text-amber-500">
                                                                <Sparkles className="w-3.5 h-3.5" />
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>

                                                {/* Date */}
                                                <td className="px-5 py-3.5 text-neutral-500 text-[11px]">
                                                    {new Date(call.created_at).toLocaleString()}
                                                </td>

                                                {/* Action */}
                                                <td className="px-5 py-3.5 text-right">
                                                    <Link href={route('client.voice.calls.show', call.uuid)}>
                                                        <Button size="sm" variant="outline" className="text-xs gap-1 text-brand-600 hover:text-brand-700">
                                                            View <ArrowRight className="w-3 h-3" />
                                                        </Button>
                                                    </Link>
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
        </ClientLayout>
    );
}

function sprintf(format, ...args) {
    let i = 0;
    return format.replace(/%02d/g, () => String(args[i++]).padStart(2, '0'));
}
