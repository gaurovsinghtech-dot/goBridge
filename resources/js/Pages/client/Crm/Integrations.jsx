import { Head, useForm, router, usePage } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { useState } from 'react';
import {
    Share2, CheckCircle2, XCircle, RefreshCw, ArrowLeftRight, Clock,
    ExternalLink, ShieldCheck, AlertCircle, Database, Settings2,
    FlaskConical, Check, ArrowRight, Layers, Workflow, Eye, EyeOff
} from 'lucide-react';
import axios from 'axios';
import ApiConnectionsNav from '@/Components/ApiConnectionsNav';

const CRM_META = {
    hubspot: {
        name: 'HubSpot CRM',
        badge: 'bg-[#FF7A59] text-white',
        desc: 'Contacts, deals, communication timeline notes & call recordings.',
        fields: [
            { key: 'access_token', label: 'Private App Access Token', type: 'password', required: true, hint: 'Starts with pat-...' },
            { key: 'portal_id', label: 'HubSpot Portal ID', type: 'text', required: false },
        ]
    },
    salesforce: {
        name: 'Salesforce CRM',
        badge: 'bg-[#00A1E0] text-white',
        desc: 'Enterprise accounts, contacts, leads, task logs & opportunities.',
        fields: [
            { key: 'instance_url', label: 'Salesforce Instance URL', type: 'text', required: true, hint: 'e.g. https://yourcompany.my.salesforce.com' },
            { key: 'access_token', label: 'Connected App Bearer Token', type: 'password', required: false },
            { key: 'client_id', label: 'Consumer Key (Client ID)', type: 'text', required: false },
            { key: 'client_secret', label: 'Consumer Secret', type: 'password', required: false },
        ]
    },
    zoho: {
        name: 'Zoho CRM',
        badge: 'bg-[#E42528] text-white',
        desc: 'Contacts, leads, activities, and interaction notes.',
        fields: [
            { key: 'access_token', label: 'Zoho OAuth Token', type: 'password', required: false },
            { key: 'data_center', label: 'Data Center Region', type: 'select', options: { com: 'United States (.com)', in: 'India (.in)', eu: 'Europe (.eu)', 'com.au': 'Australia (.com.au)' }, required: true },
        ]
    },
    pipedrive: {
        name: 'Pipedrive CRM',
        badge: 'bg-[#029b35] text-white',
        desc: 'Persons, leads, deals, and activity tracking.',
        fields: [
            { key: 'api_token', label: 'Personal API Token', type: 'password', required: true },
            { key: 'company_domain', label: 'Company Domain', type: 'text', required: false, hint: 'e.g. yourcompany' },
        ]
    },
    freshsales: {
        name: 'Freshsales CRM',
        badge: 'bg-[#0081FE] text-white',
        desc: 'Contacts, accounts, notes, and call activity logs.',
        fields: [
            { key: 'domain', label: 'Freshsales Domain', type: 'text', required: true, hint: 'e.g. yourcompany.freshsales.io' },
            { key: 'api_key', label: 'API Key', type: 'password', required: true },
        ]
    },
    dynamics: {
        name: 'Microsoft Dynamics 365',
        badge: 'bg-[#0078D4] text-white',
        desc: 'Microsoft Dataverse contacts, accounts, and automated tasks.',
        fields: [
            { key: 'resource_url', label: 'Dynamics Org URL', type: 'text', required: true, hint: 'e.g. https://orgXXXXX.crm.dynamics.com' },
            { key: 'access_token', label: 'Azure AD Access Token', type: 'password', required: false },
        ]
    },
    gohighlevel: {
        name: 'GoHighLevel',
        badge: 'bg-[#1A56DB] text-white',
        desc: 'Sub-account contacts, conversations, notes, and webhooks.',
        fields: [
            { key: 'api_key', label: 'API Key / Access Token', type: 'password', required: true },
            { key: 'location_id', label: 'Location ID', type: 'text', required: false },
        ]
    },
    custom: {
        name: 'Custom CRM via REST API',
        badge: 'bg-indigo-600 text-white',
        desc: 'Connect any proprietary or in-house CRM with custom REST endpoints.',
        fields: [
            { key: 'base_url', label: 'API Base URL', type: 'text', required: true, hint: 'e.g. https://api.yourcrm.com/v1' },
            { key: 'auth_token', label: 'Authorization Token / API Key', type: 'password', required: false },
            { key: 'contacts_endpoint', label: 'Contacts Path', type: 'text', required: false, hint: 'Default: /contacts' },
        ]
    },
    webhook: {
        name: 'Generic CRM Webhook',
        badge: 'bg-purple-600 text-white',
        desc: 'Distribute contacts, messages, and calls via real-time HTTP POST webhooks.',
        fields: [
            { key: 'outbound_webhook_url', label: 'Outbound Webhook URL', type: 'text', required: false },
            { key: 'webhook_secret', label: 'HMAC Signature Secret', type: 'password', required: false },
        ]
    }
};

export default function CrmIntegrations({ providers = [], logs = [] }) {
    const [selectedProvider, setSelectedProvider] = useState(null);
    const [credentials, setCredentials] = useState({});
    const [syncDirection, setSyncDirection] = useState('two_way');
    const [syncMode, setSyncMode] = useState('realtime');
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState(null);
    const [syncing, setSyncing] = useState(null);
    const [saving, setSaving] = useState(false);

    const openConfigModal = (p) => {
        setSelectedProvider(p);
        setCredentials(p.credentials || {});
        setSyncDirection(p.sync_direction || 'two_way');
        setSyncMode(p.sync_mode || 'realtime');
        setTestResult(null);
    };

    const handleTest = async () => {
        if (!selectedProvider) return;
        setTesting(true);
        setTestResult(null);
        try {
            const { data } = await axios.post(route('client.crm.integrations.test', selectedProvider.provider), {
                credentials,
            });
            setTestResult(data);
        } catch (e) {
            setTestResult({ ok: false, message: e?.response?.data?.message || 'Connection test failed.' });
        } finally {
            setTesting(false);
        }
    };

    const handleSave = async (e) => {
        e.preventDefault();
        if (!selectedProvider) return;
        setSaving(true);
        try {
            await axios.post(route('client.crm.integrations.connect', selectedProvider.provider), {
                credentials,
                sync_direction: syncDirection,
                sync_mode: syncMode,
            });
            setSelectedProvider(null);
            router.reload({ only: ['providers', 'logs'] });
        } catch (err) {
            alert(err?.response?.data?.message || 'Failed to save CRM connection');
        } finally {
            setSaving(false);
        }
    };

    const handleSyncNow = async (providerSlug) => {
        setSyncing(providerSlug);
        try {
            await axios.post(route('client.crm.integrations.sync', providerSlug));
            router.reload({ only: ['providers', 'logs'] });
        } catch (e) {
            alert(e?.response?.data?.message || 'Sync failed.');
        } finally {
            setSyncing(null);
        }
    };

    return (
        <ClientLayout title="CRM & Business Systems">
            <Head title="CRM & Business Systems · Growbridge Connect" />

            <div className="space-y-6 max-w-7xl mx-auto pb-12">
                <ApiConnectionsNav current="crm" />

                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold text-neutral-900 dark:text-neutral-100">
                                CRM & Business Systems
                            </h1>
                            <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">
                                Two-Way Sync Active
                            </span>
                        </div>
                        <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                            Connect your existing CRM without data migration. Growbridge synchronizes contacts, WhatsApp conversations, voice call logs, and AI interaction notes bidirectionally.
                        </p>
                    </div>
                </div>

                {/* Grid of CRM Cards */}
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {providers.map((p) => {
                        const meta = CRM_META[p.provider] || { name: p.label, badge: 'bg-neutral-800 text-white', desc: '' };
                        const isConn = p.connected;

                        return (
                            <div
                                key={p.provider}
                                className={`rounded-2xl border p-5 flex flex-col justify-between gap-4 transition duration-200 ${
                                    isConn
                                        ? 'border-indigo-300 dark:border-indigo-700 bg-indigo-50/20 dark:bg-indigo-950/10 shadow-sm'
                                        : 'border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm hover:shadow-md'
                                }`}
                            >
                                <div className="space-y-3">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex items-center gap-3">
                                            <div className={`h-10 w-10 rounded-xl flex items-center justify-center font-bold text-xs shadow-sm ${meta.badge}`}>
                                                <Share2 className="h-5 w-5" />
                                            </div>
                                            <div>
                                                <h3 className="font-bold text-sm text-neutral-900 dark:text-neutral-100">
                                                    {meta.name}
                                                </h3>
                                                <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 line-clamp-1">
                                                    {meta.desc}
                                                </p>
                                            </div>
                                        </div>

                                        <span className={`px-2 py-0.5 rounded-full text-xs font-semibold shrink-0 ${
                                            isConn
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                                : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400'
                                        }`}>
                                            {isConn ? '✓ Connected' : '○ Available'}
                                        </span>
                                    </div>

                                    {isConn && (
                                        <div className="space-y-1.5 pt-1 text-xs text-neutral-600 dark:text-neutral-300">
                                            <div className="flex items-center justify-between">
                                                <span className="text-neutral-400">Sync Direction:</span>
                                                <span className="font-semibold capitalize">{p.sync_direction.replace('_', ' ')}</span>
                                            </div>
                                            {p.last_sync_at && (
                                                <div className="flex items-center justify-between text-[11px] text-neutral-400">
                                                    <span>Last Synced:</span>
                                                    <span>{new Date(p.last_sync_at).toLocaleTimeString()}</span>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>

                                <div className="flex items-center gap-2 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                                    <button
                                        type="button"
                                        onClick={() => openConfigModal(p)}
                                        className="flex-1 rounded-xl bg-neutral-900 hover:bg-neutral-800 text-white dark:bg-neutral-100 dark:hover:bg-white dark:text-neutral-900 px-3 py-2 text-xs font-semibold transition flex items-center justify-center gap-1 shadow-sm"
                                    >
                                        <Settings2 className="h-3.5 w-3.5" />
                                        {isConn ? 'Configure Sync' : 'Connect CRM'}
                                    </button>

                                    {isConn && (
                                        <button
                                            type="button"
                                            disabled={syncing === p.provider}
                                            onClick={() => handleSyncNow(p.provider)}
                                            className="px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition disabled:opacity-50 flex items-center gap-1"
                                        >
                                            <RefreshCw className={`h-3.5 w-3.5 ${syncing === p.provider ? 'animate-spin' : ''}`} />
                                            Sync Now
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Sync Audit Logs */}
                {logs.length > 0 && (
                    <div className="rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-6 space-y-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
                                    Recent CRM Synchronization Activity
                                </h3>
                                <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                                    Real-time audit log of contact updates, conversation records, and call log pushes.
                                </p>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left">
                                <thead className="text-[11px] font-bold uppercase tracking-wider text-neutral-400 bg-neutral-50 dark:bg-neutral-800/50">
                                    <tr>
                                        <th className="py-2.5 px-3 rounded-l-lg">Time</th>
                                        <th className="py-2.5 px-3">Provider</th>
                                        <th className="py-2.5 px-3">Object</th>
                                        <th className="py-2.5 px-3">Direction</th>
                                        <th className="py-2.5 px-3">Status</th>
                                        <th className="py-2.5 px-3 rounded-r-lg">Details</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                    {logs.map((log) => (
                                        <tr key={log.id} className="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/30">
                                            <td className="py-2.5 px-3 text-neutral-500 whitespace-nowrap">
                                                {new Date(log.created_at).toLocaleTimeString()}
                                            </td>
                                            <td className="py-2.5 px-3 font-semibold text-neutral-900 dark:text-neutral-100 capitalize">
                                                {log.provider}
                                            </td>
                                            <td className="py-2.5 px-3 capitalize text-neutral-600 dark:text-neutral-300">
                                                {log.object_type}
                                            </td>
                                            <td className="py-2.5 px-3 text-neutral-500 capitalize">
                                                {log.direction}
                                            </td>
                                            <td className="py-2.5 px-3">
                                                <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold ${
                                                    log.status === 'success'
                                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                        : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300'
                                                }`}>
                                                    {log.status === 'success' ? <CheckCircle2 className="h-3 w-3" /> : <XCircle className="h-3 w-3" />}
                                                    {log.status}
                                                </span>
                                            </td>
                                            <td className="py-2.5 px-3 text-neutral-500 max-w-xs truncate">
                                                {log.error_message || (log.external_record_id ? `External ID: ${log.external_record_id}` : 'Synchronized successfully')}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Configuration Modal */}
                {selectedProvider && (
                    <div className="fixed inset-0 z-50 bg-neutral-950/60 backdrop-blur-xs flex items-center justify-center p-4">
                        <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-2xl max-w-lg w-full p-6 space-y-5 shadow-2xl animate-in fade-in zoom-in duration-150">
                            <div className="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-4">
                                <div className="flex items-center gap-3">
                                    <div className={`h-9 w-9 rounded-xl flex items-center justify-center text-xs font-bold ${CRM_META[selectedProvider.provider]?.badge || 'bg-indigo-600 text-white'}`}>
                                        <Share2 className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <h3 className="font-bold text-base text-neutral-900 dark:text-neutral-100">
                                            Configure {CRM_META[selectedProvider.provider]?.name || selectedProvider.label}
                                        </h3>
                                        <p className="text-xs text-neutral-500">Two-way synchronization settings</p>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    onClick={() => setSelectedProvider(null)}
                                    className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 text-sm font-bold p-1"
                                >
                                    ✕
                                </button>
                            </div>

                            {/* Diagnostics Alert */}
                            {testResult && (
                                <div className={`p-4 rounded-xl border text-xs space-y-2 ${
                                    testResult.ok
                                        ? 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:border-emerald-800'
                                        : 'bg-rose-50 text-rose-800 border-rose-200 dark:bg-rose-950/40 dark:text-rose-200 dark:border-rose-800'
                                }`}>
                                    <div className="flex items-center gap-2 font-bold">
                                        {testResult.ok ? <CheckCircle2 className="h-4 w-4 text-emerald-600" /> : <XCircle className="h-4 w-4 text-rose-600" />}
                                        {testResult.ok ? 'Connection Verified' : 'Connection Failed'}
                                    </div>
                                    <p className="opacity-90">{testResult.message}</p>
                                </div>
                            )}

                            <form onSubmit={handleSave} className="space-y-4">
                                {/* Dynamic CRM fields */}
                                {(CRM_META[selectedProvider.provider]?.fields || []).map((f) => (
                                    <div key={f.key}>
                                        <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                            {f.label} {f.required && <span className="text-rose-500">*</span>}
                                        </label>
                                        {f.type === 'select' ? (
                                            <select
                                                value={credentials[f.key] || ''}
                                                onChange={e => setCredentials({ ...credentials, [f.key]: e.target.value })}
                                                className="w-full rounded-xl border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm text-neutral-900 dark:text-neutral-100"
                                            >
                                                {Object.entries(f.options || {}).map(([val, lbl]) => (
                                                    <option key={val} value={val}>{lbl}</option>
                                                ))}
                                            </select>
                                        ) : (
                                            <input
                                                type={f.type || 'text'}
                                                value={credentials[f.key] || ''}
                                                onChange={e => setCredentials({ ...credentials, [f.key]: e.target.value })}
                                                placeholder={f.hint || 'Enter value...'}
                                                className="w-full rounded-xl border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm text-neutral-900 dark:text-neutral-100"
                                            />
                                        )}
                                        {f.hint && <p className="text-[11px] text-neutral-400 mt-1">{f.hint}</p>}
                                    </div>
                                ))}

                                {/* Sync Settings */}
                                <div className="grid grid-cols-2 gap-3 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                                    <div>
                                        <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                            Sync Direction
                                        </label>
                                        <select
                                            value={syncDirection}
                                            onChange={e => setSyncDirection(e.target.value)}
                                            className="w-full rounded-xl border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-xs text-neutral-900 dark:text-neutral-100"
                                        >
                                            <option value="two_way">Two-Way (Growbridge ↔ CRM)</option>
                                            <option value="outbound_only">Growbridge → CRM Only</option>
                                            <option value="inbound_only">CRM → Growbridge Only</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                                            Sync Mode
                                        </label>
                                        <select
                                            value={syncMode}
                                            onChange={e => setSyncMode(e.target.value)}
                                            className="w-full rounded-xl border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-xs text-neutral-900 dark:text-neutral-100"
                                        >
                                            <option value="realtime">Real-Time Webhooks</option>
                                            <option value="hourly">Hourly Background Sync</option>
                                            <option value="daily">Daily Sync</option>
                                            <option value="manual">Manual Only</option>
                                        </select>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between pt-4 border-t border-neutral-100 dark:border-neutral-800">
                                    <button
                                        type="button"
                                        disabled={testing}
                                        onClick={handleTest}
                                        className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 disabled:opacity-50"
                                    >
                                        <FlaskConical className={`h-3.5 w-3.5 ${testing ? 'animate-spin' : ''}`} />
                                        {testing ? 'Testing...' : 'Test Connection'}
                                    </button>

                                    <div className="flex items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={() => setSelectedProvider(null)}
                                            className="px-3 py-2 text-xs font-semibold text-neutral-500 hover:text-neutral-700"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={saving}
                                            className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md shadow-indigo-600/20 disabled:opacity-50"
                                        >
                                            {saving ? 'Connecting...' : 'Save & Connect'}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </ClientLayout>
    );
}
