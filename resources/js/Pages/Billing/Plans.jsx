import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Button, Card, Badge } from '@/Components/ui';
import { Check, Sparkles, ArrowLeft, ShieldCheck, Zap } from 'lucide-react';
import { toast } from 'sonner';

export default function PricingPlans({
    plans = [],
    currentPlanId = null,
    currentSubscription = null,
}) {
    const [billingCycle, setBillingCycle] = useState('monthly');
    const [loadingPlanId, setLoadingPlanId] = useState(null);

    const handleSelectPlan = (plan) => {
        if (plan.id === currentPlanId && currentSubscription?.status === 'active') {
            return;
        }

        setLoadingPlanId(plan.id);

        window.axios.post(route('client.billing.checkout'), {
            plan_id: plan.id,
            billing_cycle: billingCycle,
        })
        .then((res) => {
            const data = res.data;
            if (!data.success && data.message) {
                toast.error(data.message);
                return;
            }

            // Open Razorpay Standard Checkout
            const options = {
                key: data.key_id,
                amount: data.amount,
                currency: data.currency,
                name: 'Growbridge Connect',
                description: `${plan.name} (${billingCycle})`,
                order_id: data.order_id,
                handler: function (response) {
                    toast.info('Verifying payment server-side...');
                    window.axios.post(route('client.billing.verify'), {
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature: response.razorpay_signature,
                        plan_id: plan.id,
                        billing_cycle: billingCycle,
                    })
                    .then((verifyRes) => {
                        if (verifyRes.data.success) {
                            toast.success(verifyRes.data.message || 'Subscription activated!');
                            router.visit(route('client.billing.index'));
                        } else {
                            toast.error(verifyRes.data.message || 'Verification failed.');
                        }
                    })
                    .catch((err) => {
                        toast.error(err.response?.data?.message || 'Payment verification failed.');
                    });
                },
                theme: {
                    color: '#011B40',
                },
            };

            if (window.Razorpay) {
                const rzp = new window.Razorpay(options);
                rzp.open();
            } else {
                // Load Razorpay script dynamically if not present
                const script = document.createElement('script');
                script.src = 'https://checkout.razorpay.com/v1/checkout.js';
                script.onload = () => {
                    const rzp = new window.Razorpay(options);
                    rzp.open();
                };
                document.body.appendChild(script);
            }
        })
        .catch((err) => {
            toast.error(err.response?.data?.message || 'Failed to initiate checkout.');
        })
        .finally(() => {
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

                                    <div className="space-y-2.5 pt-4 border-t border-slate-100 dark:border-neutral-800 text-xs">
                                        <div className="font-semibold text-slate-700 dark:text-neutral-200">Included Limits:</div>
                                        <div className="text-slate-600 dark:text-neutral-400 space-y-1.5">
                                            <div>• <strong>{plan.limits?.contacts?.toLocaleString() ?? '1,000'}</strong> Contacts</div>
                                            <div>• <strong>{plan.limits?.ai_messages?.toLocaleString() ?? '500'}</strong> AI Messages</div>
                                            <div>• <strong>{plan.limits?.ai_voice_agents ?? '1'}</strong> AI Voice Agent ({plan.limits?.voice_calls ?? 50} calls)</div>
                                            <div>• <strong>{plan.limits?.automation_workflows ?? '5'}</strong> Workflows</div>
                                        </div>
                                    </div>
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
