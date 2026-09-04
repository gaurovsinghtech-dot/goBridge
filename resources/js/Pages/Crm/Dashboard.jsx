import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { useTranslation } from 'react-i18next';
import { 
    Plus, Filter, Search, Phone, Mail, MessageSquare, 
    Calendar, Flame, Sparkles, Building2, User, ChevronRight,
    TrendingUp, CheckCircle2, AlertCircle, ArrowRight, DollarSign,
    SlidersHorizontal, Layers
} from 'lucide-react';

export default function CrmDashboard({ board, pipelines, kpis, teamMembers, teams, filters }) {
    const { t } = useTranslation();
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');
    const [selectedUser, setSelectedUser] = useState(filters?.assigned_user_id || '');
    const [selectedBand, setSelectedBand] = useState(filters?.band || '');
    const [dueOnly, setDueOnly] = useState(filters?.due_only || false);
    const [showNewLeadModal, setShowNewLeadModal] = useState(false);
    const [movingLeadId, setMovingLeadId] = useState(null);

    // New Lead Form State
    const [formData, setFormData] = useState({
        first_name: '',
        last_name: '',
        company: '',
        phone_e164: '',
        email: '',
        deal_value: '',
        stage_id: board?.columns?.[0]?.id || '',
        assigned_user_id: '',
        priority: 'medium',
        source: 'manual',
    });

    const handleFilterApply = () => {
        router.get(route('client.crm.dashboard'), {
            pipeline_id: board.pipeline.id,
            search: searchTerm,
            assigned_user_id: selectedUser,
            band: selectedBand,
            due_only: dueOnly ? 1 : 0,
        }, { preserveState: true });
    };

    const handleCreateLead = (e) => {
        e.preventDefault();
        router.post(route('client.crm.leads.store'), formData, {
            onSuccess: () => {
                setShowNewLeadModal(false);
                setFormData({
                    first_name: '',
                    last_name: '',
                    company: '',
                    phone_e164: '',
                    email: '',
                    deal_value: '',
                    stage_id: board?.columns?.[0]?.id || '',
                    assigned_user_id: '',
                    priority: 'medium',
                    source: 'manual',
                });
            },
        });
    };

    const handleQuickMoveStage = (leadUuid, newStageId) => {
        setMovingLeadId(leadUuid);
        router.post(route('client.crm.leads.stage', leadUuid), {
            stage_id: newStageId,
        }, {
            preserveScroll: true,
            onFinish: () => setMovingLeadId(null),
        });
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
            <Head title="CRM & Pipeline Board" />

            <div className="p-6 space-y-6">
                {/* Header & KPI Summary */}
                <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold tracking-tight text-neutral-900 dark:text-white">
                                {board.pipeline?.name || 'CRM Sales Pipeline'}
                            </h1>
                            <span className="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-brand-50 text-brand-700 dark:bg-brand-950/50 dark:text-brand-300 border border-brand-200/50 dark:border-brand-800/50">
                                {board.summary?.total_leads || 0} Leads
                            </span>
                        </div>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
                            Track omnichannel deals, lead scores, and customer journey progression.
                        </p>
                    </div>

                    <div className="flex items-center gap-3 w-full md:w-auto">
                        <Link
                            href={route('client.crm.reports.index')}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium rounded-lg text-neutral-700 dark:text-neutral-200 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition"
                        >
                            <TrendingUp className="h-4 w-4" />
                            Reports
                        </Link>
                        <Link
                            href={route('client.crm.tasks.index')}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium rounded-lg text-neutral-700 dark:text-neutral-200 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition"
                        >
                            <CheckCircle2 className="h-4 w-4" />
                            Tasks
                        </Link>
                        <button
                            onClick={() => setShowNewLeadModal(true)}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-lg text-white bg-brand-600 hover:bg-brand-700 shadow-sm transition"
                        >
                            <Plus className="h-4 w-4" />
                            New Lead
                        </button>
                    </div>
                </div>

                {/* Top Metrics Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="p-4 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 shadow-xs">
                        <span className="text-xs font-medium text-neutral-500 dark:text-neutral-400">Total Pipeline Value</span>
                        <p className="text-xl font-bold text-neutral-900 dark:text-white mt-1">
                            {formatCurrency(kpis?.pipeline_value)}
                        </p>
                    </div>
                    <div className="p-4 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 shadow-xs">
                        <span className="text-xs font-medium text-neutral-500 dark:text-neutral-400">Weighted Forecast</span>
                        <p className="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                            {formatCurrency(kpis?.weighted_value)}
                        </p>
                    </div>
                    <div className="p-4 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 shadow-xs">
                        <span className="text-xs font-medium text-neutral-500 dark:text-neutral-400">Qualified Leads</span>
                        <p className="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                            {kpis?.qualified_leads || 0}
                        </p>
                    </div>
                    <div className="p-4 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 shadow-xs">
                        <span className="text-xs font-medium text-neutral-500 dark:text-neutral-400">Follow-ups Due</span>
                        <p className="text-xl font-bold text-amber-600 dark:text-amber-400 mt-1">
                            {kpis?.follow_ups_due || 0}
                        </p>
                    </div>
                </div>

                {/* Filter & Search Bar */}
                <div className="flex flex-wrap items-center justify-between gap-3 p-3 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 shadow-xs">
                    <div className="flex flex-wrap items-center gap-2 flex-1 min-w-[280px]">
                        <div className="relative flex-1 min-w-[200px] max-w-sm">
                            <Search className="absolute left-3 top-2.5 h-4 w-4 text-neutral-400" />
                            <input
                                type="text"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && handleFilterApply()}
                                placeholder="Search leads by name, phone, company..."
                                className="w-full pl-9 pr-3 py-1.5 text-sm rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white placeholder-neutral-400 focus:ring-2 focus:ring-brand-500 focus:outline-none"
                            />
                        </div>

                        <select
                            value={selectedUser}
                            onChange={(e) => setSelectedUser(e.target.value)}
                            className="text-sm py-1.5 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white"
                        >
                            <option value="">All Owners</option>
                            {teamMembers?.map((m) => (
                                <option key={m.id} value={m.id}>{m.name}</option>
                            ))}
                        </select>

                        <select
                            value={selectedBand}
                            onChange={(e) => setSelectedBand(e.target.value)}
                            className="text-sm py-1.5 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white"
                        >
                            <option value="">All Temperatures</option>
                            <option value="hot">🔥 Hot Leads</option>
                            <option value="warm">⚡ Warm Leads</option>
                            <option value="cold">❄️ Cold Leads</option>
                        </select>

                        <button
                            onClick={() => setDueOnly(!dueOnly)}
                            className={`px-3 py-1.5 text-sm font-medium rounded-lg border transition ${
                                dueOnly 
                                    ? 'bg-amber-500/10 text-amber-600 border-amber-300 dark:border-amber-700' 
                                    : 'border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800'
                            }`}
                        >
                            Follow-up Due
                        </button>
                    </div>

                    <button
                        onClick={handleFilterApply}
                        className="px-3.5 py-1.5 text-sm font-semibold rounded-lg bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:opacity-90 transition"
                    >
                        Filter
                    </button>
                </div>

                {/* Kanban Columns */}
                <div className="flex gap-4 overflow-x-auto pb-4 scrollbar-thin">
                    {board.columns?.map((col, idx) => (
                        <div
                            key={col.id}
                            className="flex-shrink-0 w-80 rounded-xl bg-neutral-100/70 dark:bg-neutral-900/60 border border-neutral-200/70 dark:border-neutral-800 flex flex-col max-h-[calc(100vh-280px)]"
                        >
                            {/* Column Header */}
                            <div className="p-3.5 border-b border-neutral-200/60 dark:border-neutral-800/80 flex items-center justify-between">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className={`h-2.5 w-2.5 rounded-full ${
                                            col.is_won ? 'bg-emerald-500' : col.is_lost ? 'bg-rose-500' : 'bg-brand-500'
                                        }`} />
                                        <h3 className="font-bold text-sm text-neutral-900 dark:text-white">
                                            {col.name}
                                        </h3>
                                        <span className="px-1.5 py-0.5 text-[11px] font-semibold rounded bg-neutral-200/80 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300">
                                            {col.stats.count}
                                        </span>
                                    </div>
                                    <p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5">
                                        {formatCurrency(col.stats.total_value)} • {col.probability}% prob
                                    </p>
                                </div>
                            </div>

                            {/* Cards Scroll Container */}
                            <div className="p-3 space-y-3 overflow-y-auto flex-1">
                                {col.leads?.map((lead) => (
                                    <div
                                        key={lead.id}
                                        className="group p-3.5 rounded-lg bg-white dark:bg-neutral-800 border border-neutral-200/80 dark:border-neutral-700/80 shadow-xs hover:shadow-md transition-all relative"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <Link
                                                href={route('client.crm.leads.show', lead.uuid)}
                                                className="font-semibold text-sm text-neutral-900 dark:text-white hover:text-brand-600 dark:hover:text-brand-400 line-clamp-1"
                                            >
                                                {lead.name}
                                            </Link>

                                            {lead.score >= 70 && (
                                                <span className="flex-shrink-0 inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-bold rounded bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200/50">
                                                    <Flame className="h-3 w-3 fill-amber-500 text-amber-500" />
                                                    {lead.score}
                                                </span>
                                            )}
                                        </div>

                                        {lead.company && (
                                            <div className="flex items-center gap-1 text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                                                <Building2 className="h-3 w-3" />
                                                <span className="truncate">{lead.company}</span>
                                            </div>
                                        )}

                                        <div className="flex items-center justify-between mt-3 pt-2.5 border-t border-neutral-100 dark:border-neutral-700/50 text-xs">
                                            <span className="font-bold text-neutral-900 dark:text-white">
                                                {formatCurrency(lead.deal_value)}
                                            </span>

                                            <div className="flex items-center gap-1.5">
                                                {lead.is_overdue && (
                                                    <span className="text-[10px] font-semibold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/50 px-1.5 py-0.5 rounded">
                                                        Overdue
                                                    </span>
                                                )}
                                                <span className="text-[11px] text-neutral-400 capitalize">
                                                    {lead.source}
                                                </span>
                                            </div>
                                        </div>

                                        {/* Stage Quick Advance Button */}
                                        {idx < board.columns.length - 1 && (
                                            <button
                                                disabled={movingLeadId === lead.uuid}
                                                onClick={() => handleQuickMoveStage(lead.uuid, board.columns[idx + 1].id)}
                                                className="opacity-0 group-hover:opacity-100 transition-opacity absolute right-2 bottom-2 p-1 rounded-md bg-brand-50 dark:bg-brand-950 text-brand-600 hover:bg-brand-100 dark:hover:bg-brand-900"
                                                title={`Advance to ${board.columns[idx + 1].name}`}
                                            >
                                                <ArrowRight className="h-3.5 w-3.5" />
                                            </button>
                                        )}
                                    </div>
                                ))}

                                {col.leads?.length === 0 && (
                                    <div className="py-8 text-center text-xs text-neutral-400">
                                        No leads in this stage
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* New Lead Modal */}
            {showNewLeadModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
                    <div className="w-full max-w-lg rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-2xl p-6">
                        <div className="flex items-center justify-between pb-4 border-b border-neutral-100 dark:border-neutral-800">
                            <h3 className="font-bold text-lg text-neutral-900 dark:text-white">Create New CRM Lead</h3>
                            <button onClick={() => setShowNewLeadModal(false)} className="text-neutral-400 hover:text-neutral-600">✕</button>
                        </div>

                        <form onSubmit={handleCreateLead} className="mt-4 space-y-3.5">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">First Name *</label>
                                    <input
                                        required
                                        type="text"
                                        value={formData.first_name}
                                        onChange={(e) => setFormData({ ...formData, first_name: e.target.value })}
                                        className="w-full px-3 py-1.5 text-sm rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Last Name</label>
                                    <input
                                        type="text"
                                        value={formData.last_name}
                                        onChange={(e) => setFormData({ ...formData, last_name: e.target.value })}
                                        className="w-full px-3 py-1.5 text-sm rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Phone (E.164)</label>
                                    <input
                                        type="text"
                                        value={formData.phone_e164}
                                        onChange={(e) => setFormData({ ...formData, phone_e164: e.target.value })}
                                        placeholder="+919876543210"
                                        className="w-full px-3 py-1.5 text-sm rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Email</label>
                                    <input
                                        type="email"
                                        value={formData.email}
                                        onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                        placeholder="lead@example.com"
                                        className="w-full px-3 py-1.5 text-sm rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Company</label>
                                    <input
                                        type="text"
                                        value={formData.company}
                                        onChange={(e) => setFormData({ ...formData, company: e.target.value })}
                                        className="w-full px-3 py-1.5 text-sm rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Deal Value (₹)</label>
                                    <input
                                        type="number"
                                        value={formData.deal_value}
                                        onChange={(e) => setFormData({ ...formData, deal_value: e.target.value })}
                                        placeholder="50000"
                                        className="w-full px-3 py-1.5 text-sm rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Stage</label>
                                    <select
                                        value={formData.stage_id}
                                        onChange={(e) => setFormData({ ...formData, stage_id: e.target.value })}
                                        className="w-full px-3 py-1.5 text-sm rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white"
                                    >
                                        {board.columns?.map((c) => (
                                            <option key={c.id} value={c.id}>{c.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Source</label>
                                    <select
                                        value={formData.source}
                                        onChange={(e) => setFormData({ ...formData, source: e.target.value })}
                                        className="w-full px-3 py-1.5 text-sm rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white"
                                    >
                                        <option value="manual">Manual</option>
                                        <option value="whatsapp">WhatsApp</option>
                                        <option value="instagram">Instagram</option>
                                        <option value="website">Website</option>
                                        <option value="google_ads">Google Ads</option>
                                        <option value="voice">AI Voice Call</option>
                                    </select>
                                </div>
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-4 border-t border-neutral-100 dark:border-neutral-800">
                                <button
                                    type="button"
                                    onClick={() => setShowNewLeadModal(false)}
                                    className="px-4 py-2 text-sm font-medium rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 text-sm font-semibold rounded-lg text-white bg-brand-600 hover:bg-brand-700"
                                >
                                    Save Lead
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </ClientLayout>
    );
}
