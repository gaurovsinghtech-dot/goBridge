import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Card, Badge, Button } from '@/Components/ui';
import {
    Activity, Server, Database, HardDrive, Cpu, Clock,
    CheckCircle2, AlertTriangle, XCircle, RefreshCw, Layers, ShieldCheck,
    Cloud, Mail, MessageSquare, Phone, Bot, Play, Send, Check
} from 'lucide-react';

export default function SystemHealth({ diagnostics = {}, s3 = {}, providers = {}, webhooks = {} }) {
    const [runningBackup, setRunningBackup] = useState(false);
    const [retryingJobs, setRetryingJobs] = useState(false);
    const [testEmailLoading, setTestEmailLoading] = useState(false);
    const [emailResult, setEmailResult] = useState(null);

    const isDbHealthy = diagnostics.db_status === 'healthy';
    const isCronHealthy = diagnostics.cron_status === 'healthy';
    const isS3Healthy = s3.status === 'Connected' || s3.is_configured;
    const isStorageHealthy = diagnostics.storage_writable;

    const handleRunBackup = () => {
        setRunningBackup(true);
        router.post(route('admin.system-health.run-backup'), {}, {
            onFinish: () => setRunningBackup(false),
        });
    };

    const handleRetryJobs = () => {
        setRetryingJobs(true);
        router.post(route('admin.system-health.retry-failed-jobs'), {}, {
            onFinish: () => setRetryingJobs(false),
        });
    };

    const handleSendTestEmail = async () => {
        setTestEmailLoading(true);
        setEmailResult(null);
        try {
            const res = await window.axios.post(route('admin.system-health.test-email'));
            setEmailResult(res.data);
        } catch (err) {
            setEmailResult({
                ok: false,
                message: err.response?.data?.message || err.message || 'Failed to dispatch test email.',
            });
        } finally {
            setTestEmailLoading(false);
        }
    };

    return (
        <AdminLayout>
            <Head title="System Health & Operations — Admin" />

            <div className="space-y-6 max-w-7xl mx-auto pb-12">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 text-xs font-semibold uppercase tracking-wider mb-1">
                            <Activity className="w-3.5 h-3.5" /> Production Telemetry & Diagnostics
                        </div>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                            System Health & Operations Control Center
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Real-time platform diagnostics, database health, AWS S3 status, queue workers, cron heartbeat, and provider connections.
                        </p>
                    </div>

                    <div className="flex items-center gap-2 flex-wrap">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleRunBackup}
                            disabled={runningBackup}
                            className="gap-1.5 text-xs bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700"
                        >
                            <Database className={`w-3.5 h-3.5 ${runningBackup ? 'animate-spin' : ''}`} />
                            {runningBackup ? 'Running Backup...' : 'Run DB Backup'}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleSendTestEmail}
                            disabled={testEmailLoading}
                            className="gap-1.5 text-xs bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700"
                        >
                            <Mail className={`w-3.5 h-3.5 ${testEmailLoading ? 'animate-spin' : ''}`} />
                            {testEmailLoading ? 'Sending Test...' : 'Test SMTP Mail'}
                        </Button>
                        <Link href={route('admin.system-health.index')}>
                            <Button size="sm" className="gap-1.5 text-xs bg-[#011B40] hover:bg-[#022859] text-white">
                                <RefreshCw className="w-3.5 h-3.5" /> Refresh Telemetry
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Email Test Feedback Banner */}
                {emailResult && (
                    <div className={`p-4 rounded-xl border flex items-start gap-3 ${
                        emailResult.ok
                            ? 'bg-emerald-50 border-emerald-200 text-emerald-900 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300'
                            : 'bg-rose-50 border-rose-200 text-rose-900 dark:bg-rose-950/40 dark:border-rose-800 dark:text-rose-300'
                    }`}>
                        {emailResult.ok ? (
                            <CheckCircle2 className="w-5 h-5 text-emerald-600 mt-0.5 flex-shrink-0" />
                        ) : (
                            <AlertTriangle className="w-5 h-5 text-rose-600 mt-0.5 flex-shrink-0" />
                        )}
                        <div className="flex-1 text-xs">
                            <p className="font-semibold text-sm">{emailResult.ok ? 'Email Test Dispatched' : 'Email Test Error'}</p>
                            <p className="mt-0.5">{emailResult.message}</p>
                        </div>
                    </div>
                )}

                {/* Row 1: Core Infrastructure Metrics */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Database */}
                    <Card className="p-5 border-slate-200 dark:border-slate-800 flex items-start gap-4">
                        <div className={`p-3 rounded-xl ${isDbHealthy ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-400'}`}>
                            <Database className="w-6 h-6" />
                        </div>
                        <div>
                            <span className="text-xs text-slate-500 font-medium">Relational Database</span>
                            <div className="flex items-center gap-2 mt-1">
                                <span className="text-lg font-bold text-slate-900 dark:text-white capitalize">{diagnostics.db_status}</span>
                                <Badge variant={isDbHealthy ? 'success' : 'danger'} className="text-[10px]">
                                    {diagnostics.db_latency_ms}ms
                                </Badge>
                            </div>
                            <p className="text-[11px] text-slate-400 mt-1">
                                {diagnostics.db_connection?.toUpperCase()} • {diagnostics.db_name}
                            </p>
                        </div>
                    </Card>

                    {/* AWS S3 Storage */}
                    <Card className="p-5 border-slate-200 dark:border-slate-800 flex items-start gap-4">
                        <div className={`p-3 rounded-xl ${isS3Healthy ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'}`}>
                            <Cloud className="w-6 h-6" />
                        </div>
                        <div>
                            <span className="text-xs text-slate-500 font-medium">AWS S3 Storage</span>
                            <div className="flex items-center gap-2 mt-1">
                                <span className="text-lg font-bold text-slate-900 dark:text-white">{s3.status || 'Active'}</span>
                            </div>
                            <p className="text-[11px] text-slate-400 mt-1 truncate max-w-[180px]" title={s3.bucket}>
                                Bucket: {s3.bucket || 'Configured'} ({s3.region || 'us-east-1'})
                            </p>
                        </div>
                    </Card>

                    {/* Cron Scheduler */}
                    <Card className="p-5 border-slate-200 dark:border-slate-800 flex items-start gap-4">
                        <div className={`p-3 rounded-xl ${isCronHealthy ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'}`}>
                            <Clock className="w-6 h-6" />
                        </div>
                        <div>
                            <span className="text-xs text-slate-500 font-medium">Scheduler Heartbeat</span>
                            <div className="flex items-center gap-2 mt-1">
                                <span className="text-lg font-bold text-slate-900 dark:text-white capitalize">{diagnostics.cron_status}</span>
                                <Badge variant={isCronHealthy ? 'success' : 'warning'} className="text-[10px]">
                                    {diagnostics.cron_minutes_ago !== null ? `${diagnostics.cron_minutes_ago}m ago` : 'Active'}
                                </Badge>
                            </div>
                            <p className="text-[11px] text-slate-400 mt-1">
                                Last run: {diagnostics.last_cron_run}
                            </p>
                        </div>
                    </Card>

                    {/* Queue Status */}
                    <Card className="p-5 border-slate-200 dark:border-slate-800 flex items-start gap-4">
                        <div className="p-3 rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-400">
                            <Layers className="w-6 h-6" />
                        </div>
                        <div className="flex-1">
                            <span className="text-xs text-slate-500 font-medium">Queue & Failed Jobs</span>
                            <div className="flex items-center justify-between gap-2 mt-1">
                                <span className="text-lg font-bold text-slate-900 dark:text-white">
                                    {diagnostics.queue_pending_jobs} Pending
                                </span>
                                {diagnostics.failed_jobs_count > 0 && (
                                    <Button
                                        size="xs"
                                        variant="outline"
                                        onClick={handleRetryJobs}
                                        disabled={retryingJobs}
                                        className="text-[10px] text-amber-600 hover:bg-amber-50 border-amber-200"
                                    >
                                        {retryingJobs ? 'Retrying...' : `Retry ${diagnostics.failed_jobs_count}`}
                                    </Button>
                                )}
                            </div>
                            <p className="text-[11px] text-slate-400 mt-1">
                                Driver: {diagnostics.queue_driver} • Failed: {diagnostics.failed_jobs_count}
                            </p>
                        </div>
                    </Card>
                </div>

                {/* Row 2: Application Specs & Database Backup Summary */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* App & Runtime Specification */}
                    <Card className="p-5 border-slate-200 dark:border-slate-800 space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <Server className="w-4 h-4 text-emerald-600" /> Runtime Specification
                            </h2>
                            <Badge variant="outline" className="text-[10px]">
                                {diagnostics.app_env?.toUpperCase()}
                            </Badge>
                        </div>

                        <div className="space-y-2.5 text-xs">
                            <div className="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span className="text-slate-500">PHP Version</span>
                                <span className="font-semibold text-slate-800 dark:text-slate-200">{diagnostics.php_version}</span>
                            </div>
                            <div className="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span className="text-slate-500">Laravel Version</span>
                                <span className="font-semibold text-slate-800 dark:text-slate-200">v{diagnostics.laravel_version}</span>
                            </div>
                            <div className="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span className="text-slate-500">Debug Mode (APP_DEBUG)</span>
                                <span className={`font-semibold ${diagnostics.app_debug ? 'text-rose-600' : 'text-emerald-600'}`}>
                                    {diagnostics.app_debug ? 'Enabled (Warning)' : 'Disabled (Secure)'}
                                </span>
                            </div>
                            <div className="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span className="text-slate-500">HTTPS Transport</span>
                                <span className="font-semibold text-emerald-600">
                                    {diagnostics.is_https ? 'Active (SSL/TLS)' : 'HTTP (Proxy/Dev)'}
                                </span>
                            </div>
                            <div className="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span className="text-slate-500">Session Driver</span>
                                <span className="font-semibold text-slate-800 dark:text-slate-200">{diagnostics.session_driver}</span>
                            </div>
                            <div className="flex justify-between py-1">
                                <span className="text-slate-500">Secure Cookies</span>
                                <span className="font-semibold text-emerald-600">
                                    {diagnostics.secure_cookies ? 'Enabled' : 'Default'}
                                </span>
                            </div>
                        </div>
                    </Card>

                    {/* Database Backup Status */}
                    <Card className="p-5 border-slate-200 dark:border-slate-800 space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <ShieldCheck className="w-4 h-4 text-sky-600" /> Database Backup Strategy
                            </h2>
                            <Badge variant={diagnostics.last_backup_status === 'success' ? 'success' : 'outline'} className="text-[10px]">
                                {diagnostics.last_backup_status === 'success' ? 'Protected' : 'Pending'}
                            </Badge>
                        </div>

                        <div className="space-y-2.5 text-xs">
                            <div className="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span className="text-slate-500">Last Backup Executed</span>
                                <span className="font-semibold text-slate-800 dark:text-slate-200">{diagnostics.last_backup_at}</span>
                            </div>
                            <div className="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span className="text-slate-500">Backup Filename</span>
                                <span className="font-mono text-[11px] text-slate-700 dark:text-slate-300 truncate max-w-[160px]" title={diagnostics.last_backup_filename}>
                                    {diagnostics.last_backup_filename || 'None'}
                                </span>
                            </div>
                            <div className="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span className="text-slate-500">Archive Size</span>
                                <span className="font-semibold text-slate-800 dark:text-slate-200">
                                    {diagnostics.last_backup_size_mb ? `${diagnostics.last_backup_size_mb} MB` : '0 MB'}
                                </span>
                            </div>
                            <div className="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span className="text-slate-500">Automated Schedule</span>
                                <span className="font-semibold text-slate-800 dark:text-slate-200">Daily at 02:00 UTC</span>
                            </div>
                            <div className="pt-2">
                                <Button
                                    size="xs"
                                    onClick={handleRunBackup}
                                    disabled={runningBackup}
                                    className="w-full bg-slate-900 hover:bg-slate-800 text-white gap-1.5"
                                >
                                    <Play className="w-3 h-3" /> Trigger Database Backup Now
                                </Button>
                            </div>
                        </div>
                    </Card>

                    {/* Integrated Providers Status */}
                    <Card className="p-5 border-slate-200 dark:border-slate-800 space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <Cpu className="w-4 h-4 text-purple-600" /> External Provider Status
                            </h2>
                            <Link href={route('admin.integrations.index')} className="text-xs text-sky-600 hover:underline">
                                Manage
                            </Link>
                        </div>

                        <div className="space-y-3 pt-1">
                            {/* WhatsApp */}
                            <div className="flex items-center justify-between text-xs p-2 rounded-lg bg-slate-50 dark:bg-slate-800/40">
                                <div className="flex items-center gap-2">
                                    <MessageSquare className="w-4 h-4 text-emerald-600" />
                                    <span className="font-medium text-slate-800 dark:text-slate-200">WhatsApp Cloud API</span>
                                </div>
                                <Badge variant={providers.whatsapp?.configured ? 'success' : 'warning'} className="text-[10px]">
                                    {providers.whatsapp?.configured ? 'Configured' : 'Needs Setup'}
                                </Badge>
                            </div>

                            {/* Twilio */}
                            <div className="flex items-center justify-between text-xs p-2 rounded-lg bg-slate-50 dark:bg-slate-800/40">
                                <div className="flex items-center gap-2">
                                    <Phone className="w-4 h-4 text-sky-600" />
                                    <span className="font-medium text-slate-800 dark:text-slate-200">Twilio Telephony</span>
                                </div>
                                <Badge variant={providers.twilio?.configured ? 'success' : 'warning'} className="text-[10px]">
                                    {providers.twilio?.configured ? 'Configured' : 'Optional'}
                                </Badge>
                            </div>

                            {/* Email / SMTP */}
                            <div className="flex items-center justify-between text-xs p-2 rounded-lg bg-slate-50 dark:bg-slate-800/40">
                                <div className="flex items-center gap-2">
                                    <Mail className="w-4 h-4 text-amber-600" />
                                    <span className="font-medium text-slate-800 dark:text-slate-200">SMTP / Mailer</span>
                                </div>
                                <Badge variant={providers.email?.configured ? 'success' : 'warning'} className="text-[10px]">
                                    {providers.email?.configured ? `${providers.email?.mailer}` : 'Log Driver'}
                                </Badge>
                            </div>

                            {/* AI Engine */}
                            <div className="flex items-center justify-between text-xs p-2 rounded-lg bg-slate-50 dark:bg-slate-800/40">
                                <div className="flex items-center gap-2">
                                    <Bot className="w-4 h-4 text-purple-600" />
                                    <span className="font-medium text-slate-800 dark:text-slate-200">AI LLM Engine</span>
                                </div>
                                <Badge variant={providers.ai?.configured ? 'success' : 'warning'} className="text-[10px]">
                                    {providers.ai?.configured ? providers.ai?.provider : 'Needs API Key'}
                                </Badge>
                            </div>
                        </div>
                    </Card>
                </div>

                {/* Webhooks Ingestion Endpoints */}
                <Card className="p-5 border-slate-200 dark:border-slate-800 space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <Activity className="w-4 h-4 text-emerald-600" /> Inbound Webhooks Intake Endpoints
                        </h2>
                        <span className="text-xs text-slate-400">Signature-Verified & Idempotent</span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead>
                                <tr className="border-b border-slate-100 dark:border-slate-800 text-slate-400 font-semibold">
                                    <th className="pb-2">Webhook Service</th>
                                    <th className="pb-2">Public Endpoint URL</th>
                                    <th className="pb-2">Security Verification</th>
                                    <th className="pb-2">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {Object.entries(webhooks || {}).map(([key, item]) => (
                                    <tr key={key} className="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                        <td className="py-2.5 font-medium text-slate-900 dark:text-white">{item.name}</td>
                                        <td className="py-2.5 font-mono text-[11px] text-slate-500 max-w-[320px] truncate" title={item.url}>
                                            {item.url}
                                        </td>
                                        <td className="py-2.5 text-slate-500">HMAC-SHA256 / Token Auth</td>
                                        <td className="py-2.5">
                                            <Badge variant={item.status === 'active' ? 'success' : 'outline'} className="text-[10px] capitalize">
                                                {item.status}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AdminLayout>
    );
}
