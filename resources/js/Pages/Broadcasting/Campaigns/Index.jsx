import { Head, Link, router, usePage } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import EmptyState from '@/Components/EmptyState';
import {
    Plus,
    Play,
    Pause,
    Square,
    Trash2,
    BarChart2,
    Pencil,
    Radio,
    Search,
    Users,
    TrendingUp,
    Zap,
    Clock,
    Megaphone,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { browserTz, formatInTz } from '@/Utils/datetime';
import { ChannelBrandIcon, CHANNEL_LABELS } from '@/Components/BrandIcons';

const STATUS_CONFIG = {
    draft: {
        color: 'bg-neutral-500/10 text-neutral-600 dark:text-neutral-400 border-neutral-500/20',
        dot: 'bg-neutral-400',
        labelKey: 'campaign.status_draft',
        defaultLabel: 'Draft',
    },
    queued: {
        color: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
        dot: 'bg-blue-500 animate-pulse',
        labelKey: 'campaign.status_queued',
        defaultLabel: 'Queued',
    },
    sending: {
        color: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
        dot: 'bg-amber-500 animate-pulse',
        labelKey: 'campaign.status_sending',
        defaultLabel: 'Sending',
    },
    paused: {
        color: 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20',
        dot: 'bg-orange-500',
        labelKey: 'campaign.status_paused',
        defaultLabel: 'Paused',
    },
    completed: {
        color: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
        dot: 'bg-emerald-500',
        labelKey: 'campaign.status_completed',
        defaultLabel: 'Completed',
    },
    failed: {
        color: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
        dot: 'bg-rose-500',
        labelKey: 'campaign.status_failed',
        defaultLabel: 'Failed',
    },
    cancelled: {
        color: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
        dot: 'bg-rose-500',
        labelKey: 'campaign.status_cancelled',
        defaultLabel: 'Cancelled',
    },
    stopped: {
        color: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
        dot: 'bg-rose-500',
        labelKey: 'campaign.status_stopped',
        defaultLabel: 'Stopped',
    },
};

export default function CampaignsIndex({ campaigns, filters }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const flash = props.flash ?? {};
    const userTz = props.timezone || browserTz() || 'Asia/Dhaka';

    const [searchQuery, setSearchQuery] = useState(filters.q || '');

    const handleFilter = (key, val) =>
        router.get(
            route('client.campaigns.index'),
            { ...filters, [key]: val },
            { preserveState: true, replace: true },
        );

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        handleFilter('q', searchQuery.trim() || null);
    };

    const handleLaunch = (id) =>
        router.post(route('client.campaigns.launch', id), {}, { preserveScroll: true });
    const handlePause = (id) =>
        router.post(route('client.campaigns.pause', id), {}, { preserveScroll: true });
    const handleCancel = (id) => {
        if (confirm(t('campaign.stop_confirm', 'Are you sure you want to stop this campaign? Any unsent messages will be cancelled.'))) {
            router.post(route('client.campaigns.cancel', id), {}, { preserveScroll: true });
        }
    };
    const handleDelete = (id) => {
        if (confirm(t('campaign.delete_confirm'))) {
            router.delete(route('client.campaigns.destroy', id), { preserveScroll: true });
        }
    };

    // Auto-refresh while any campaign is actively sending so totals tick up.
    const liveCount = campaigns.data.filter((c) => ['queued', 'sending'].includes(c.status)).length;
    useEffect(() => {
        if (liveCount === 0) return;
        const id = window.setInterval(() => {
            router.reload({ only: ['campaigns'], preserveScroll: true });
        }, 10000);
        return () => window.clearInterval(id);
    }, [liveCount]);

    // Summary Analytics
    const totalRecipients = campaigns.data.reduce((acc, c) => acc + (c.totals_json?.total || 0), 0);
    const totalDelivered = campaigns.data.reduce((acc, c) => acc + (c.totals_json?.delivered || 0), 0);
    const avgDeliveryRate = totalRecipients > 0 ? Math.round((totalDelivered / totalRecipients) * 100) : 0;

    return (
        <ClientLayout title={t('campaign.title', 'Campaigns')}>
            <Head title={t('campaign.head_title', 'Campaigns Overview')} />
            <div className="max-w-7xl mx-auto space-y-6">

                {/* Top Banner & Title Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-neutral-900 dark:text-white flex items-center gap-2.5">
                            {t('campaign.title', 'Campaigns')}
                        </h1>
                        <p className="text-xs sm:text-sm text-neutral-500 dark:text-neutral-400 mt-1">
                            {t('campaign.subtitle', 'Create, schedule, and analyze your multi-channel marketing campaigns.')}
                        </p>
                    </div>

                    <Link
                        href={route('client.campaigns.create')}
                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:from-blue-500 hover:to-indigo-500 active:scale-95 transition-all shrink-0"
                    >
                        <Plus className="h-4 w-4" />
                        <span>{t('campaign.new_campaign', 'New Campaign')}</span>
                    </Link>
                </div>

                {/* Flash Messages */}
                {flash.success && (
                    <div className="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300 font-medium">
                        {flash.success}
                    </div>
                )}

                {/* KPI Overview Cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <div className="rounded-2xl border border-neutral-200/80 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">{t('campaign.kpi_total', 'Total Campaigns')}</span>
                            <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <Megaphone className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 text-2xl font-bold text-neutral-900 dark:text-white">
                            {(campaigns.total ?? campaigns.data.length).toLocaleString()}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-neutral-200/80 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">{t('campaign.kpi_audience', 'Total Audience')}</span>
                            <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400">
                                <Users className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 text-2xl font-bold text-neutral-900 dark:text-white">
                            {totalRecipients.toLocaleString()}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-neutral-200/80 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">{t('campaign.kpi_active', 'Active Executions')}</span>
                            <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                <Zap className="h-4 w-4 animate-pulse" />
                            </div>
                        </div>
                        <div className="mt-3 text-2xl font-bold text-neutral-900 dark:text-white">
                            {liveCount}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-neutral-200/80 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">{t('campaign.kpi_delivered', 'Avg Delivery Rate')}</span>
                            <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                <TrendingUp className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 text-2xl font-bold text-neutral-900 dark:text-white">
                            {avgDeliveryRate}%
                        </div>
                    </div>
                </div>

                {/* Filter Toolbar & Search */}
                <div className="rounded-2xl border border-neutral-200/80 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-4 shadow-sm">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        
                        {/* Channel Filter Pills */}
                        <div className="flex items-center gap-1.5 overflow-x-auto pb-1 md:pb-0 text-xs">
                            <button
                                onClick={() => handleFilter('channel', null)}
                                className={`inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 font-semibold transition ${
                                    !filters.channel
                                        ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 shadow-sm'
                                        : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700'
                                }`}
                            >
                                <span>{t('campaign.all_channels', 'All Channels')}</span>
                            </button>
                            {['whatsapp', 'sms', 'email'].map((c) => (
                                <button
                                    key={c}
                                    onClick={() => handleFilter('channel', c)}
                                    className={`inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 font-semibold transition ${
                                        filters.channel === c
                                            ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 shadow-sm'
                                            : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700'
                                    }`}
                                >
                                    <ChannelBrandIcon channel={c} className="h-3.5 w-3.5" />
                                    <span>{CHANNEL_LABELS[c] ?? c}</span>
                                </button>
                            ))}
                        </div>

                        {/* Search & Status Controls */}
                        <div className="flex flex-wrap items-center gap-2">
                            {/* Search Form */}
                            <form onSubmit={handleSearchSubmit} className="relative flex-1 sm:w-64">
                                <Search className="absolute left-3 top-2.5 h-4 w-4 text-neutral-400" />
                                <input
                                    type="text"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    placeholder={t('campaign.search_placeholder', 'Search campaigns...')}
                                    className="w-full rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50 pl-9 pr-3 py-1.5 text-xs text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                            </form>

                            {/* Status Dropdown */}
                            <select
                                value={filters.status ?? ''}
                                onChange={(e) => handleFilter('status', e.target.value || null)}
                                className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50 px-3 py-1.5 text-xs text-neutral-700 dark:text-neutral-200 focus:border-blue-500 focus:outline-none"
                            >
                                <option value="">{t('campaign.all_statuses', 'All Statuses')}</option>
                                {['draft', 'queued', 'sending', 'paused', 'completed', 'failed', 'cancelled'].map((s) => (
                                    <option key={s} value={s}>
                                        {STATUS_CONFIG[s]?.defaultLabel || s}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                </div>

                {/* Campaigns List Table */}
                <div className="overflow-hidden rounded-2xl border border-neutral-200/80 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs text-left">
                            <thead className="border-b border-neutral-200/80 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-800/50 text-neutral-400 font-semibold uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th className="py-3.5 px-4">{t('campaign.col_campaign', 'Campaign')}</th>
                                    <th className="py-3.5 px-4">{t('campaign.col_channel', 'Channel')}</th>
                                    <th className="py-3.5 px-4">{t('campaign.col_status', 'Status')}</th>
                                    <th className="py-3.5 px-4">{t('campaign.col_recipients', 'Audience')}</th>
                                    <th className="py-3.5 px-4">{t('campaign.col_delivered', 'Delivered')}</th>
                                    <th className="py-3.5 px-4">{t('campaign.col_scheduled', 'Scheduled')}</th>
                                    <th className="py-3.5 px-4 text-right">{t('common.actions', 'Actions')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800/60">
                                {campaigns.data.map((c) => {
                                    const totals = c.totals_json ?? {};
                                    const totalRec = totals.total || 0;
                                    const deliveredCount = totals.delivered || 0;
                                    const deliveredPct = totalRec > 0 ? Math.round((deliveredCount / totalRec) * 100) : 0;
                                    
                                    const live = ['queued', 'sending'].includes(c.status);
                                    const canEdit = ['draft', 'paused'].includes(c.status);
                                    const stCfg = STATUS_CONFIG[c.status] || STATUS_CONFIG.draft;

                                    return (
                                        <tr key={c.id} className="hover:bg-neutral-50/80 dark:hover:bg-neutral-800/40 transition">
                                            {/* Campaign Name */}
                                            <td className="py-3.5 px-4">
                                                <div className="space-y-0.5">
                                                    <Link
                                                        href={route('client.campaigns.show', c.uuid)}
                                                        className="font-bold text-sm text-neutral-900 dark:text-neutral-100 hover:text-blue-600 dark:hover:text-blue-400 transition"
                                                    >
                                                        {c.name}
                                                    </Link>
                                                    <div className="text-[11px] text-neutral-400 flex items-center gap-1 font-mono">
                                                        <span>UUID:</span> {c.uuid.slice(0, 8)}...
                                                    </div>
                                                </div>
                                            </td>

                                            {/* Channel */}
                                            <td className="py-3.5 px-4">
                                                <span className="inline-flex items-center gap-1.5 font-semibold text-neutral-700 dark:text-neutral-300 capitalize">
                                                    <ChannelBrandIcon channel={c.channel} className="h-4 w-4 shrink-0" />
                                                    <span>{CHANNEL_LABELS[c.channel] ?? c.channel}</span>
                                                </span>
                                            </td>

                                            {/* Status Badge */}
                                            <td className="py-3.5 px-4">
                                                <div className="inline-flex items-center gap-2">
                                                    <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold border ${stCfg.color}`}>
                                                        <span className={`h-1.5 w-1.5 rounded-full ${stCfg.dot}`} />
                                                        <span>{stCfg.labelKey && t(stCfg.labelKey) ? t(stCfg.labelKey) : stCfg.defaultLabel}</span>
                                                    </span>
                                                    {live && (
                                                        <span className="inline-flex items-center gap-1 text-[11px] font-bold text-amber-600 dark:text-amber-400">
                                                            <span className="relative flex h-2 w-2">
                                                                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75" />
                                                                <span className="relative inline-flex h-2 w-2 rounded-full bg-amber-500" />
                                                            </span>
                                                            {t('campaign.live', 'Live')}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>

                                            {/* Recipients Count */}
                                            <td className="py-3.5 px-4 font-semibold text-neutral-800 dark:text-neutral-200">
                                                {totalRec.toLocaleString()}
                                            </td>

                                            {/* Delivered Progress */}
                                            <td className="py-3.5 px-4">
                                                <div className="space-y-1.5 w-32">
                                                    <div className="flex justify-between text-[11px]">
                                                        <span className="font-semibold text-neutral-800 dark:text-neutral-200">
                                                            {deliveredCount}
                                                        </span>
                                                        <span className="text-neutral-400 font-mono">
                                                            {deliveredPct}%
                                                        </span>
                                                    </div>
                                                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                                                        <div
                                                            className={`h-full rounded-full transition-all duration-500 ${
                                                                deliveredPct > 0 ? 'bg-emerald-500' : 'bg-neutral-300 dark:bg-neutral-700'
                                                            }`}
                                                            style={{ width: `${deliveredPct}%` }}
                                                        />
                                                    </div>
                                                </div>
                                            </td>

                                            {/* Scheduled Time */}
                                            <td className="py-3.5 px-4 text-neutral-400 font-mono text-[11px]">
                                                {c.schedule_at ? (
                                                    <span className="inline-flex items-center gap-1" title={c.timezone || userTz}>
                                                        <Clock className="h-3 w-3 text-neutral-400" />
                                                        {formatInTz(c.schedule_at, c.timezone || userTz)}
                                                    </span>
                                                ) : (
                                                    <span className="text-neutral-300 dark:text-neutral-700">—</span>
                                                )}
                                            </td>

                                            {/* Quick Actions Toolbar */}
                                            <td className="py-3.5 px-4 text-right">
                                                <div className="inline-flex items-center gap-1 bg-neutral-50 dark:bg-neutral-800/80 p-1 rounded-xl border border-neutral-200/60 dark:border-neutral-700/60">
                                                    <Link
                                                        href={route('client.campaigns.show', c.uuid)}
                                                        title={t('campaign.view_full_report', 'View Analytics')}
                                                        className="p-1.5 rounded-lg text-neutral-500 hover:text-blue-600 hover:bg-white dark:hover:bg-neutral-700 transition"
                                                    >
                                                        <BarChart2 className="h-4 w-4" />
                                                    </Link>

                                                    {canEdit && (
                                                        <Link
                                                            href={route('client.campaigns.edit', c.uuid)}
                                                            title={t('common.edit', 'Edit')}
                                                            className="p-1.5 rounded-lg text-neutral-500 hover:text-blue-600 hover:bg-white dark:hover:bg-neutral-700 transition"
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Link>
                                                    )}

                                                    {c.status === 'draft' && (
                                                        <button
                                                            onClick={() => handleLaunch(c.uuid)}
                                                            title={t('campaign.launch', 'Launch Campaign')}
                                                            className="p-1.5 rounded-lg text-neutral-500 hover:text-emerald-600 hover:bg-white dark:hover:bg-neutral-700 transition"
                                                        >
                                                            <Play className="h-4 w-4 fill-current text-emerald-500" />
                                                        </button>
                                                    )}

                                                    {['queued', 'sending'].includes(c.status) && (
                                                        <button
                                                            onClick={() => handlePause(c.uuid)}
                                                            title={t('campaign.pause', 'Pause Campaign')}
                                                            className="p-1.5 rounded-lg text-amber-500 hover:text-amber-600 hover:bg-white dark:hover:bg-neutral-700 transition"
                                                        >
                                                            <Pause className="h-4 w-4 fill-current" />
                                                        </button>
                                                    )}

                                                    {c.status === 'paused' && (
                                                        <button
                                                            onClick={() => handleLaunch(c.uuid)}
                                                            title={t('campaign.resume', 'Resume Campaign')}
                                                            className="p-1.5 rounded-lg text-emerald-500 hover:text-emerald-600 hover:bg-white dark:hover:bg-neutral-700 transition"
                                                        >
                                                            <Play className="h-4 w-4 fill-current" />
                                                        </button>
                                                    )}

                                                    {['queued', 'sending', 'paused', 'scheduled'].includes(c.status) && (
                                                        <button
                                                            onClick={() => handleCancel(c.uuid)}
                                                            title={t('campaign.stop_campaign', 'Stop Campaign')}
                                                            className="p-1.5 rounded-lg text-rose-500 hover:text-rose-600 hover:bg-white dark:hover:bg-neutral-700 transition"
                                                        >
                                                            <Square className="h-4 w-4 fill-current" />
                                                        </button>
                                                    )}

                                                    {!['queued', 'sending'].includes(c.status) && (
                                                        <button
                                                            onClick={() => handleDelete(c.uuid)}
                                                            title={t('common.delete', 'Delete')}
                                                            className="p-1.5 rounded-lg text-neutral-400 hover:text-rose-600 hover:bg-white dark:hover:bg-neutral-700 transition"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}

                                {campaigns.data.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="py-12">
                                            <EmptyState
                                                icon={<Radio className="h-8 w-8 text-neutral-400" />}
                                                title={t('campaign.empty_title', 'No Campaigns Found')}
                                                description={t('campaign.empty_desc', 'Get started by creating your first broadcast outreach campaign.')}
                                                action={{
                                                    label: t('campaign.new_campaign', 'Create Campaign'),
                                                    href: route('client.campaigns.create'),
                                                }}
                                            />
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Pagination Controls */}
                {campaigns.last_page > 1 && (
                    <div className="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2">
                        <p className="text-xs text-neutral-500 dark:text-neutral-400">
                            Showing <span className="font-semibold text-neutral-900 dark:text-white">{campaigns.from ?? 1}</span> to{' '}
                            <span className="font-semibold text-neutral-900 dark:text-white">{campaigns.to ?? campaigns.data.length}</span> of{' '}
                            <span className="font-semibold text-neutral-900 dark:text-white">{campaigns.total}</span> campaigns
                        </p>
                        <div className="flex items-center gap-1">
                            {campaigns.links.map((link, i) => (
                                <a
                                    key={i}
                                    href={link.url ?? '#'}
                                    className={`px-3 py-1.5 rounded-xl text-xs font-semibold border transition ${
                                        link.active
                                            ? 'bg-blue-600 border-blue-600 text-white shadow-sm'
                                            : 'border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-750'
                                    } ${!link.url ? 'opacity-40 pointer-events-none' : ''}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}

            </div>
        </ClientLayout>
    );
}

