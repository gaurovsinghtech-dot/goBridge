import React, { useState } from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    PhoneCall, PhoneIncoming, PhoneOutgoing, ArrowLeft,
    Headphones, FileText, Sparkles, MessageSquare,
    CheckCircle2, Clock, Users, Flame, Tag,
    Calendar, RefreshCw, Send, Zap, UserCheck, Play, Pause,
    Volume2, ShieldCheck, AlertCircle
} from 'lucide-react';
import { Card, Button, Badge, Modal } from '@/Components/ui';
import { toast } from 'sonner';

export default function VoiceCallShow({
    call = {},
    recipient = null,
    transcriptTurns = [],
    availableTags = [],
}) {
    const [transcriptQuery, setTranscriptQuery] = useState('');
    const [isAnalyzing, setIsAnalyzing] = useState(false);
    const [tagModal, setTagModal] = useState(false);
    const [callbackModal, setCallbackModal] = useState(false);
    const [selectedTag, setSelectedTag] = useState('');
    const [callbackTime, setCallbackTime] = useState('');
    const [callbackNotes, setCallbackNotes] = useState('');

    const duration = sprintf('%02d:%02d', Math.floor((call.duration_sec || 0) / 60), (call.duration_sec || 0) % 60);

    const handleReAnalyze = () => {
        setIsAnalyzing(true);
        router.post(route('client.voice.calls.analyze', call.uuid), {}, {
            onSuccess: () => {
                toast.success('AI conversation analysis refreshed.');
                setIsAnalyzing(false);
            },
            onError: () => setIsAnalyzing(false),
        });
    };

    const handleAddTag = (e) => {
        e.preventDefault();
        if (!selectedTag) return;

        router.post(route('client.voice.calls.follow-up', call.uuid), {
            action_type: 'tag',
            tag_name: selectedTag,
        }, {
            onSuccess: () => {
                toast.success(`Tag "${selectedTag}" attached to contact.`);
                setTagModal(false);
            },
        });
    };

    const handleScheduleCallback = (e) => {
        e.preventDefault();
        if (!callbackTime) return;

        router.post(route('client.voice.calls.follow-up', call.uuid), {
            action_type: 'callback',
            callback_time: callbackTime,
            notes: callbackNotes,
        }, {
            onSuccess: () => {
                toast.success(`Priority callback scheduled for ${callbackTime}.`);
                setCallbackModal(false);
            },
        });
    };

    const filteredTurns = transcriptQuery.trim() === ''
        ? transcriptTurns
        : transcriptTurns.filter(t => t.text.toLowerCase().includes(transcriptQuery.toLowerCase()));

    const highlightText = (text, query) => {
        if (!query.trim()) return text;
        const parts = text.split(new RegExp(`(${query})`, 'gi'));
        return parts.map((part, idx) =>
            part.toLowerCase() === query.toLowerCase()
                ? <mark key={idx} className="bg-amber-200 dark:bg-amber-800 text-neutral-900 dark:text-white px-0.5 rounded">{part}</mark>
                : part
        );
    };

    return (
        <ClientLayout>
            <Head title={`Call Details — ${call.contact ? `${call.contact.first_name} ${call.contact.last_name || ''}` : call.to_number || call.from_number}`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div className="flex items-center gap-3.5">
                        <Link href={route('client.voice.calls.index')}>
                            <Button variant="ghost" size="sm" className="p-2">
                                <ArrowLeft className="w-4 h-4" />
                            </Button>
                        </Link>
                        <div>
                            <div className="flex items-center gap-2.5">
                                <h1 className="text-xl font-bold text-neutral-900 dark:text-white">
                                    {call.contact ? `${call.contact.first_name} ${call.contact.last_name || ''}` : (call.to_number || call.from_number)}
                                </h1>
                                <Badge variant={
                                    call.outcome === 'interested' || call.outcome === 'qualified' ? 'success' :
                                    call.outcome === 'human_handoff' || call.outcome === 'transferred' ? 'warning' :
                                    call.outcome === 'callback_requested' ? 'brand' : 'neutral'
                                } className="capitalize text-xs">
                                    ● {call.outcome?.replace('_', ' ') || call.status}
                                </Badge>
                            </div>
                            <p className="text-xs text-neutral-500 mt-0.5">
                                Agent: <span className="font-semibold text-neutral-700 dark:text-neutral-300">{call.agent?.name || 'Voice Assistant'}</span> • Duration: <span className="font-mono font-bold text-neutral-800 dark:text-neutral-200">⏱ {duration}</span> • Provider: <span className="capitalize font-semibold">{call.provider}</span>
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={handleReAnalyze}
                            disabled={isAnalyzing}
                            className="text-xs font-bold gap-1 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40 border-amber-300"
                        >
                            <Sparkles className={`w-3.5 h-3.5 ${isAnalyzing ? 'animate-spin' : ''}`} />
                            {isAnalyzing ? 'Analyzing...' : 'Re-Analyze AI'}
                        </Button>

                        {call.contact && (
                            <Link href={route('client.contacts.show', call.contact.uuid || call.contact.id)}>
                                <Button size="sm" variant="brand" className="text-xs font-bold gap-1 bg-brand-600 text-white">
                                    <Users className="w-3.5 h-3.5" /> View CRM Contact
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>

                {/* 3-Column Intelligence Layout */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    {/* LEFT COLUMN: Metadata & Audio Recording (4 cols) */}
                    <div className="lg:col-span-4 space-y-6">
                        {/* Audio Recording Player */}
                        <Card className="border-neutral-200 dark:border-neutral-800 p-5 space-y-3.5">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Headphones className="w-4 h-4 text-blue-600" />
                                    <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
                                        Call Recording
                                    </h3>
                                </div>
                                <span className="text-[10px] text-neutral-400 font-mono">⏱ {duration}</span>
                            </div>

                            {call.recording_url ? (
                                <div className="space-y-3 bg-neutral-50 dark:bg-neutral-800/60 p-4 rounded-xl border border-neutral-200 dark:border-neutral-700">
                                    <audio controls className="w-full h-10" preload="metadata">
                                        <source src={call.recording_url} type="audio/mpeg" />
                                        Your browser does not support audio playback.
                                    </audio>
                                    <div className="flex items-center justify-between text-[10px] text-neutral-500">
                                        <span>Secure stream from {call.provider}</span>
                                        <span>Retention: {call.recording_retention_days || 30} days</span>
                                    </div>
                                </div>
                            ) : (
                                <div className="p-6 text-center text-neutral-400 text-xs bg-neutral-50 dark:bg-neutral-800/30 rounded-xl border border-dashed border-neutral-200 dark:border-neutral-800">
                                    <Headphones className="w-6 h-6 mx-auto mb-1.5 opacity-30" />
                                    Recording not available for this call.
                                </div>
                            )}
                        </Card>

                        {/* Call Metadata & Telephony Info */}
                        <Card className="border-neutral-200 dark:border-neutral-800 p-5 space-y-3 text-xs">
                            <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider">
                                Telephony Session Details
                            </h3>

                            <div className="divide-y divide-neutral-100 dark:divide-neutral-800 space-y-2">
                                <div className="flex justify-between py-1">
                                    <span className="text-neutral-500">Call Direction</span>
                                    <span className="font-semibold text-neutral-900 dark:text-white capitalize flex items-center gap-1">
                                        {call.direction === 'outbound' ? <PhoneOutgoing className="w-3 h-3 text-blue-600" /> : <PhoneIncoming className="w-3 h-3 text-emerald-600" />}
                                        {call.direction}
                                    </span>
                                </div>
                                <div className="flex justify-between py-1">
                                    <span className="text-neutral-500">Caller Phone</span>
                                    <span className="font-mono font-bold text-neutral-900 dark:text-white">{call.from_number || '—'}</span>
                                </div>
                                <div className="flex justify-between py-1">
                                    <span className="text-neutral-500">Destination Number</span>
                                    <span className="font-mono font-bold text-neutral-900 dark:text-white">{call.to_number || '—'}</span>
                                </div>
                                <div className="flex justify-between py-1">
                                    <span className="text-neutral-500">Started At</span>
                                    <span className="text-neutral-700 dark:text-neutral-300">
                                        {call.started_at ? new Date(call.started_at).toLocaleTimeString() : new Date(call.created_at).toLocaleTimeString()}
                                    </span>
                                </div>
                                <div className="flex justify-between py-1">
                                    <span className="text-neutral-500">Provider Call SID</span>
                                    <span className="font-mono text-[10px] text-neutral-400 truncate max-w-[140px]">{call.provider_call_id || call.uuid}</span>
                                </div>
                            </div>
                        </Card>

                        {/* CRM Tags & Contact Context */}
                        <Card className="border-neutral-200 dark:border-neutral-800 p-5 space-y-3 text-xs">
                            <div className="flex items-center justify-between">
                                <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider">CRM Tags</h3>
                                <button
                                    type="button"
                                    onClick={() => setTagModal(true)}
                                    className="text-xs font-bold text-brand-600 hover:underline"
                                >
                                    + Add Tag
                                </button>
                            </div>

                            <div className="flex flex-wrap gap-1.5">
                                {call.contact?.tags && call.contact.tags.length > 0 ? (
                                    call.contact.tags.map((t) => (
                                        <Badge key={t.id} variant="neutral" className="text-[11px]">
                                            🏷 {t.name}
                                        </Badge>
                                    ))
                                ) : (
                                    <span className="text-neutral-400 text-xs italic">No tags attached.</span>
                                )}
                            </div>
                        </Card>
                    </div>

                    {/* CENTER COLUMN: Verbatim Speaker Transcript (5 cols) */}
                    <div className="lg:col-span-5 space-y-4">
                        <Card className="border-neutral-200 dark:border-neutral-800 p-5 space-y-4 flex flex-col h-full">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <FileText className="w-4 h-4 text-emerald-600" />
                                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Conversation Transcript</h3>
                                </div>
                                <span className="text-xs text-neutral-500">{filteredTurns.length} turns</span>
                            </div>

                            {/* Transcript Keyword Search */}
                            <div className="relative">
                                <Search className="w-3.5 h-3.5 absolute left-3 top-2.5 text-neutral-400" />
                                <input
                                    type="text"
                                    placeholder="Search transcript (e.g. price, demo)..."
                                    value={transcriptQuery}
                                    onChange={(e) => setTranscriptQuery(e.target.value)}
                                    className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/70 pl-8 pr-3 py-2 text-neutral-900 dark:text-white placeholder:text-neutral-400"
                                />
                            </div>

                            {/* Chat Transcript Turns */}
                            <div className="flex-1 space-y-3.5 overflow-y-auto max-h-[550px] pr-1">
                                {filteredTurns.length === 0 ? (
                                    <div className="p-10 text-center text-neutral-400 text-xs">
                                        <FileText className="w-8 h-8 mx-auto mb-2 opacity-30" />
                                        {call.transcript ? 'No matching speech found for search term.' : 'Transcript not available for this call.'}
                                    </div>
                                ) : (
                                    filteredTurns.map((turn, idx) => {
                                        const isAI = turn.speaker === 'agent';
                                        return (
                                            <div
                                                key={idx}
                                                className={`flex flex-col ${isAI ? 'items-start' : 'items-end'} text-xs`}
                                            >
                                                <span className="text-[10px] font-bold text-neutral-400 mb-1 px-1 flex items-center gap-1">
                                                    {isAI ? <Bot className="w-3 h-3 text-purple-600" /> : <Users className="w-3 h-3 text-blue-600" />}
                                                    {turn.speaker_label}
                                                </span>
                                                <div
                                                    className={`p-3.5 rounded-2xl max-w-[90%] leading-relaxed ${
                                                        isAI
                                                            ? 'bg-purple-50 dark:bg-purple-950/30 text-neutral-900 dark:text-neutral-100 border border-purple-100 dark:border-purple-900/40 rounded-tl-xs'
                                                            : 'bg-blue-600 text-white rounded-tr-xs shadow-xs'
                                                    }`}
                                                >
                                                    {highlightText(turn.text, transcriptQuery)}
                                                </div>
                                            </div>
                                        );
                                    })
                                )}
                            </div>
                        </Card>
                    </div>

                    {/* RIGHT COLUMN: AI Summary & Conversation Intelligence (3 cols) */}
                    <div className="lg:col-span-3 space-y-5">
                        {/* AI Executive Summary Card */}
                        <Card className="border-neutral-200 dark:border-neutral-800 p-5 space-y-4">
                            <div className="flex items-center gap-2">
                                <Sparkles className="w-4 h-4 text-amber-500" />
                                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">AI Summary</h3>
                            </div>

                            <div className="p-3.5 rounded-xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-900/40 text-xs text-neutral-800 dark:text-neutral-200 leading-relaxed font-medium">
                                {call.summary || 'Summary pending analysis.'}
                            </div>

                            {/* Intent & Signals */}
                            <div className="space-y-2.5 text-xs">
                                <div className="flex justify-between items-center">
                                    <span className="text-neutral-500">Customer Intent</span>
                                    <Badge variant="brand" className="capitalize text-[10px]">
                                        {call.intent || 'Sales'}
                                    </Badge>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-neutral-500">Lead Interest</span>
                                    <span className="font-bold text-xs text-emerald-600 capitalize">
                                        🔥 {call.lead_interest || 'High'}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-neutral-500">Conversation Signal</span>
                                    <span className="font-semibold text-xs text-neutral-800 dark:text-neutral-200 capitalize">
                                        {call.conversation_signal === 'positive' ? '😊 Positive' :
                                         call.conversation_signal === 'negative' ? '🙁 Negative' : '😐 Neutral'}
                                    </span>
                                </div>
                            </div>

                            {/* Extracted Topics */}
                            {call.topics && call.topics.length > 0 && (
                                <div className="space-y-1.5 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                                    <span className="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">
                                        Extracted Topics
                                    </span>
                                    <div className="flex flex-wrap gap-1">
                                        {call.topics.map((t, idx) => (
                                            <span key={idx} className="px-2 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                                                #{t}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Important Moments */}
                            {call.important_moments && call.important_moments.length > 0 && (
                                <div className="space-y-1.5 pt-2 border-t border-neutral-100 dark:border-neutral-800 text-xs">
                                    <span className="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">
                                        Important Moments
                                    </span>
                                    <div className="space-y-1">
                                        {call.important_moments.map((m, idx) => (
                                            <div key={idx} className="flex items-baseline gap-1.5 text-[11px]">
                                                <span className="font-mono font-bold text-blue-600 shrink-0">{m.timestamp}</span>
                                                <span className="text-neutral-600 dark:text-neutral-300">{m.text}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </Card>

                        {/* Quick Follow-Up Actions */}
                        <Card className="border-neutral-200 dark:border-neutral-800 p-5 space-y-3">
                            <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider">
                                Recommended Next Action
                            </h3>

                            <p className="text-xs font-semibold text-neutral-800 dark:text-neutral-200">
                                {call.next_action || 'Sales team follow-up via WhatsApp or call.'}
                            </p>

                            <div className="space-y-2 pt-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setCallbackModal(true)}
                                    className="w-full text-xs font-bold gap-1.5 text-blue-600 border-blue-200 hover:bg-blue-50"
                                >
                                    <Calendar className="w-3.5 h-3.5" /> Schedule Callback
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setTagModal(true)}
                                    className="w-full text-xs font-bold gap-1.5 text-purple-600 border-purple-200 hover:bg-purple-50"
                                >
                                    <Tag className="w-3.5 h-3.5" /> Attach Lead Tag
                                </Button>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Tag Modal */}
            <Modal show={tagModal} onClose={() => setTagModal(false)} title="Attach Tag to Contact">
                <form onSubmit={handleAddTag} className="space-y-4">
                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Select or Enter Tag Name</label>
                        <input
                            type="text"
                            required
                            placeholder="e.g. WhatsApp-Interested, Demo-Requested"
                            value={selectedTag}
                            onChange={(e) => setSelectedTag(e.target.value)}
                            className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 px-3 py-2"
                        />
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" size="sm" onClick={() => setTagModal(false)}>Cancel</Button>
                        <Button type="submit" variant="brand" size="sm">Attach Tag</Button>
                    </div>
                </form>
            </Modal>

            {/* Callback Modal */}
            <Modal show={callbackModal} onClose={() => setCallbackModal(false)} title="Schedule Priority Follow-up Callback">
                <form onSubmit={handleScheduleCallback} className="space-y-4">
                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Callback Date & Time</label>
                        <input
                            type="datetime-local"
                            required
                            value={callbackTime}
                            onChange={(e) => setCallbackTime(e.target.value)}
                            className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 px-3 py-2"
                        />
                    </div>
                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Notes</label>
                        <textarea
                            rows={2}
                            placeholder="Customer requested pricing discussion..."
                            value={callbackNotes}
                            onChange={(e) => setCallbackNotes(e.target.value)}
                            className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 px-3 py-2"
                        />
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" size="sm" onClick={() => setCallbackModal(false)}>Cancel</Button>
                        <Button type="submit" variant="brand" size="sm" className="bg-emerald-600 hover:bg-emerald-700 text-white">Schedule Callback</Button>
                    </div>
                </form>
            </Modal>
        </ClientLayout>
    );
}

function sprintf(format, ...args) {
    let i = 0;
    return format.replace(/%02d/g, () => String(args[i++]).padStart(2, '0'));
}
