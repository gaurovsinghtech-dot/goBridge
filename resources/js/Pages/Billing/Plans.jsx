import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Button, Card, Badge } from '@/Components/ui';
import { Check, Sparkles, ArrowLeft, ShieldCheck, Zap } from 'lucide-react';
import { toast } from 'sonner';
import { useTranslation } from 'react-i18next';

export default function PricingPlans({
    plans = [],
    currentPlanId = null,
    currentSubscription = null,
    isEligibleForTrial = false,
}) {
    const { t } = useTranslation();
    const [billingCycle, setBillingCycle] = useState('monthly');
    const [loadingPlanId, setLoadingPlanId] = useState(null);
    const [optInTrial, setOptInTrial] = useState(false);

    const handleSelectPlan = (plan) => {
        if (plan.id === currentPlanId && currentSubscription?.status === 'active') {
            return;
        }

        setLoadingPlanId(plan.id);

        window.axios.post(route('client.billing.checkout'), {
            plan_id: plan.id,
            billing_cycle: billingCycle,
            opt_in_trial: optInTrial,
        })
        .then((res) => {
            const data = res.data;
            if (!data.success || !data.url) {
                toast.error(data.message || 'Failed to initiate checkout.');
                setLoadingPlanId(null);
                return;
            }

            // Razorpay's hosted Subscriptions checkout page handles the ₹1 mandate
            // authorization and, for recurring plans, the trial/billing cycle itself —
            // fulfillment happens server-side via webhook, so we just follow the redirect.
            window.location.href = data.url;
        })
        .catch((err) => {
            toast.error(err.response?.data?.message || 'Failed to initiate checkout.');
            setLoadingPlanId(null);
        });
    };

    return (
        <ClientLayout>
            <Head title="Choose a Plan — Growbridge Connect" />

            <div className="space-y-8 max-w-6xl mx-auto">
                <div className="text-center space-y-3">
                    <div className="flex items-center justify-center gap-2">
                        <Link href={route('client.billing.index')}>
                            <Button type="button" variant="ghost" size="sm" className="p-2">
                                <ArrowLeft className="w-4 h-4" />
                            </Button>
                        </Link>
                        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-brand-50 text-brand-700 dark:bg-neutral-800 dark:text-brand-400 text-xs font-semibold uppercase">
                            <Sparkles className="w-3.5 h-3.5" /> Simple, Transparent Pricing
                        </div>
                    </div>

                    <h1 className="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">
                        Scale Your Omnichannel Growth
                    </h1>
                    <p className="text-sm text-slate-500 dark:text-neutral-400 max-w-xl mx-auto">
                        Deploy AI Voice Agents, WhatsApp Automations, and Unified CRM with guaranteed uptime.
                    </p>

                    {/* Monthly / Yearly Toggle */}
                    <div className="flex items-center justify-center gap-3 pt-2">
                        <span className={`text-xs font-semibold ${billingCycle === 'monthly' ? 'text-slate-900 dark:text-white' : 'text-slate-400'}`}>
                            Monthly Billing
                        </span>
                        <button
                            type="button"
                            onClick={() => setBillingCycle(billingCycle === 'monthly' ? 'yearly' : 'monthly')}
                            className="relative w-12 h-6 rounded-full bg-brand-900 transition-colors p-0.5"
                        >
                            <div className={`w-5 h-5 rounded-full bg-accent-400 transition-transform ${billingCycle === 'yearly' ? 'translate-x-6' : 'translate-x-0'}`} />
                        </button>
                        <span className={`text-xs font-semibold ${billingCycle === 'yearly' ? 'text-slate-900 dark:text-white' : 'text-slate-400'}`}>
                            Yearly Billing <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold ml-1">SAVE 20%</span>
                        </span>
                    </div>

                    {isEligibleForTrial && (
                        <div className="mt-8 mx-auto max-w-lg bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-xl p-4 flex items-start gap-4 text-left transition-all hover:shadow-md">
                            <div className="pt-0.5">
                                <div className="relative flex items-center">
                                    <input
                                        type="checkbox"
                                        id="trial_opt_in"
                                        checked={optInTrial}
                                        onChange={(e) => setOptInTrial(e.target.checked)}
                                        className="peer h-5 w-5 cursor-pointer appearance-none rounded-md border-2 border-indigo-300 bg-white checked:border-indigo-600 checked:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 dark:border-indigo-700 dark:bg-neutral-900 dark:checked:border-indigo-500 dark:checked:bg-indigo-500 transition-all"
                                    />
                                    <Check className="pointer-events-none absolute left-1/2 top-1/2 h-3.5 w-3.5 -translate-x-1/2 -translate-y-1/2 text-white opacity-0 peer-checked:opacity-100 transition-opacity" />
                                </div>
                            </div>
                            <div className="flex-1">
                                <label htmlFor="trial_opt_in" className="cursor-pointer">
                                    <h4 className="text-sm font-bold text-indigo-900 dark:text-indigo-300">
                                        Start with a 14-Day Free Trial
                                    </h4>
                                    <p className="text-xs text-indigo-700/80 dark:text-indigo-400/80 mt-1 leading-relaxed">
                                        Check this box to try before you buy! ₹1 will be charged immediately to verify your card, and the full plan amount will auto-deduct after 14 days.
                                    </p>
                                </label>
                            </div>
                        </div>
                    )}
                </div>

                {/* Plans Grid */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">
                    {plans.map((plan) => {
                        const priceCents = billingCycle === 'yearly'
                            ? (plan.yearly_price_cents ? plan.yearly_price_cents / 12 : plan.price_cents)
                            : (plan.monthly_price_cents || plan.price_cents);
                        const isCurrent = plan.id === currentPlanId;

                        return (
                            <Card
                                key={plan.id}
                                className={`p-6 flex flex-col justify-between border-2 transition-all relative ${
                                    plan.popular
                                        ? 'border-brand-600 shadow-lg dark:border-brand-500'
                                        : 'border-slate-200 dark:border-neutral-800'
                                }`}
                            >
                                {plan.popular && (
                                    <div className="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full bg-brand-600 text-white text-[10px] font-bold uppercase tracking-wider shadow-sm">
                                        Most Popular
                                    </div>
                                )}

                                <div>
                                    <h3 className="text-lg font-bold text-slate-900 dark:text-white">{plan.name}</h3>
                                    <p className="text-xs text-slate-500 dark:text-neutral-400 mt-1 min-h-[32px]">{plan.description}</p>

                                    <div className="my-5">
                                        <span className="text-3xl font-extrabold text-slate-900 dark:text-white">
                                            ₹{((priceCents || 0) / 100).toFixed(0)}
                                        </span>
                                        <span className="text-xs text-slate-400 font-medium"> / month</span>
                                    </div>

                                    <ul className="space-y-2.5 pt-4 border-t border-slate-100 dark:border-neutral-800 text-sm text-slate-600 dark:text-neutral-300">
                                        {plan.features?.map((f, i) => (
                                            <li key={i} className="flex items-center gap-2">
                                                <Check className="h-4 w-4 text-brand-500 shrink-0" />
                                                {f}
                                            </li>
                                        ))}
                                        {plan.white_label_enabled && (
                                            <li className="flex items-center gap-2">
                                                <Check className="h-4 w-4 text-brand-500 shrink-0" /> {t('pricing.white_label', 'White-label branding')}
                                            </li>
                                        )}
                                    </ul>
                                </div>

                                <div className="pt-6 mt-6 border-t border-slate-100 dark:border-neutral-800">
                                    <Button
                                        type="button"
                                        disabled={isCurrent || loadingPlanId === plan.id}
                                        onClick={() => handleSelectPlan(plan)}
                                        className={`w-full text-xs font-semibold ${
                                            isCurrent
                                                ? 'bg-slate-100 dark:bg-neutral-800 text-slate-500 cursor-not-allowed'
                                                : plan.popular
                                                    ? 'bg-brand-600 hover:bg-brand-700 text-white'
                                                    : 'bg-slate-900 hover:bg-slate-800 text-white dark:bg-white dark:text-slate-900'
                                        }`}
                                    >
                                        {isCurrent ? 'Current Plan' : loadingPlanId === plan.id ? 'Processing...' : 'Subscribe / Upgrade'}
                                    </Button>
                                </div>
                            </Card>
                        );
                    })}
                </div>
            </div>
        </ClientLayout>
    );
}
