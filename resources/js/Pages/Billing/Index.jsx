import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Button, Card, Badge } from '@/Components/ui';
import {
    CreditCard, CheckCircle2, AlertTriangle, ArrowRight,
    Sparkles, ShieldCheck, Download, Clock, Layers,
} from 'lucide-react';
import { toast } from 'sonner';

export default function BillingDashboard({
    subscription = null,
    usage = {},
    invoices = [],
}) {
    const plan = subscription?.plan;
    const isTrial = subscription?.is_trialing;
    const trialDays = subscription?.trial_days_remaining ?? 0;

    const renderProgressBar = (label, current, max, unit = '') => {
        const isUnlimited = max === -1 || max === null;
        const percentage = isUnlimited ? 0 : Math.min(100, Math.round((current / max) * 100));
        const isHigh = percentage >= 80;

        return (
            <div className="space-y-1.5 text-xs">
                <div className="flex items-center justify-between">
                    <span className="font-medium text-slate-700 dark:text-neutral-200">{label}</span>
                    <span className="font-mono text-slate-500 dark:text-neutral-400">
                        {current.toLocaleString()} / {isUnlimited ? '∞' : max.toLocaleString()} {unit}
                    </span>
                </div>
                <div className="h-2 w-full rounded-full bg-slate-100 dark:bg-neutral-800 overflow-hidden">
                    <div
                        className={`h-full rounded-full transition-all ${
                            isHigh ? 'bg-amber-500' : 'bg-brand-600'
                        }`}
                        style={{ width: `${isUnlimited ? 5 : percentage}%` }}
                    />
                </div>
            </div>
        );
    };

    const handleCancel = () => {
        if (!confirm('Are you sure you want to cancel your subscription? Your access will remain active until the end of the billing period.')) return;

        router.post(route('client.billing.cancel'), {}, {
            onSuccess: () => toast.success('Subscription cancelled.'),
        });
    };

    return (
        <ClientLayout>
            <Head title="Billing & Subscription — Growbridge Connect" />

            <div className="space-y-6 max-w-5xl mx-auto">
                {/* Trial Alert Banner */}
                {isTrial && (
                    <div className="flex items-center justify-between p-4 rounded-xl bg-gradient-to-r from-accent-500/20 via-accent-500/10 to-transparent border border-accent-500/30 text-xs">
                        <div className="flex items-center gap-3">
                            <Clock className="w-5 h-5 text-accent-600" />
                            <div>
                                <h4 className="font-bold text-slate-900 dark:text-white">
                                    Free Trial Active — {trialDays} {trialDays === 1 ? 'day' : 'days'} remaining
                                </h4>
                                <p className="text-slate-600 dark:text-neutral-300">
                                    Upgrade now to keep your AI voice agents, virtual numbers, and omnichannel automations running uninterrupted.
                                </p>
                            </div>
                        </div>
                        <Link href={route('client.billing.plans')}>
                            <Button size="sm" className="bg-brand-600 hover:bg-brand-700 text-white font-semibold text-xs">
                                Upgrade Plan
                            </Button>
                        </Link>
                    </div>
                )}

                {/* Header & Plan Summary */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 dark:text-white">Billing & Subscription</h1>
                        <p className="text-xs text-slate-500 dark:text-neutral-400">
                            Manage your Growbridge Connect SaaS plan, quota usage, and payment invoices.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link href={route('client.billing.plans')}>
                            <Button className="bg-brand-600 hover:bg-brand-700 text-white gap-1.5 text-xs">
                                <Sparkles className="w-3.5 h-3.5" /> Change / Upgrade Plan
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Plan Overview Card */}
                <Card className="p-6 border-slate-200 dark:border-neutral-800 space-y-5">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-neutral-800">
                        <div>
                            <span className="text-xs text-slate-400 font-medium">CURRENT PLAN</span>
                            <div className="flex items-center gap-2.5 mt-1">
                                <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                                    {plan?.name || 'Free Trial'}
                                </h2>
                                <Badge variant={subscription?.status === 'active' || isTrial ? 'success' : 'neutral'} className="capitalize text-[10px]">
                                    {isTrial ? 'Free Trial' : subscription?.status || 'Active'}
                                </Badge>
                            </div>
                        </div>

                        <div className="text-left sm:text-right">
                            <span className="text-xs text-slate-400 font-medium">RENEWAL / EXPIRY DATE</span>
                            <p className="font-semibold text-sm text-slate-900 dark:text-white mt-1">
                                {subscription?.current_period_end ? new Date(subscription.current_period_end).toLocaleDateString() : 'N/A'}
                            </p>
                        </div>
                    </div>

                    {/* Monthly Usage Meters */}
                    <div className="space-y-4">
                        <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">Monthly Quota & Usage</h3>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {renderProgressBar('Contacts Managed', usage.contacts?.current ?? 0, usage.contacts?.max ?? 1000)}
                            {renderProgressBar('AI Chatbot & Flow Messages', usage.ai_messages?.current ?? 0, usage.ai_messages?.max ?? 500)}
                            {renderProgressBar('AI Voice Calls', usage.voice_calls?.current ?? 0, usage.voice_calls?.max ?? 50)}
                            {renderProgressBar('Outbound Messages (WhatsApp/SMS)', usage.messages?.current ?? 0, usage.messages?.max ?? 5000)}
                            {renderProgressBar('Automation Workflows', usage.automations?.current ?? 0, usage.automations?.max ?? 10)}
                        </div>
                    </div>

                    {subscription && subscription.status === 'active' && (
                        <div className="pt-3 border-t border-slate-100 dark:border-neutral-800 flex justify-end">
                            <Button type="button" variant="ghost" size="sm" onClick={handleCancel} className="text-rose-600 hover:text-rose-700 text-xs">
                                Cancel Subscription
                            </Button>
                        </div>
                    )}
                </Card>

                {/* Invoices List */}
                <Card className="border-slate-200 dark:border-neutral-800 overflow-hidden">
                    <div className="p-4 border-b border-slate-100 dark:border-neutral-800">
                        <h3 className="font-bold text-sm text-slate-900 dark:text-white">Payment Invoices</h3>
                    </div>

                    {invoices.length === 0 ? (
                        <div className="p-8 text-center text-xs text-slate-400">
                            No invoices generated yet.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-50 dark:bg-neutral-800/60 text-slate-500 dark:text-neutral-400 uppercase font-semibold border-b border-slate-200 dark:border-neutral-800">
                                    <tr>
                                        <th className="px-5 py-3">Invoice #</th>
                                        <th className="px-5 py-3">Date</th>
                                        <th className="px-5 py-3">Amount</th>
                                        <th className="px-5 py-3">Status</th>
                                        <th className="px-5 py-3">Method</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-neutral-800">
                                    {invoices.map((inv) => (
                                        <tr key={inv.id} className="hover:bg-slate-50/50 dark:hover:bg-neutral-800/30">
                                            <td className="px-5 py-3 font-mono font-medium text-slate-900 dark:text-white">
                                                {inv.invoice_number}
                                            </td>
                                            <td className="px-5 py-3 text-slate-500">
                                                {new Date(inv.created_at).toLocaleDateString()}
                                            </td>
                                            <td className="px-5 py-3 font-semibold text-slate-900 dark:text-white">
                                                ₹{(inv.total_cents / 100).toFixed(2)}
                                            </td>
                                            <td className="px-5 py-3">
                                                <Badge variant={inv.status === 'paid' ? 'success' : 'neutral'} className="capitalize">
                                                    {inv.status}
                                                </Badge>
                                            </td>
                                            <td className="px-5 py-3 text-slate-600 dark:text-neutral-300 uppercase font-mono text-[11px]">
                                                {inv.payment_method}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
            </div>
        </ClientLayout>
    );
}
