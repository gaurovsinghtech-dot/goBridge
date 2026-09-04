import React, { useState } from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    ListTodo, PhoneCall, CheckCircle2, Clock,
    AlertCircle, Search, Filter, Plus, Calendar,
    ArrowRight, Sparkles, MessageSquare, Mail, Tag,
    UserCheck, Bot, Flame, Settings, XCircle
} from 'lucide-react';
import { Card, Button, Badge, Modal } from '@/Components/ui';
import { toast } from 'sonner';

export default function VoiceFollowUpsIndex({
    followUps = { data: [] },
    stats = {},
    filters = {},
}) {
    const list = followUps?.data || [];
    const [rescheduleModal, setRescheduleModal] = useState(false);
    const [selectedItem, setSelectedItem] = useState(null);
    const [newDueAt, setNewDueAt] = useState('');

    const handleFilterChange = (key, value) => {
        const query = { ...filters, [key]: value };
        if (value === 'all' || value === '' || !value) delete query[key];
        router.get(route('client.voice.follow-ups.index'), query, { preserveState: true, replace: true });
    };

    const handleComplete = (uuid) => {
        router.post(route('client.voice.follow-ups.complete', uuid), {}, {
            onSuccess: () => toast.success('Follow-up marked as completed.'),
        });
    };

    const handleCancel = (uuid) => {
        if (!confirm('Are you sure you want to cancel this follow-up?')) return;
        router.post(route('client.voice.follow-ups.cancel', uuid), {}, {
            onSuccess: () => toast.success('Follow-up cancelled.'),
        });
    };

    const handleRescheduleSubmit = (e) => {
        e.preventDefault();
        if (!newDueAt || !selectedItem) return;

        router.post(route('client.voice.follow-ups.reschedule', selectedItem.uuid), {
            due_at: newDueAt,
        }, {
            onSuccess: () => {
                toast.success('Follow-up rescheduled.');
                setRescheduleModal(false);
            },
        });
    };

    const typeIcons = {
        callback: <PhoneCall className="w-3.5 h-3.5 text-blue-600" />,
        crm_task: <ListTodo className="w-3.5 h-3.5 text-purple-600" />,
        whatsapp: <MessageSquare className="w-3.5 h-3.5 text-emerald-600" />,
        email: <Mail className="w-3.5 h-3.5 text-amber-600" />,
        team_notify: <Sparkles className="w-3.5 h-3.5 text-brand-600" />,
    };

    return (
        <ClientLayout>
            <Head title="Voice Call Follow-ups & Callback Automation" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <h1 className="text-xl font-bold text-neutral-900 dark:text-white">Follow-up & Callback Automation</h1>
                            <Badge variant="brand" className="text-xs">
                                Omnichannel
                            </Badge>
                        </div>
                        <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                            Convert completed AI voice calls into automated voice callbacks, CRM sales tasks, and WhatsApp messages.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link href={route('client.voice.follow-ups.rules')}>
                            <Button variant="outline" size="sm" className="text-xs font-bold gap-1.5">
                                <Settings className="w-3.5 h-3.5" /> Automation Rules
                            </Button>
                        </Link>
                        <Link href={route('client.voice.follow-ups.create')}>
                            <Button size="sm" variant="brand" className="text-xs font-bold gap-1.5 bg-brand-600 text-white">
                                <Plus className="w-3.5 h-3.5" /> Schedule Follow-up
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Top KPI Cards */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <Card className="p-4 border-neutral-200 dark:border-neutral-800">
                        <span className="text-[11px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider block">Due Today</span>
                        <span className="text-2xl font-extrabold text-neutral-900 dark:text-white mt-1 block">{stats.due_today || 0}</span>
                    </Card>
                    <Card className="p-4 border-neutral-200 dark:border-neutral-800">
                        <span className="text-[11px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider block">Scheduled</span>
                        <span className="text-2xl font-extrabold text-neutral-900 dark:text-white mt-1 block">{stats.scheduled || 0}</span>
                    </Card>
                    <Card className="p-4 border-neutral-200 dark:border-neutral-800">
                        <span className="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider block">Completed</span>
                        <span className="text-2xl font-extrabold text-neutral-900 dark:text-white mt-1 block">{stats.completed || 0}</span>
                    </Card>
                    <Card className="p-4 border-neutral-200 dark:border-neutral-800">
                        <span className="text-[11px] font-bold text-red-600 dark:text-red-400 uppercase tracking-wider block">Overdue</span>
                        <span className="text-2xl font-extrabold text-neutral-900 dark:text-white mt-1 block">{stats.overdue || 0}</span>
                    </Card>
                </div>

                {/* Filter Bar */}
                <Card className="p-4 border-neutral-200 dark:border-neutral-800 space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-2">
                            {/* Date Filter */}
                            <select
                                value={filters.date || 'all'}
                                onChange={(e) => handleFilterChange('date', e.target.value)}
                                className="text-xs font-semibold rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-1.5 text-neutral-800 dark:text-neutral-200"
                            >
                                <option value="all">All Dates</option>
                                <option value="today">Due Today</option>
                                <option value="overdue">Overdue</option>
                                <option value="upcoming">Upcoming</option>
                            </select>

                            {/* Type Filter */}
                            <select
                                value={filters.type || 'all'}
                                onChange={(e) => handleFilterChange('type', e.target.value)}
                                className="text-xs font-semibold rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-1.5 text-neutral-800 dark:text-neutral-200"
                            >
                                <option value="all">All Types</option>
                                <option value="callback">📞 Voice Callback</option>
                                <option value="crm_task">📋 CRM Task</option>
                                <option value="whatsapp">💬 WhatsApp</option>
                                <option value="email">📧 Email</option>
                            </select>

                            {/* Status Filter */}
                            <select
                                value={filters.status || 'all'}
                                onChange={(e) => handleFilterChange('status', e.target.value)}
                                className="text-xs font-semibold rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-1.5 text-neutral-800 dark:text-neutral-200"
                            >
                                <option value="all">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>

                            {/* Priority Filter */}
                            <select
                                value={filters.priority || 'all'}
                                onChange={(e) => handleFilterChange('priority', e.target.value)}
                                className="text-xs font-semibold rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-1.5 text-neutral-800 dark:text-neutral-200"
                            >
                                <option value="all">All Priorities</option>
                                <option value="high">🔥 High</option>
                                <option value="medium">🟡 Medium</option>
                                <option value="low">⚪ Low</option>
                            </select>
                        </div>

                        {/* Search Input */}
                        <div className="relative w-full sm:w-72">
                            <Search className="w-3.5 h-3.5 absolute left-3 top-2.5 text-neutral-400" />
                            <input
                                type="text"
                                placeholder="Search customer, title, notes..."
                                value={filters.search || ''}
                                onChange={(e) => handleFilterChange('search', e.target.value)}
                                className="w-full text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 pl-8 pr-3 py-1.5 text-neutral-900 dark:text-white"
                            />
                        </div>
                    </div>
                </Card>

                {/* Follow-ups List Table */}
                <Card className="border-neutral-200 dark:border-neutral-800 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-neutral-50 dark:bg-neutral-800/50 border-b border-neutral-200 dark:border-neutral-800 text-neutral-500 font-semibold uppercase text-[10px]">
                                <tr>
                                    <th className="px-5 py-3">Customer & Title</th>
                                    <th className="px-5 py-3">Type</th>
                                    <th className="px-5 py-3">Priority</th>
                                    <th className="px-5 py-3">Due Time</th>
                                    <th className="px-5 py-3">Assigned Agent</th>
                                    <th className="px-5 py-3">Status</th>
                                    <th className="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                                {list.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-5 py-10 text-center text-neutral-400">
                                            No follow-ups match the selected filters.
                                        </td>
                                    </tr>
                                ) : (
                                    list.map((item) => {
                                        const isCompleted = item.status === 'completed';
                                        const isCancelled = item.status === 'cancelled';

                                        return (
                                            <tr key={item.id} className="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/30 transition">
                                                {/* Customer & Title */}
                                                <td className="px-5 py-3.5">
                                                    <div className="font-bold text-neutral-900 dark:text-white">
                                                        {item.contact ? `${item.contact.first_name} ${item.contact.last_name || ''}` : 'Customer'}
                                                    </div>
                                                    <div className="text-[11px] text-neutral-500 line-clamp-1 mt-0.5">
                                                        {item.title}
                                                    </div>
                                                </td>

                                                {/* Type */}
                                                <td className="px-5 py-3.5">
                                                    <div className="flex items-center gap-1.5 font-semibold text-neutral-700 dark:text-neutral-300 capitalize">
                                                        {typeIcons[item.type] || <ListTodo className="w-3.5 h-3.5" />}
                                                        {item.type.replace('_', ' ')}
                                                    </div>
                                                </td>

                                                {/* Priority */}
                                                <td className="px-5 py-3.5">
                                                    <Badge variant={
                                                        item.priority === 'high' ? 'danger' :
                                                        item.priority === 'medium' ? 'warning' : 'neutral'
                                                    } className="capitalize text-[10px]">
                                                        {item.priority === 'high' && '🔥 '}
                                                        {item.priority}
                                                    </Badge>
                                                </td>

                                                {/* Due Time */}
                                                <td className="px-5 py-3.5 text-neutral-700 dark:text-neutral-300 font-medium">
                                                    {item.due_at ? new Date(item.due_at).toLocaleString() : '—'}
                                                </td>

                                                {/* Agent */}
                                                <td className="px-5 py-3.5 text-neutral-600 dark:text-neutral-400">
                                                    {item.assigned_user?.name || item.voice_agent?.name || 'Sales Team'}
                                                </td>

                                                {/* Status */}
                                                <td className="px-5 py-3.5">
                                                    <Badge variant={
                                                        item.status === 'completed' ? 'success' :
                                                        item.status === 'scheduled' ? 'brand' :
                                                        item.status === 'cancelled' ? 'neutral' : 'warning'
                                                    } className="capitalize text-[10px]">
                                                        ● {item.status}
                                                    </Badge>
                                                </td>

                                                {/* Actions */}
                                                <td className="px-5 py-3.5 text-right space-x-1">
                                                    {!isCompleted && !isCancelled && (
                                                        <>
                                                            <button
                                                                type="button"
                                                                title="Mark Completed"
                                                                onClick={() => handleComplete(item.uuid)}
                                                                className="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
                                                            >
                                                                <CheckCircle2 className="w-4 h-4" />
                                                            </button>

                                                            <button
                                                                type="button"
                                                                title="Reschedule"
                                                                onClick={() => {
                                                                    setSelectedItem(item);
                                                                    setNewDueAt(item.due_at ? item.due_at.substring(0, 16) : '');
                                                                    setRescheduleModal(true);
                                                                }}
                                                                className="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/40"
                                                            >
                                                                <Clock className="w-4 h-4" />
                                                            </button>

                                                            <button
                                                                type="button"
                                                                title="Cancel"
                                                                onClick={() => handleCancel(item.uuid)}
                                                                className="p-1.5 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40"
                                                            >
                                                                <XCircle className="w-4 h-4" />
                                                            </button>
                                                        </>
                                                    )}

                                                    <Link href={route('client.voice.follow-ups.show', item.uuid)}>
                                                        <Button size="sm" variant="ghost" className="text-xs p-1">
                                                            <ArrowRight className="w-3.5 h-3.5" />
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

            {/* Reschedule Modal */}
            <Modal show={rescheduleModal} onClose={() => setRescheduleModal(false)} title="Reschedule Follow-up">
                <form onSubmit={handleRescheduleSubmit} className="space-y-4">
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
