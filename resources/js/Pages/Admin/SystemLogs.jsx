import { useState } from 'react';
import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Card, Badge, Button } from '@/Components/ui';
import { ShieldCheck, Activity, Terminal, Webhook, Key, Clock } from 'lucide-react';

export default function SystemLogs({
    auditLogs = [],
    apiLogs = [],
    webhookLogs = [],
    activeCategory = 'all',
}) {
    const [tab, setTab] = useState('audit');

    return (
        <AdminLayout>
            <Head title="System Logs & Telemetry — Super Admin" />

            <div className="space-y-6 max-w-6xl mx-auto">
                <div>
                    <div className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-brand-50 text-brand-700 dark:bg-neutral-800 dark:text-brand-400 text-xs font-semibold uppercase mb-1">
                        <Terminal className="w-3.5 h-3.5" /> Platform Logs
                    </div>
                    <h1 className="text-xl font-bold text-slate-900 dark:text-white">
                        System Logs & Audit Trail
                    </h1>
                    <p className="text-xs text-slate-500 dark:text-neutral-400">
                        Masked audit logs, telephony API request logs, and webhook delivery lifecycles.
                    </p>
                </div>

                {/* Tab Navigation */}
                <div className="flex border-b border-slate-200 dark:border-neutral-800 gap-4 text-xs font-medium">
                    <button
                        type="button"
                        onClick={() => setTab('audit')}
                        className={`pb-2.5 border-b-2 transition-colors ${
                            tab === 'audit'
                                ? 'border-brand-600 text-brand-600 dark:text-brand-400 font-semibold'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        Security & Audit Trail ({auditLogs.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => setTab('api')}
                        className={`pb-2.5 border-b-2 transition-colors ${
                            tab === 'api'
                                ? 'border-brand-600 text-brand-600 dark:text-brand-400 font-semibold'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        Telephony & Gateway API Logs ({apiLogs.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => setTab('webhooks')}
                        className={`pb-2.5 border-b-2 transition-colors ${
                            tab === 'webhooks'
                                ? 'border-brand-600 text-brand-600 dark:text-brand-400 font-semibold'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        Webhook Deliveries ({webhookLogs.length})
                    </button>
                </div>

                {/* Tab 1: Audit Logs */}
                {tab === 'audit' && (
                    <Card className="border-slate-200 dark:border-neutral-800 overflow-hidden">
                        {auditLogs.length === 0 ? (
                            <div className="p-8 text-center text-xs text-slate-400">No security audit logs recorded.</div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="bg-slate-50 dark:bg-neutral-800/60 text-slate-500 dark:text-neutral-400 uppercase font-semibold border-b border-slate-200 dark:border-neutral-800">
                                        <tr>
                                            <th className="px-5 py-3">Action</th>
                                            <th className="px-5 py-3">User</th>
                                            <th className="px-5 py-3">IP Address</th>
                                            <th className="px-5 py-3">Time</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-neutral-800">
                                        {auditLogs.map((log) => (
                                            <tr key={log.id} className="hover:bg-slate-50/50 dark:hover:bg-neutral-800/30">
                                                <td className="px-5 py-3 font-semibold text-slate-900 dark:text-white font-mono">
                                                    {log.action}
                                                </td>
                                                <td className="px-5 py-3 text-slate-600 dark:text-neutral-300">
                                                    {log.user}
                                                </td>
                                                <td className="px-5 py-3 font-mono text-slate-400 text-[11px]">
                                                    {log.ip || '127.0.0.1'}
                                                </td>
                                                <td className="px-5 py-3 text-slate-400 text-[11px]">
                                                    {new Date(log.created_at).toLocaleString()}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                )}

                {/* Tab 2: API Logs */}
                {tab === 'api' && (
                    <Card className="border-slate-200 dark:border-neutral-800 overflow-hidden">
                        {apiLogs.length === 0 ? (
                            <div className="p-8 text-center text-xs text-slate-400">No API logs recorded.</div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="bg-slate-50 dark:bg-neutral-800/60 text-slate-500 dark:text-neutral-400 uppercase font-semibold border-b border-slate-200 dark:border-neutral-800">
                                        <tr>
                                            <th className="px-5 py-3">Endpoint</th>
                                            <th className="px-5 py-3">Organization</th>
                                            <th className="px-5 py-3">Response Time</th>
                                            <th className="px-5 py-3">Status</th>
                                            <th className="px-5 py-3">Time</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-neutral-800">
                                        {apiLogs.map((log) => (
                                            <tr key={log.id} className="hover:bg-slate-50/50 dark:hover:bg-neutral-800/30">
                                                <td className="px-5 py-3 font-mono font-semibold text-slate-900 dark:text-white">
                                                    {log.action}
                                                </td>
                                                <td className="px-5 py-3 text-slate-600 dark:text-neutral-300">
                                                    {log.organization}
                                                </td>
                                                <td className="px-5 py-3 font-mono text-slate-500 text-[11px]">
                                                    {log.response_time}
                                                </td>
                                                <td className="px-5 py-3">
                                                    <Badge variant={log.level === 'info' ? 'success' : 'danger'}>
                                                        {log.status_code}
                                                    </Badge>
                                                </td>
                                                <td className="px-5 py-3 text-slate-400 text-[11px]">
                                                    {new Date(log.created_at).toLocaleString()}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                )}

                {/* Tab 3: Webhook Logs */}
                {tab === 'webhooks' && (
                    <Card className="border-slate-200 dark:border-neutral-800 overflow-hidden">
                        {webhookLogs.length === 0 ? (
                            <div className="p-8 text-center text-xs text-slate-400">No webhook events received.</div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="bg-slate-50 dark:bg-neutral-800/60 text-slate-500 dark:text-neutral-400 uppercase font-semibold border-b border-slate-200 dark:border-neutral-800">
                                        <tr>
                                            <th className="px-5 py-3">Event Name</th>
                                            <th className="px-5 py-3">Organization</th>
                                            <th className="px-5 py-3">Call ID / Reference</th>
                                            <th className="px-5 py-3">Status</th>
                                            <th className="px-5 py-3">Time</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-neutral-800">
                                        {webhookLogs.map((log) => (
                                            <tr key={log.id} className="hover:bg-slate-50/50 dark:hover:bg-neutral-800/30">
                                                <td className="px-5 py-3 font-mono font-semibold text-slate-900 dark:text-white">
                                                    {log.action}
                                                </td>
                                                <td className="px-5 py-3 text-slate-600 dark:text-neutral-300">
                                                    {log.organization}
                                                </td>
                                                <td className="px-5 py-3 font-mono text-slate-500 text-[11px]">
                                                    {log.call_id}
                                                </td>
                                                <td className="px-5 py-3">
                                                    <Badge variant={log.status === 'processed' ? 'success' : 'neutral'}>
                                                        {log.status}
                                                    </Badge>
                                                </td>
                                                <td className="px-5 py-3 text-slate-400 text-[11px]">
                                                    {new Date(log.created_at).toLocaleString()}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                )}
            </div>
        </AdminLayout>
    );
}
