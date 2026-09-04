import React, { useState } from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft, MessageSquare, Phone, Mail, Globe,
    Camera, Trash2, Upload, Sparkles, CheckCircle2,
    Clock, ListTodo, Bot, Users, Tag, AlertTriangle,
    Search, Filter, ExternalLink, Calendar, Plus,
    Send, Zap, ShieldCheck, ChevronRight
} from 'lucide-react';
import { Card, Button, Badge, Modal } from '@/Components/ui';
import { toast } from 'sonner';

export default function UnifiedCustomerShow({
    contact = {},
    timeline = [],
    journey = {},
    aiSummary = {},
    staticSegments = [],
    filters = {},
}) {
    const [channelFilter, setChannelFilter] = useState(filters.channel || 'all');
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [noteModal, setNoteModal] = useState(false);
    const [noteText, setNoteText] = useState('');
    const [editModal, setEditModal] = useState(false);
    const [editForm, setEditForm] = useState({
        first_name: contact.first_name || '',
        last_name: contact.last_name || '',
        email: contact.email || '',
        phone_e164: contact.phone_e164 || '',
        custom_fields: contact.custom_fields || {},
    });

    const name = `${contact.first_name ?? ''} ${contact.last_name ?? ''}`.trim() || 'Customer Profile';
    const initials = name
        ? name.split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase()
        : '?';

    const handleFilterChange = (channel) => {
        setChannelFilter(channel);
        router.get(route('client.contacts.show', contact.uuid || contact.id), {
            channel,
            search: searchQuery,
        }, { preserveState: true, replace: true });
    };

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        router.get(route('client.contacts.show', contact.uuid || contact.id), {
            channel: channelFilter,
            search: searchQuery,
        }, { preserveState: true, replace: true });
    };

    const handleAddNote = (e) => {
        e.preventDefault();
        if (!noteText.trim()) return;

        router.post(route('client.contacts.notes', contact.uuid || contact.id), {
            body: noteText,
        }, {
            onSuccess: () => {
                toast.success('Note added to timeline.');
                setNoteText('');
                setNoteModal(false);
            },
        });
    };

    const handleMerge = (secondaryId) => {
        if (!confirm('Are you sure you want to merge this duplicate contact into the current profile? All messages and call records will be preserved.')) return;

        router.post(route('client.contacts.merge', contact.uuid || contact.id), {
            secondary_contact_id: secondaryId,
        }, {
            onSuccess: () => toast.success('Contacts merged successfully.'),
        });
    };

    const channelIcons = {
        whatsapp: <MessageSquare className="w-4 h-4 text-emerald-600" />,
        messenger: <MessageSquare className="w-4 h-4 text-blue-600" />,
        instagram: <MessageSquare className="w-4 h-4 text-pink-600" />,
        email: <Mail className="w-4 h-4 text-amber-600" />,
        voice: <Phone className="w-4 h-4 text-purple-600" />,
        human_call: <Users className="w-4 h-4 text-blue-600" />,
        crm: <Tag className="w-4 h-4 text-indigo-600" />,
        automation: <Zap className="w-4 h-4 text-amber-500" />,
        task: <ListTodo className="w-4 h-4 text-violet-600" />,
        note: <Sparkles className="w-4 h-4 text-neutral-500" />,
        callback: <Phone className="w-4 h-4 text-blue-600" />,
    };

    return (
        <ClientLayout>
            <Head title={`Customer 360 — ${name}`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* Header Profile Bar */}
                <div className="bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div className="flex items-center gap-4">
                            <Link href={route('client.contacts.index')}>
                                <Button variant="ghost" size="sm" className="p-2">
                                    <ArrowLeft className="w-4 h-4" />
                                </Button>
                            </Link>

                            {/* Avatar */}
                            <div className="h-14 w-14 rounded-full bg-brand-100 dark:bg-brand-900/40 text-brand-700 dark:text-brand-300 flex items-center justify-center text-lg font-bold border-2 border-neutral-200 dark:border-neutral-700 shrink-0">
                                {contact.avatar ? (
                                    <img src={contact.avatar} alt={name} className="h-full w-full rounded-full object-cover" />
                                ) : initials}
                            </div>

                            {/* Info */}
                            <div>
                                <div className="flex items-center gap-2.5">
                                    <h1 className="text-xl font-bold text-neutral-900 dark:text-white">{name}</h1>
                                    <Badge variant="brand" className="text-xs">
                                        ● {contact.status || 'Lead'}
                                    </Badge>
                                </div>
                                <div className="flex flex-wrap items-center gap-3 text-xs text-neutral-500 mt-1 font-mono">
                                    {contact.phone_e164 && <span>📞 {contact.phone_e164}</span>}
                                    {contact.email && <span>✉ {contact.email}</span>}
                                </div>
                            </div>
                        </div>

                        {/* Quick Action Bar */}
                        <div className="flex flex-wrap items-center gap-2">
                            <Link href={route('client.inbox.index', { search: contact.phone_e164 || contact.first_name })}>
                                <Button size="sm" variant="outline" className="text-xs font-bold gap-1 text-emerald-600 border-emerald-200 hover:bg-emerald-50">
                                    <MessageSquare className="w-3.5 h-3.5" /> WhatsApp
                                </Button>
                            </Link>

                            <Link href={route('client.voice.call-center')}>
                                <Button size="sm" variant="outline" className="text-xs font-bold gap-1 text-purple-600 border-purple-200 hover:bg-purple-50">
                                    <Phone className="w-3.5 h-3.5" /> Call
                                </Button>
                            </Link>

                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setNoteModal(true)}
                                className="text-xs font-bold gap-1 text-neutral-700 dark:text-neutral-300"
                            >
                                <Plus className="w-3.5 h-3.5" /> Note
                            </Button>

                            <Link href={route('client.voice.follow-ups.create')}>
                                <Button size="sm" variant="brand" className="text-xs font-bold gap-1 bg-brand-600 text-white">
                                    <ListTodo className="w-3.5 h-3.5" /> Schedule Follow-up
                                </Button>
                            </Link>
                        </div>
                    </div>

                    {/* Connected Channel Badges */}
                    <div className="flex flex-wrap items-center gap-2 pt-3 border-t border-neutral-100 dark:border-neutral-800 text-xs">
                        <span className="text-neutral-400 font-semibold text-[11px] uppercase tracking-wider mr-1">Active Channels:</span>
                        {journey.channels && journey.channels.length > 0 ? (
                            journey.channels.map((ch) => (
                                <Badge key={ch} variant="neutral" className="capitalize text-[11px] gap-1 py-0.5">
                                    {ch === 'whatsapp' && '💬 WhatsApp'}
                                    {ch === 'messenger' && '🔵 Messenger'}
                                    {ch === 'instagram' && '📷 Instagram'}
                                    {ch === 'email' && '📧 Email'}
                                    {ch === 'voice' && '📞 AI Voice'}
                                </Badge>
                            ))
                        ) : (
                            <span className="text-neutral-400 italic">No channels linked.</span>
                        )}
                    </div>
                </div>

                {/* 3-Column Customer 360 Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    {/* LEFT COLUMN: Profile Context & Journey Stats (3 cols) */}
                    <div className="lg:col-span-3 space-y-6">
                        {/* Customer 360 Metadata */}
                        <Card className="border-neutral-200 dark:border-neutral-800 p-5 space-y-3.5 text-xs">
                            <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider">
                                Customer 360 Profile
                            </h3>

                            <div className="divide-y divide-neutral-100 dark:divide-neutral-800 space-y-2">
                                <div className="flex justify-between py-1">
                                    <span className="text-neutral-500">First Contact</span>
                                    <span className="font-semibold text-neutral-900 dark:text-white">{journey.first_contact || '—'}</span>
                                </div>
                                <div className="flex justify-between py-1">
                                    <span className="text-neutral-500">Last Active</span>
                                    <span className="font-semibold text-neutral-900 dark:text-white">{journey.last_contact || '—'}</span>
                                </div>
                                <div className="flex justify-between py-1">
                                    <span className="text-neutral-500">Total Calls</span>
                                    <span className="font-mono font-bold text-purple-600">{journey.total_calls || 0}</span>
                                </div>
                                <div className="flex justify-between py-1">
                                    <span className="text-neutral-500">Total Messages</span>
                                    <span className="font-mono font-bold text-emerald-600">{journey.total_messages || 0}</span>
                                </div>
                                <div className="flex justify-between py-1">
                                    <span className="text-neutral-500">AI / Human Chats</span>
                                    <span className="text-neutral-700 dark:text-neutral-300 font-semibold">
                                        🤖 {journey.ai_conversations || 0} / 👤 {journey.human_conversations || 0}
                                    </span>
                                </div>
                            </div>
                        </Card>

                        {/* CRM Tags */}
                        <Card className="border-neutral-200 dark:border-neutral-800 p-5 space-y-3 text-xs">
                            <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider">
                                CRM Tags
                            </h3>
                            <div className="flex flex-wrap gap-1.5">
                                {contact.tags && contact.tags.length > 0 ? (
                                    contact.tags.map((t) => (
                                        <Badge key={t.id} variant="neutral" className="text-[11px]">
                                            🏷 {t.name}
                                        </Badge>
                                    ))
                                ) : (
                                    <span className="text-neutral-400 italic">No tags attached.</span>
                                )}
                            </div>
                        </Card>

                        {/* Potential Duplicates & Merge */}
                        {journey.potential_duplicates && journey.potential_duplicates.length > 0 && (
                            <Card className="border-amber-200 dark:border-amber-900/40 p-5 space-y-3 bg-amber-50/40 dark:bg-amber-950/20 text-xs">
                                <div className="flex items-center gap-1.5 text-amber-700 dark:text-amber-300 font-bold">
                                    <AlertTriangle className="w-4 h-4" /> Possible Duplicate Contact
                                </div>
                                {journey.potential_duplicates.map((dup) => (
                                    <div key={dup.id} className="space-y-2 p-2.5 bg-white dark:bg-neutral-900 rounded-xl border border-amber-200 dark:border-amber-800">
                                        <div className="font-bold text-neutral-900 dark:text-white">
                                            {dup.first_name} {dup.last_name || ''}
                                        </div>
                                        <div className="text-[11px] text-neutral-500 font-mono">
                                            {dup.phone_e164 || dup.email}
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => handleMerge(dup.id)}
                                            className="w-full text-[11px] font-bold text-amber-700 border-amber-300 hover:bg-amber-100"
                                        >
                                            Merge into this Profile
                                        </Button>
                                    </div>
                                ))}
                            </Card>
                        )}
                    </div>

                    {/* CENTER COLUMN: Unified Chronological Timeline (6 cols) */}
                    <div className="lg:col-span-6 space-y-4">
                        {/* Filter Bar */}
                        <Card className="border-neutral-200 dark:border-neutral-800 p-4 space-y-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div className="flex flex-wrap items-center gap-1.5">
                                    {[
                                        { id: 'all', label: 'All Events' },
                                        { id: 'whatsapp', label: '💬 WhatsApp' },
                                        { id: 'voice', label: '📞 Voice' },
                                        { id: 'email', label: '📧 Email' },
                                        { id: 'crm', label: '📋 CRM & Tasks' },
                                        { id: 'automation', label: '⚙ Automation' },
                                    ].map((f) => (
                                        <button
                                            key={f.id}
                                            type="button"
                                            onClick={() => handleFilterChange(f.id)}
                                            className={`px-2.5 py-1 rounded-lg text-xs font-semibold transition ${
                                                channelFilter === f.id
                                                    ? 'bg-brand-600 text-white'
                                                    : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-200'
                                            }`}
                                        >
                                            {f.label}
                                        </button>
                                    ))}
                                </div>

                                {/* Search */}
                                <form onSubmit={handleSearchSubmit} className="relative w-full sm:w-48">
                                    <Search className="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-neutral-400" />
                                    <input
                                        type="text"
                                        placeholder="Search timeline..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="w-full text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/80 pl-8 pr-2.5 py-1.5 text-neutral-900 dark:text-white"
                                    />
                                </form>
                            </div>
                        </Card>

                        {/* Timeline Feed */}
                        <div className="space-y-4">
                            {timeline.length === 0 ? (
                                <Card className="p-12 text-center border-neutral-200 dark:border-neutral-800 text-neutral-400 text-xs">
                                    <Sparkles className="w-8 h-8 mx-auto mb-2 opacity-30" />
                                    No timeline events match the selected filter.
                                </Card>
                            ) : (
                                timeline.map((event) => (
                                    <Card key={event.id} className="border-neutral-200 dark:border-neutral-800 p-4 space-y-2.5 hover:border-brand-500/30 transition">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex items-center gap-2">
                                                <div className="p-2 rounded-xl bg-neutral-100 dark:bg-neutral-800 shrink-0">
                                                    {channelIcons[event.type] || <MessageSquare className="w-4 h-4" />}
                                                </div>
                                                <div>
                                                    <h4 className="text-xs font-bold text-neutral-900 dark:text-white">
                                                        {event.title}
                                                    </h4>
                                                    <span className="text-[10px] text-neutral-400 font-mono">
                                                        {event.formatted_date} at {event.formatted_time}
                                                    </span>
                                                </div>
                                            </div>

                                            {event.badge && (
                                                <Badge variant={event.badge.variant || 'neutral'} className="text-[10px]">
                                                    {event.badge.text}
                                                </Badge>
                                            )}
                                        </div>

                                        <p className="text-xs text-neutral-700 dark:text-neutral-300 leading-relaxed bg-neutral-50 dark:bg-neutral-800/50 p-3 rounded-xl border border-neutral-100 dark:border-neutral-800 font-medium">
                                            {event.summary}
                                        </p>

                                        {event.action_url && (
                                            <div className="flex justify-end pt-1">
                                                <Link href={event.action_url}>
                                                    <Button size="sm" variant="ghost" className="text-xs font-bold gap-1 text-brand-600 hover:text-brand-700 p-1">
                                                        {event.action_label || 'View'} <ChevronRight className="w-3 h-3" />
                                                    </Button>
                                                </Link>
                                            </div>
                                        )}
                                    </Card>
                                ))
                            )}
                        </div>
                    </div>

                    {/* RIGHT COLUMN: Next Action & AI Customer Summary (3 cols) */}
                    <div className="lg:col-span-3 space-y-6">
                        {/* Prominent Next Action Card */}
                        <Card className="border-brand-300 dark:border-brand-800 p-5 space-y-3.5 bg-brand-50/30 dark:bg-brand-950/20 text-xs">
                            <div className="flex items-center gap-2">
                                <Clock className="w-4 h-4 text-brand-600" />
                                <h3 className="text-xs font-bold text-brand-900 dark:text-brand-100 uppercase tracking-wider">
                                    Next Action
                                </h3>
                            </div>

                            {journey.next_action ? (
                                <div className="space-y-3">
                                    <div>
                                        <h4 className="font-bold text-sm text-neutral-900 dark:text-white">
                                            {journey.next_action.title}
                                        </h4>
                                        <span className="text-xs font-mono font-semibold text-brand-600 block mt-0.5">
                                            ⏱ Due: {journey.next_action.due_at}
                                        </span>
                                    </div>

                                    <div className="flex items-center gap-2 pt-1">
                                        <Link href={route('client.voice.follow-ups.show', journey.next_action.uuid)} className="w-full">
                                            <Button size="sm" variant="brand" className="w-full text-xs font-bold bg-brand-600 text-white">
                                                Execute Action
                                            </Button>
                                        </Link>
                                    </div>
                                </div>
                            ) : (
                                <div className="p-4 text-center text-neutral-400 text-xs italic">
                                    No pending actions scheduled.
                                </div>
                            )}
                        </Card>

                        {/* AI Customer Summary Card */}
                        <Card className="border-neutral-200 dark:border-neutral-800 p-5 space-y-4">
                            <div className="flex items-center gap-2">
                                <Sparkles className="w-4 h-4 text-amber-500" />
                                <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
                                    AI Customer Summary
                                </h3>
                            </div>

                            <div className="p-3.5 rounded-xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-900/40 text-xs text-neutral-800 dark:text-neutral-200 leading-relaxed font-medium">
                                {aiSummary.summary || 'Summary unavailable.'}
                            </div>

                            <div className="space-y-2.5 text-xs">
                                <div className="flex justify-between items-center">
                                    <span className="text-neutral-500">Current Intent</span>
                                    <Badge variant="brand" className="capitalize text-[10px]">
                                        {aiSummary.intent || 'Sales'}
                                    </Badge>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-neutral-500">Lead Interest</span>
                                    <span className="font-bold text-xs text-emerald-600 capitalize">
                                        🔥 {aiSummary.lead_interest || 'High'}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-neutral-500">Conversation Signal</span>
                                    <span className="font-semibold text-xs text-neutral-800 dark:text-neutral-200 capitalize">
                                        {aiSummary.conversation_signal || 'Positive'}
                                    </span>
                                </div>
                            </div>

                            {/* Extracted Topics */}
                            {aiSummary.topics && aiSummary.topics.length > 0 && (
                                <div className="space-y-1.5 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                                    <span className="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">
                                        Topics of Interest
                                    </span>
                                    <div className="flex flex-wrap gap-1">
                                        {aiSummary.topics.map((t, idx) => (
                                            <span key={idx} className="px-2 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                                                #{t}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </Card>
                    </div>
                </div>
            </div>

            {/* Quick Note Modal */}
            <Modal show={noteModal} onClose={() => setNoteModal(false)} title="Add Quick Note to Customer Timeline">
                <form onSubmit={handleAddNote} className="space-y-4">
                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Note Content</label>
                        <textarea
                            rows={4}
                            required
                            placeholder="Customer requested pricing brochure via WhatsApp tomorrow morning..."
                            value={noteText}
                            onChange={(e) => setNoteText(e.target.value)}
                            className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 px-3 py-2"
                        />
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" size="sm" onClick={() => setNoteModal(false)}>Cancel</Button>
                        <Button type="submit" variant="brand" size="sm">Save Note</Button>
                    </div>
                </form>
            </Modal>
        </ClientLayout>
    );
}
