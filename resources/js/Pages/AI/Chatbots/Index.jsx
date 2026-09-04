import { Head, Link, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import EmptyState from '@/Components/EmptyState';
import {
    Plus, Bot, Trash2, Play, Settings, Send, X, BookOpen, Zap,
    MessageSquare, ChevronDown, CheckCircle2, Phone, Mail, Instagram,
    Sparkles, Activity, ShieldAlert, ArrowRight, UserCheck, Copy,
    Pause, ShieldCheck, Clock, Check, AlertTriangle, Layers, Users, ExternalLink
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import axios from 'axios';

export default function ChatbotsIndex({
    chatbots = [],
    knowledgeBases = [],
    teamMembers = [],
    connectedChannels = {},
    templates = {},
    stats = {},
}) {
    const { t } = useTranslation();
    const [activeTab, setActiveTab] = useState('all'); // all, published, testing, draft, paused
    const [searchQuery, setSearchQuery] = useState('');
    const [testingAgent, setTestingAgent] = useState(null);

    // Quick Action Handlers
    const handlePublish = async (agent) => {
        try {
            const res = await axios.post(route('client.ai-agents.publish', agent.uuid));
            toast.success(res.data?.message || 'Agent published.');
            router.reload({ preserveScroll: true });
        } catch (e) {
            toast.error(e.response?.data?.message || 'Failed to publish agent. Please ensure name, channels, and instructions are configured.');
        }
    };

    const handlePause = async (agent) => {
        try {
            const res = await axios.post(route('client.ai-agents.pause', agent.uuid));
            toast.success(res.data?.message || 'Agent status updated.');
            router.reload({ preserveScroll: true });
        } catch (e) {
            toast.error('Failed to update agent status.');
        }
    };

    const handleDuplicate = async (agent) => {
        try {
            const res = await axios.post(route('client.ai-agents.duplicate', agent.uuid));
            toast.success(res.data?.message || 'Agent duplicated.');
            router.reload({ preserveScroll: true });
        } catch (e) {
            toast.error('Failed to duplicate agent.');
        }
    };

    const handleDelete = async (agent) => {
        if (!confirm(`Are you sure you want to delete AI Agent "${agent.name}"?`)) return;
        try {
            await axios.delete(route('client.ai-agents.destroy', agent.uuid));
            toast.success('AI Agent deleted.');
            router.reload({ preserveScroll: true });
        } catch (e) {
            toast.error('Failed to delete agent.');
        }
    };

    // Filter Agents
    const filteredAgents = chatbots.filter(agent => {
        if (activeTab === 'published') return agent.status === 'published' || agent.status === 'active';
        if (activeTab === 'testing') return agent.status === 'testing';
        if (activeTab === 'draft') return agent.status === 'draft';
        if (activeTab === 'paused') return agent.status === 'paused';
        return true;
    }).filter(agent => {
        if (!searchQuery) return true;
        const q = searchQuery.toLowerCase();
        return (agent.name && agent.name.toLowerCase().includes(q)) ||
               (agent.agent_type && agent.agent_type.toLowerCase().includes(q)) ||
               (agent.purpose && agent.purpose.toLowerCase().includes(q)) ||
               (agent.description && agent.description.toLowerCase().includes(q));
    });

    const getStatusBadge = (status) => {
        switch (status) {
            case 'published':
            case 'active':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-500/20">
                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Published
                    </span>
                );
            case 'testing':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-500/20">
                        <span className="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> Testing
                    </span>
                );
            case 'paused':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                        <span className="w-1.5 h-1.5 rounded-full bg-neutral-400"></span> Paused
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 border border-blue-500/20">
                        <span className="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Draft
                    </span>
                );
        }
    };

    const getChannelIcon = (ch) => {
        switch (ch) {
            case 'whatsapp': return <span title="WhatsApp" className="p-1 rounded-md bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 text-[10px] font-bold flex items-center gap-1"><MessageSquare className="w-3 h-3" /> WhatsApp</span>;
            case 'voice': return <span title="AI Voice" className="p-1 rounded-md bg-blue-50 text-blue-600 dark:bg-blue-950/40 text-[10px] font-bold flex items-center gap-1"><Phone className="w-3 h-3" /> AI Voice</span>;
            case 'instagram': return <span title="Instagram" className="p-1 rounded-md bg-pink-50 text-pink-600 dark:bg-pink-950/40 text-[10px] font-bold flex items-center gap-1"><Instagram className="w-3 h-3" /> Instagram</span>;
            case 'messenger': return <span title="Messenger" className="p-1 rounded-md bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 text-[10px] font-bold flex items-center gap-1"><Zap className="w-3 h-3" /> Messenger</span>;
            case 'email': return <span title="Email" className="p-1 rounded-md bg-amber-50 text-amber-600 dark:bg-amber-950/40 text-[10px] font-bold flex items-center gap-1"><Mail className="w-3 h-3" /> Email</span>;
            default: return <span className="p-1 rounded-md bg-neutral-100 text-neutral-600 text-[10px] uppercase font-bold">{ch}</span>;
        }
    };

    return (
        <ClientLayout>
            <Head title="AI Agents & Prompt Studio — Growbridge Connect" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="p-2 rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400">
                                <Bot className="w-5 h-5" />
                            </span>
                            <h1 className="text-xl font-bold text-neutral-900 dark:text-white">AI Agents Studio</h1>
                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                <ShieldCheck className="w-3.5 h-3.5" /> No-Code Training
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-neutral-500">
                            Create, configure, train, test, and deploy intelligent AI agents across WhatsApp, Voice, Messenger, Instagram, and Email.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link
                            href={route('client.ai-agents.create')}
                            className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 text-white text-xs font-bold hover:bg-brand-700 shadow-sm transition"
                        >
                            <Plus className="w-4 h-4" /> Create AI Agent
                        </Link>
                    </div>
                </div>

                {/* KPI Metrics */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <div className="p-3.5 rounded-2xl bg-white dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60">
                        <div className="flex items-center gap-2 text-neutral-500 text-xs mb-1">
                            <Bot className="w-4 h-4 text-brand-500" /> Total Agents
                        </div>
                        <div className="text-xl font-bold text-neutral-900 dark:text-white">
                            {stats.total_agents || 0}
                        </div>
                        <div className="text-[11px] text-neutral-400 mt-0.5">Configured Bots</div>
                    </div>

                    <div className="p-3.5 rounded-2xl bg-white dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60">
                        <div className="flex items-center gap-2 text-neutral-500 text-xs mb-1">
                            <CheckCircle2 className="w-4 h-4 text-emerald-500" /> Published (Active)
                        </div>
                        <div className="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                            {stats.published_count || 0}
                        </div>
                        <div className="text-[11px] text-emerald-600 font-semibold mt-0.5">Live on Channels</div>
                    </div>

                    <div className="p-3.5 rounded-2xl bg-white dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60">
                        <div className="flex items-center gap-2 text-neutral-500 text-xs mb-1">
                            <Activity className="w-4 h-4 text-amber-500" /> Draft / Testing
                        </div>
                        <div className="text-xl font-bold text-amber-600 dark:text-amber-400">
                            {(stats.testing_count || 0) + (stats.draft_count || 0)}
                        </div>
                        <div className="text-[11px] text-neutral-400 mt-0.5">In Development</div>
                    </div>

                    <div className="p-3.5 rounded-2xl bg-white dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60">
                        <div className="flex items-center gap-2 text-neutral-500 text-xs mb-1">
                            <MessageSquare className="w-4 h-4 text-indigo-500" /> Conversations
                        </div>
                        <div className="text-xl font-bold text-neutral-900 dark:text-white">
                            {stats.total_conversations?.toLocaleString() || 0}
                        </div>
                        <div className="text-[11px] text-indigo-600 font-semibold mt-0.5">{stats.avg_resolution_rate}% Resolution</div>
                    </div>

                    <div className="p-3.5 rounded-2xl bg-white dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60 col-span-2 md:col-span-1">
                        <div className="flex items-center gap-2 text-neutral-500 text-xs mb-1">
                            <Sparkles className="w-4 h-4 text-purple-500" /> Leads Generated
                        </div>
                        <div className="text-xl font-bold text-purple-600 dark:text-purple-400">
                            {stats.leads_generated || 0}
                        </div>
                        <div className="text-[11px] text-purple-600 font-semibold mt-0.5">Auto-qualified CRM</div>
                    </div>
                </div>

                {/* Filter Tabs & Search */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-200 dark:border-neutral-800 pb-2">
                    <div className="flex items-center gap-1.5 overflow-x-auto text-xs pb-1 sm:pb-0">
                        {[
                            { id: 'all', label: 'All Agents', count: stats.total_agents },
                            { id: 'published', label: '● Published', count: stats.published_count },
                            { id: 'testing', label: '● Testing', count: stats.testing_count },
                            { id: 'draft', label: '○ Draft', count: stats.draft_count },
                            { id: 'paused', label: '⏸ Paused', count: stats.paused_count },
                        ].map(tab => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id)}
                                className={`px-3 py-1.5 rounded-xl font-semibold whitespace-nowrap transition flex items-center gap-1.5 ${
                                    activeTab === tab.id
                                        ? 'bg-brand-600 text-white shadow-xs'
                                        : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                                }`}
                            >
                                <span>{tab.label}</span>
                                {tab.count !== undefined && tab.count > 0 && (
                                    <span className={`px-1.5 py-0.2 rounded-full text-[10px] ${
                                        activeTab === tab.id ? 'bg-white/20 text-white' : 'bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300'
                                    }`}>
                                        {tab.count}
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>

                    <div className="relative w-full sm:w-64">
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={e => setSearchQuery(e.target.value)}
                            placeholder="Search AI agents..."
                            className="w-full pl-3 pr-3 py-1.5 text-xs bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl focus:outline-none"
                        />
                    </div>
                </div>

                {/* AI Agents Grid / Cards */}
                {filteredAgents.length === 0 ? (
                    <div className="p-12 text-center bg-white dark:bg-neutral-800/40 rounded-2xl border border-dashed border-neutral-300 dark:border-neutral-700 space-y-3">
                        <Bot className="w-10 h-10 text-neutral-400 mx-auto" />
                        <h3 className="text-sm font-bold text-neutral-800 dark:text-neutral-200">No AI Agents found</h3>
                        <p className="text-xs text-neutral-500 max-w-sm mx-auto">
                            Get started by creating a Sales, Support, Lead Qualification, or Appointment Booking AI agent in minutes.
                        </p>
                        <Link
                            href={route('client.ai-agents.create')}
                            className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 text-white text-xs font-bold hover:bg-brand-700 shadow-sm"
                        >
                            <Plus className="w-4 h-4" /> Create First AI Agent
                        </Link>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {filteredAgents.map(agent => (
                            <div
                                key={agent.id}
                                className="p-4 rounded-2xl bg-white dark:bg-neutral-800/70 border border-neutral-200 dark:border-neutral-700/60 shadow-2xs hover:border-brand-500/40 transition flex flex-col justify-between space-y-4"
                            >
                                <div className="space-y-2.5">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <div className="w-8 h-8 rounded-xl bg-brand-50 dark:bg-brand-950/50 text-brand-600 dark:text-brand-400 flex items-center justify-center font-bold text-xs">
                                                🤖
                                            </div>
                                            <div>
                                                <h3 className="text-sm font-bold text-neutral-900 dark:text-white line-clamp-1" title={agent.name}>
                                                    {agent.name}
                                                </h3>
                                                <div className="flex items-center gap-1.5 text-[11px] text-neutral-400 capitalize">
                                                    <span>{agent.agent_type?.replace('_', ' ') || 'Custom Agent'}</span>
                                                    <span>•</span>
                                                    <span className="font-mono font-semibold text-neutral-500">v{agent.version || 1}</span>
                                                </div>
                                            </div>
                                        </div>
                                        {getStatusBadge(agent.status)}
                                    </div>

                                    <p className="text-xs text-neutral-600 dark:text-neutral-400 line-clamp-2 min-h-[32px]">
                                        {agent.description || agent.purpose || 'Autonomous AI agent answering inquiries and qualifying leads.'}
                                    </p>

                                    {/* Channels Badges */}
                                    <div className="space-y-1">
                                        <span className="text-[10px] font-bold text-neutral-400 uppercase tracking-wider">Channels</span>
                                        <div className="flex flex-wrap gap-1">
                                            {agent.channels && agent.channels.length > 0 ? (
                                                agent.channels.map(ch => (
                                                    <span key={ch}>{getChannelIcon(ch)}</span>
                                                ))
                                            ) : (
                                                <span className="text-[11px] text-neutral-400 italic">No channels connected</span>
                                            )}
                                        </div>
                                    </div>

                                    {/* Knowledge Connection */}
                                    <div className="flex items-center justify-between text-xs pt-1 border-t border-neutral-100 dark:border-neutral-800 text-neutral-500">
                                        <span className="flex items-center gap-1 text-[11px]">
                                            <BookOpen className="w-3.5 h-3.5 text-brand-500" />
                                            {agent.knowledge_base?.name || 'Workspace Knowledge Base'}
                                        </span>
                                        {agent.strict_knowledge_mode && (
                                            <span className="px-1.5 py-0.2 rounded text-[10px] bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 font-semibold">
                                                Strict RAG
                                            </span>
                                        )}
                                    </div>
                                </div>

                                {/* Actions Bar */}
                                <div className="pt-3 border-t border-neutral-100 dark:border-neutral-800 flex items-center justify-between gap-1">
                                    <div className="flex items-center gap-1">
                                        <Link
                                            href={route('client.ai-agents.show', agent.uuid)}
                                            className="px-3 py-1.5 rounded-xl bg-neutral-100 dark:bg-neutral-700/80 text-neutral-800 dark:text-neutral-200 font-bold text-xs hover:bg-neutral-200 transition"
                                        >
                                            Configure
                                        </Link>
                                        <Link
                                            href={`${route('client.ai-agents.show', agent.uuid)}?tab=testing`}
                                            className="px-2.5 py-1.5 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 font-bold text-xs hover:bg-purple-100 transition flex items-center gap-1"
                                        >
                                            <Play className="w-3 h-3" /> Test
                                        </Link>
                                    </div>

                                    <div className="flex items-center gap-1">
                                        {agent.status === 'published' || agent.status === 'active' ? (
                                            <button
                                                onClick={() => handlePause(agent)}
                                                className="p-1.5 rounded-lg text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-700"
                                                title="Pause Agent"
                                            >
                                                <Pause className="w-3.5 h-3.5" />
                                            </button>
                                        ) : (
                                            <button
                                                onClick={() => handlePublish(agent)}
                                                className="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/30"
                                                title="Publish Agent"
                                            >
                                                <Check className="w-3.5 h-3.5" />
                                            </button>
                                        )}
                                        <button
                                            onClick={() => handleDuplicate(agent)}
                                            className="p-1.5 rounded-lg text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-700"
                                            title="Duplicate Agent"
                                        >
                                            <Copy className="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            onClick={() => handleDelete(agent)}
                                            className="p-1.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30"
                                            title="Delete Agent"
                                        >
                                            <Trash2 className="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </ClientLayout>
    );
}
