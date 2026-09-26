import { Head, Link } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { ArrowLeft, Sparkles } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import CampaignForm from './CampaignForm';

export default function CampaignWizard({
    whatsappTemplates = [],
    whatsappPhoneNumbers = [],
    segments = [],
    tags = [],
    contactTokens = [],
}) {
    const { t } = useTranslation();
    return (
        <ClientLayout title={t('campaign.new_campaign')}>
            <Head title={t('campaign.new_head_title')} />

            <div className="space-y-6">
                {/* ── Hero Header ─────────────────────────────────────────── */}
                <div className="relative overflow-hidden rounded-2xl border border-neutral-200/80 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-5 sm:p-6 shadow-sm">
                    {/* Background Subtle Gradient Glow */}
                    <div className="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-gradient-to-br from-brand-500/10 via-purple-500/10 to-transparent blur-3xl" />

                    <div className="relative z-10 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="space-y-1.5">
                            {/* Breadcrumb */}
                            <div className="flex items-center gap-2 text-xs font-medium text-neutral-500 dark:text-neutral-400">
                                <Link
                                    href={route('client.campaigns.index')}
                                    className="hover:text-brand-600 dark:hover:text-brand-400 transition"
                                >
                                    {t('campaign.campaigns', 'Campaigns')}
                                </Link>
                                <span>/</span>
                                <span className="text-neutral-800 dark:text-neutral-200 font-semibold">
                                    {t('campaign.new_campaign', 'Create Campaign')}
                                </span>
                            </div>

                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500/15 to-purple-500/15 dark:from-brand-500/25 dark:to-purple-500/25 text-brand-600 dark:text-brand-400 ring-1 ring-brand-500/20">
                                    <Sparkles className="h-5 w-5" />
                                </div>
                                <div>
                                    <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                                        {t('campaign.new_campaign')}
                                    </h1>
                                    <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                        {t('campaign.wizard_subtitle', 'Build and launch targeted broadcast campaigns across WhatsApp, Email, and SMS.')}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <Link
                            href={route('client.campaigns.index')}
                            className="inline-flex items-center gap-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition shadow-xs shrink-0 self-start sm:self-center"
                        >
                            <ArrowLeft className="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
                            <span>{t('common.back', 'Back')}</span>
                        </Link>
                    </div>
                </div>

                <CampaignForm
                    mode="create"
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

