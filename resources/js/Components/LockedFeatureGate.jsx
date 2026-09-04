import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Lock, Sparkles, ArrowRight, ShieldAlert } from 'lucide-react';
import { Button, Card, Badge } from '@/Components/ui';

export default function LockedFeatureGate({
    featureName = 'Voice Calling & AI Voice Agents',
    description = 'This feature is available on higher plans. Upgrade your plan to activate Twilio phone numbers, voice calling and AI voice assistants.',
    requiredPlan = 'Pro',
    onUpgradeClick,
}) {
    const { t } = useTranslation();

    return (
        <div className="min-h-[500px] flex items-center justify-center p-6">
            <Card className="max-w-lg w-full p-8 text-center bg-white/80 dark:bg-neutral-900/80 backdrop-blur-xl border border-neutral-200/80 dark:border-neutral-800 shadow-soft-2xl rounded-2xl relative overflow-hidden">
                {/* Background glow decorative effects */}
                <div className="absolute -top-24 -left-24 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none" />
                <div className="absolute -bottom-24 -right-24 w-48 h-48 bg-amber-500/10 rounded-full blur-3xl pointer-events-none" />

                <div className="mx-auto w-16 h-16 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200/60 dark:border-amber-700/50 flex items-center justify-center text-amber-600 dark:text-amber-400 mb-6 shadow-inner">
                    <Lock className="w-8 h-8" />
                </div>

                <Badge variant="outline" className="mb-4 inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-700/60 bg-amber-50/50 dark:bg-amber-900/20">
                    <Sparkles className="w-3.5 h-3.5" />
                    {t('ui.upgrade_required', 'Upgrade Required')} • {requiredPlan}+
                </Badge>

                <h3 className="text-2xl font-bold text-neutral-900 dark:text-white tracking-tight mb-2">
                    {featureName}
                </h3>

                <p className="text-sm text-neutral-600 dark:text-neutral-300 leading-relaxed mb-8">
                    {description}
                </p>

                <div className="flex flex-col sm:flex-row items-center justify-center gap-3">
                    <Link
                        href={route('client.pricing')}
                        className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-sm font-semibold rounded-xl transition shadow-soft hover:shadow-emerald-500/20"
                        onClick={onUpgradeClick}
                    >
                        <span>{t('ui.view_plans', 'View Plans')}</span>
                        <ArrowRight className="w-4 h-4" />
                    </Link>

                    <Link
                        href={route('client.pricing')}
                        className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-neutral-800 dark:text-neutral-200 text-sm font-semibold rounded-xl transition"
                    >
                        {t('ui.upgrade_now', 'Upgrade Now')}
                    </Link>
                </div>
            </Card>
        </div>
    );
}
