import React, { useState } from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft, PhoneCall, ListTodo, MessageSquare,
    Mail, Calendar, Clock, UserCheck, Flame, Sparkles
} from 'lucide-react';
import { Card, Button } from '@/Components/ui';
import { toast } from 'sonner';

export default function VoiceFollowUpsCreate({
    contacts = [],
    agents = [],
    teamUsers = [],
}) {
    const [form, setForm] = useState({
        type: 'callback',
        contact_id: '',
        voice_agent_id: '',
        assigned_user_id: '',
        priority: 'high',
        due_at: '',
        title: '',
        notes: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        router.post(route('client.voice.follow-ups.store'), form, {
            onSuccess: () => toast.success('Follow-up created successfully.'),
        });
    };

    return (
        <ClientLayout>
            <Head title="Schedule Follow-up / Callback" />

            <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <Link href={route('client.voice.follow-ups.index')}>
                        <Button variant="ghost" size="sm" className="p-2">
                            <ArrowLeft className="w-4 h-4" />
                        </Button>
                    </Link>
                    <div>
                        <h1 className="text-xl font-bold text-neutral-900 dark:text-white">Schedule Follow-up / Callback</h1>
                        <p className="text-xs text-neutral-500">
                            Queue an automated AI voice callback or assign a sales task for your team.
                        </p>
                    </div>
                </div>

                {/* Form Card */}
                <Card className="border-neutral-200 dark:border-neutral-800 p-6">
                    <form onSubmit={handleSubmit} className="space-y-5">
                        {/* Type Selection */}
                        <div className="space-y-1.5">
                            <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300 uppercase tracking-wider">
                                Follow-up Type
                            </label>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                {[
                                    { id: 'callback', label: 'Voice Callback', icon: PhoneCall, desc: 'Queued in Smart Queue' },
                                    { id: 'crm_task', label: 'CRM Sales Task', icon: ListTodo, desc: 'Assigned to sales user' },
                                    { id: 'whatsapp', label: 'WhatsApp Message', icon: MessageSquare, desc: 'WhatsApp follow-up' },
                                    { id: 'email', label: 'Email Follow-up', icon: Mail, desc: 'Sales email sequence' },
                                ].map((t) => {
                                    const Icon = t.icon;
                                    const selected = form.type === t.id;
                                    return (
                                        <button
                                            key={t.id}
                                            type="button"
                                            onClick={() => setForm({ ...form, type: t.id })}
                                            className={`p-3 rounded-xl border text-left transition flex flex-col justify-between ${
                                                selected
                                                    ? 'border-brand-600 bg-brand-50/50 dark:bg-brand-950/20 text-brand-900 dark:text-brand-100 ring-2 ring-brand-500/20'
                                                    : 'border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/40 hover:bg-neutral-100'
                                            }`}
                                        >
                                            <Icon className={`w-5 h-5 mb-2 ${selected ? 'text-brand-600' : 'text-neutral-500'}`} />
                                            <div>
                                                <span className="text-xs font-bold block">{t.label}</span>
                                                <span className="text-[10px] text-neutral-400 block">{t.desc}</span>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Title */}
                        <div className="space-y-1">
                            <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                Title / Subject *
                            </label>
                            <input
                                type="text"
                                required
                                placeholder="e.g. Discuss enterprise pricing and schedule demo"
                                value={form.title}
                                onChange={(e) => setForm({ ...form, title: e.target.value })}
                                className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 px-3.5 py-2.5"
                            />
                        </div>

                        {/* Contact & Voice Agent */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                    Target Customer / Contact
                                </label>
                                <select
                                    value={form.contact_id}
                                    onChange={(e) => setForm({ ...form, contact_id: e.target.value })}
                                    className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 px-3.5 py-2.5 bg-white dark:bg-neutral-800"
                                >
                                    <option value="">Select Contact</option>
                                    {contacts.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.first_name} {c.last_name || ''} ({c.phone_e164})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                    AI Voice Agent (for Callbacks)
                                </label>
                                <select
                                    value={form.voice_agent_id}
                                    onChange={(e) => setForm({ ...form, voice_agent_id: e.target.value })}
                                    className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 px-3.5 py-2.5 bg-white dark:bg-neutral-800"
                                >
                                    <option value="">Select AI Agent</option>
                                    {agents.map((ag) => (
                                        <option key={ag.id} value={ag.id}>{ag.name}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Due Date & Priority */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                    Due Date & Time *
                                </label>
                                <input
                                    type="datetime-local"
                                    required
                                    value={form.due_at}
                                    onChange={(e) => setForm({ ...form, due_at: e.target.value })}
                                    className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 px-3.5 py-2.5"
                                />
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                    Priority Level
                                </label>
                                <select
                                    value={form.priority}
                                    onChange={(e) => setForm({ ...form, priority: e.target.value })}
                                    className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 px-3.5 py-2.5 bg-white dark:bg-neutral-800"
                                >
                                    <option value="high">🔥 High Priority</option>
                                    <option value="medium">🟡 Medium Priority</option>
                                    <option value="low">⚪ Low Priority</option>
                                </select>
                            </div>
                        </div>

                        {/* Notes */}
                        <div className="space-y-1">
                            <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                Instructions / Notes
                            </label>
                            <textarea
                                rows={3}
                                placeholder="Customer expressed high interest in WhatsApp automation. Follow up with brochure."
                                value={form.notes}
                                onChange={(e) => setForm({ ...form, notes: e.target.value })}
                                className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 px-3.5 py-2.5"
                            />
                        </div>

                        {/* Actions */}
                        <div className="flex justify-end gap-3 pt-4 border-t border-neutral-100 dark:border-neutral-800">
                            <Link href={route('client.voice.follow-ups.index')}>
                                <Button type="button" variant="outline" size="sm">Cancel</Button>
                            </Link>
                            <Button type="submit" variant="brand" size="sm" className="bg-brand-600 text-white font-bold">
                                Create Follow-up
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </ClientLayout>
    );
}
