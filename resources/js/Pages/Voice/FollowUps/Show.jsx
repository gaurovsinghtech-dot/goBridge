import React, { useState } from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft, PhoneCall, ListTodo, MessageSquare,
    Mail, Calendar, Clock, UserCheck, CheckCircle2,
    XCircle, Tag, Users, Bot, Sparkles, ExternalLink
} from 'lucide-react';
import { Card, Button, Badge, Modal } from '@/Components/ui';
import { toast } from 'sonner';

export default function VoiceFollowUpsShow({
    followUp = {},
}) {
    const [rescheduleModal, setRescheduleModal] = useState(false);
    const [newDueAt, setNewDueAt] = useState(followUp.due_at ? followUp.due_at.substring(0, 16) : '');
    const [notes, setNotes] = useState('');

    const handleComplete = () => {
        router.post(route('client.voice.follow-ups.complete', followUp.uuid), { notes }, {
            onSuccess: () => toast.success('Follow-up marked as completed.'),
        });
    };

    const handleCancel = () => {
        if (!confirm('Are you sure you want to cancel this follow-up?')) return;
        router.post(route('client.voice.follow-ups.cancel', followUp.uuid), {}, {
            onSuccess: () => toast.success('Follow-up cancelled.'),
        });
    };

    const handleReschedule = (e) => {
        e.preventDefault();
        router.post(route('client.voice.follow-ups.reschedule', followUp.uuid), {
            due_at: newDueAt,
        }, {
            onSuccess: () => {
                toast.success('Follow-up rescheduled.');
                setRescheduleModal(false);
            },
        });
    };

    const isPending = followUp.status === 'pending' || followUp.status === 'scheduled';

    return (
        <ClientLayout>
            <Head title={`Follow-up — ${followUp.title}`} />

            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div className="flex items-center gap-3">
                        <Link href={route('client.voice.follow-ups.index')}>
                            <Button variant="ghost" size="sm" className="p-2">
                                <ArrowLeft className="w-4 h-4" />
                            </Button>
                        </Link>
                        <div>
                            <div className="flex items-center gap-2.5">
                                <h1 className="text-xl font-bold text-neutral-900 dark:text-white">{followUp.title}</h1>
                                <Badge variant={
                                    followUp.status === 'completed' ? 'success' :
                                    followUp.status === 'scheduled' ? 'brand' :
                                    followUp.status === 'cancelled' ? 'neutral' : 'warning'
                                } className="capitalize text-xs">
                                    ● {followUp.status}
                                </Badge>
                            </div>
                            <p className="text-xs text-neutral-500 mt-0.5">
                                Type: <span className="capitalize font-semibold text-neutral-800 dark:text-neutral-200">{followUp.type?.replace('_', ' ')}</span> • Priority: <span className="font-bold uppercase text-amber-600">{followUp.priority}</span>
                            </p>
                        </div>
                    </div>

                    {isPending && (
                        <div className="flex items-center gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setRescheduleModal(true)}
                                className="text-xs font-bold gap-1 text-blue-600 border-blue-200"
                            >
                                <Clock className="w-3.5 h-3.5" /> Reschedule
                            </Button>

                            <Button
                                size="sm"
                                variant="brand"
                                onClick={handleComplete}
                                className="text-xs font-bold gap-1 bg-emerald-600 hover:bg-emerald-700 text-white"
                            >
                                <CheckCircle2 className="w-3.5 h-3.5" /> Mark Completed
                            </Button>
                        </div>
                    )}
                </div>

                {/* Main Content Grid */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {/* Left: Task Info (2 cols) */}
                    <div className="md:col-span-2 space-y-6">
                        <Card className="border-neutral-200 dark:border-neutral-800 p-6 space-y-4">
                            <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider">
                                Follow-up Details & Instructions
                            </h3>

                            <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 text-xs text-neutral-800 dark:text-neutral-200 leading-relaxed whitespace-pre-line font-medium">
                                {followUp.notes || 'No specific notes recorded.'}
                            </div>

                            <div className="divide-y divide-neutral-100 dark:divide-neutral-800 text-xs">
                                <div className="flex justify-between py-2.5">
                                    <span className="text-neutral-500">Scheduled Due Time</span>
                                    <span className="font-semibold text-neutral-900 dark:text-white">
                                        {followUp.due_at ? new Date(followUp.due_at).toLocaleString() : '—'}
                                    </span>
                                </div>

                                <div className="flex justify-between py-2.5">
                                    <span className="text-neutral-500">Assigned Team User</span>
                                    <span className="font-semibold text-neutral-900 dark:text-white">
                                        {followUp.assigned_user?.name || 'Sales Team Pool'}
                                    </span>
                                </div>

                                <div className="flex justify-between py-2.5">
                                    <span className="text-neutral-500">Triggered From Outcome</span>
                                    <Badge variant="brand" className="capitalize text-[10px]">
                                        {followUp.outcome_trigger || 'AI Call'}
                                    </Badge>
                                </div>

                                {followUp.completed_at && (
                                    <div className="flex justify-between py-2.5">
                                        <span className="text-neutral-500">Completed At</span>
                                        <span className="font-semibold text-emerald-600">
                                            {new Date(followUp.completed_at).toLocaleString()}
                                        </span>
                                    </div>
                                )}
                            </div>
                        </Card>

                        {/* Source Call Card */}
                        {followUp.call && (
                            <Card className="border-neutral-200 dark:border-neutral-800 p-6 space-y-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Bot className="w-4 h-4 text-purple-600" />
                                        <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
                                            Source AI Voice Call
                                        </h3>
                                    </div>
                                    <Link href={route('client.voice.calls.show', followUp.call.uuid)}>
                                        <Button size="sm" variant="outline" className="text-xs gap-1 text-brand-600">
                                            View Call Intelligence <ExternalLink className="w-3 h-3" />
                                        </Button>
                                    </Link>
                                </div>

                                <div className="p-3.5 rounded-xl bg-purple-50/50 dark:bg-purple-950/20 border border-purple-100 dark:border-purple-900/40 text-xs">
                                    <p className="font-medium text-neutral-800 dark:text-neutral-200">
                                        {followUp.call.summary || 'Completed voice call with AI Agent.'}
                                    </p>
                                    <div className="flex items-center gap-4 mt-2 text-[11px] text-neutral-500 font-mono">
                                        <span>⏱ Duration: {followUp.call.duration_sec}s</span>
                                        <span>Outcome: {followUp.call.outcome}</span>
                                    </div>
                                </div>
                            </Card>
                        )}
                    </div>

                    {/* Right: Contact & Quick Links (1 col) */}
                    <div className="space-y-6">
                        {/* Target Customer */}
                        <Card className="border-neutral-200 dark:border-neutral-800 p-5 space-y-3.5 text-xs">
                            <h3 className="text-xs font-bold text-neutral-500 uppercase tracking-wider">Customer Profile</h3>

                            {followUp.contact ? (
                                <div className="space-y-2">
                                    <div className="font-bold text-sm text-neutral-900 dark:text-white">
                                        {followUp.contact.first_name} {followUp.contact.last_name || ''}
                                    </div>
                                    <div className="font-mono text-neutral-600 dark:text-neutral-400">
                                        {followUp.contact.phone_e164}
                                    </div>

                                    {/* CRM Tags */}
                                    <div className="flex flex-wrap gap-1 pt-2">
                                        {followUp.contact.tags?.map((t) => (
                                            <Badge key={t.id} variant="neutral" className="text-[10px]">
                                                🏷 {t.name}
                                            </Badge>
                                        ))}
                                    </div>

                                    <div className="pt-3">
                                        <Link href={route('client.contacts.show', followUp.contact.uuid || followUp.contact.id)}>
                                            <Button size="sm" variant="outline" className="w-full text-xs font-bold gap-1">
                                                <Users className="w-3.5 h-3.5" /> Open in CRM
                                            </Button>
                                        </Link>
                                    </div>
                                </div>
                            ) : (
                                <span className="text-neutral-400">No contact linked.</span>
                            )}
                        </Card>

                        {/* Danger zone */}
                        {isPending && (
                            <Card className="border-red-200 dark:border-red-900/40 p-4 space-y-2">
                                <h4 className="text-xs font-bold text-red-600">Cancel Follow-up</h4>
                                <p className="text-[11px] text-neutral-500">Stop this follow-up from appearing in your daily tasks.</p>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={handleCancel}
                                    className="w-full text-xs font-bold text-red-600 border-red-200 hover:bg-red-50"
                                >
                                    <XCircle className="w-3.5 h-3.5 mr-1" /> Cancel Task
                                </Button>
                            </Card>
                        )}
                    </div>
                </div>
            </div>

            {/* Reschedule Modal */}
            <Modal show={rescheduleModal} onClose={() => setRescheduleModal(false)} title="Reschedule Follow-up">
                <form onSubmit={handleReschedule} className="space-y-4">
                    <div className="space-y-1">
                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">New Due Date & Time</label>
                        <input
                            type="datetime-local"
                            required
                            value={newDueAt}
                            onChange={(e) => setNewDueAt(e.target.value)}
                            className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 px-3 py-2"
                        />
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" size="sm" onClick={() => setRescheduleModal(false)}>Cancel</Button>
                        <Button type="submit" variant="brand" size="sm">Reschedule</Button>
                    </div>
                </form>
            </Modal>
        </ClientLayout>
    );
}
