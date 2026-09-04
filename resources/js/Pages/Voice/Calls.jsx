import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Card, Badge, Button, Pagination } from '@/Components/ui';
import {
    PhoneCall, ArrowLeft, Clock, FileText, PhoneIncoming, PhoneOutgoing,
    Sparkles, PhoneForwarded, Filter, CheckCircle2, AlertCircle,
    User, MessageSquare, ExternalLink, Flame
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function VoiceCalls({ calls, filters = {} }) {
    const { t } = useTranslation();
    const callList = calls?.data ?? [];
    const [selectedFilter, setSelectedFilter] = useState(filters.status || 'all');

    const handleFilter = (status) => {
        setSelectedFilter(status);
        router.get(route('client.voice.calls.index'), {
            status: status === 'all' ? null : status,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const getStatusBadge = (call) => {
        if (call.outcome === 'transferred' || call.handoff_reason) {
            return <Badge variant="warning" className="flex items-center gap-1"><PhoneForwarded className="w-3 h-3" /> Transferred</Badge>;
        }
        switch (call.status) {
            case 'completed': return <Badge variant="success">Completed</Badge>;
            case 'in-progress': return <Badge variant="brand">In Progress</Badge>;
            case 'failed':
            case 'busy': return <Badge variant="danger">{call.status}</Badge>;
            default: return <Badge variant="neutral">{call.status}</Badge>;
        }
    };

    return (
        <ClientLayout>
            <Head title="Voice Call History & AI Summaries — Growbridge Connect" />

            <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* ─── Header & Filters ────────────────────────────────────── */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div className="flex items-center gap-3">
                        <Link href={route('client.voice.index')}>
                            <Button type="button" variant="ghost" size="sm" className="p-2">
                                <ArrowLeft className="w-4 h-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-xl font-bold text-neutral-900 dark:text-white">Call Logs & AI Transcripts</h1>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                Detailed voice call records, AI conversation summaries, lead qualifications, and audio playback.
                            </p>
                        </div>
                    </div>

                    {/* Filter Pills */}
                    <div className="flex flex-wrap items-center gap-1.5 bg-neutral-100 dark:bg-neutral-800 p-1 rounded-xl text-xs font-semibold">
                        {[
                            { id: 'all', label: 'All' },
                            { id: 'inbound', label: 'Inbound' },
                            { id: 'outbound', label: 'Outbound' },
                            { id: 'completed', label: 'AI Resolved' },
                            { id: 'transferred', label: 'Transferred' },
                            { id: 'failed', label: 'Failed' },
                        ].map(({ id, label }) => (
                            <button
                                key={id}
                                onClick={() => handleFilter(id)}
                                className={`px-3 py-1 rounded-lg transition ${
                                    selectedFilter === id
                                        ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-xs'
                                        : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                </div>

                {/* ─── Calls List ──────────────────────────────────────────── */}
                <Card className="border-neutral-200 dark:border-neutral-800 overflow-hidden">
                    {callList.length === 0 ? (
                        <div className="p-12 text-center">
                            <PhoneCall className="w-10 h-10 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
                            <p className="text-sm font-bold text-neutral-700 dark:text-neutral-300">No calls recorded</p>
                            <p className="text-xs text-neutral-400 mt-1">
                                Inbound calls to your virtual numbers or outbound test calls will appear here with transcripts & summaries.
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y divide-neutral-100 dark:divide-neutral-800">
                            {callList.map((call) => (
                                <div key={call.id} className="p-5 hover:bg-neutral-50/50 dark:hover:bg-neutral-800/30 transition-colors space-y-3">
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                        <div className="flex items-center gap-3">
                                            <div className={`p-2.5 rounded-xl ${
                                                call.direction === 'inbound'
                                                    ? 'bg-blue-50 text-blue-600 dark:bg-neutral-800'
                                                    : 'bg-emerald-50 text-emerald-600 dark:bg-neutral-800'
                                            }`}>
                                                {call.direction === 'inbound' ? <PhoneIncoming className="w-4 h-4" /> : <PhoneOutgoing className="w-4 h-4" />}
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-bold text-neutral-900 dark:text-white text-sm">
                                                        {call.from_number || call.to_number}
                                                    </span>
                                                    {getStatusBadge(call)}

                                                    {call.lead_score >= 80 && (
                                                        <span className="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 font-bold">
                                                            <Flame className="w-3 h-3 text-amber-500 fill-amber-500" /> Hot Lead ({call.lead_score})
                                                        </span>
                                                    )}

                                                    {call.outcome && (
                                                        <span className="text-[11px] px-2 py-0.5 rounded bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-300 font-semibold capitalize">
                                                            {call.outcome.replace('_', ' ')}
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="flex flex-wrap items-center gap-4 text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                                                    <span>Agent: {call.agent?.name || 'Voice Assistant'}</span>
                                                    <span className="flex items-center gap-1"><Clock className="w-3 h-3" /> {call.duration_sec}s</span>
                                                    <span>{call.created_at ? new Date(call.created_at).toLocaleString() : ''}</span>
                                                    {call.contact && (
                                                        <span className="text-brand-600 dark:text-brand-400 font-semibold">
                                                            Contact: {call.contact.first_name || 'Caller'}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        {call.recording_url && (
                                            <div className="flex items-center gap-2">
                                                <audio controls src={call.recording_url} className="h-8 max-w-[220px]" />
                                            </div>
                                        )}
                                    </div>

                                    {/* AI Call Summary Box */}
                                    {call.summary && (
                                        <div className="bg-neutral-50 dark:bg-neutral-800/60 rounded-xl p-3.5 border border-neutral-200 dark:border-neutral-700/70 text-xs space-y-1">
                                            <div className="flex items-center gap-1.5 font-bold text-brand-700 dark:text-brand-400">
                                                <Sparkles className="w-3.5 h-3.5" />
                                                AI Call Summary & Extracted Intent
                                            </div>
                                            <p className="text-neutral-700 dark:text-neutral-300 leading-relaxed">
                                                {call.summary}
                                            </p>
                                        </div>
                                    )}

                                    {/* Full Call Transcript Accordion */}
                                    {call.transcript && (
                                        <details className="text-xs text-neutral-600 dark:text-neutral-400">
                                            <summary className="cursor-pointer font-bold hover:text-brand-600 select-none py-1 flex items-center gap-1">
                                                <FileText className="w-3.5 h-3.5" /> View Call Transcript
                                            </summary>
                                            <div className="mt-2 p-3.5 bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-700 font-mono text-[11px] whitespace-pre-wrap leading-relaxed">
                                                {call.transcript}
                                            </div>
                                        </details>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </Card>

                {calls?.links && (
                    <div className="flex justify-end">
                        <Pagination links={calls.links} />
                    </div>
                )}
            </div>
        </ClientLayout>
    );
}
