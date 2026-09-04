import React, { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    IndianRupee,
    TrendingUp,
    ShieldCheck,
    AlertCircle,
    Sliders,
    Layers,
    Server,
    DollarSign,
    Percent,
    ArrowUpRight,
    Edit2,
    CheckCircle2,
    RefreshCw,
    Activity,
} from 'lucide-react';
import { toast } from 'sonner';

export default function ProviderCostLedger({
    financials = {},
    provider_accounts = [],
    pricing_rules = [],
    recent_usage = [],
}) {
    const { t } = useTranslation();
    const [editingRule, setEditingRule] = useState(null);

    // Edit Pricing Rule Form
    const editForm = useForm({
        provider_cost_rupees: 0,
        customer_price_rupees: 0,
        is_active: true,
    });

    const openEditRuleModal = (rule) => {
        setEditingRule(rule);
        editForm.setData({
            provider_cost_rupees: rule.provider_cost_cents / 100,
            customer_price_rupees: rule.customer_price_cents / 100,
            is_active: Boolean(rule.is_active),
        });
    };

    const handleUpdateRule = (e) => {
        e.preventDefault();
        if (!editingRule) return;

        editForm.put(route('admin.billing.pricing-rules.update', editingRule.id), {
            onSuccess: () => {
                setEditingRule(null);
                toast.success('Pricing rule updated successfully!');
            },
        });
    };

    return (
        <AdminLayout title="Provider Cost Ledger & Margins">
            <Head title="Provider Cost Ledger & Margins" />

            <div className="max-w-7xl mx-auto space-y-8 p-4 sm:p-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-neutral-900 dark:text-white flex items-center gap-2.5">
                            <IndianRupee className="h-6 w-6 text-emerald-500" />
                            Provider Cost Ledger & Revenue Analytics
                        </h1>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
                            Centralized billing management: Track underlying provider expenses (Meta, Twilio, OpenAI), customer retail markups, and gross margins.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <span className="text-xs px-3 py-1 rounded-full font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex items-center gap-1.5">
                            <Activity className="h-3.5 w-3.5" /> Margin: {financials.gross_margin_percent}%
                        </span>
                    </div>
                </div>

                {/* 1. Revenue & Margin KPI Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    {/* Gross Revenue */}
                    <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm space-y-2">
                        <div className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                            Gross Revenue
                        </div>
                        <div className="text-2xl font-extrabold text-neutral-900 dark:text-white">
                            {financials.gross_revenue_formatted}
                        </div>
                        <div className="text-[11px] text-neutral-400">
                            Sub: {financials.subscription_revenue_formatted} + Usage: {financials.usage_revenue_formatted}
                        </div>
                    </div>

                    {/* Total Provider Costs */}
                    <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm space-y-2">
                        <div className="text-xs font-semibold text-rose-500 dark:text-rose-400 uppercase tracking-wider">
                            Total Provider Costs
                        </div>
                        <div className="text-2xl font-extrabold text-rose-600 dark:text-rose-400">
                            {financials.total_provider_cost_formatted}
                        </div>
                        <div className="text-[11px] text-neutral-400">
                            Meta + Twilio + OpenAI + SMTP Relay
                        </div>
                    </div>

                    {/* Gross Margin */}
                    <div className="p-5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 shadow-sm space-y-2">
                        <div className="text-xs font-semibold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">
                            Gross Margin
                        </div>
                        <div className="text-2xl font-extrabold text-emerald-700 dark:text-emerald-300">
                            {financials.gross_margin_formatted}
                        </div>
                        <div className="text-[11px] text-emerald-600/80 dark:text-emerald-400/80 font-medium">
                            {financials.gross_margin_percent}% Net Profitability
                        </div>
                    </div>

                    {/* Usage Billing Revenue */}
                    <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm space-y-2">
                        <div className="text-xs font-semibold text-indigo-500 dark:text-indigo-400 uppercase tracking-wider">
                            Metered Usage Revenue
                        </div>
                        <div className="text-2xl font-extrabold text-neutral-900 dark:text-white">
                            {financials.usage_revenue_formatted}
                        </div>
                        <div className="text-[11px] text-neutral-400">
                            Automated Wallet Deductions
                        </div>
                    </div>
                </div>

                {/* 2. Provider Infrastructure Health & Balances */}
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-base font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                            <Server className="h-5 w-5 text-emerald-500" />
                            Provider Infrastructure & Health
                        </h2>
                        <span className="text-xs text-neutral-400">Low-balance protection active</span>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        {provider_accounts.map((acc) => (
                            <div
                                key={acc.provider}
                                className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-2"
                            >
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-bold text-neutral-800 dark:text-neutral-200 uppercase">
                                        {acc.provider}
                                    </span>
                                    <span className="flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                        <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" /> {acc.status}
                                    </span>
                                </div>
                                <div className="text-sm font-semibold text-neutral-900 dark:text-white truncate">
                                    {acc.name}
                                </div>
                                <div className="pt-2 border-t border-neutral-100 dark:border-neutral-800 flex justify-between text-xs">
                                    <span className="text-neutral-400">Spend:</span>
                                    <span className="font-bold text-neutral-800 dark:text-neutral-200">{acc.monthly_spend_formatted}</span>
                                </div>
                                {acc.balance_formatted && (
                                    <div className="flex justify-between text-xs">
                                        <span className="text-neutral-400">Balance:</span>
                                        <span className="font-bold text-emerald-600 dark:text-emerald-400">{acc.balance_formatted}</span>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                {/* 3. Service Pricing & Retail Markup Rules Table */}
                <div className="rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 p-6 shadow-sm space-y-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-base font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                <Sliders className="h-5 w-5 text-emerald-500" />
                                Service Pricing & Markup Rules
                            </h2>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                Set unit cost paid to providers vs retail rate charged to customer wallets.
                            </p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-neutral-200 dark:border-neutral-800 text-xs font-semibold text-neutral-500 dark:text-neutral-400">
                                    <th className="py-3 px-3">Service Module</th>
                                    <th className="py-3 px-3">Provider</th>
                                    <th className="py-3 px-3">Billing Unit</th>
                                    <th className="py-3 px-3 text-right">Provider Base Cost</th>
                                    <th className="py-3 px-3 text-right">Customer Retail Price</th>
                                    <th className="py-3 px-3 text-right">Unit Margin</th>
                                    <th className="py-3 px-3 text-center">Status</th>
                                    <th className="py-3 px-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                {pricing_rules.map((rule) => (
                                    <tr key={rule.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition">
                                        <td className="py-3.5 px-3 font-semibold text-neutral-900 dark:text-white capitalize">
                                            {rule.service.replace(/_/g, ' ')}
                                        </td>
                                        <td className="py-3.5 px-3">
                                            <span className="text-xs px-2 py-0.5 rounded-md font-semibold bg-neutral-100 dark:bg-neutral-800 uppercase text-neutral-700 dark:text-neutral-300">
                                                {rule.provider}
                                            </span>
                                        </td>
                                        <td className="py-3.5 px-3 text-xs text-neutral-500">
                                            per {rule.unit}
                                        </td>
                                        <td className="py-3.5 px-3 text-xs text-rose-500 font-semibold text-right">
                                            {rule.provider_cost_formatted}
                                        </td>
                                        <td className="py-3.5 px-3 text-xs text-neutral-900 dark:text-white font-bold text-right">
                                            {rule.customer_price_formatted}
                                        </td>
                                        <td className="py-3.5 px-3 text-xs text-emerald-600 dark:text-emerald-400 font-bold text-right">
                                            +{rule.margin_formatted} ({rule.margin_percent}%)
                                        </td>
                                        <td className="py-3.5 px-3 text-center">
                                            <span className={[
                                                'text-[10px] font-bold px-2 py-0.5 rounded-full',
                                                rule.is_active ? 'bg-emerald-500/15 text-emerald-600' : 'bg-neutral-200 text-neutral-600',
                                            ].join(' ')}>
                                                {rule.is_active ? 'Active' : 'Disabled'}
                                            </span>
                                        </td>
                                        <td className="py-3.5 px-3 text-right">
                                            <button
                                                type="button"
                                                onClick={() => openEditRuleModal(rule)}
                                                className="p-1.5 rounded-lg text-neutral-500 hover:text-emerald-600 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                                            >
                                                <Edit2 className="h-4 w-4" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* 4. Live Metered Usage & Margin Stream */}
                <div className="rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 p-6 shadow-sm space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-base font-bold text-neutral-900 dark:text-white">
                            Live Metered Usage & Margin Stream
                        </h2>
                        <span className="text-xs text-neutral-400">{recent_usage.length} recent events</span>
                    </div>

                    {recent_usage.length === 0 ? (
                        <div className="py-8 text-center text-sm text-neutral-400">
                            No metered events logged yet.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-neutral-200 dark:border-neutral-800 text-xs font-semibold text-neutral-500 dark:text-neutral-400">
                                        <th className="py-3 px-3">Date</th>
                                        <th className="py-3 px-3">Workspace</th>
                                        <th className="py-3 px-3">Service</th>
                                        <th className="py-3 px-3">Provider</th>
                                        <th className="py-3 px-3">Quantity</th>
                                        <th className="py-3 px-3 text-right">Cost</th>
                                        <th className="py-3 px-3 text-right">Charged</th>
                                        <th className="py-3 px-3 text-right">Gross Margin</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                    {recent_usage.map((u) => (
                                        <tr key={u.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition">
                                            <td className="py-3 px-3 text-xs text-neutral-500 whitespace-nowrap">{u.recorded_at}</td>
                                            <td className="py-3 px-3 text-xs font-semibold text-neutral-900 dark:text-white">{u.workspace_name}</td>
                                            <td className="py-3 px-3 text-xs capitalize">{u.service.replace(/_/g, ' ')}</td>
                                            <td className="py-3 px-3 text-xs font-bold text-neutral-700 dark:text-neutral-300">{u.provider}</td>
                                            <td className="py-3 px-3 text-xs text-neutral-500">{u.quantity}</td>
                                            <td className="py-3 px-3 text-xs text-rose-500 text-right">{u.provider_cost_formatted}</td>
                                            <td className="py-3 px-3 text-xs font-bold text-neutral-900 dark:text-white text-right">{u.customer_charge_formatted}</td>
                                            <td className="py-3 px-3 text-xs font-bold text-emerald-600 dark:text-emerald-400 text-right">+{u.gross_margin_formatted}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Edit Pricing Rule Modal */}
            {editingRule && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in">
                    <div className="w-full max-w-md rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-2xl p-6 space-y-5">
                        <div className="flex items-center justify-between">
                            <h3 className="text-lg font-bold text-neutral-900 dark:text-white capitalize">
                                Edit Pricing: {editingRule.service.replace(/_/g, ' ')}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setEditingRule(null)}
                                className="text-neutral-400 hover:text-neutral-600 dark:hover:text-white"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleUpdateRule} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                    Provider Base Cost (₹)
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={editForm.data.provider_cost_rupees}
                                    onChange={(e) => editForm.setData('provider_cost_rupees', Number(e.target.value))}
                                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                    Customer Retail Price (₹)
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={editForm.data.customer_price_rupees}
                                    onChange={(e) => editForm.setData('customer_price_rupees', Number(e.target.value))}
                                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm font-bold text-emerald-600 dark:text-emerald-400"
                                    required
                                />
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-4 border-t border-neutral-100 dark:border-neutral-800">
                                <button
                                    type="button"
                                    onClick={() => setEditingRule(null)}
                                    className="px-4 py-2 text-sm text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={editForm.processing}
                                    className="px-5 py-2 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md transition"
                                >
                                    Save Pricing Rule
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
