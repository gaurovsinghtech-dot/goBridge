import { Head, Link, router, usePage } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import EmptyState from '@/Components/EmptyState';
import { ChannelBrandIcon } from '@/Components/BrandIcons';
import {
    ArrowLeft,
    Play,
    Pause,
    Square,
    BarChart2,
    Users,
    Pencil,
    Clock,
    ExternalLink,
    Trash2,
    AlertCircle,
    RotateCcw,
    Send,
    CheckCircle2,
    Eye,
    MessageSquare,
    XCircle,
    Search,
    TrendingUp,
    Zap,
    Globe,
    AlertTriangle,
} from 'lucide-react';
import { useEffect, useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { browserTz, formatInTz } from '@/Utils/datetime';

const STATUS_CONFIG = {
    draft: {
        bg: 'bg-neutral-500/10 text-neutral-600 dark:text-neutral-400 border-neutral-500/20',
        badge: 'bg-neutral-500',
        labelKey: 'campaign.status_draft',
        defaultLabel: 'Draft',
    },
    queued: {
        bg: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
        badge: 'bg-blue-500 animate-pulse',
        labelKey: 'campaign.status_queued',
        defaultLabel: 'Queued',
    },
    sending: {
        bg: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
        badge: 'bg-amber-500 animate-pulse',
        labelKey: 'campaign.status_sending',
        defaultLabel: 'Sending',
    },
    paused: {
        bg: 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20',
        badge: 'bg-orange-500',
        labelKey: 'campaign.status_paused',
        defaultLabel: 'Paused',
    },
    completed: {
        bg: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
        badge: 'bg-emerald-500',
        labelKey: 'campaign.status_completed',
        defaultLabel: 'Completed',
    },
    failed: {
        bg: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
        badge: 'bg-rose-500',
        labelKey: 'campaign.status_failed',
        defaultLabel: 'Failed',
    },
    cancelled: {
        bg: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
        badge: 'bg-rose-500',
        labelKey: 'campaign.status_cancelled',
        defaultLabel: 'Cancelled',
    },
    stopped: {
        bg: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
        badge: 'bg-rose-500',
        labelKey: 'campaign.status_stopped',
        defaultLabel: 'Stopped',
    },
};

const RECIPIENT_STATUS_CONFIG = {
    sent: {
        color: 'text-blue-600 dark:text-blue-400 bg-blue-500/10 border-blue-500/20',
        Icon: Send,
        label: 'Sent',
    },
    delivered: {
        color: 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
        Icon: CheckCircle2,
        label: 'Delivered',
    },
    read: {
        color: 'text-purple-600 dark:text-purple-400 bg-purple-500/10 border-purple-500/20',
        Icon: Eye,
        label: 'Read',
    },
    replied: {
        color: 'text-teal-600 dark:text-teal-400 bg-teal-500/10 border-teal-500/20',
        Icon: MessageSquare,
        label: 'Replied',
    },
    failed: {
        color: 'text-rose-600 dark:text-rose-400 bg-rose-500/10 border-rose-500/20',
        Icon: XCircle,
        label: 'Failed',
    },
    queued: {
        color: 'text-amber-600 dark:text-amber-400 bg-amber-500/10 border-amber-500/20',
        Icon: Clock,
        label: 'Queued',
    },
};

function useCountdown(target) {
    const [remaining, setRemaining] = useState(() => calc(target));
    useEffect(() => {
        if (!target) return;
        const id = window.setInterval(() => setRemaining(calc(target)), 1000);
        return () => window.clearInterval(id);
    }, [target]);
    return remaining;
}

function calc(target) {
    if (!target) return null;
    const ms = new Date(target).getTime() - Date.now();
    if (ms <= 0) return null;
    const total = Math.floor(ms / 1000);
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

export default function CampaignShow({ campaign, sample = [], reportUrl }) {
    const { t } = useTranslation();
    const { props } = usePage();
    const userTz = props.timezone || browserTz() || 'Asia/Dhaka';
    const totals = campaign.totals_json ?? {};
    const total = totals.total || 1;

    const [activityFilter, setActivityFilter] = useState('all');
    const [searchQuery, setSearchQuery] = useState('');

    // Auto-refresh while a campaign is actively sending so the user sees live progress.
    useEffect(() => {
        if (!['queued', 'sending'].includes(campaign.status)) return;
        const id = window.setInterval(() => {
            router.reload({ only: ['campaign', 'sample'] });
        }, 8000);
        return () => window.clearInterval(id);
    }, [campaign.status]);

    const countdown = useCountdown(
        campaign.status === 'queued' && campaign.schedule_at ? campaign.schedule_at : null,
    );

    const metrics = [
        {
            key: 'total',
            label: t('campaign.metric_total', 'Total Audience'),
            value: totals.total ?? 0,
            pct: 100,
            Icon: Users,
            color: 'from-blue-500 to-indigo-600',
            textColor: 'text-blue-600 dark:text-blue-400',
            bgColor: 'bg-blue-500/10',
            borderColor: 'border-blue-500/20',
        },
        {
            key: 'sent',
            label: t('campaign.metric_sent', 'Sent'),
            value: totals.sent ?? 0,
            pct: Math.round(((totals.sent ?? 0) / total) * 100),
            Icon: Send,
            color: 'from-sky-500 to-blue-600',
            textColor: 'text-sky-600 dark:text-sky-400',
            bgColor: 'bg-sky-500/10',
            borderColor: 'border-sky-500/20',
        },
        {
            key: 'delivered',
            label: t('campaign.metric_delivered', 'Delivered'),
            value: totals.delivered ?? 0,
            pct: Math.round(((totals.delivered ?? 0) / total) * 100),
            Icon: CheckCircle2,
            color: 'from-emerald-500 to-teal-600',
            textColor: 'text-emerald-600 dark:text-emerald-400',
            bgColor: 'bg-emerald-500/10',
            borderColor: 'border-emerald-500/20',
        },
        {
            key: 'read',
            label: t('campaign.metric_read', 'Read'),
            value: totals.read ?? 0,
            pct: Math.round(((totals.read ?? 0) / total) * 100),
            Icon: Eye,
            color: 'from-purple-500 to-indigo-600',
            textColor: 'text-purple-600 dark:text-purple-400',
            bgColor: 'bg-purple-500/10',
            borderColor: 'border-purple-500/20',
        },
        {
            key: 'replied',
            label: t('campaign.metric_replied', 'Replies'),
            value: totals.replied ?? campaign.replied_count ?? 0,
            pct: Math.round(((totals.replied ?? campaign.replied_count ?? 0) / total) * 100),
            Icon: MessageSquare,
            color: 'from-teal-500 to-cyan-600',
            textColor: 'text-teal-600 dark:text-teal-400',
            bgColor: 'bg-teal-500/10',
            borderColor: 'border-teal-500/20',
        },
        {
            key: 'failed',
            label: t('campaign.metric_failed', 'Failed'),
            value: totals.failed ?? 0,
            pct: Math.round(((totals.failed ?? 0) / total) * 100),
            Icon: XCircle,
            color: 'from-rose-500 to-red-600',
            textColor: 'text-rose-600 dark:text-rose-400',
            bgColor: 'bg-rose-500/10',
            borderColor: 'border-rose-500/20',
        },
    ];

    const handleLaunch = () =>
        router.post(route('client.campaigns.launch', campaign.uuid), {}, { preserveScroll: true });
    const handlePause = () =>
        router.post(route('client.campaigns.pause', campaign.uuid), {}, { preserveScroll: true });
    const handleCancel = () => {
        if (confirm(t('campaign.stop_confirm', 'Are you sure you want to stop this campaign? Any unsent messages will be cancelled.'))) {
            router.post(route('client.campaigns.cancel', campaign.uuid));
        }
    };
    const handleDelete = () => {
        if (confirm(t('campaign.delete_confirm'))) {
            router.delete(route('client.campaigns.destroy', campaign.uuid));
        }
    };
    const handleRetryFailed = () => {
        if (confirm(t('campaign.retry_failed_confirm', 'Retry sending to failed recipients?'))) {
            router.post(route('client.campaigns.retry-failed', campaign.uuid), {}, { preserveScroll: true });
        }
    };

    const canEdit = ['draft', 'paused'].includes(campaign.status);
    const statusCfg = STATUS_CONFIG[campaign.status] || STATUS_CONFIG.draft;

    const filteredSample = useMemo(() => {
        return sample.filter((r) => {
            if (activityFilter !== 'all' && r.status !== activityFilter) return false;
            if (searchQuery.trim()) {
                const q = searchQuery.toLowerCase();
                const c = r.contact ?? {};
                const name = `${c.first_name ?? ''} ${c.last_name ?? ''}`.toLowerCase();
                const phone = (c.phone_e164 ?? '').toLowerCase();
                const email = (c.email ?? '').toLowerCase();
                const reason = (r.failed_reason ?? '').toLowerCase();
                return name.includes(q) || phone.includes(q) || email.includes(q) || reason.includes(q);
            }
            return true;
        });
    }, [sample, activityFilter, searchQuery]);

    const deliverySuccessRate = totals.sent > 0 ? Math.round(((totals.delivered ?? 0) / totals.sent) * 100) : 0;
    const readRate = totals.delivered > 0 ? Math.round(((totals.read ?? 0) / totals.delivered) * 100) : 0;

    return (
        <ClientLayout title={campaign.name}>
            <Head title={t('campaign.show_head_title', { name: campaign.name })} />
            <div className="max-w-7xl mx-auto space-y-6">

                {/* Header Card / Action Bar */}
                <div className="relative overflow-hidden rounded-2xl border border-neutral-200/80 dark:border-neutral-800 bg-white/90 dark:bg-neutral-900/90 p-5 sm:p-6 shadow-sm backdrop-blur-md">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div className="space-y-2">
                            <div className="flex items-center gap-2">
                                <Link
                                    href={route('client.campaigns.index')}
                                    className="inline-flex items-center gap-1 text-xs font-medium text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100 transition"
                                >
                                    <ArrowLeft className="h-3.5 w-3.5" />
                                    <span>{t('campaign.back_to_campaigns', 'Campaigns')}</span>
                                </Link>
                                <span className="text-neutral-300 dark:text-neutral-700">•</span>
                                <div className="inline-flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400 capitalize">
                                    <ChannelBrandIcon channel={campaign.channel} className="h-3.5 w-3.5" />
                                    <span>{campaign.channel || 'Broadcast'}</span>
                                </div>
                            </div>
                            
                            <div className="flex items-center gap-3 flex-wrap">
                                <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-neutral-900 dark:text-white">
                                    {campaign.name}
                                </h1>
                                <span
                                    className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold border ${statusCfg.bg} ${statusCfg.border}`}
                                >
                                    <span className={`h-2 w-2 rounded-full ${statusCfg.badge}`} />
                                    {statusCfg.labelKey && t(statusCfg.labelKey) ? t(statusCfg.labelKey) : statusCfg.defaultLabel}
                                </span>
                            </div>
                        </div>

                        {/* Action Toolbar */}
                        <div className="flex flex-wrap items-center gap-2.5 pt-2 lg:pt-0 border-t border-neutral-100 dark:border-neutral-800/80 lg:border-none">
                            {totals.failed > 0 && !['queued', 'sending'].includes(campaign.status) && (
                                <button
                                    onClick={handleRetryFailed}
                                    className="inline-flex items-center gap-2 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-2 text-sm font-semibold text-amber-700 dark:text-amber-300 hover:bg-amber-500/20 active:scale-95 transition-all shadow-sm"
                                >
                                    <RotateCcw className="h-4 w-4" />
                                    <span>{t('campaign.retry_failed', 'Retry Failed ({count})', { count: totals.failed })}</span>
                                </button>
                            )}

                            {canEdit && (
                                <Link
                                    href={route('client.campaigns.edit', campaign.uuid)}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3.5 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-750 transition shadow-sm"
                                >
                                    <Pencil className="h-4 w-4 text-neutral-500" />
                                    <span>{t('common.edit', 'Edit')}</span>
                                </Link>
                            )}

                            {reportUrl && (
                                <a
                                    href={reportUrl}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3.5 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-750 transition shadow-sm"
                                >
                                    <BarChart2 className="h-4 w-4 text-neutral-500" />
                                    <span>{t('campaign.full_report', 'Full Report')}</span>
                                    <ExternalLink className="h-3.5 w-3.5 text-neutral-400" />
                                </a>
                            )}

                            {campaign.status === 'draft' && (
                                <button
                                    onClick={handleLaunch}
                                    className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-4 py-2 text-sm font-semibold shadow-sm hover:from-emerald-500 hover:to-teal-500 active:scale-95 transition-all"
                                >
                                    <Play className="h-4 w-4 fill-current" />
                                    <span>{t('campaign.launch', 'Launch Campaign')}</span>
                                </button>
                            )}

                            {['queued', 'sending'].includes(campaign.status) && (
                                <button
                                    onClick={handlePause}
                                    className="inline-flex items-center gap-2 rounded-xl bg-amber-500 text-white px-4 py-2 text-sm font-semibold shadow-sm hover:bg-amber-600 active:scale-95 transition-all"
                                >
                                    <Pause className="h-4 w-4 fill-current" />
                                    <span>{t('campaign.pause', 'Pause Campaign')}</span>
                                </button>
                            )}

                            {campaign.status === 'paused' && (
                                <button
                                    onClick={handleLaunch}
                                    className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 text-white px-4 py-2 text-sm font-semibold shadow-sm hover:bg-emerald-500 active:scale-95 transition-all"
                                >
                                    <Play className="h-4 w-4 fill-current" />
                                    <span>{t('campaign.resume', 'Resume')}</span>
                                </button>
                            )}

                            {['queued', 'sending', 'paused', 'scheduled'].includes(campaign.status) && (
                                <button
                                    onClick={handleCancel}
                                    className="inline-flex items-center gap-2 rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50 dark:bg-rose-950/40 px-3.5 py-2 text-sm font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/60 transition"
                                >
                                    <Square className="h-4 w-4 fill-current" />
                                    <span>{t('campaign.stop_campaign', 'Stop Campaign')}</span>
                                </button>
                            )}

                            {!['queued', 'sending'].includes(campaign.status) && (
                                <button
                                    onClick={handleDelete}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-neutral-200 dark:border-neutral-800 text-neutral-500 dark:text-neutral-400 px-3 py-2 text-sm font-medium hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition"
                                    title={t('common.delete', 'Delete')}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Countdown Alert Banner */}
                {countdown && (
                    <div className="flex items-center justify-between gap-4 rounded-2xl border border-blue-500/30 bg-blue-500/10 px-5 py-4 text-blue-900 dark:text-blue-200 backdrop-blur-sm">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-500 text-white shadow-sm">
                                <Clock className="h-5 w-5 animate-pulse" />
                            </div>
                            <div>
                                <h4 className="font-semibold text-sm">{t('campaign.scheduled_launch', 'Scheduled Launch')}</h4>
                                <p className="text-xs text-blue-700 dark:text-blue-300">
                                    {t('campaign.sending_in', 'Scheduled to start in')}{' '}
                                    <span className="font-mono font-bold text-sm text-blue-900 dark:text-white">{countdown}</span>
                                    {` (${campaign.timezone || userTz})`}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Failure / Diagnostic Banner */}
                {(campaign.status === 'failed' || totals.failed_reason || totals.failed > 0) && (
                    <div className="relative overflow-hidden rounded-2xl border border-rose-500/30 bg-rose-500/10 p-5 backdrop-blur-sm">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div className="flex items-start gap-3.5">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/20 text-rose-600 dark:text-rose-400">
                                    <AlertCircle className="h-6 w-6" />
                                </div>
                                <div className="space-y-1">
                                    <h4 className="font-semibold text-sm text-rose-900 dark:text-rose-200">
                                        {totals.failed_reason ? t('campaign.failed_reason_title', 'Delivery Issue Detected') : t('campaign.failed_title', 'Campaign Delivery Failure')}
                                    </h4>
                                    <p className="text-xs text-rose-700 dark:text-rose-300 leading-relaxed max-w-3xl">
                                        {totals.failed_reason || campaign.failed_reason || t('campaign.failed_desc', 'Some messages could not be delivered (e.g. rate limit reached, missing valid credentials, opted out contacts, or unverified provider parameters).')}
                                    </p>
                                </div>
                            </div>
                            {totals.failed > 0 && !['queued', 'sending'].includes(campaign.status) && (
                                <button
                                    onClick={handleRetryFailed}
                                    className="shrink-0 inline-flex items-center gap-2 rounded-xl bg-rose-600 text-white px-4 py-2.5 text-xs font-semibold hover:bg-rose-700 active:scale-95 transition shadow-sm"
                                >
                                    <RotateCcw className="h-4 w-4" />
                                    <span>{t('campaign.retry_failed_now', 'Retry Unsent Contacts')}</span>
                                </button>
                            )}
                        </div>
                    </div>
                )}

                {/* KPI Metrics Grid */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
                    {metrics.map((m) => {
                        const { Icon } = m;
                        return (
                            <div
                                key={m.key}
                                className={`relative flex flex-col justify-between overflow-hidden rounded-2xl border ${m.borderColor} bg-white dark:bg-neutral-900 p-4 transition-all duration-200 hover:shadow-md hover:-translate-y-0.5`}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 truncate">
                                        {m.label}
                                    </span>
                                    <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-xl ${m.bgColor} ${m.textColor}`}>
                                        <Icon className="h-4 w-4" />
                                    </div>
                                </div>

                                <div className="mt-3 space-y-1">
                                    <div className="text-2xl font-bold tracking-tight text-neutral-900 dark:text-white">
                                        {m.value.toLocaleString()}
                                    </div>
                                    <div className="flex items-center justify-between text-[11px]">
                                        <span className="text-neutral-400">Share</span>
                                        <span className={`font-semibold ${m.textColor}`}>
                                            {m.pct}%
                                        </span>
                                    </div>
                                </div>

                                <div className="mt-2.5 h-1.5 w-full overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                                    <div
                                        className={`h-full rounded-full bg-gradient-to-r ${m.color} transition-all duration-500`}
                                        style={{ width: `${Math.min(m.pct, 100)}%` }}
                                    />
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Delivery Flow & Conversion Funnel */}
                <div className="rounded-2xl border border-neutral-200/80 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-5 sm:p-6 shadow-sm">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                        <div>
                            <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                                <BarChart2 className="h-5 w-5 text-blue-500" />
                                {t('campaign.delivery_report', 'Delivery Funnel')}
                            </h3>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                                {t('campaign.delivery_funnel_desc', 'Visual breakdown of recipient message progression and engagement rates.')}
                            </p>
                        </div>

                        {/* Conversion Rate Badges */}
                        <div className="flex items-center gap-3">
                            <div className="flex items-center gap-1.5 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                <TrendingUp className="h-3.5 w-3.5" />
                                <span>{deliverySuccessRate}% {t('campaign.delivery_rate', 'Delivery Rate')}</span>
                            </div>
                            <div className="flex items-center gap-1.5 rounded-xl border border-purple-500/20 bg-purple-500/10 px-3 py-1.5 text-xs font-semibold text-purple-600 dark:text-purple-400">
                                <Eye className="h-3.5 w-3.5" />
                                <span>{readRate}% {t('campaign.read_rate', 'Read Rate')}</span>
                            </div>
                        </div>
                    </div>

                    {(totals.total ?? 0) === 0 ? (
                        <EmptyState
                            icon={<Users className="h-8 w-8" />}
                            title={t('campaign.no_recipients_title', 'No Audience Found')}
                            description={t('campaign.no_recipients_desc', 'No recipients have been attached to this campaign execution yet.')}
                        />
                    ) : (
                        <div className="space-y-6">
                            {/* Unified Stacked Visual Progress Bar */}
                            <div className="space-y-2">
                                <div className="flex justify-between text-xs text-neutral-500 dark:text-neutral-400 font-medium">
                                    <span>{t('campaign.distribution', 'Audience Distribution')}</span>
                                    <span>{totals.total ?? 0} {t('campaign.total_contacts', 'contacts')}</span>
                                </div>
                                <div className="flex h-3.5 w-full overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800 p-0.5 gap-0.5">
                                    {totals.delivered > 0 && (
                                        <div
                                            className="h-full rounded-l-full bg-emerald-500 transition-all"
                                            style={{ width: `${((totals.delivered - (totals.read ?? 0)) / total) * 100}%` }}
                                            title={`Delivered: ${totals.delivered - (totals.read ?? 0)}`}
                                        />
                                    )}
                                    {totals.read > 0 && (
                                        <div
                                            className="h-full bg-purple-500 transition-all"
                                            style={{ width: `${(totals.read / total) * 100}%` }}
                                            title={`Read: ${totals.read}`}
                                        />
                                    )}
                                    {totals.replied > 0 && (
                                        <div
                                            className="h-full bg-teal-400 transition-all"
                                            style={{ width: `${((totals.replied ?? 0) / total) * 100}%` }}
                                            title={`Replied: ${totals.replied}`}
                                        />
                                    )}
                                    {totals.sent > totals.delivered && (
                                        <div
                                            className="h-full bg-sky-400 transition-all"
                                            style={{ width: `${((totals.sent - totals.delivered) / total) * 100}%` }}
                                            title={`In Transit: ${totals.sent - totals.delivered}`}
                                        />
                                    )}
                                    {totals.failed > 0 && (
                                        <div
                                            className="h-full rounded-r-full bg-rose-500 transition-all"
                                            style={{ width: `${(totals.failed / total) * 100}%` }}
                                            title={`Failed: ${totals.failed}`}
                                        />
                                    )}
                                </div>

                                <div className="flex flex-wrap items-center justify-between gap-3 text-xs pt-1">
                                    <div className="flex items-center gap-4 flex-wrap">
                                        <div className="flex items-center gap-1.5 text-neutral-600 dark:text-neutral-400">
                                            <span className="h-2.5 w-2.5 rounded-full bg-emerald-500" />
                                            <span>Delivered ({totals.delivered ?? 0})</span>
                                        </div>
                                        <div className="flex items-center gap-1.5 text-neutral-600 dark:text-neutral-400">
                                            <span className="h-2.5 w-2.5 rounded-full bg-purple-500" />
                                            <span>Read ({totals.read ?? 0})</span>
                                        </div>
                                        <div className="flex items-center gap-1.5 text-neutral-600 dark:text-neutral-400">
                                            <span className="h-2.5 w-2.5 rounded-full bg-teal-400" />
                                            <span>Replied ({totals.replied ?? 0})</span>
                                        </div>
                                        <div className="flex items-center gap-1.5 text-neutral-600 dark:text-neutral-400">
                                            <span className="h-2.5 w-2.5 rounded-full bg-rose-500" />
                                            <span>Failed ({totals.failed ?? 0})</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Main Content Grid: Activity Table + Details Sidebar */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {/* Left 2 Columns: Activity Log Table */}
                    <div className="lg:col-span-2 space-y-4">
                        <div className="rounded-2xl border border-neutral-200/80 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-5 shadow-sm">
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
                                <div>
                                    <h3 className="font-bold text-base text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                                        <Zap className="h-4 w-4 text-amber-500" />
                                        {t('campaign.recent_activity', 'Recipient Log & Activity')}
                                    </h3>
                                    <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                        {t('campaign.activity_desc', 'Live delivery status log per recipient.')}
                                    </p>
                                </div>

                                {/* Status Filter Tabs */}
                                <div className="flex items-center gap-1 overflow-x-auto rounded-xl bg-neutral-100 dark:bg-neutral-800 p-1 text-xs">
                                    {['all', 'failed', 'delivered', 'read', 'sent'].map((tab) => (
                                        <button
                                            key={tab}
                                            onClick={() => setActivityFilter(tab)}
                                            className={`rounded-lg px-2.5 py-1 font-semibold capitalize transition ${
                                                activityFilter === tab
                                                    ? 'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white shadow-sm'
                                                    : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
                                            }`}
                                        >
                                            {tab}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Search bar */}
                            {sample.length > 0 && (
                                <div className="relative mb-4">
                                    <Search className="absolute left-3 top-2.5 h-4 w-4 text-neutral-400" />
                                    <input
                                        type="text"
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        placeholder={t('campaign.search_recipient', 'Filter by recipient name, phone, or failure error...')}
                                        className="w-full rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50 pl-9 pr-4 py-2 text-xs text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    />
                                </div>
                            )}

                            {filteredSample.length === 0 ? (
                                <div className="py-12 text-center text-xs text-neutral-400">
                                    {sample.length === 0
                                        ? t('campaign.no_activity_yet', 'No activity logs recorded yet.')
                                        : t('campaign.no_matching_logs', 'No recipients match the active filter criteria.')}
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-xs text-left">
                                        <thead>
                                            <tr className="border-b border-neutral-200 dark:border-neutral-800 text-neutral-400 font-semibold uppercase tracking-wider text-[10px]">
                                                <th className="pb-3 pl-1">{t('campaign.col_contact', 'Recipient')}</th>
                                                <th className="pb-3">{t('campaign.col_status', 'Status')}</th>
                                                <th className="pb-3 text-right pr-1">{t('campaign.col_last_update', 'Timestamp')}</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800/60">
                                            {filteredSample.map((r) => {
                                                const c = r.contact ?? {};
                                                const name =
                                                    `${c.first_name ?? ''} ${c.last_name ?? ''}`.trim() ||
                                                    c.phone_e164 ||
                                                    c.email ||
                                                    `#${r.contact_id}`;
                                                const contactSub = c.phone_e164 || c.email || '';
                                                const initials = name
                                                    .split(' ')
                                                    .filter(Boolean)
                                                    .map((n) => n[0])
                                                    .join('')
                                                    .slice(0, 2)
                                                    .toUpperCase() || 'C';

                                                const last =
                                                    r.read_at || r.delivered_at || r.sent_at || r.updated_at;
                                                const stCfg = RECIPIENT_STATUS_CONFIG[r.status] || RECIPIENT_STATUS_CONFIG.sent;
                                                const { Icon: StatusIcon } = stCfg;

                                                return (
                                                    <tr key={r.id} className="hover:bg-neutral-50/80 dark:hover:bg-neutral-800/40 transition">
                                                        <td className="py-3 pl-1">
                                                            <div className="flex items-center gap-3">
                                                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-neutral-100 dark:bg-neutral-800 font-bold text-[11px] text-neutral-600 dark:text-neutral-300">
                                                                    {initials}
                                                                </div>
                                                                <div>
                                                                    <div className="font-semibold text-neutral-900 dark:text-neutral-100">{name}</div>
                                                                    {contactSub && (
                                                                        <div className="text-[11px] text-neutral-400 font-mono">
                                                                            {contactSub}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="py-3">
                                                            <div className="space-y-1">
                                                                <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold border ${stCfg.color}`}>
                                                                    <StatusIcon className="h-3 w-3" />
                                                                    <span className="capitalize">{r.status}</span>
                                                                </span>
                                                                {r.status === 'failed' && r.failed_reason && (
                                                                    <div className="text-[11px] text-rose-500 font-medium flex items-center gap-1 max-w-xs truncate" title={r.failed_reason}>
                                                                        <AlertTriangle className="h-3 w-3 shrink-0" />
                                                                        <span>{r.failed_reason}</span>
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="py-3 text-right pr-1 text-neutral-400 font-mono text-[11px]">
                                                            {last ? formatInTz(last, userTz) : '—'}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Right 1 Column: Campaign Meta & Parameters */}
                    <div className="space-y-4">
                        <div className="rounded-2xl border border-neutral-200/80 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-5 shadow-sm space-y-4">
                            <h3 className="font-bold text-base text-neutral-900 dark:text-neutral-100 border-b border-neutral-100 dark:border-neutral-800 pb-3 flex items-center gap-2">
                                <Globe className="h-4 w-4 text-blue-500" />
                                {t('campaign.details', 'Campaign Specifications')}
                            </h3>

                            <div className="space-y-3 text-xs">
                                <div className="flex items-center justify-between py-1 border-b border-neutral-100 dark:border-neutral-800/60">
                                    <span className="text-neutral-500 dark:text-neutral-400">{t('campaign.col_channel', 'Channel')}</span>
                                    <div className="flex items-center gap-1.5 font-semibold text-neutral-900 dark:text-neutral-100 capitalize">
                                        <ChannelBrandIcon channel={campaign.channel} className="h-4 w-4" />
                                        <span>{campaign.channel || 'Standard'}</span>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between py-1 border-b border-neutral-100 dark:border-neutral-800/60">
                                    <span className="text-neutral-500 dark:text-neutral-400">{t('campaign.audience', 'Audience Segment')}</span>
                                    <span className="font-semibold text-neutral-900 dark:text-neutral-100 max-w-[160px] truncate text-right">
                                        {campaign.audience_type}
                                        {campaign.audience_ref ? ` (${typeof campaign.audience_ref === 'object' ? JSON.stringify(campaign.audience_ref) : campaign.audience_ref})` : ''}
                                    </span>
                                </div>

                                <div className="flex items-center justify-between py-1 border-b border-neutral-100 dark:border-neutral-800/60">
                                    <span className="text-neutral-500 dark:text-neutral-400">{t('campaign.col_scheduled', 'Schedule')}</span>
                                    <span className="font-semibold text-neutral-900 dark:text-neutral-100 text-right">
                                        {campaign.schedule_at ? formatInTz(campaign.schedule_at, userTz) : t('campaign.on_demand', 'Immediate On-Demand')}
                                    </span>
                                </div>

                                <div className="flex items-center justify-between py-1 border-b border-neutral-100 dark:border-neutral-800/60">
                                    <span className="text-neutral-500 dark:text-neutral-400">{t('campaign.timezone', 'Timezone')}</span>
                                    <span className="font-mono text-neutral-900 dark:text-neutral-100">
                                        {campaign.timezone || userTz}
                                    </span>
                                </div>

                                <div className="flex items-center justify-between py-1">
                                    <span className="text-neutral-500 dark:text-neutral-400">{t('campaign.created', 'Created At')}</span>
                                    <span className="font-mono text-neutral-900 dark:text-neutral-100">
                                        {formatInTz(campaign.created_at, userTz)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </ClientLayout>
    );
}

