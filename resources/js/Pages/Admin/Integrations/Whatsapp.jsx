import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Card, Badge, Button } from '@/Components/ui';
import {
    MessageSquare, CheckCircle2, AlertCircle, AlertTriangle, RefreshCw,
    Send, ShieldCheck, Zap, Server, Activity, Copy, Check, ExternalLink,
    Phone, Building, FileText
} from 'lucide-react';

export default function AdminWhatsappIntegration({
    metaConfig = {},
    metrics = {},
    accounts = [],
    recentLogs = [],
}) {
    const [testingApi, setTestingApi] = useState(false);
    const [apiTestResult, setApiTestResult] = useState(null);
    const [testingWebhook, setTestingWebhook] = useState(false);
    const [webhookTestResult, setWebhookTestResult] = useState(null);
    const [copiedVerifyToken, setCopiedVerifyToken] = useState(false);
    const [copiedWebhookUrl, setCopiedWebhookUrl] = useState(false);

    const handleTestApi = async () => {
        setTestingApi(true);
        setApiTestResult(null);
        try {
            const res = await window.axios.post(route('admin.integrations.whatsapp.test-api'));
            setApiTestResult(res.data);
        } catch (err) {
            setApiTestResult({
                success: false,
                message: err.response?.data?.message || err.message || 'API test failed',
            });
        } finally {
            setTestingApi(false);
        }
    };

    const handleTestWebhook = async () => {
        setTestingWebhook(true);
        setWebhookTestResult(null);
        try {
            const res = await window.axios.post(route('admin.integrations.whatsapp.test-webhook'));
            setWebhookTestResult(res.data);
        } catch (err) {
            setWebhookTestResult({
                success: false,
                message: err.response?.data?.message || err.message || 'Webhook test failed',
            });
        } finally {
            setTestingWebhook(false);
        }
    };

    const copyToClipboard = (text, type) => {
        navigator.clipboard.writeText(text);
        if (type === 'token') {
            setCopiedVerifyToken(true);
            setTimeout(() => setCopiedVerifyToken(false), 2000);
        } else {
            setCopiedWebhookUrl(true);
            setTimeout(() => setCopiedWebhookUrl(false), 2000);
        }
    };

    return (
        <AdminLayout>
            <Head title="WhatsApp Integration & Operations — Admin Control Center" />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-5">
                    <div>
                        <h1 className="text-2xl font-bold text-[#011B40] flex items-center gap-2">
                            <MessageSquare className="w-7 h-7 text-[#064E3B]" />
                            Meta WhatsApp Cloud API Integration
                        </h1>
                        <p className="text-sm text-slate-500 mt-1">
                            Manage Meta Cloud API platform connections, global webhooks, throughput and tenant telemetry.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Button
                            variant="secondary"
                            onClick={handleTestWebhook}
                            disabled={testingWebhook}
                            className="flex items-center gap-2 text-xs font-semibold"
                        >
                            <RefreshCw className={`w-4 h-4 ${testingWebhook ? 'animate-spin' : ''}`} />
                            Test Webhook Endpoint
                        </Button>
                        <Button
                            onClick={handleTestApi}
                            disabled={testingApi}
                            className="bg-[#064E3B] hover:bg-[#043327] text-white flex items-center gap-2 text-xs font-semibold"
                        >
                            <Zap className={`w-4 h-4 ${testingApi ? 'animate-spin' : ''}`} />
                            Live Meta API Ping
                        </Button>
                    </div>
                </div>

                {/* API Test Results */}
                {apiTestResult && (
                    <div className={`p-4 rounded-xl border flex items-start gap-3 ${
                        apiTestResult.success ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900'
                    }`}>
                        {apiTestResult.success ? (
                            <CheckCircle2 className="w-5 h-5 text-emerald-600 mt-0.5 shrink-0" />
                        ) : (
                            <AlertCircle className="w-5 h-5 text-rose-600 mt-0.5 shrink-0" />
                        )}
                        <div className="text-sm">
                            <div className="font-semibold">
                                {apiTestResult.success ? 'Meta Cloud API Connected' : 'Meta API Check Failed'}
                            </div>
                            <div className="mt-1">{apiTestResult.message}</div>
                            {apiTestResult.latency_ms && (
                                <div className="text-xs text-emerald-700 mt-1 font-mono">
                                    Round-trip Latency: {apiTestResult.latency_ms} ms
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* Metrics Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <Card className="p-4 bg-white border border-slate-200 shadow-sm">
                        <div className="text-xs font-medium text-slate-500 uppercase tracking-wider">Connected WABAs</div>
                        <div className="text-2xl font-bold text-[#011B40] mt-1">{metrics.connected_businesses || 0}</div>
                        <div className="text-xs text-slate-400 mt-1 flex items-center gap-1">
                            <Building className="w-3.5 h-3.5 text-[#064E3B]" /> Active Workspaces
                        </div>
                    </Card>
                    <Card className="p-4 bg-white border border-slate-200 shadow-sm">
                        <div className="text-xs font-medium text-slate-500 uppercase tracking-wider">Phone Numbers</div>
                        <div className="text-2xl font-bold text-[#011B40] mt-1">{metrics.active_phone_numbers || 0}</div>
                        <div className="text-xs text-slate-400 mt-1 flex items-center gap-1">
                            <Phone className="w-3.5 h-3.5 text-[#064E3B]" /> Registered Numbers
                        </div>
                    </Card>
                    <Card className="p-4 bg-white border border-slate-200 shadow-sm">
                        <div className="text-xs font-medium text-slate-500 uppercase tracking-wider">Approved Templates</div>
                        <div className="text-2xl font-bold text-[#064E3B] mt-1">{metrics.approved_templates || 0}</div>
                        <div className="text-xs text-slate-400 mt-1 flex items-center gap-1">
                            <FileText className="w-3.5 h-3.5 text-[#064E3B]" /> Ready for Campaigns
                        </div>
                    </Card>
                    <Card className="p-4 bg-white border border-slate-200 shadow-sm">
                        <div className="text-xs font-medium text-slate-500 uppercase tracking-wider">Messages Today</div>
                        <div className="text-2xl font-bold text-[#011B40] mt-1">{metrics.messages_today || 0}</div>
                        <div className="text-xs text-slate-400 mt-1 flex items-center gap-1">
                            <Send className="w-3.5 h-3.5 text-blue-600" /> Inbound + Outbound
                        </div>
                    </Card>
                    <Card className="p-4 bg-white border border-slate-200 shadow-sm">
                        <div className="text-xs font-medium text-slate-500 uppercase tracking-wider">Failed Messages</div>
                        <div className={`text-2xl font-bold mt-1 ${metrics.failed_messages_today > 0 ? 'text-rose-600' : 'text-slate-700'}`}>
                            {metrics.failed_messages_today || 0}
                        </div>
                        <div className="text-xs text-slate-400 mt-1 flex items-center gap-1">
                            <AlertTriangle className="w-3.5 h-3.5 text-amber-500" /> Delivery Failures
                        </div>
                    </Card>
                </div>

                {/* Configuration & Webhook Information */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <Card className="p-5 bg-white border border-slate-200 shadow-sm space-y-4">
                        <div className="flex items-center justify-between border-b pb-3">
                            <h2 className="text-base font-bold text-[#011B40] flex items-center gap-2">
                                <ShieldCheck className="w-5 h-5 text-[#064E3B]" />
                                Meta API Credentials Status
                            </h2>
                            {metaConfig.has_app_id && metaConfig.has_app_secret ? (
                                <Badge className="bg-emerald-100 text-emerald-800 border-emerald-200">Configured</Badge>
                            ) : (
                                <Badge className="bg-amber-100 text-amber-800 border-amber-200">Missing Credentials</Badge>
                            )}
                        </div>

                        <div className="space-y-3 text-sm">
                            <div className="flex justify-between py-1.5 border-b border-slate-100">
                                <span className="text-slate-500">Meta App ID</span>
                                <span className="font-mono font-medium text-slate-800">{metaConfig.app_id_masked || 'Not Configured'}</span>
                            </div>
                            <div className="flex justify-between py-1.5 border-b border-slate-100">
                                <span className="text-slate-500">App Secret (HMAC-SHA256)</span>
                                <span className="font-medium text-slate-800">{metaConfig.has_app_secret ? '•••••••••••••••• (Encrypted)' : 'Not Set'}</span>
                            </div>
                            <div className="flex justify-between py-1.5 border-b border-slate-100">
                                <span className="text-slate-500">System Access Token</span>
                                <span className="font-medium text-slate-800">{metaConfig.has_system_token ? 'Permanent System User Token (Active)' : 'Per-WABA Tokens'}</span>
                            </div>
                            <div className="flex justify-between py-1.5">
                                <span className="text-slate-500">Graph API Version</span>
                                <span className="font-mono font-semibold text-[#064E3B]">v20.0 (Latest Cloud API)</span>
                            </div>
                        </div>
                    </Card>

                    <Card className="p-5 bg-white border border-slate-200 shadow-sm space-y-4">
                        <div className="flex items-center justify-between border-b pb-3">
                            <h2 className="text-base font-bold text-[#011B40] flex items-center gap-2">
                                <Activity className="w-5 h-5 text-[#064E3B]" />
                                Global Webhook Configuration
                            </h2>
                            <Badge className="bg-blue-100 text-blue-800 border-blue-200">Meta Callback</Badge>
                        </div>

                        <div className="space-y-3">
                            <div>
                                <label className="text-xs font-semibold text-slate-500 uppercase">Global Callback URL</label>
                                <div className="mt-1 flex items-center gap-2">
                                    <input
                                        type="text"
                                        readOnly
                                        value={metaConfig.global_webhook_url || ''}
                                        className="w-full text-xs font-mono bg-slate-50 border border-slate-200 rounded px-2.5 py-2 text-slate-700 select-all"
                                    />
                                    <button
                                        onClick={() => copyToClipboard(metaConfig.global_webhook_url, 'url')}
                                        className="p-2 border border-slate-200 rounded hover:bg-slate-50 text-slate-600"
                                        title="Copy URL"
                                    >
                                        {copiedWebhookUrl ? <Check className="w-4 h-4 text-emerald-600" /> : <Copy className="w-4 h-4" />}
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label className="text-xs font-semibold text-slate-500 uppercase">Verify Token (hub.verify_token)</label>
                                <div className="mt-1 flex items-center gap-2">
                                    <input
                                        type="text"
                                        readOnly
                                        value={metaConfig.verify_token || ''}
                                        className="w-full text-xs font-mono bg-slate-50 border border-slate-200 rounded px-2.5 py-2 text-slate-700 select-all"
                                    />
                                    <button
                                        onClick={() => copyToClipboard(metaConfig.verify_token, 'token')}
                                        className="p-2 border border-slate-200 rounded hover:bg-slate-50 text-slate-600"
                                        title="Copy Verify Token"
                                    >
                                        {copiedVerifyToken ? <Check className="w-4 h-4 text-emerald-600" /> : <Copy className="w-4 h-4" />}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>

                {/* Connected Businesses & Phone Numbers Table */}
                <Card className="bg-white border border-slate-200 shadow-sm overflow-hidden">
                    <div className="p-4 border-b border-slate-200 flex items-center justify-between">
                        <h3 className="font-bold text-[#011B40] text-sm flex items-center gap-2">
                            <Building className="w-4 h-4 text-[#064E3B]" />
                            Connected Tenant WhatsApp Accounts ({accounts.length})
                        </h3>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-slate-50 text-xs font-semibold uppercase text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th className="px-4 py-3">Workspace</th>
                                    <th className="px-4 py-3">WABA Name / ID</th>
                                    <th className="px-4 py-3">Phone Numbers</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Connected At</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {accounts.length === 0 ? (
                                    <tr>
                                        <td colSpan="5" className="px-4 py-8 text-center text-slate-400 text-sm">
                                            No WhatsApp accounts connected yet.
                                        </td>
                                    </tr>
                                ) : (
                                    accounts.map((acc) => (
                                        <tr key={acc.id} className="hover:bg-slate-50/50">
                                            <td className="px-4 py-3 font-semibold text-[#011B40]">
                                                {acc.workspace_name}
                                                <div className="text-xs text-slate-400 font-normal">Workspace #{acc.workspace_id}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-slate-800">{acc.name || 'WhatsApp Business'}</div>
                                                <div className="text-xs font-mono text-slate-400">{acc.waba_id}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {acc.phones && acc.phones.length > 0 ? (
                                                    acc.phones.map((p, idx) => (
                                                        <div key={idx} className="flex items-center gap-2 text-xs">
                                                            <span className="font-medium text-slate-700">{p.display_phone || p.phone_number_id}</span>
                                                            {p.quality_rating && (
                                                                <Badge className={
                                                                    p.quality_rating === 'GREEN' ? 'bg-emerald-100 text-emerald-800 text-[10px]' :
                                                                    p.quality_rating === 'YELLOW' ? 'bg-amber-100 text-amber-800 text-[10px]' :
                                                                    'bg-rose-100 text-rose-800 text-[10px]'
                                                                }>
                                                                    {p.quality_rating}
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    ))
                                                ) : (
                                                    <span className="text-xs text-slate-400">No phones linked</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge className={acc.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'}>
                                                    {acc.status}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-xs text-slate-500">
                                                {acc.connected_at || 'N/A'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AdminLayout>
    );
}
