import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { 
    Phone, Mail, MessageSquare, Flame, Sparkles, Building2, 
    User, Calendar, Clock, DollarSign, CheckCircle2, AlertCircle, 
    Send, Plus, ArrowRight, UserPlus, UserCheck, ShieldAlert, 
    FileText, Tag, ChevronLeft, PhoneCall
} from 'lucide-react';

export default function LeadDetail({ lead, pipelines, teamMembers, teams }) {
    const [activeTab, setActiveTab] = useState('timeline');
    const [noteContent, setNoteContent] = useState('');
    const [isSubmittingNote, setIsSubmittingNote] = useState(false);
    const [isQualifyingAi, setIsQualifyingAi] = useState(false);
    const [aiResult, setAiResult] = useState(null);

    // Deal Form
    const [showNewDealModal, setShowNewDealModal] = useState(false);
    const [dealData, setDealData] = useState({
        name: '',
        value: '',
        probability: 50,
        expected_close_date: '',
    });

    // Task Form
    const [showNewTaskModal, setShowNewTaskModal] = useState(false);
    const [taskData, setTaskData] = useState({
        title: '',
        description: '',
        due_at: '',
        priority: 'medium',
        assigned_user_id: lead.assigned_user_id || '',
    });

    const handleSaveNote = (e) => {
        e.preventDefault();
        if (!noteContent.trim()) return;
        setIsSubmittingNote(true);

        router.post(route('client.crm.notes.store'), {
            contact_id: lead.id,
            content: noteContent,
            is_private: true,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setNoteContent('');
                setIsSubmittingNote(false);
            },
            onFinish: () => setIsSubmittingNote(false),
        });
    };

    const handleQualifyAi = () => {
        setIsQualifyingAi(true);
        window.axios.post(route('client.crm.leads.qualify-ai', lead.uuid), {
            context_message: lead.conversations?.[0]?.messages?.[0]?.content || '',
        }).then((res) => {
            setAiResult(res.data.data);
            router.reload({ only: ['lead'] });
        }).finally(() => {
            setIsQualifyingAi(false);
        });
    };

    const handleCreateDeal = (e) => {
        e.preventDefault();
        router.post(route('client.crm.deals.store'), {
            ...dealData,
            contact_id: lead.id,
        }, {
            onSuccess: () => {
                setShowNewDealModal(false);
                setDealData({ name: '', value: '', probability: 50, expected_close_date: '' });
            },
        });
    };

    const handleCreateTask = (e) => {
        e.preventDefault();
        router.post(route('client.crm.tasks.store'), {
            ...taskData,
            contact_id: lead.id,
        }, {
            onSuccess: () => {
                setShowNewTaskModal(false);
                setTaskData({ title: '', description: '', due_at: '', priority: 'medium', assigned_user_id: lead.assigned_user_id || '' });
            },
        });
    };

    const handleToggleTaskStatus = (task) => {
        const nextStatus = task.status === 'completed' ? 'pending' : 'completed';
        router.put(route('client.crm.tasks.status', task.id), {
            status: nextStatus,
        }, { preserveScroll: true });
    };

    const formatCurrency = (val) => {
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            maximumFractionDigits: 0,
        }).format(val || 0);
    };

    return (
        <ClientLayout>
            <Head title={`${lead.full_name || 'Lead Details'} - CRM`} />

            <div className="p-6 space-y-6 max-w-7xl mx-auto">
                {/* Back to CRM */}
                <div className="flex items-center gap-2">
                    <Link
                        href={route('client.crm.dashboard')}
                        className="inline-flex items-center gap-1 text-sm font-medium text-neutral-500 hover:text-neutral-900 dark:hover:text-white transition"
                    >
                        <ChevronLeft className="h-4 w-4" />
                        Back to Pipeline
                    </Link>
                </div>

                {/* Lead Hero Bar */}
                <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div className="flex items-start gap-4">
                        <div className="h-14 w-14 rounded-2xl bg-brand-500/10 text-brand-600 dark:text-brand-400 flex items-center justify-center text-xl font-bold border border-brand-500/20">
                            {lead.first_name?.[0]?.toUpperCase() || 'L'}
                        </div>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-bold text-neutral-900 dark:text-white">
                                    {lead.full_name || lead.phone_e164 || 'Unnamed Lead'}
                                </h1>
                                <span className={`px-2.5 py-0.5 text-xs font-bold rounded-full ${
                                    lead.lead_score >= 70 
                                        ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200/60'
                                        : 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300'
                                }`}>
                                    Score: {lead.lead_score || 0}/100 • {lead.lead_score_band || 'Cold'}
                                </span>
                            </div>
                            <div className="flex flex-wrap items-center gap-4 mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                                {lead.company && (
                                    <span className="flex items-center gap-1">
                                        <Building2 className="h-4 w-4" />
                                        {lead.company}
                                    </span>
                                )}
                                {lead.phone_e164 && (
                                    <span className="flex items-center gap-1 font-mono text-xs">
                                        <Phone className="h-3.5 w-3.5" />
                                        {lead.phone_e164}
                                    </span>
                                )}
                                {lead.email && (
                                    <span className="flex items-center gap-1">
                                        <Mail className="h-3.5 w-3.5" />
                                        {lead.email}
                                    </span>
                                )}
                                <span className="text-xs px-2 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 uppercase font-semibold text-neutral-600 dark:text-neutral-300">
                                    {lead.source || 'manual'}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Omnichannel Quick Action Bar */}
                    <div className="flex flex-wrap items-center gap-2.5">
                        <Link
                            href={`/app/inbox?contact_id=${lead.id}`}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition"
                        >
                            <MessageSquare className="h-4 w-4" />
                            WhatsApp
                        </Link>
                        <button
                            onClick={handleQualifyAi}
                            disabled={isQualifyingAi}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition disabled:opacity-50"
                        >
                            <Sparkles className="h-4 w-4" />
                            {isQualifyingAi ? 'Analyzing...' : 'AI Qualify'}
                        </button>
                    </div>
                </div>

                {/* Main Grid: Left Timeline/Notes, Right Deal & Info Widgets */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left 2 Cols: Tabs (Timeline, Notes, Tasks) */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Tab Bar */}
                        <div className="flex items-center gap-2 border-b border-neutral-200 dark:border-neutral-800 pb-2">
                            <button
                                onClick={() => setActiveTab('timeline')}
                                className={`px-4 py-2 text-sm font-semibold rounded-lg transition ${
                                    activeTab === 'timeline'
                                        ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                                        : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                                }`}
                            >
                                Activity Timeline ({lead.timeline_events?.length || 0})
                            </button>
                            <button
                                onClick={() => setActiveTab('notes')}
                                className={`px-4 py-2 text-sm font-semibold rounded-lg transition ${
                                    activeTab === 'notes'
                                        ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                                        : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                                }`}
                            >
                                Internal Notes ({lead.crm_notes?.length || 0})
                            </button>
                            <button
                                onClick={() => setActiveTab('tasks')}
                                className={`px-4 py-2 text-sm font-semibold rounded-lg transition ${
                                    activeTab === 'tasks'
                                        ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                                        : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                                }`}
                            >
                                Follow-up Tasks ({lead.crm_tasks?.length || 0})
                            </button>
                        </div>

                        {/* Timeline Tab */}
                        {activeTab === 'timeline' && (
                            <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 space-y-6">
                                <h3 className="font-bold text-sm text-neutral-900 dark:text-white">Omnichannel History</h3>
                                <div className="space-y-4 relative before:absolute before:inset-0 before:left-3.5 before:w-0.5 before:bg-neutral-200 dark:before:bg-neutral-800">
                                    {lead.timeline_events?.map((evt) => (
                                        <div key={evt.id} className="relative flex items-start gap-4 pl-8">
                                            <div className="absolute left-2 top-1.5 h-3.5 w-3.5 rounded-full bg-brand-500 ring-4 ring-white dark:ring-neutral-900" />
                                            <div className="flex-1 p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200/60 dark:border-neutral-700/60">
                                                <div className="flex items-center justify-between text-xs text-neutral-400 mb-1">
                                                    <span className="font-semibold text-neutral-700 dark:text-neutral-300 uppercase tracking-wider text-[10px]">
                                                        {evt.event_type}
                                                    </span>
                                                    <span>{evt.occurred_at || evt.created_at}</span>
                                                </div>
                                                <h4 className="font-semibold text-sm text-neutral-900 dark:text-white">
                                                    {evt.title}
                                                </h4>
                                                {evt.description && (
                                                    <p className="text-xs text-neutral-600 dark:text-neutral-400 mt-1">
                                                        {evt.description}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    ))}

                                    {lead.timeline_events?.length === 0 && (
                                        <p className="text-xs text-neutral-400 pl-8">No recorded activity timeline events yet.</p>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Notes Tab */}
                        {activeTab === 'notes' && (
                            <div className="space-y-4">
                                <form onSubmit={handleSaveNote} className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 space-y-3">
                                    <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300">
                                        Add Private Team Note (@mention teammates)
                                    </label>
                                    <textarea
                                        rows={3}
                                        value={noteContent}
                                        onChange={(e) => setNoteContent(e.target.value)}
                                        placeholder="Internal context, buyer objections, notes... Type @name to mention."
                                        className="w-full p-3 text-sm rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white focus:ring-2 focus:ring-brand-500"
                                    />
                                    <div className="flex justify-end">
                                        <button
                                            type="submit"
                                            disabled={isSubmittingNote || !noteContent.trim()}
                                            className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-lg text-white bg-brand-600 hover:bg-brand-700 disabled:opacity-50 transition"
                                        >
                                            <Send className="h-3.5 w-3.5" />
                                            Post Note
                                        </button>
                                    </div>
                                </form>

                                <div className="space-y-3">
                                    {lead.crm_notes?.map((note) => (
                                        <div key={note.id} className="p-4 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 space-y-2">
                                            <div className="flex items-center justify-between text-xs text-neutral-400">
                                                <span className="font-semibold text-neutral-800 dark:text-neutral-200">{note.user?.name || 'Teammate'}</span>
                                                <span>{note.created_at}</span>
                                            </div>
                                            <p className="text-sm text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap">{note.content}</p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Tasks Tab */}
                        {activeTab === 'tasks' && (
                            <div className="space-y-4">
                                <div className="flex justify-between items-center">
                                    <h3 className="font-bold text-sm text-neutral-900 dark:text-white">Scheduled Follow-ups</h3>
                                    <button
                                        onClick={() => setShowNewTaskModal(true)}
                                        className="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg bg-brand-50 dark:bg-brand-950 text-brand-600 dark:text-brand-400 hover:bg-brand-100"
                                    >
                                        <Plus className="h-3.5 w-3.5" />
                                        Add Task
                                    </button>
                                </div>

                                <div className="space-y-2.5">
                                    {lead.crm_tasks?.map((task) => (
                                        <div key={task.id} className="p-3.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 flex items-center justify-between gap-4">
                                            <div className="flex items-center gap-3">
                                                <input
                                                    type="checkbox"
                                                    checked={task.status === 'completed'}
                                                    onChange={() => handleToggleTaskStatus(task)}
                                                    className="h-4 w-4 rounded text-brand-600 focus:ring-brand-500"
                                                />
                                                <div>
                                                    <p className={`text-sm font-semibold ${task.status === 'completed' ? 'line-through text-neutral-400' : 'text-neutral-900 dark:text-white'}`}>
                                                        {task.title}
                                                    </p>
                                                    {task.due_at && (
                                                        <span className="text-xs text-neutral-400 flex items-center gap-1 mt-0.5">
                                                            <Calendar className="h-3 w-3" />
                                                            Due: {task.due_at}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>

                                            <span className={`px-2 py-0.5 text-[10px] font-bold uppercase rounded ${
                                                task.priority === 'urgent' ? 'bg-red-100 text-red-700' : 'bg-neutral-100 text-neutral-600'
                                            }`}>
                                                {task.priority}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Right Col: Deals & Qualification Details */}
                    <div className="space-y-6">
                        {/* Stage & Pipeline Status */}
                        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 space-y-4">
                            <h3 className="font-bold text-sm text-neutral-900 dark:text-white">Pipeline Stage</h3>
                            <select
                                value={lead.stage_id || ''}
                                onChange={(e) => router.post(route('client.crm.leads.stage', lead.uuid), { stage_id: e.target.value })}
                                className="w-full p-2.5 text-sm font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white"
                            >
                                {pipelines?.[0]?.stages?.map((s) => (
                                    <option key={s.id} value={s.id}>{s.name} ({s.probability}%)</option>
                                ))}
                            </select>

                            <div className="pt-2 border-t border-neutral-100 dark:border-neutral-800 text-xs space-y-2">
                                <div className="flex justify-between text-neutral-500">
                                    <span>Total Deal Value:</span>
                                    <span className="font-bold text-neutral-900 dark:text-white">{formatCurrency(lead.deal_value)}</span>
                                </div>
                                <div className="flex justify-between text-neutral-500">
                                    <span>Owner:</span>
                                    <span className="font-semibold text-neutral-800 dark:text-neutral-200">{lead.assigned_user?.name || 'Unassigned'}</span>
                                </div>
                            </div>
                        </div>

                        {/* Deals Manager */}
                        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 space-y-3">
                            <div className="flex items-center justify-between">
                                <h3 className="font-bold text-sm text-neutral-900 dark:text-white">Deals ({lead.deals?.length || 0})</h3>
                                <button
                                    onClick={() => setShowNewDealModal(true)}
                                    className="p-1 rounded-md text-brand-600 hover:bg-brand-50"
                                >
                                    <Plus className="h-4 w-4" />
                                </button>
                            </div>

                            <div className="space-y-2">
                                {lead.deals?.map((d) => (
                                    <div key={d.id} className="p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200/60 dark:border-neutral-700/60 text-xs">
                                        <div className="flex justify-between font-semibold text-neutral-900 dark:text-white">
                                            <span>{d.name}</span>
                                            <span>{formatCurrency(d.value)}</span>
                                        </div>
                                        <div className="flex justify-between text-neutral-400 mt-1">
                                            <span>Status: {d.status}</span>
                                            <span>{d.probability}% prob</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Deal Modal */}
            {showNewDealModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white dark:bg-neutral-900 p-6 shadow-2xl border border-neutral-200 dark:border-neutral-800">
                        <h3 className="font-bold text-base mb-4 text-neutral-900 dark:text-white">Add Deal</h3>
                        <form onSubmit={handleCreateDeal} className="space-y-3 text-sm">
                            <div>
                                <label className="block text-xs font-semibold mb-1">Deal Title</label>
                                <input
                                    required
                                    type="text"
                                    value={dealData.name}
                                    onChange={(e) => setDealData({ ...dealData, name: e.target.value })}
                                    className="w-full p-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold mb-1">Value (₹)</label>
                                <input
                                    required
                                    type="number"
                                    value={dealData.value}
                                    onChange={(e) => setDealData({ ...dealData, value: e.target.value })}
                                    className="w-full p-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800"
                                />
                            </div>
                            <div className="flex justify-end gap-2 pt-3">
                                <button type="button" onClick={() => setShowNewDealModal(false)} className="px-3 py-1.5 rounded text-neutral-500">Cancel</button>
                                <button type="submit" className="px-4 py-1.5 rounded-lg bg-brand-600 text-white font-semibold">Save Deal</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Task Modal */}
            {showNewTaskModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white dark:bg-neutral-900 p-6 shadow-2xl border border-neutral-200 dark:border-neutral-800">
                        <h3 className="font-bold text-base mb-4 text-neutral-900 dark:text-white">Schedule Task</h3>
                        <form onSubmit={handleCreateTask} className="space-y-3 text-sm">
                            <div>
                                <label className="block text-xs font-semibold mb-1">Task Title</label>
                                <input
                                    required
                                    type="text"
                                    value={taskData.title}
                                    onChange={(e) => setTaskData({ ...taskData, title: e.target.value })}
                                    className="w-full p-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold mb-1">Due Date</label>
                                <input
                                    type="datetime-local"
                                    value={taskData.due_at}
                                    onChange={(e) => setTaskData({ ...taskData, due_at: e.target.value })}
                                    className="w-full p-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800"
                                />
                            </div>
                            <div className="flex justify-end gap-2 pt-3">
                                <button type="button" onClick={() => setShowNewTaskModal(false)} className="px-3 py-1.5 rounded text-neutral-500">Cancel</button>
                                <button type="submit" className="px-4 py-1.5 rounded-lg bg-brand-600 text-white font-semibold">Schedule</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </ClientLayout>
    );
}
