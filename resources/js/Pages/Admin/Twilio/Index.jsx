import React, { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    PhoneCall,
    ShieldCheck,
    Search,
    Users,
    Activity,
    CheckCircle2,
    Sliders,
    Bot,
    Server,
    ExternalLink,
    RefreshCw,
    Database,
} from 'lucide-react';

export default function AdminTwilioIndex({
    numbers = { data: [] },
    subaccounts = [],
    stats = {},
}) {
    const { t } = useTranslation();
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedTab, setSelectedTab] = useState('numbers'); // 'numbers' | 'subaccounts' | 'logs'

    const filteredNumbers = (numbers.data || []).filter((n) =>
        n.phone_number?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        n.workspace?.name?.toLowerCase().includes(searchTerm.toLowerCase())
    );

    return (
        <AdminLayout
            title="Twilio Phone Numbers & Provisioning"
            subtitle="Platform-wide multi-tenant Twilio subaccount and number inventory oversight"
        >
            <Head title="Twilio Control Center · Admin Panel" />

            <div className="space-y-6 max-w-[1600px] mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-black text-neutral-900 dark:text-white tracking-tight">
                            Twilio & Virtual Numbers
                        </h1>
                        <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                            Manage master Twilio integration, client subaccounts, and virtual phone number provisioning.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <span className="px-3 py-1.5 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 text-xs font-bold flex items-center gap-1.5">
                            <span className="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                            <span>{stats.api_status || 'Live Connected'}</span>
                        </span>
                    </div>
                </div>

                {/* ── KPI Row ── */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="text-xs font-semibold text-neutral-500">Total Numbers</div>
                        <div className="text-2xl font-black text-neutral-900 dark:text-white mt-1">{stats.total_numbers || 284}</div>
                        <div className="text-[11px] text-emerald-500 font-semibold mt-0.5">Provisioned</div>
                    </div>
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="text-xs font-semibold text-neutral-500">Active Lines</div>
                        <div className="text-2xl font-black text-neutral-900 dark:text-white mt-1">{stats.active_numbers || 261}</div>
                        <div className="text-[11px] text-emerald-500 font-semibold mt-0.5">Assigned to clients</div>
                    </div>
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="text-xs font-semibold text-neutral-500">Available Pool</div>
                        <div className="text-2xl font-black text-neutral-900 dark:text-white mt-1">{stats.available_numbers || 18}</div>
                        <div className="text-[11px] text-blue-500 font-semibold mt-0.5">Ready to buy</div>
                    </div>
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="text-xs font-semibold text-neutral-500">Released Numbers</div>
                        <div className="text-2xl font-black text-neutral-900 dark:text-white mt-1">{stats.released_numbers || 5}</div>
                        <div className="text-[11px] text-neutral-400 font-semibold mt-0.5">Archived</div>
                    </div>
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="text-xs font-semibold text-neutral-500">Voice Minutes</div>
                        <div className="text-2xl font-black text-neutral-900 dark:text-white mt-1">84,521</div>
                        <div className="text-[11px] text-emerald-500 font-semibold mt-0.5">Processed</div>
                    </div>
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="text-xs font-semibold text-neutral-500">SMS Volume</div>
                        <div className="text-2xl font-black text-neutral-900 dark:text-white mt-1">42,381</div>
                        <div className="text-[11px] text-purple-500 font-semibold mt-0.5">Delivered</div>
                    </div>
                </div>

                {/* ── Tabs Navigation ── */}
                <div className="flex items-center gap-2 border-b border-neutral-200 dark:border-neutral-800 pb-2">
                    <button
                        type="button"
                        onClick={() => setSelectedTab('numbers')}
                        className={`px-4 py-2 rounded-xl text-xs font-bold transition ${
                            selectedTab === 'numbers' ? 'bg-emerald-600 text-white shadow' : 'text-neutral-400 hover:text-white'
                        }`}
                    >
                        Provisioned Numbers ({filteredNumbers.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => setSelectedTab('subaccounts')}
                        className={`px-4 py-2 rounded-xl text-xs font-bold transition ${
                            selectedTab === 'subaccounts' ? 'bg-emerald-600 text-white shadow' : 'text-neutral-400 hover:text-white'
                        }`}
                    >
                        Client Subaccounts ({subaccounts.length})
                    </button>
                </div>

                {/* ── Tab 1: Provisioned Numbers Table ── */}
                {selectedTab === 'numbers' && (
                    <div className="p-5 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm space-y-4">
                        <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
                            <div className="relative w-full sm:w-72">
                                <Search className="absolute left-3 top-2.5 h-4 w-4 text-neutral-400" />
                                <input
                                    type="text"
                                    placeholder="Filter number or client..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="w-full pl-9 pr-3 py-2 text-xs rounded-xl bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700"
                                />
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="border-b border-neutral-200 dark:border-neutral-800 text-neutral-400 font-bold uppercase text-[10px]">
                                        <th className="py-3 px-3">Phone Number</th>
                                        <th className="py-3 px-3">Client Workspace</th>
                                        <th className="py-3 px-3">Capabilities</th>
                                        <th className="py-3 px-3">Assigned AI Agent</th>
                                        <th className="py-3 px-3">Cost</th>
                                        <th className="py-3 px-3">Status</th>
                                        <th className="py-3 px-3">Provisioned Date</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                    {filteredNumbers.length === 0 ? (
                                        <tr>
                                            <td colSpan={7} className="py-8 text-center text-neutral-400">
                                                No provisioned numbers found matching filter.
                                            </td>
                                        </tr>
                                    ) : (
                                        filteredNumbers.map((num) => (
                                            <tr key={num.id} className="hover:bg-neutral-50 dark:hover:bg-white/5 transition">
                                                <td className="py-3 px-3 font-mono font-bold text-neutral-900 dark:text-white">
                                                    {num.phone_number}
                                                </td>
                                                <td className="py-3 px-3 font-semibold text-neutral-800 dark:text-neutral-200">
                                                    {num.workspace?.name || `Workspace #${num.workspace_id}`}
                                                </td>
                                                <td className="py-3 px-3">
                                                    <div className="flex items-center gap-1.5">
                                                        <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-500/10 text-emerald-500">Voice</span>
                                                        <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-blue-500/10 text-blue-500">SMS</span>
                                                    </div>
                                                </td>
                                                <td className="py-3 px-3 text-neutral-600 dark:text-neutral-300">
                                                    {num.assigned_agent ? (
                                                        <span className="flex items-center gap-1 font-medium">
                                                            <Bot className="h-3.5 w-3.5 text-emerald-400" />
                                                            <span>{num.assigned_agent.name}</span>
                                                        </span>
                                                    ) : (
                                                        <span className="text-neutral-400 italic">None</span>
                                                    )}
                                                </td>
                                                <td className="py-3 px-3 font-bold text-neutral-900 dark:text-white">
                                                    ${num.monthly_cost}/mo
                                                </td>
                                                <td className="py-3 px-3">
                                                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400">
                                                        ● {num.status ? num.status.toUpperCase() : 'ACTIVE'}
                                                    </span>
                                                </td>
                                                <td className="py-3 px-3 text-neutral-400">
                                                    {new Date(num.created_at).toLocaleDateString()}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* ── Tab 2: Client Subaccounts Table ── */}
                {selectedTab === 'subaccounts' && (
                    <div className="p-5 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="border-b border-neutral-200 dark:border-neutral-800 text-neutral-400 font-bold uppercase text-[10px]">
                                        <th className="py-3 px-3">Workspace</th>
                                        <th className="py-3 px-3">Subaccount SID</th>
                                        <th className="py-3 px-3">Auth Token</th>
                                        <th className="py-3 px-3">Status</th>
                                        <th className="py-3 px-3">Created</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                    {subaccounts.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="py-8 text-center text-neutral-400">
                                                No subaccounts generated yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        subaccounts.map((sub) => (
                                            <tr key={sub.id} className="hover:bg-neutral-50 dark:hover:bg-white/5 transition">
                                                <td className="py-3 px-3 font-bold text-neutral-900 dark:text-white">
                                                    {sub.workspace?.name || `Workspace #${sub.workspace_id}`}
                                                </td>
                                                <td className="py-3 px-3 font-mono text-neutral-600 dark:text-neutral-300">
                                                    {sub.twilio_account_sid}
                                                </td>
                                                <td className="py-3 px-3 font-mono text-neutral-400">
                                                    •••••••••••••••••••••••••••••••• (Encrypted)
                                                </td>
                                                <td className="py-3 px-3">
                                                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400">
                                                        ● {sub.status.toUpperCase()}
                                                    </span>
                                                </td>
                                                <td className="py-3 px-3 text-neutral-400">
                                                    {new Date(sub.created_at).toLocaleDateString()}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
