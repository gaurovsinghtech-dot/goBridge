import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { LineChart, DonutChart } from '@/Components/Charts';
import {
    Download,
    ArrowLeft,
    Filter,
    Clock,
    Users,
    CheckCircle2,
    Eye,
    AlertTriangle,
    MousePointer,
    UserX,
    RefreshCw,
    Send,
    TrendingUp,
    AlertCircle,
    MessageSquare,
    Phone,
    Mail,
    Info,
    Sparkles,
    ShieldAlert,
    Copy,
    Check,
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { formatInTz } from '@/Utils/datetime';
import { useTranslation } from 'react-i18next';

const STATUS_CONFIG = {
    queued: {
        bg: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
        dot: 'bg-amber-500',
        icon: Clock,
        label: 'Queued',
    },
    sent: {
        bg: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
        dot: 'bg-blue-500',
        icon: Send,
        label: 'Sent',
    },
    delivered: {
        bg: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
        dot: 'bg-emerald-500',
        icon: CheckCircle2,
        label: 'Delivered',
    },
    read: {
        bg: 'bg-violet-500/10 text-violet-600 dark:text-violet-400 border-violet-500/20',
        dot: 'bg-violet-500',
        icon: Eye,
        label: 'Read',
    },
    failed: {
        bg: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
        dot: 'bg-rose-500',
        icon: AlertCircle,
        label: 'Failed',
    },
};

function humanSeconds(seconds) {
    if (!seconds || seconds <= 0) return '—';
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.round(seconds / 60)}m ${seconds % 60}s`;
    const h = Math.floor(seconds / 3600);
    const m = Math.round((seconds % 3600) / 60);
    return `${h}h ${m}m`;
}

function getInitials(name) {
    if (!name) return '??';
    const parts = name.trim().split(' ').filter(Boolean);
    if (parts.length >= 2) return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
    return name.slice(0, 2).toUpperCase();
}

export default function CampaignReportShow({
    campaign,
    kpis,
    funnel = [],
    deliveryOverTime = [],
    failedReasons = [],
    lag,
    recipients,
    filters = {},
}) {
    const { t } = useTranslation();
    const userTz = usePage().props.timezone || 'Asia/Dhaka';
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [copiedPhone, setCopiedPhone] = useState(null);

    const handleRefresh = () => {
        setIsRefreshing(true);
        router.reload({
            onFinish: () => setIsRefreshing(false),
        });
    };

    const applyFilter = (status) => {
        setStatusFilter(status);
        router.get(
            route('client.reports.campaigns.show', campaign.uuid),
            { status: status || undefined },
            { preserveState: true },
        );
    };

    const handleCopy = (text) => {
        if (!text) return;
        navigator.clipboard.writeText(text);
        setCopiedPhone(text);
        setTimeout(() => setCopiedPhone(null), 2000);
    };

    const exportUrl = route('reports.exports.campaign-recipients', campaign.uuid);

    // Calculate step funnel data safely
    const funnelSteps = useMemo(() => {
        const total = kpis.total || 0;
        const sent = kpis.sent || (kpis.total - (kpis.queued || 0));
        const delivered = kpis.delivered || 0;
        const read = kpis.read || 0;
        const clicked = kpis.clicked || 0;

        return [
            {
                key: 'total',
                name: t('reports.funnel_total', 'Total Targeted'),
                count: total,
                pct: 100,
                color: 'from-blue-600 to-indigo-600',
                bgColor: 'bg-blue-500/10 text-blue-500',
                icon: Users,
            },
            {
                key: 'sent',
                name: t('reports.series_sent', 'Sent'),
                count: sent,
                pct: total > 0 ? Math.round((sent / total) * 100) : 0,
                color: 'from-indigo-600 to-violet-600',
                bgColor: 'bg-indigo-500/10 text-indigo-500',
                icon: Send,
            },
            {
                key: 'delivered',
                name: t('reports.series_delivered', 'Delivered'),
                count: delivered,
                pct: total > 0 ? Math.round((delivered / total) * 100) : 0,
                color: 'from-emerald-600 to-teal-600',
                bgColor: 'bg-emerald-500/10 text-emerald-500',
                icon: CheckCircle2,
            },
            {
                key: 'read',
                name: t('reports.series_read', 'Read / Engaged'),
                count: read,
                pct: total > 0 ? Math.round((read / total) * 100) : 0,
                color: 'from-violet-600 to-purple-600',
                bgColor: 'bg-violet-500/10 text-violet-500',
                icon: Eye,
            },
            {
                key: 'clicked',
                name: t('reports.kpi_clicked', 'Clicked Link'),
                count: clicked,
                pct: total > 0 ? Math.round((clicked / total) * 100) : 0,
                color: 'from-amber-500 to-orange-600',
                bgColor: 'bg-amber-500/10 text-amber-500',
                icon: MousePointer,
            },
        ];
    }, [kpis, t]);

    // Check if failure reasons suggest billing or limit issue
    const mainFailureAlert = useMemo(() => {
        if (!failedReasons || failedReasons.length === 0) return null;
        const topReason = failedReasons[0]?.name || '';
        if (topReason.toLowerCase().includes('balance') || topReason.toLowerCase().includes('rate limit')) {
            return {
                title: 'WhatsApp Billing / Rate Limit Alert',
                desc: 'Messages were failed due to Meta Prepaid Balance exhaustion or tier limits. Top up your Meta Billing balance in Meta Business Suite.',
            };
        }
        return null;
    }, [failedReasons]);

    return (
        <ClientLayout title={t('reports.campaign_report_title', { name: campaign.name, defaultValue: `Campaign Analytics: ${campaign.name}` })}>
            <Head title={t('reports.campaign_report_head', { name: campaign.name, defaultValue: `Report: ${campaign.name}` })} />

            <div className="space-y-6 pb-12">
                {/* Modern Dark Hero Header */}
                <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 sm:p-8 text-white shadow-xl border border-slate-800">
                    <div className="absolute -top-16 -right-16 h-64 w-64 rounded-full bg-indigo-500/20 blur-3xl pointer-events-none" />
                    <div className="absolute -bottom-16 -left-16 h-64 w-64 rounded-full bg-violet-500/20 blur-3xl pointer-events-none" />

                    <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div className="space-y-3">
                            <div className="flex items-center gap-3">
                                <Link
                                    href={route('client.campaigns.index')}
                                    className="inline-flex items-center justify-center p-2 rounded-xl bg-white/10 hover:bg-white/20 text-white transition-all backdrop-blur-md border border-white/10"
                                    title="Back to Campaigns"
                                >
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                                <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 backdrop-blur-md">
                                    <MessageSquare className="h-3.5 w-3.5" />
                                    <span className="capitalize">{campaign.channel || 'whatsapp'}</span>
                                </span>
                                <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-white/10 text-slate-200 border border-white/10">
                                    <Clock className="h-3.5 w-3.5 text-slate-400" />
                                    {campaign.created_at ? formatInTz(campaign.created_at, userTz) : 'Recent'}
                                </span>
                            </div>

                            <div>
                                <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-white flex items-center gap-3">
                                    {campaign.name}
                                </h1>
                                <p className="text-sm text-slate-300 mt-1 flex items-center gap-2">
                                    <span>Campaign Execution Analytics & Recipient Delivery Logs</span>
                                </p>
                            </div>
                        </div>

                        {/* Actions */}
                        <div className="flex items-center gap-3 shrink-0">
                            <button
                                onClick={handleRefresh}
                                disabled={isRefreshing}
                                className="inline-flex items-center gap-2 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 px-4 py-2.5 text-sm font-medium text-white shadow-sm backdrop-blur-md transition-all active:scale-95 disabled:opacity-50"
                            >
                                <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                                <span className="hidden sm:inline">Refresh Data</span>
                            </button>
                            <a
                                href={exportUrl}
                                className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white border border-indigo-400/30 px-4 py-2.5 text-sm font-semibold shadow-lg shadow-indigo-600/30 backdrop-blur-md transition-all active:scale-95"
                            >
                                <Download className="h-4 w-4" />
                                <span>Export CSV</span>
                            </a>
                        </div>
                    </div>
                </div>

                {/* Meta Billing Alert if detected */}
                {mainFailureAlert && (
                    <div className="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 sm:p-5 text-amber-950 dark:text-amber-200 backdrop-blur-md flex items-start gap-4">
                        <div className="p-2.5 rounded-xl bg-amber-500/20 shrink-0 text-amber-600 dark:text-amber-400">
                            <ShieldAlert className="h-6 w-6" />
                        </div>
                        <div className="space-y-1">
                            <h3 className="font-bold text-sm text-amber-900 dark:text-amber-100 flex items-center gap-2">
                                {mainFailureAlert.title}
                            </h3>
                            <p className="text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
                                {mainFailureAlert.desc}
                            </p>
                        </div>
                    </div>
                )}

                {/* KPI Strip */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-6">
                    {/* Total */}
                    <MetricCard
                        label={t('reports.kpi_total_recipients', 'Total Targeted')}
                        value={kpis.total}
                        subtext="100% Audience"
                        icon={Users}
                        accent="from-indigo-500/10 to-blue-500/10 border-indigo-500/20"
                        iconBg="bg-indigo-500/15 text-indigo-500"
                    />

                    {/* Delivered */}
                    <MetricCard
                        label={t('reports.kpi_delivered', 'Delivered')}
                        value={`${kpis.delivered_pct}%`}
                        subtext={`${kpis.delivered ?? 0} recipients`}
                        icon={CheckCircle2}
                        accent="from-emerald-500/10 to-teal-500/10 border-emerald-500/20"
                        iconBg="bg-emerald-500/15 text-emerald-500"
                    />

                    {/* Read Rate */}
                    <MetricCard
                        label={t('reports.kpi_read', 'Read Rate')}
                        value={`${kpis.read_pct}%`}
                        subtext={`${kpis.read ?? 0} opened`}
                        icon={Eye}
                        accent="from-violet-500/10 to-purple-500/10 border-violet-500/20"
                        iconBg="bg-violet-500/15 text-violet-500"
                    />

                    {/* Failed */}
                    <MetricCard
                        label={t('reports.kpi_failed', 'Failed')}
                        value={`${kpis.failed_pct}%`}
                        subtext={`${kpis.failed ?? 0} errors`}
                        icon={AlertTriangle}
                        accent="from-rose-500/10 to-red-500/10 border-rose-500/20"
                        iconBg="bg-rose-500/15 text-rose-500"
                    />

                    {/* Clicked */}
                    <MetricCard
                        label={t('reports.kpi_clicked', 'Clicked Rate')}
                        value={`${kpis.clicked_pct ?? 0}%`}
                        subtext={`${kpis.clicked ?? 0} clicks`}
                        icon={MousePointer}
                        accent="from-amber-500/10 to-orange-500/10 border-amber-500/20"
                        iconBg="bg-amber-500/15 text-amber-500"
                    />

                    {/* Opted Out */}
                    <MetricCard
                        label={t('reports.kpi_opted_out', 'Opted Out')}
                        value={kpis.opted_out ?? 0}
                        subtext="Unsubscribed"
                        icon={UserX}
                        accent="from-slate-500/10 to-zinc-500/10 border-slate-500/20"
                        iconBg="bg-slate-500/15 text-slate-400"
                    />
                </div>

                {/* Status Lag Timeline */}
                {(lag?.sent_to_delivered || lag?.delivered_to_read || lag?.sent_to_read) ? (
                    <div className="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 shadow-sm">
                        <div className="flex items-center gap-2 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                            <Clock className="h-4 w-4 text-indigo-500" />
                            <span>{t('reports.status_timeline', 'Delivery Latency Benchmark (Avg Seconds)')}</span>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <LagCard label={t('reports.lag_sent_to_delivered', 'Sent → Delivered')} value={lag.sent_to_delivered} color="text-blue-500" bg="bg-blue-500/5 border-blue-500/20" />
                            <LagCard label={t('reports.lag_delivered_to_read', 'Delivered → Read')} value={lag.delivered_to_read} color="text-violet-500" bg="bg-violet-500/5 border-violet-500/20" />
                            <LagCard label={t('reports.lag_sent_to_read', 'Overall Time to Read')} value={lag.sent_to_read} color="text-emerald-500" bg="bg-emerald-500/5 border-emerald-500/20" />
                        </div>
                    </div>
                ) : null}

                {/* Charts Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    {/* Modern Custom Funnel Flow (5 cols) */}
                    <div className="lg:col-span-5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-sm flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between mb-4">
                                <div>
                                    <h3 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                        <TrendingUp className="h-5 w-5 text-indigo-500" />
                                        <span>{t('reports.delivery_funnel', 'Delivery & Engagement Funnel')}</span>
                                    </h3>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        Progression of recipient status from audience to link click.
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-4 my-2">
                                {funnelSteps.map((step) => {
                                    const IconComp = step.icon;
                                    return (
                                        <div key={step.key} className="space-y-1.5">
                                            <div className="flex items-center justify-between text-xs font-semibold">
                                                <div className="flex items-center gap-2">
                                                    <span className={`p-1.5 rounded-lg ${step.bgColor}`}>
                                                        <IconComp className="h-3.5 w-3.5" />
                                                    </span>
                                                    <span className="text-gray-700 dark:text-gray-200">{step.name}</span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-gray-900 dark:text-white font-bold">{step.count.toLocaleString()}</span>
                                                    <span className="text-gray-400 text-[11px]">({step.pct}%)</span>
                                                </div>
                                            </div>
                                            {/* Progress bar container */}
                                            <div className="h-3.5 w-full bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden p-0.5 border border-gray-200/50 dark:border-gray-700/50">
                                                <div
                                                    className={`h-full rounded-full bg-gradient-to-r ${step.color} transition-all duration-700 shadow-sm`}
                                                    style={{ width: `${Math.max(step.pct, step.count > 0 ? 3 : 0)}%` }}
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="mt-4 p-3 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200/80 dark:border-gray-700/60 flex items-center justify-between text-xs">
                            <span className="text-gray-500 dark:text-gray-400">Conversion Rate</span>
                            <span className="font-bold text-emerald-600 dark:text-emerald-400">
                                {kpis.delivered_pct}% Delivered · {kpis.read_pct}% Read
                            </span>
                        </div>
                    </div>

                    {/* Delivery Over Time (7 cols) */}
                    <div className="lg:col-span-7 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-sm flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between mb-4">
                                <div>
                                    <h3 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                        <Clock className="h-5 w-5 text-indigo-500" />
                                        <span>{t('reports.delivery_over_time', 'Hourly Delivery Velocity')}</span>
                                    </h3>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        Message dispatch and status updates over time.
                                    </p>
                                </div>
                            </div>

                            {deliveryOverTime.length > 0 ? (
                                <LineChart
                                    data={deliveryOverTime}
                                    xKey="hour"
                                    yKeys={['sent', 'delivered', 'read']}
                                    labels={{
                                        sent: t('reports.series_sent', 'Sent'),
                                        delivered: t('reports.series_delivered', 'Delivered'),
                                        read: t('reports.series_read', 'Read'),
                                    }}
                                    height={260}
                                />
                            ) : (
                                <div className="h-64 flex flex-col items-center justify-center text-center p-6 border-2 border-dashed border-gray-200 dark:border-gray-800 rounded-xl">
                                    <Clock className="h-8 w-8 text-gray-400 mb-2" />
                                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">{t('reports.no_timeseries', 'No hourly time-series data captured yet')}</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Failed Reasons Diagnostics & Recipients Table */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    {/* Failed Reasons Breakdown */}
                    <div className="lg:col-span-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-sm flex flex-col justify-between">
                        <div>
                            <h3 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2 mb-4">
                                <AlertTriangle className="h-5 w-5 text-rose-500" />
                                <span>{t('reports.failed_reasons', 'Failure Diagnostics')}</span>
                            </h3>

                            {failedReasons.length > 0 ? (
                                <div className="space-y-4">
                                    <DonutChart data={failedReasons} nameKey="name" valueKey="value" height={190} />
                                    <div className="space-y-2 mt-4 max-h-48 overflow-y-auto pr-1">
                                        {failedReasons.map((item, idx) => (
                                            <div key={idx} className="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-800/80 border border-gray-200/60 dark:border-gray-700/60 flex items-center justify-between text-xs">
                                                <span className="font-medium text-gray-800 dark:text-gray-200 max-w-[180px] truncate" title={item.name}>
                                                    {item.name}
                                                </span>
                                                <span className="font-bold px-2 py-0.5 rounded-md bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                                    {item.value} ({Math.round((item.value / (kpis.failed || 1)) * 100)}%)
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ) : (
                                <div className="h-56 flex flex-col items-center justify-center text-center p-6 border-2 border-dashed border-gray-200 dark:border-gray-800 rounded-xl">
                                    <CheckCircle2 className="h-8 w-8 text-emerald-500 mb-2" />
                                    <p className="text-sm font-semibold text-gray-700 dark:text-gray-300">Clean Dispatch!</p>
                                    <p className="text-xs text-gray-400 mt-1">{t('reports.no_failures', 'No delivery failures recorded for this campaign.')}</p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Recipients Log Table (8 cols) */}
                    <div className="lg:col-span-8 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-sm space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 dark:border-gray-800 pb-4">
                            <div>
                                <h3 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <Users className="h-5 w-5 text-indigo-500" />
                                    <span>{t('reports.recipients', 'Recipient Logs & Statuses')}</span>
                                </h3>
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    Individual contact statuses and Meta error response logs.
                                </p>
                            </div>

                            {/* Status filter toolbar */}
                            <div className="flex items-center gap-1.5 bg-gray-100 dark:bg-gray-800 p-1 rounded-xl overflow-x-auto max-w-full">
                                {['', 'failed', 'delivered', 'read', 'sent', 'queued'].map((st) => {
                                    const isActive = statusFilter === st;
                                    const label = st === '' ? 'All' : (STATUS_CONFIG[st]?.label || st);
                                    return (
                                        <button
                                            key={st}
                                            onClick={() => applyFilter(st)}
                                            className={`px-3 py-1.5 rounded-lg text-xs font-semibold capitalize whitespace-nowrap transition-all ${
                                                isActive
                                                    ? 'bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-300 shadow-sm'
                                                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'
                                            }`}
                                        >
                                            {label}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Table */}
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs border-separate border-spacing-y-1.5">
                                <thead>
                                    <tr className="text-gray-400 dark:text-gray-500 uppercase font-bold text-[10px] tracking-wider">
                                        <th className="px-3 py-2">{t('contacts_page.contact_alt', 'Contact / Recipient')}</th>
                                        <th className="px-3 py-2">{t('reports.col_status', 'Status')}</th>
                                        <th className="px-3 py-2">{t('reports.col_sent_at', 'Sent Time')}</th>
                                        <th className="px-3 py-2">{t('reports.col_delivered_at', 'Delivered')}</th>
                                        <th className="px-3 py-2">{t('reports.col_read_at', 'Read')}</th>
                                        <th className="px-3 py-2">{t('reports.col_reason', 'Failure Reason / Logs')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-transparent">
                                    {recipients.data.map((r) => {
                                        const c = r.contact ?? {};
                                        const fullName = `${c.first_name ?? ''} ${c.last_name ?? ''}`.trim();
                                        const contactTitle = fullName || c.phone_e164 || c.email || `#${r.contact_id}`;
                                        const phoneOrEmail = c.phone_e164 || c.email || '';
                                        const initials = getInitials(fullName || contactTitle);
                                        const cfg = STATUS_CONFIG[r.status] || STATUS_CONFIG.queued;
                                        const StatusIcon = cfg.icon;

                                        return (
                                            <tr key={r.id} className="bg-gray-50/70 dark:bg-gray-800/40 hover:bg-gray-100/80 dark:hover:bg-gray-800 transition-colors rounded-xl">
                                                {/* Contact */}
                                                <td className="px-3 py-2.5 rounded-l-xl">
                                                    <div className="flex items-center gap-3">
                                                        <div className="h-8 w-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-sm">
                                                            {initials}
                                                        </div>
                                                        <div className="min-w-0">
                                                            <div className="font-semibold text-gray-900 dark:text-white truncate max-w-[150px]">
                                                                {contactTitle}
                                                            </div>
                                                            {phoneOrEmail && (
                                                                <div className="text-[11px] text-gray-400 flex items-center gap-1">
                                                                    <span className="truncate max-w-[140px]">{phoneOrEmail}</span>
                                                                    <button
                                                                        onClick={() => handleCopy(phoneOrEmail)}
                                                                        className="hover:text-indigo-400 p-0.5 rounded transition-colors"
                                                                        title="Copy phone"
                                                                    >
                                                                        {copiedPhone === phoneOrEmail ? (
                                                                            <Check className="h-3 w-3 text-emerald-400" />
                                                                        ) : (
                                                                            <Copy className="h-3 w-3" />
                                                                        )}
                                                                    </button>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>

                                                {/* Status badge */}
                                                <td className="px-3 py-2.5">
                                                    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-semibold border ${cfg.bg}`}>
                                                        <StatusIcon className="h-3.5 w-3.5" />
                                                        <span className="capitalize">{r.status}</span>
                                                    </span>
                                                </td>

                                                {/* Timestamps */}
                                                <td className="px-3 py-2.5 text-gray-600 dark:text-gray-300 font-mono text-[11px]">
                                                    {r.sent_at ? formatInTz(r.sent_at, userTz) : '—'}
                                                </td>
                                                <td className="px-3 py-2.5 text-gray-600 dark:text-gray-300 font-mono text-[11px]">
                                                    {r.delivered_at ? formatInTz(r.delivered_at, userTz) : '—'}
                                                </td>
                                                <td className="px-3 py-2.5 text-gray-600 dark:text-gray-300 font-mono text-[11px]">
                                                    {r.read_at ? formatInTz(r.read_at, userTz) : '—'}
                                                </td>

                                                {/* Failure Details */}
                                                <td className="px-3 py-2.5 rounded-r-xl">
                                                    {r.failed_reason ? (
                                                        <div
                                                            className="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 text-[11px] font-medium max-w-[200px] truncate"
                                                            title={r.failed_reason}
                                                        >
                                                            <AlertCircle className="h-3.5 w-3.5 shrink-0 text-rose-500" />
                                                            <span className="truncate">{r.failed_reason}</span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-gray-400">—</span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}

                                    {recipients.data.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="py-12 text-center text-gray-400">
                                                <div className="flex flex-col items-center justify-center space-y-2">
                                                    <Users className="h-8 w-8 text-gray-400" />
                                                    <p className="text-sm font-medium">{t('reports.no_recipients', 'No recipients match the selected filter.')}</p>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {recipients.last_page > 1 && (
                            <div className="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-gray-800 text-xs text-gray-500">
                                <span>
                                    {t('reports.page_of', { current: recipients.current_page, total: recipients.last_page, defaultValue: `Page ${recipients.current_page} of ${recipients.last_page}` })}
                                </span>
                                <div className="flex items-center gap-2">
                                    {recipients.prev_page_url && (
                                        <Link
                                            href={recipients.prev_page_url}
                                            className="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            {t('common.previous', 'Previous')}
                                        </Link>
                                    )}
                                    {recipients.next_page_url && (
                                        <Link
                                            href={recipients.next_page_url}
                                            className="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            {t('common.next', 'Next')}
                                        </Link>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </ClientLayout>
    );
}

// Subcomponent: Metric Card
function MetricCard({ label, value, subtext, icon: IconComponent, accent, iconBg }) {
    return (
        <div className={`relative overflow-hidden rounded-2xl border bg-gradient-to-b ${accent} bg-white dark:bg-gray-900 p-4 sm:p-5 shadow-sm hover:-translate-y-0.5 transition-all duration-200 flex flex-col justify-between`}>
            <div className="flex items-center justify-between">
                <span className="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{label}</span>
                {IconComponent && (
                    <div className={`p-2 rounded-xl ${iconBg} shadow-sm shrink-0`}>
                        <IconComponent className="h-4 w-4" />
                    </div>
                )}
            </div>

            <div className="mt-3">
                <div className="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">{value}</div>
                {subtext && <p className="text-xs text-gray-400 dark:text-gray-400 mt-1 font-medium">{subtext}</p>}
            </div>
        </div>
    );
}

// Subcomponent: Lag Card
function LagCard({ label, value, color, bg }) {
    return (
        <div className={`rounded-xl border ${bg} p-3.5 flex items-center justify-between`}>
            <span className="text-xs font-medium text-gray-600 dark:text-gray-400">{label}</span>
            <span className={`text-base font-bold ${color} font-mono`}>{humanSeconds(value)}</span>
        </div>
    );
}

