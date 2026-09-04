import React, { useState } from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    Wallet as WalletIcon,
    Plus,
    CreditCard,
    ArrowUpRight,
    ArrowDownLeft,
    AlertTriangle,
    BellRing,
    RefreshCw,
    MessageSquare,
    Bot,
    PhoneCall,
    Sliders,
    CheckCircle2,
    ShieldAlert,
    TrendingUp,
} from 'lucide-react';
import { toast } from 'sonner';

export default function Wallet({ wallet, transactions = [], usage_breakdown = {} }) {
    const { t } = useTranslation();
    const [topupModalOpen, setTopupModalOpen] = useState(false);
    const [settingsModalOpen, setSettingsModalOpen] = useState(false);

    // Top-up Form
    const topupForm = useForm({
        amount_in_rupees: 1000,
    });

    // Preferences Form
    const settingsForm = useForm({
        low_balance_threshold_rupees: wallet.low_balance_threshold_cents / 100,
        low_balance_alert_enabled: Boolean(wallet.low_balance_alert_enabled),
        auto_recharge_enabled: Boolean(wallet.auto_recharge_enabled),
        auto_recharge_amount_rupees: wallet.auto_recharge_amount_cents / 100,
    });

    const handleTopupSubmit = (e) => {
        e.preventDefault();
        topupForm.post(route('client.billing.wallet.topup'), {
            onSuccess: () => {
                setTopupModalOpen(false);
                toast.success(t('wallet.topup_success', 'Funds added to your Growbridge Wallet!'));
            },
        });
    };

    const handleSettingsSubmit = (e) => {
        e.preventDefault();
        settingsForm.post(route('client.billing.wallet.settings'), {
            onSuccess: () => {
                setSettingsModalOpen(false);
                toast.success(t('wallet.settings_success', 'Wallet preferences updated!'));
            },
        });
    };

    const quickRechargePacks = [500, 1000, 2500, 5000];

    return (
        <ClientLayout title={t('wallet.title', 'Growbridge Balance & Usage')}>
            <Head title={t('wallet.title', 'Growbridge Balance & Usage')} />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-neutral-900 dark:text-white flex items-center gap-2.5">
                            <WalletIcon className="h-6 w-6 text-emerald-500" />
                            {t('wallet.title', 'Growbridge Wallet & Usage')}
                        </h1>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
                            {t('wallet.subtitle', 'Pay for software and metered consumption from one unified Growbridge balance.')}
                        </p>
                    </div>

                    <div className="flex items-center gap-2.5">
                        <button
                            type="button"
                            onClick={() => setSettingsModalOpen(true)}
                            className="px-4 py-2 rounded-xl text-sm font-medium border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700/50 transition shadow-sm flex items-center gap-2"
                        >
                            <Sliders className="h-4 w-4" />
                            {t('wallet.manage_settings', 'Settings')}
                        </button>
                        <button
                            type="button"
                            onClick={() => setTopupModalOpen(true)}
                            className="px-5 py-2 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/20 transition flex items-center gap-2"
                        >
                            <Plus className="h-4 w-4" />
                            {t('wallet.add_money', 'Add Balance')}
                        </button>
                    </div>
                </div>

                {/* Low Balance Warning Banner */}
                {wallet.is_low_balance && (
                    <div className="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-900 dark:text-amber-300 flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <AlertTriangle className="h-5 w-5 text-amber-500 shrink-0" />
                            <div className="text-sm">
                                <span className="font-semibold">{t('wallet.low_balance_warning', 'Low Balance Warning:')}</span>{' '}
                                {t('wallet.low_balance_desc', 'Your balance is below')} {wallet.low_balance_threshold_formatted}.{' '}
                                {t('wallet.low_balance_action', 'Top up now to prevent automated campaigns and AI replies from pausing.')}
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={() => setTopupModalOpen(true)}
                            className="shrink-0 px-3 py-1.5 rounded-lg text-xs font-bold text-amber-950 bg-amber-400 hover:bg-amber-300 transition"
                        >
                            {t('wallet.recharge_now', 'Recharge')}
                        </button>
                    </div>
                )}

                {/* Grid: 1. Balance Card, 2. Monthly Metered Usage Breakdown */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Available Balance Card */}
                    <div className="rounded-3xl bg-gradient-to-br from-emerald-950 via-neutral-900 to-neutral-950 border border-emerald-500/30 p-6 text-white shadow-xl relative overflow-hidden flex flex-col justify-between">
                        <div className="absolute -top-12 -right-12 w-40 h-40 bg-emerald-500/15 rounded-full blur-2xl pointer-events-none" />

                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold uppercase tracking-wider text-emerald-400">
                                    {t('wallet.available_balance', 'Available Balance')}
                                </span>
                                <span className="text-xs px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                    {wallet.currency}
                                </span>
                            </div>

                            <div>
                                <div className="text-4xl font-extrabold tracking-tight">
                                    {wallet.balance_formatted}
                                </div>
                                <p className="text-xs text-neutral-400 mt-1">
                                    {t('wallet.single_bill_notice', 'Deductions occur automatically for WhatsApp, AI tokens, and telephony.')}
                                </p>
                            </div>
                        </div>

                        <div className="pt-6 border-t border-white/10 mt-6 flex items-center justify-between">
                            <div className="text-xs text-neutral-300">
                                <div>{t('wallet.estimated_spend', 'Est. Monthly Usage:')}</div>
                                <span className="font-semibold text-white">{usage_breakdown.estimated_spend_formatted}</span>
                            </div>
                            <button
                                type="button"
                                onClick={() => setTopupModalOpen(true)}
                                className="px-4 py-2 rounded-xl text-xs font-bold text-neutral-950 bg-emerald-400 hover:bg-emerald-300 transition shadow"
                            >
                                + {t('wallet.quick_add', 'Add Money')}
                            </button>
                        </div>
                    </div>

                    {/* Monthly Metered Usage Breakdown */}
                    <div className="lg:col-span-2 rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 p-6 shadow-sm space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-base font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                <TrendingUp className="h-5 w-5 text-emerald-500" />
                                {t('wallet.usage_this_month', 'Metered Consumption (This Month)')}
                            </h2>
                            <span className="text-xs text-neutral-500">
                                {t('wallet.growbridge_managed_billing', 'Unified Growbridge Billing')}
                            </span>
                        </div>

                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2">
                            {/* WhatsApp Messages */}
                            <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-100 dark:border-neutral-800 space-y-1">
                                <div className="flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400">
                                    <MessageSquare className="h-3.5 w-3.5 text-emerald-500" />
                                    WhatsApp
                                </div>
                                <div className="text-xl font-bold text-neutral-900 dark:text-white">
                                    {Number(usage_breakdown.whatsapp_messages || 0).toLocaleString()}
                                </div>
                                <div className="text-[11px] text-neutral-400">Messages sent</div>
                            </div>

                            {/* AI Messages */}
                            <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-100 dark:border-neutral-800 space-y-1">
                                <div className="flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400">
                                    <Bot className="h-3.5 w-3.5 text-indigo-500" />
                                    AI Agents
                                </div>
                                <div className="text-xl font-bold text-neutral-900 dark:text-white">
                                    {Number(usage_breakdown.ai_messages || 0).toLocaleString()}
                                </div>
                                <div className="text-[11px] text-neutral-400">AI responses</div>
                            </div>

                            {/* Voice Minutes */}
                            <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-100 dark:border-neutral-800 space-y-1">
                                <div className="flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400">
                                    <PhoneCall className="h-3.5 w-3.5 text-amber-500" />
                                    Voice
                                </div>
                                <div className="text-xl font-bold text-neutral-900 dark:text-white">
                                    {Number(usage_breakdown.voice_minutes || 0).toLocaleString()} <span className="text-xs font-normal">min</span>
                                </div>
                                <div className="text-[11px] text-neutral-400">{usage_breakdown.voice_calls || 0} calls</div>
                            </div>

                            {/* Estimated Usage Cost */}
                            <div className="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/25 space-y-1">
                                <div className="flex items-center gap-1.5 text-xs text-emerald-700 dark:text-emerald-400 font-medium">
                                    <CreditCard className="h-3.5 w-3.5 text-emerald-500" />
                                    Usage Cost
                                </div>
                                <div className="text-xl font-bold text-emerald-700 dark:text-emerald-300">
                                    {usage_breakdown.estimated_spend_formatted}
                                </div>
                                <div className="text-[11px] text-emerald-600/70 dark:text-emerald-400/70">From Wallet</div>
                            </div>
                        </div>

                        <p className="text-xs text-neutral-500 dark:text-neutral-400 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                            💡 {t('wallet.no_provider_accounts', "You don't need separate billing accounts with Meta, Twilio or OpenAI. Growbridge handles all underlying provider infrastructure and debits your wallet directly.")}
                        </p>
                    </div>
                </div>

                {/* Recent Wallet Transactions Ledger */}
                <div className="rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 p-6 shadow-sm space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-base font-bold text-neutral-900 dark:text-white">
                            {t('wallet.transaction_history', 'Transaction History & Deductions')}
                        </h2>
                        <span className="text-xs text-neutral-400">
                            {transactions.length} transactions
                        </span>
                    </div>

                    {transactions.length === 0 ? (
                        <div className="py-12 text-center text-sm text-neutral-400 space-y-2">
                            <WalletIcon className="h-8 w-8 mx-auto text-neutral-300 dark:text-neutral-700" />
                            <div>{t('wallet.no_transactions', 'No transactions recorded yet.')}</div>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-neutral-200 dark:border-neutral-800 text-xs font-semibold text-neutral-500 dark:text-neutral-400">
                                        <th className="py-3 px-3">Date</th>
                                        <th className="py-3 px-3">Category</th>
                                        <th className="py-3 px-3">Description</th>
                                        <th className="py-3 px-3 text-right">Amount</th>
                                        <th className="py-3 px-3 text-right">Balance</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                    {transactions.map((tx) => (
                                        <tr key={tx.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition">
                                            <td className="py-3 px-3 text-xs text-neutral-500 whitespace-nowrap">
                                                {tx.created_at}
                                            </td>
                                            <td className="py-3 px-3">
                                                <span className={[
                                                    'text-[11px] font-semibold px-2 py-0.5 rounded-full capitalize',
                                                    tx.type === 'credit'
                                                        ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                                        : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300',
                                                ].join(' ')}>
                                                    {tx.category.replace('_', ' ')}
                                                </span>
                                            </td>
                                            <td className="py-3 px-3 text-xs text-neutral-700 dark:text-neutral-300 max-w-md truncate">
                                                {tx.description}
                                            </td>
                                            <td className={[
                                                'py-3 px-3 text-xs font-bold text-right whitespace-nowrap',
                                                tx.type === 'credit' ? 'text-emerald-600 dark:text-emerald-400' : 'text-neutral-900 dark:text-white',
                                            ].join(' ')}>
                                                {tx.amount_formatted}
                                            </td>
                                            <td className="py-3 px-3 text-xs text-neutral-500 text-right whitespace-nowrap">
                                                {tx.balance_after_formatted}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Top-up Balance Modal */}
            {topupModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in">
                    <div className="w-full max-w-md rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-2xl p-6 space-y-5">
                        <div className="flex items-center justify-between">
                            <h3 className="text-lg font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                <Plus className="h-5 w-5 text-emerald-500" />
                                {t('wallet.topup_heading', 'Add Growbridge Balance')}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setTopupModalOpen(false)}
                                className="text-neutral-400 hover:text-neutral-600 dark:hover:text-white"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleTopupSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                                    {t('wallet.choose_amount', 'Choose or Enter Amount (INR)')}
                                </label>
                                <div className="grid grid-cols-4 gap-2 mb-3">
                                    {quickRechargePacks.map((pack) => (
                                        <button
                                            key={pack}
                                            type="button"
                                            onClick={() => topupForm.setData('amount_in_rupees', pack)}
                                            className={[
                                                'py-2 rounded-xl text-xs font-bold border transition',
                                                topupForm.data.amount_in_rupees === pack
                                                    ? 'bg-emerald-500 text-white border-emerald-600 shadow'
                                                    : 'bg-neutral-50 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100',
                                            ].join(' ')}
                                        >
                                            ₹{pack}
                                        </button>
                                    ))}
                                </div>
                                <div className="relative">
                                    <span className="absolute inset-y-0 left-0 pl-3 flex items-center text-neutral-400 font-bold">
                                        ₹
                                    </span>
                                    <input
                                        type="number"
                                        min="100"
                                        max="500000"
                                        value={topupForm.data.amount_in_rupees}
                                        onChange={(e) => topupForm.setData('amount_in_rupees', Number(e.target.value))}
                                        className="w-full pl-8 pr-4 py-2.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm font-semibold"
                                        required
                                    />
                                </div>
                            </div>

                            <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/70 border border-neutral-200 dark:border-neutral-700 text-xs text-neutral-500 space-y-1">
                                <div className="font-semibold text-neutral-700 dark:text-neutral-300">GST & Invoicing:</div>
                                <div>Consolidated tax invoices are generated automatically with GST breakdown for your business records.</div>
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setTopupModalOpen(false)}
                                    className="px-4 py-2 text-sm text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300"
                                >
                                    {t('common.cancel', 'Cancel')}
                                </button>
                                <button
                                    type="submit"
                                    disabled={topupForm.processing}
                                    className="px-6 py-2 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md transition"
                                >
                                    {t('wallet.confirm_deposit', 'Confirm & Recharge')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Wallet Settings Modal */}
            {settingsModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in">
                    <div className="w-full max-w-md rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-2xl p-6 space-y-5">
                        <div className="flex items-center justify-between">
                            <h3 className="text-lg font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                <Sliders className="h-5 w-5 text-emerald-500" />
                                {t('wallet.settings_heading', 'Balance & Alert Preferences')}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setSettingsModalOpen(false)}
                                className="text-neutral-400 hover:text-neutral-600 dark:hover:text-white"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleSettingsSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                    {t('wallet.low_balance_threshold', 'Low-Balance Alert Threshold (₹)')}
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    value={settingsForm.data.low_balance_threshold_rupees}
                                    onChange={(e) => settingsForm.setData('low_balance_threshold_rupees', Number(e.target.value))}
                                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm"
                                    required
                                />
                                <p className="text-[11px] text-neutral-400 mt-1">
                                    Notify your team when balance drops below this amount.
                                </p>
                            </div>

                            <div className="space-y-3 pt-2">
                                <label className="flex items-center gap-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={settingsForm.data.low_balance_alert_enabled}
                                        onChange={(e) => settingsForm.setData('low_balance_alert_enabled', e.target.checked)}
                                        className="h-4 w-4 rounded border-neutral-300 text-emerald-600 focus:ring-emerald-500"
                                    />
                                    <span className="text-xs font-medium text-neutral-700 dark:text-neutral-300">
                                        Send Email & WhatsApp Low-Balance alerts
                                    </span>
                                </label>
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-4 border-t border-neutral-100 dark:border-neutral-800">
                                <button
                                    type="button"
                                    onClick={() => setSettingsModalOpen(false)}
                                    className="px-4 py-2 text-sm text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300"
                                >
                                    {t('common.cancel', 'Cancel')}
                                </button>
                                <button
                                    type="submit"
                                    disabled={settingsForm.processing}
                                    className="px-5 py-2 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md transition"
                                >
                                    {t('common.save', 'Save Changes')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </ClientLayout>
    );
}
