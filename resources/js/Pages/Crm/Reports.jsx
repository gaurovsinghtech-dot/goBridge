import React from 'react';
import { Head, Link } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { TrendingUp, Award, BarChart3, ChevronLeft, ArrowUpRight, DollarSign, Layers } from 'lucide-react';

export default function CrmReports({ kpis, attribution, funnel, days }) {
    const formatCurrency = (val) => {
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            maximumFractionDigits: 0,
        }).format(val || 0);
    };

    return (
        <ClientLayout>
            <Head title="CRM Sales Reports & Attribution" />

            <div className="p-6 space-y-6 max-w-6xl mx-auto">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-neutral-900 dark:text-white">Sales & Channel Attribution Reports</h1>
                        <p className="text-sm text-neutral-500 mt-1">Measure ROI across WhatsApp, Instagram, Google Ads, and AI conversions.</p>
                    </div>

                    <Link
                        href={route('client.crm.dashboard')}
                        className="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-200"
                    >
                        <ChevronLeft className="h-4 w-4" />
                        Pipeline Board
                    </Link>
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 shadow-xs">
                        <span className="text-xs font-semibold text-neutral-500">Won Revenue</span>
                        <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                            {formatCurrency(kpis?.pipeline_value)}
                        </p>
                    </div>
                    <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 shadow-xs">
                        <span className="text-xs font-semibold text-neutral-500">Win Rate %</span>
                        <p className="text-2xl font-bold text-brand-600 dark:text-brand-400 mt-1">
                            {kpis?.conversion_rate || 0}%
                        </p>
                    </div>
                    <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 shadow-xs">
                        <span className="text-xs font-semibold text-neutral-500">Won Deals</span>
                        <p className="text-2xl font-bold text-neutral-900 dark:text-white mt-1">
                            {kpis?.won_deals || 0}
                        </p>
                    </div>
                    <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 shadow-xs">
                        <span className="text-xs font-semibold text-neutral-500">Qualified Leads</span>
                        <p className="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                            {kpis?.qualified_leads || 0}
                        </p>
                    </div>
                </div>

                {/* Channel Source Attribution Table */}
                <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 shadow-xs space-y-4">
                    <h3 className="font-bold text-base text-neutral-900 dark:text-white">Channel Source Performance & Attribution</h3>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-neutral-200 dark:border-neutral-800 text-xs text-neutral-500 uppercase">
                                <tr>
                                    <th className="py-3 px-4">Channel / Source</th>
                                    <th className="py-3 px-4">Total Leads</th>
                                    <th className="py-3 px-4">Won Deals</th>
                                    <th className="py-3 px-4">Pipeline Value</th>
                                    <th className="py-3 px-4">Conversion Rate</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                {attribution?.map((attr) => (
                                    <tr key={attr.source} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40">
                                        <td className="py-3.5 px-4 font-semibold text-neutral-900 dark:text-white">
                                            {attr.label}
                                        </td>
                                        <td className="py-3.5 px-4 text-neutral-700 dark:text-neutral-300">
                                            {attr.leads}
                                        </td>
                                        <td className="py-3.5 px-4 font-bold text-emerald-600">
                                            {attr.won}
                                        </td>
                                        <td className="py-3.5 px-4 font-mono font-medium">
                                            {formatCurrency(attr.total_value)}
                                        </td>
                                        <td className="py-3.5 px-4">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-xs">{attr.conversion_pct}%</span>
                                                <div className="w-16 h-1.5 rounded-full bg-neutral-200 dark:bg-neutral-700 overflow-hidden">
                                                    <div
                                                        className="h-full bg-emerald-500 rounded-full"
                                                        style={{ width: `${Math.min(100, attr.conversion_pct)}%` }}
                                                    />
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </ClientLayout>
    );
}
