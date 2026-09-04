import { Head, Link, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import LineChart from '@/Components/Charts/LineChart';
import BarChart from '@/Components/Charts/BarChart';
import {
    BarChart3, Bot, Sparkles, HelpCircle, ArrowUpRight, ArrowDownRight,
    Clock, CheckCircle2, AlertCircle, MessageSquare, Phone, Mail,
    Zap, ThumbsUp, ThumbsDown, Database, Filter, Calendar, ExternalLink,
    ChevronRight, Check, RefreshCw, AlertTriangle
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';

export default function AnalyticsIndex({
    overview = {},
    timeseries = [],
    channelPerformance = [],
    handoffs = {},
    feedback = {},
    knowledge = [],
    failedQuestions = [],
    agentsComparison = [],
    usage = {},
    agents = [],
    filters = {},
}) {
    const { t } = useTranslation();
    const [period, setPeriod] = useState(filters.period || '30d');
    const [selectedAgent, setSelectedAgent] = useState(filters.agent_id || '');
    const [selectedChannel, setSelectedChannel] = useState(filters.channel || 'all');

    const handleFilterChange = (newPeriod, newAgent, newChannel) => {
        router.get(route('client.ai.analytics.index'), {
            period: newPeriod ?? period,
            agent_id: newAgent !== undefined ? newAgent : selectedAgent,
            channel: newChannel ?? selectedChannel,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePeriodChange = (p) => {
        setPeriod(p);
        handleFilterChange(p, selectedAgent, selectedChannel);
    };

    const handleAgentChange = (e) => {
        const val = e.target.value;
        setSelectedAgent(val);
        handleFilterChange(period, val ? val : null, selectedChannel);
    };

    const handleChannelChange = (e) => {
        const val = e.target.value;
        setSelectedChannel(val);
        handleFilterChange(period, selectedAgent, val);
    };

    const handleResolveQuestion = (qId) => {
        router.post(route('client.ai.analytics.question.resolve', qId), {}, {
            onSuccess: () => toast.success('Question marked as resolved.'),
        });
    };

    return (
        <ClientLayout>
            <Head title="AI Agent Analytics & Usage — Growbridge Connect" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* ─── Header & Global Filters ────────────────────────────────── */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <div className="h-10 w-10 rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400 flex items-center justify-center">
                                <BarChart3 className="w-5 h-5" />
                            </div>
                            <div>
                                <h1 className="text-xl font-bold text-neutral-900 dark:text-white">AI Agent Analytics & Usage</h1>
                                <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                    Comprehensive insights into AI resolution rates, human handoff triggers, channel performance, and knowledge gaps.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Filter Controls */}
                    <div className="flex flex-wrap items-center gap-2 text-xs">
                        {/* Period Selector */}
                        <div className="flex items-center bg-neutral-100 dark:bg-neutral-800 p-1 rounded-xl font-semibold">
                            {[
                                { id: 'today', label: 'Today' },
                                { id: '7d', label: '7 Days' },
                                { id: '30d', label: '30 Days' },
                                { id: '90d', label: '90 Days' },
                            ].map(({ id, label }) => (
                                <button
                                    key={id}
                                    onClick={() => handlePeriodChange(id)}
                                    className={`px-3 py-1 rounded-lg transition ${
                                        period === id
                                            ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-xs'
                                            : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'
                                    }`}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>

                        {/* Agent Selector */}
                        <select
                            value={selectedAgent}
                            onChange={handleAgentChange}
                            className="text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-1.5 text-neutral-800 dark:text-neutral-200 focus:outline-none"
                        >
                            <option value="">All AI Agents</option>
                            {agents.map(a => (
                                <option key={a.id} value={a.id}>{a.name}</option>
                            ))}
                        </select>

                        {/* Channel Selector */}
                        <select
                            value={selectedChannel}
                            onChange={handleChannelChange}
                            className="text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-1.5 text-neutral-800 dark:text-neutral-200 focus:outline-none"
                        >
                            <option value="all">All Channels</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="instagram">Instagram</option>
                            <option value="messenger">Messenger</option>
                            <option value="email">Email</option>
                            <option value="phone">Phone / Voice</option>
                        </select>
                    </div>
                </div>

                {/* ─── 6 Primary KPI Summary Cards ────────────────────────────── */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5">
                    <div className="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                        <span className="text-[11px] font-bold text-neutral-500">AI Conversations</span>
                        <div className="text-xl font-extrabold text-neutral-900 dark:text-white mt-1">
                            {overview.total_conversations?.toLocaleString() || 0}
                        </div>
                        <span className="text-[10px] text-neutral-400">Handled by AI Agent</span>
                    </div>

                    <div className="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                        <span className="text-[11px] font-bold text-neutral-500">AI Resolved</span>
                        <div className="text-xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">
                            {overview.ai_resolved?.toLocaleString() || 0}
                        </div>
                        <span className="text-[10px] text-neutral-400">Zero human intervention</span>
                    </div>

                    <div className="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                        <span className="text-[11px] font-bold text-neutral-500">Human Handoffs</span>
                        <div className="text-xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">
                            {overview.human_handoffs?.toLocaleString() || 0}
                        </div>
                        <span className="text-[10px] text-neutral-400">Transferred to agent</span>
                    </div>

                    <div className="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                        <span className="text-[11px] font-bold text-neutral-500">AI Resolution Rate</span>
                        <div className="text-xl font-extrabold text-brand-600 dark:text-brand-400 mt-1">
                            {overview.resolution_rate || 0}%
                        </div>
                        <span className="text-[10px] text-neutral-400">Resolved ÷ Total</span>
                    </div>

                    <div className="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                        <span className="text-[11px] font-bold text-neutral-500">Avg Response Time</span>
                        <div className="text-xl font-extrabold text-neutral-900 dark:text-white mt-1">
                            {overview.avg_response_sec || 1.4}s
                        </div>
                        <span className="text-[10px] text-neutral-400">Speed per reply</span>
                    </div>

                    <div className="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                        <span className="text-[11px] font-bold text-neutral-500">AI Messages</span>
                        <div className="text-xl font-extrabold text-neutral-900 dark:text-white mt-1">
                            {overview.ai_messages?.toLocaleString() || 0}
                        </div>
                        <span className="text-[10px] text-neutral-400">Messages sent</span>
                    </div>
                </div>

                {/* ─── Trajectory Chart & Resolution Breakdown ─────────────────── */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Conversations Timeseries Chart (2 cols) */}
                    <div className="lg:col-span-2 bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">AI Conversations & Resolution Trend</h3>
                                <p className="text-xs text-neutral-400">Daily volume of AI handled vs. resolved conversations</p>
                            </div>
                        </div>

                        <div className="h-64 pt-2">
                            <LineChart
                                data={timeseries}
                                xKey="date"
                                yKeys={['conversations', 'resolved', 'handoffs']}
                                labels={{ conversations: 'Total AI', resolved: 'Resolved', handoffs: 'Handoffs' }}
                                height={240}
                            />
                        </div>
                    </div>

                    {/* AI Resolution & Handoff Summary (1 col) */}
                    <div className="bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4 flex flex-col justify-between">
                        <div>
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">AI Resolution Breakdown</h3>
                            <p className="text-xs text-neutral-400">Performance outcome distribution</p>

                            <div className="space-y-3 pt-4">
                                <div>
                                    <div className="flex items-center justify-between text-xs mb-1">
                                        <span className="font-semibold text-neutral-700 dark:text-neutral-300">AI Resolved ({overview.resolution_rate}%)</span>
                                        <span className="font-bold text-emerald-600">{overview.ai_resolved || 0}</span>
                                    </div>
                                    <div className="w-full bg-neutral-100 dark:bg-neutral-800 h-2 rounded-full overflow-hidden">
                                        <div className="bg-emerald-500 h-full rounded-full" style={{ width: `${overview.resolution_rate || 0}%` }} />
                                    </div>
                                </div>

                                <div>
                                    <div className="flex items-center justify-between text-xs mb-1">
                                        <span className="font-semibold text-neutral-700 dark:text-neutral-300">Human Handoff ({overview.handoff_rate}%)</span>
                                        <span className="font-bold text-amber-600">{overview.human_handoffs || 0}</span>
                                    </div>
                                    <div className="w-full bg-neutral-100 dark:bg-neutral-800 h-2 rounded-full overflow-hidden">
                                        <div className="bg-amber-500 h-full rounded-full" style={{ width: `${overview.handoff_rate || 0}%` }} />
                                    </div>
                                </div>

                                <div>
                                    <div className="flex items-center justify-between text-xs mb-1">
                                        <span className="font-semibold text-neutral-700 dark:text-neutral-300">Unresolved / In-Progress</span>
                                        <span className="font-bold text-neutral-500">{overview.unresolved || 0}</span>
                                    </div>
                                    <div className="w-full bg-neutral-100 dark:bg-neutral-800 h-2 rounded-full overflow-hidden">
                                        <div className="bg-neutral-400 h-full rounded-full" style={{ width: `${overview.total_conversations > 0 ? (overview.unresolved / overview.total_conversations) * 100 : 0}%` }} />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* User Feedback Box */}
                        <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700/60 text-xs">
                            <div className="flex items-center justify-between mb-1.5">
                                <span className="font-bold text-neutral-800 dark:text-neutral-200">AI Answer Feedback</span>
                                <span className="font-bold text-brand-600">{feedback.helpful_rate || 91.9}% Helpful</span>
                            </div>
                            <div className="flex items-center gap-4 text-neutral-500 text-[11px]">
                                <span className="flex items-center gap-1"><ThumbsUp className="w-3 h-3 text-emerald-500" /> {feedback.helpful || 0} Helpful</span>
                                <span className="flex items-center gap-1"><ThumbsDown className="w-3 h-3 text-red-500" /> {feedback.not_helpful || 0} Not Helpful</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* ─── Channel Performance & Handoff Reasons ───────────────────── */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Channel Performance Table */}
                    <div className="bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
                        <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Channel Performance</h3>

                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left">
                                <thead className="border-b border-neutral-200 dark:border-neutral-800 text-neutral-400 font-bold">
                                    <tr>
                                        <th className="pb-2.5">Channel</th>
                                        <th className="pb-2.5">Conversations</th>
                                        <th className="pb-2.5">Resolution Rate</th>
                                        <th className="pb-2.5">Handoffs</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800/60">
                                    {channelPerformance.map(ch => (
                                        <tr key={ch.channel} className="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/30 transition">
                                            <td className="py-3 font-bold text-neutral-800 dark:text-neutral-200 capitalize">
                                                {ch.name}
                                            </td>
                                            <td className="py-3 text-neutral-600 dark:text-neutral-300">
                                                {ch.conversations}
                                            </td>
                                            <td className="py-3">
                                                <span className="font-bold text-emerald-600 dark:text-emerald-400">{ch.resolution_rate}%</span>
                                            </td>
                                            <td className="py-3 text-neutral-500">
                                                {ch.handoffs} ({ch.handoff_rate}%)
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Human Handoff Reasons Breakdown */}
                    <div className="bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Human Handoff Analytics</h3>
                                <p className="text-xs text-neutral-400">Total Handoffs: {handoffs.total_handoffs || 0}</p>
                            </div>
                        </div>

                        <div className="space-y-2.5 pt-1">
                            {(handoffs.breakdown || []).map((item, idx) => (
                                <div key={idx} className="space-y-1 text-xs">
                                    <div className="flex items-center justify-between">
                                        <span className="text-neutral-700 dark:text-neutral-300 font-medium">{item.reason}</span>
                                        <span className="font-bold text-neutral-900 dark:text-white">{item.count} ({item.percentage}%)</span>
                                    </div>
                                    <div className="w-full bg-neutral-100 dark:bg-neutral-800 h-1.5 rounded-full overflow-hidden">
                                        <div className="bg-brand-500 h-full rounded-full" style={{ width: `${item.percentage}%` }} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* ─── Questions AI Couldn't Answer (Knowledge Improvement) ───── */}
                <div className="bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <AlertTriangle className="w-4 h-4 text-amber-500" />
                            <div>
                                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Questions AI Couldn't Answer</h3>
                                <p className="text-xs text-neutral-400">Top missing or unverified knowledge queries requested by customers</p>
                            </div>
                        </div>

                        <Link
                            href={route('client.ai.knowledge.index')}
                            className="inline-flex items-center gap-1 px-3.5 py-1.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold transition shadow-xs"
                        >
                            <Database className="w-3.5 h-3.5" /> Improve Knowledge Base
                        </Link>
                    </div>

                    {failedQuestions.length === 0 ? (
                        <div className="py-6 text-center text-xs text-neutral-400 italic">
                            ✓ No unresolved customer questions detected. Your AI Knowledge Base is answering inquiries smoothly!
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            {failedQuestions.map(q => (
                                <div key={q.id} className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700 flex flex-col justify-between">
                                    <div>
                                        <p className="text-xs font-bold text-neutral-900 dark:text-white line-clamp-2">
                                            "{q.question}"
                                        </p>
                                        <div className="flex items-center gap-2 mt-2 text-[10px] text-neutral-400">
                                            <span className="bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 px-1.5 py-0.5 rounded font-semibold">
                                                {q.occurrences} occurrences
                                            </span>
                                            {q.category_suggested && (
                                                <span className="capitalize">{q.category_suggested}</span>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex items-center justify-between pt-3 mt-2 border-t border-neutral-200 dark:border-neutral-700 text-[11px]">
                                        <Link href={route('client.ai.knowledge.index')} className="text-brand-600 dark:text-brand-400 font-bold hover:underline">
                                            + Add Knowledge
                                        </Link>
                                        <button onClick={() => handleResolveQuestion(q.id)} className="text-neutral-400 hover:text-emerald-600">
                                            <Check className="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* ─── AI Usage, Tokens & Cost Section ─────────────────────────── */}
                <div className="bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">AI Consumption & Token Usage</h3>

                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                        <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700">
                            <span className="text-neutral-500 font-medium">AI Requests</span>
                            <div className="text-lg font-bold text-neutral-900 dark:text-white mt-1">
                                {usage.ai_requests?.toLocaleString() || 0}
                            </div>
                        </div>

                        <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700">
                            <span className="text-neutral-500 font-medium">Input Tokens</span>
                            <div className="text-lg font-bold text-neutral-900 dark:text-white mt-1">
                                {usage.input_tokens ? (usage.input_tokens > 1000000 ? `${(usage.input_tokens / 1000000).toFixed(1)}M` : `${Math.round(usage.input_tokens / 1000)}k`) : 0}
                            </div>
                        </div>

                        <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700">
                            <span className="text-neutral-500 font-medium">Output Tokens</span>
                            <div className="text-lg font-bold text-neutral-900 dark:text-white mt-1">
                                {usage.output_tokens ? (usage.output_tokens > 1000000 ? `${(usage.output_tokens / 1000000).toFixed(1)}M` : `${Math.round(usage.output_tokens / 1000)}k`) : 0}
                            </div>
                        </div>

                        <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700">
                            <span className="text-neutral-500 font-medium">Estimated Cost</span>
                            <div className="text-sm font-bold text-neutral-800 dark:text-neutral-200 mt-1.5">
                                {usage.cost_display || 'Cost data unavailable'}
                            </div>
                        </div>
                    </div>
                </div>

                {/* ─── Multi-Agent Comparison Table ───────────────────────────── */}
                {agentsComparison.length > 1 && (
                    <div className="bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
                        <h3 className="text-sm font-bold text-neutral-900 dark:text-white">AI Agent Comparison</h3>

                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left">
                                <thead className="border-b border-neutral-200 dark:border-neutral-800 text-neutral-400 font-bold">
                                    <tr>
                                        <th className="pb-2.5">Agent</th>
                                        <th className="pb-2.5">Type</th>
                                        <th className="pb-2.5">Status</th>
                                        <th className="pb-2.5">Conversations</th>
                                        <th className="pb-2.5">Resolution Rate</th>
                                        <th className="pb-2.5">Handoff Rate</th>
                                        <th className="pb-2.5">Avg Speed</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800/60">
                                    {agentsComparison.map(ag => (
                                        <tr key={ag.id} className="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/30 transition">
                                            <td className="py-3 font-bold text-neutral-900 dark:text-white">{ag.name}</td>
                                            <td className="py-3 text-neutral-500 capitalize">{ag.type}</td>
                                            <td className="py-3">
                                                <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold capitalize ${
                                                    ag.status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-neutral-100 text-neutral-600'
                                                }`}>
                                                    {ag.status}
                                                </span>
                                            </td>
                                            <td className="py-3 font-semibold text-neutral-800 dark:text-neutral-200">{ag.conversations}</td>
                                            <td className="py-3 font-bold text-emerald-600">{ag.resolution_rate}%</td>
                                            <td className="py-3 text-neutral-500">{ag.handoff_rate}%</td>
                                            <td className="py-3 text-neutral-500">{ag.avg_response_sec}s</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </ClientLayout>
    );
}
