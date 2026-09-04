import React, { useState } from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft, Plus, Settings, Zap, Bot,
    PhoneCall, ListTodo, MessageSquare, Mail,
    Tag, CheckCircle2, Trash2, ToggleLeft, ToggleRight, Sparkles
} from 'lucide-react';
import { Card, Button, Badge, Modal } from '@/Components/ui';
import { toast } from 'sonner';

export default function VoiceFollowUpRules({
    rules = [],
    agents = [],
    campaigns = [],
}) {
    const [createModal, setCreateModal] = useState(false);
    const [form, setForm] = useState({
        name: '',
        trigger_event: 'interested',
        voice_agent_id: '',
        voice_campaign_id: '',
        actions: [
            { type: 'create_crm_task', priority: 'high', due_hours: 24, title: 'Sales follow-up from AI voice call' },
            { type: 'add_tag', tag_name: 'Voice-Interested' },
        ],
    });

    const handleToggle = (uuid) => {
        router.post(route('client.voice.follow-ups.rules.toggle', uuid), {}, {
            onSuccess: () => toast.success('Rule status updated.'),
        });
    };

    const handleDelete = (uuid) => {
        if (!confirm('Are you sure you want to delete this follow-up rule?')) return;
        router.delete(route('client.voice.follow-ups.rules.destroy', uuid), {
            onSuccess: () => toast.success('Rule deleted.'),
        });
    };

    const handleCreateRule = (e) => {
        e.preventDefault();
        router.post(route('client.voice.follow-ups.rules.store'), form, {
            onSuccess: () => {
                toast.success('Follow-up rule created successfully.');
                setCreateModal(false);
            },
        });
    };

    const handleAddAction = (actionType) => {
        if (actionType === 'schedule_callback') {
            setForm({
                ...form,
                actions: [...form.actions, { type: 'schedule_callback', delay_minutes: 180, notes: 'Follow-up callback' }],
            });
        } else if (actionType === 'create_crm_task') {
            setForm({
                ...form,
                actions: [...form.actions, { type: 'create_crm_task', priority: 'high', due_hours: 24, title: 'Sales Task' }],
            });
        } else if (actionType === 'add_tag') {
            setForm({
                ...form,
                actions: [...form.actions, { type: 'add_tag', tag_name: 'Voice-Lead' }],
            });
        } else if (actionType === 'trigger_automation') {
            setForm({
                ...form,
                actions: [...form.actions, { type: 'trigger_automation', event_name: 'voice_call_interested' }],
            });
        }
    };

    const handleRemoveAction = (idx) => {
        setForm({
            ...form,
            actions: form.actions.filter((_, i) => i !== idx),
        });
    };

    const triggerLabels = {
        interested: '🔥 Customer Interested',
        qualified: '✓ Lead Qualified',
        callback_requested: '📞 Callback Requested',
        call_completed: '● Any Completed Call',
        no_answer: '⏳ No Answer',
        human_handoff: '⚡ Human Handoff',
        not_interested: '🚫 Not Interested',
        failed: '✕ Call Failed',
    };

    return (
        <ClientLayout>
            <Head title="Follow-up & Callback Automation Rules" />

            <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div className="flex items-center gap-3">
                        <Link href={route('client.voice.follow-ups.index')}>
                            <Button variant="ghost" size="sm" className="p-2">
                                <ArrowLeft className="w-4 h-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-xl font-bold text-neutral-900 dark:text-white">Follow-up Automation Rules</h1>
                            <p className="text-xs text-neutral-500">
                                Define IF/THEN actions executed automatically when an AI call finishes with a specific outcome.
                            </p>
                        </div>
                    </div>

                    <Button
                        size="sm"
                        variant="brand"
                        onClick={() => setCreateModal(true)}
                        className="text-xs font-bold gap-1.5 bg-brand-600 text-white"
                    >
                        <Plus className="w-3.5 h-3.5" /> Create Follow-up Rule
                    </Button>
                </div>

                {/* Rules List */}
                <div className="space-y-4">
                    {rules.length === 0 ? (
                        <Card className="p-12 text-center border-neutral-200 dark:border-neutral-800 space-y-3">
                            <Zap className="w-8 h-8 text-neutral-400 mx-auto opacity-50" />
                            <h3 className="text-sm font-bold text-neutral-800 dark:text-neutral-200">No Custom Follow-up Rules Defined</h3>
                            <p className="text-xs text-neutral-500 max-w-md mx-auto">
                                The system is currently using smart defaults (Interested → Sales Task & Tag, Callback → Smart Queue). Click below to create custom automated workflows.
                            </p>
                            <Button size="sm" variant="brand" onClick={() => setCreateModal(true)} className="text-xs font-bold gap-1.5">
                                <Plus className="w-3.5 h-3.5" /> Add Rule
                            </Button>
                        </Card>
                    ) : (
                        rules.map((rule) => (
                            <Card key={rule.id} className="border-neutral-200 dark:border-neutral-800 p-5 space-y-4 hover:border-brand-500/40 transition">
                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2.5">
                                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">{rule.name}</h3>
                                            <Badge variant={rule.is_active ? 'success' : 'neutral'} className="text-[10px]">
                                                {rule.is_active ? '● Active' : '○ Disabled'}
                                            </Badge>
                                        </div>
                                        <p className="text-xs text-neutral-500">
                                            Scope: {rule.agent ? <span className="font-semibold text-neutral-700 dark:text-neutral-300">{rule.agent.name}</span> : 'All AI Voice Agents'}
                                        </p>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={() => handleToggle(rule.uuid)}
                                            className="p-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                                            title="Toggle Rule"
                                        >
                                            {rule.is_active ? <ToggleRight className="w-6 h-6 text-brand-600" /> : <ToggleLeft className="w-6 h-6" />}
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() => handleDelete(rule.uuid)}
                                            className="p-1.5 rounded-lg text-neutral-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40"
                                            title="Delete Rule"
                                        >
                                            <Trash2 className="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>

                                {/* Flow Visualization */}
                                <div className="grid grid-cols-1 md:grid-cols-12 gap-3 items-center bg-neutral-50 dark:bg-neutral-800/40 p-3.5 rounded-xl border border-neutral-200 dark:border-neutral-700/60 text-xs">
                                    {/* WHEN */}
                                    <div className="md:col-span-4 space-y-1">
                                        <span className="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">WHEN Trigger</span>
                                        <Badge variant="brand" className="text-xs font-semibold py-1 px-2.5">
                                            {triggerLabels[rule.trigger_event] || rule.trigger_event}
                                        </Badge>
                                    </div>

                                    {/* THEN */}
                                    <div className="md:col-span-8 space-y-1">
                                        <span className="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">THEN Execute Actions</span>
                                        <div className="flex flex-wrap gap-1.5">
                                            {rule.actions?.map((act, idx) => (
                                                <span key={idx} className="px-2.5 py-1 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 font-semibold text-neutral-800 dark:text-neutral-200 flex items-center gap-1.5">
                                                    {act.type === 'schedule_callback' && <PhoneCall className="w-3 h-3 text-blue-600" />}
                                                    {act.type === 'create_crm_task' && <ListTodo className="w-3 h-3 text-purple-600" />}
                                                    {act.type === 'add_tag' && <Tag className="w-3 h-3 text-emerald-600" />}
                                                    {act.type === 'trigger_automation' && <Zap className="w-3 h-3 text-amber-600" />}
                                                    <span className="capitalize">{act.type.replace('_', ' ')}</span>
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        ))
                    )}
                </div>
            </div>

            {/* Create Rule Modal */}
            <Modal show={createModal} onClose={() => setCreateModal(false)} title="Create Follow-up Automation Rule">
                <form onSubmit={handleCreateRule} className="space-y-4">
                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Rule Name *</label>
                        <input
                            type="text"
                            required
                            placeholder="e.g. Hot Lead: Send WhatsApp & Sales Task"
                            value={form.name}
                            onChange={(e) => setForm({ ...form, name: e.target.value })}
                            className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 px-3 py-2"
                        />
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">WHEN Outcome *</label>
                            <select
                                value={form.trigger_event}
                                onChange={(e) => setForm({ ...form, trigger_event: e.target.value })}
                                className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 px-3 py-2 bg-white dark:bg-neutral-800"
                            >
                                <option value="interested">🔥 Customer Interested</option>
                                <option value="qualified">✓ Lead Qualified</option>
                                <option value="callback_requested">📞 Callback Requested</option>
                                <option value="no_answer">⏳ No Answer</option>
                                <option value="human_handoff">⚡ Human Handoff</option>
                                <option value="call_completed">● Any Completed Call</option>
                                <option value="failed">✕ Call Failed</option>
                            </select>
                        </div>

                        <div className="space-y-1">
                            <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Voice Agent Scope</label>
                            <select
                                value={form.voice_agent_id}
                                onChange={(e) => setForm({ ...form, voice_agent_id: e.target.value })}
                                className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 px-3 py-2 bg-white dark:bg-neutral-800"
                            >
                                <option value="">All Voice Agents</option>
                                {agents.map((ag) => (
                                    <option key={ag.id} value={ag.id}>{ag.name}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {/* Actions List */}
                    <div className="space-y-2 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                        <div className="flex items-center justify-between">
                            <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300 uppercase tracking-wider">
                                THEN Execute Actions ({form.actions.length})
                            </label>
                        </div>

                        <div className="space-y-2">
                            {form.actions.map((act, idx) => (
                                <div key={idx} className="flex items-center justify-between p-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 text-xs">
                                    <div className="flex items-center gap-2">
                                        <CheckCircle2 className="w-3.5 h-3.5 text-brand-600" />
                                        <span className="font-semibold capitalize text-neutral-900 dark:text-white">
                                            {act.type.replace('_', ' ')}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => handleRemoveAction(idx)}
                                        className="text-neutral-400 hover:text-red-600"
                                    >
                                        ✕
                                    </button>
                                </div>
                            ))}
                        </div>

                        {/* Add Action Buttons */}
                        <div className="flex flex-wrap gap-1.5 pt-1">
                            <Button type="button" size="sm" variant="outline" onClick={() => handleAddAction('schedule_callback')} className="text-[11px] font-semibold gap-1">
                                + Callback
                            </Button>
                            <Button type="button" size="sm" variant="outline" onClick={() => handleAddAction('create_crm_task')} className="text-[11px] font-semibold gap-1">
                                + CRM Task
                            </Button>
                            <Button type="button" size="sm" variant="outline" onClick={() => handleAddAction('add_tag')} className="text-[11px] font-semibold gap-1">
                                + Tag
                            </Button>
                            <Button type="button" size="sm" variant="outline" onClick={() => handleAddAction('trigger_automation')} className="text-[11px] font-semibold gap-1">
                                + Workflow Event
                            </Button>
                        </div>
                    </div>

                    <div className="flex justify-end gap-2 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                        <Button type="button" variant="outline" size="sm" onClick={() => setCreateModal(false)}>Cancel</Button>
                        <Button type="submit" variant="brand" size="sm">Save Rule</Button>
                    </div>
                </form>
            </Modal>
        </ClientLayout>
    );
}
