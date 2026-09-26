import { Head, Link } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import {
    ArrowLeft,
    Eye,
    Sparkles,
    FileEdit,
    Clock,
    Send,
    PauseCircle,
    CheckCircle2,
    AlertCircle,
    Calendar,
    Users,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ChannelBrandIcon } from '@/Components/BrandIcons';
import CampaignForm from './CampaignForm';

const STATUS_CONFIG = {
    draft: {
        labelKey: 'campaign.status_draft',
        bg: 'bg-slate-100 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        Icon: FileEdit,
    },
    queued: {
        labelKey: 'campaign.status_queued',
        bg: 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        Icon: Clock,
    },
    sending: {
        labelKey: 'campaign.status_sending',
        bg: 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800',
        Icon: Send,
        pulse: true,
    },
    paused: {
        labelKey: 'campaign.status_paused',
        bg: 'bg-orange-100 dark:bg-orange-950/60 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-800',
        Icon: PauseCircle,
    },
    completed: {
        labelKey: 'campaign.status_completed',
        bg: 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
        Icon: CheckCircle2,
    },
    failed: {
        labelKey: 'campaign.status_failed',
        bg: 'bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
        Icon: AlertCircle,
    },
};

export default function CampaignEdit({
    campaign,
    whatsappTemplates = [],
    whatsappPhoneNumbers = [],
    segments = [],
    tags = [],
    contactTokens = [],
}) {
    const { t } = useTranslation();
    const statusCfg = STATUS_CONFIG[campaign.status] || STATUS_CONFIG.draft;
    const StatusIcon = statusCfg.Icon;

    return (
        <ClientLayout title={t('campaign.edit_layout_title', { name: campaign.name })}>
            <Head title={t('campaign.edit_head_title', { name: campaign.name })} />

            <div className="space-y-6">
                {/* ── Premium Hero Header ───────────────────────────────────── */}
                <div className="relative overflow-hidden rounded-2xl border border-neutral-200/80 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-5 sm:p-6 shadow-sm">
                    {/* Background Subtle Mesh Gradient */}
                    <div className="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-gradient-to-br from-brand-500/10 via-purple-500/10 to-transparent blur-3xl" />
                    <div className="pointer-events-none absolute -left-16 -bottom-16 h-64 w-64 rounded-full bg-gradient-to-tr from-emerald-500/10 via-brand-500/5 to-transparent blur-3xl" />

                    <div className="relative z-10 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        {/* Title & Metadata */}
                        <div className="space-y-2">
                            {/* Breadcrumb */}
                            <div className="flex items-center gap-2 text-xs font-medium text-neutral-500 dark:text-neutral-400">
                                <Link
                                    href={route('client.campaigns.index')}
                                    className="hover:text-brand-600 dark:hover:text-brand-400 transition"
                                >
                                    {t('campaign.campaigns', 'Campaigns')}
                                </Link>
                                <span>/</span>
                                <Link
                                    href={route('client.campaigns.show', campaign.uuid)}
                                    className="hover:text-brand-600 dark:hover:text-brand-400 transition max-w-[150px] truncate"
                                >
                                    {campaign.name}
                                </Link>
                                <span>/</span>
                                <span className="text-neutral-800 dark:text-neutral-200 font-semibold">
                                    {t('common.edit', 'Edit')}
                                </span>
                            </div>

                            {/* Main Title & Status Badge */}
                            <div className="flex flex-wrap items-center gap-3">
                                <div className="flex items-center gap-2.5">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500/15 to-purple-500/15 dark:from-brand-500/25 dark:to-purple-500/25 text-brand-600 dark:text-brand-400 ring-1 ring-brand-500/20">
                                        <Sparkles className="h-5 w-5" />
                                    </div>
                                    <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                                        {campaign.name}
                                    </h1>
                                </div>

                                <span
                                    className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold shadow-xs ${statusCfg.bg}`}
                                >
                                    <StatusIcon className={`h-3.5 w-3.5 ${statusCfg.pulse ? 'animate-pulse' : ''}`} />
                                    {statusCfg.labelKey ? t(statusCfg.labelKey) : campaign.status}
                                </span>
                            </div>

                            {/* Meta Info Pills */}
                            <div className="flex flex-wrap items-center gap-3 pt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                <div className="inline-flex items-center gap-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-800/80 px-2.5 py-1 font-medium text-neutral-700 dark:text-neutral-300">
                                    <ChannelBrandIcon channel={campaign.channel} className="h-4 w-4" />
                                    <span className="capitalize">{campaign.channel}</span>
                                </div>

                                {campaign.estimated_count > 0 && (
                                    <div className="inline-flex items-center gap-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-800/80 px-2.5 py-1 font-medium text-neutral-700 dark:text-neutral-300">
                                        <Users className="h-3.5 w-3.5 text-neutral-400" />
                                        <span>{campaign.estimated_count.toLocaleString()} {t('campaign.reachable', 'Reachable')}</span>
                                    </div>
                                )}

                                {campaign.created_at && (
                                    <div className="inline-flex items-center gap-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-800/80 px-2.5 py-1 font-medium text-neutral-700 dark:text-neutral-300">
                                        <Calendar className="h-3.5 w-3.5 text-neutral-400" />
                                        <span>{t('campaign.created', 'Created')} {new Date(campaign.created_at).toLocaleDateString()}</span>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Top Action Buttons */}
                        <div className="flex items-center gap-2.5 shrink-0">
                            <Link
                                href={route('client.campaigns.show', campaign.uuid)}
                                className="inline-flex items-center gap-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition shadow-xs"
                            >
                                <Eye className="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
                                <span>{t('campaign.view_details', 'View Details')}</span>
                            </Link>

                            <Link
                                href={route('client.campaigns.index')}
                                className="inline-flex items-center gap-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition shadow-xs"
                            >
                                <ArrowLeft className="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
                                <span className="hidden sm:inline">{t('common.back', 'Back')}</span>
                            </Link>
                        </div>
                    </div>
                </div>

                {/* ── Main Campaign Form ────────────────────────────────────── */}
                <CampaignForm
                    mode="edit"
                    campaign={campaign}
                    whatsappTemplates={whatsappTemplates}
                    whatsappPhoneNumbers={whatsappPhoneNumbers}
                    segments={segments}
                    tags={tags}
                    contactTokens={contactTokens}
                />
            </div>
        </ClientLayout>
    );
}

