import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Card, Badge, Button } from '@/Components/ui';
import {
    HardDrive, Cloud, Server, Database, ShieldCheck, RefreshCw, Trash2,
    CheckCircle2, AlertCircle, AlertTriangle, ArrowRight, Folder, FileText,
    Image, Video, Mic, UploadCloud, Users, Sparkles
} from 'lucide-react';

export default function StorageIndex({ stats = {}, orphanStats = {}, recentFiles = [], workspaces = [] }) {
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState(null);
    const [pruning, setPruning] = useState(false);

    const handleTestS3 = async () => {
        setTesting(true);
        setTestResult(null);
        try {
            const res = await window.axios.post(route('admin.storage.test-connection'));
            setTestResult(res.data);
        } catch (err) {
            setTestResult({
                ok: false,
                message: err.response?.data?.message || err.message || 'Connection test failed.',
            });
        } finally {
            setTesting(false);
        }
    };

    const handlePruneOrphans = () => {
        if (confirm('Are you sure you want to permanently prune all orphaned/soft-deleted storage objects older than 7 days?')) {
            setPruning(true);
            router.post(route('admin.storage.prune-orphans'), { days: 7 }, {
                onFinish: () => setPruning(false),
            });
        }
    };

    const isConnected = stats.status === 'Connected' || testResult?.ok === true;

    return (
        <AdminLayout>
            <Head title="AWS S3 & Storage Control Center — Admin" />

            <div className="space-y-6 max-w-7xl mx-auto pb-12">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 text-xs font-semibold uppercase tracking-wider mb-1">
                            <Cloud className="w-3.5 h-3.5" /> AWS S3 Object Storage Layer
                        </div>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                            Storage & Cloud Assets Control Center
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Centralized S3 object storage management, workspace quotas, media distribution, and orphan cleanup.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleTestS3}
                            disabled={testing}
                            className="gap-1.5 text-xs bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700"
                        >
                            <RefreshCw className={`w-3.5 h-3.5 ${testing ? 'animate-spin' : ''}`} />
                            {testing ? 'Testing AWS S3...' : 'Test S3 Connection'}
                        </Button>
                        <Link href={route('admin.integrations.edit', 'storage_s3')}>
                            <Button size="sm" className="gap-1.5 text-xs bg-[#011B40] hover:bg-[#022859] text-white">
                                <Server className="w-3.5 h-3.5" /> Configure S3 Credentials
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Connection Test Result Banner */}
                {testResult && (
                    <div className={`p-4 rounded-xl border flex items-start gap-3 ${
                        testResult.ok
                            ? 'bg-emerald-50 border-emerald-200 text-emerald-900 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300'
                            : 'bg-rose-50 border-rose-200 text-rose-900 dark:bg-rose-950/40 dark:border-rose-800 dark:text-rose-300'
                    }`}>
                        {testResult.ok ? (
                            <CheckCircle2 className="w-5 h-5 text-emerald-600 mt-0.5 flex-shrink-0" />
                        ) : (
                            <AlertCircle className="w-5 h-5 text-rose-600 mt-0.5 flex-shrink-0" />
                        )}
                        <div className="flex-1 text-xs">
                            <p className="font-semibold text-sm">{testResult.ok ? 'Connection Verified' : 'Connection Error'}</p>
                            <p className="mt-0.5">{testResult.message}</p>
                        </div>
                    </div>
                )}

                {/* Top Metrics Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Status Card */}
                    <Card className="p-5 border-slate-200 dark:border-slate-800 flex items-start gap-4">
                        <div className={`p-3 rounded-xl ${isConnected ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'}`}>
                            <Cloud className="w-6 h-6" />
                        </div>
                        <div>
                            <span className="text-xs text-slate-500 font-medium">Active Provider</span>
                            <div className="flex items-center gap-2 mt-1">
                                <span className="text-lg font-bold text-slate-900 dark:text-white">AWS S3</span>
                                <Badge variant={isConnected ? 'success' : 'warning'} className="text-[10px]">
                                    {isConnected ? 'Active' : 'Untested'}
                                </Badge>
                            </div>
                            <p className="text-[11px] text-slate-400 mt-1">
                                {stats.bucket && stats.bucket !== 'Not Configured' ? `Bucket: ${stats.bucket}` : 'Using local fallback'}
                            </p>
                        </div>
                    </Card>

                    {/* Total Storage Used */}
                    <Card className="p-5 border-slate-200 dark:border-slate-800 flex items-start gap-4">
                        <div className="p-3 rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-400">
                            <HardDrive className="w-6 h-6" />
                        </div>
                        <div>
                            <span className="text-xs text-slate-500 font-medium">Total Stored Media</span>
                            <div className="flex items-center gap-2 mt-1">
                                <span className="text-lg font-bold text-slate-900 dark:text-white">
                                    {stats.total_storage_formatted || '0 B'}
                                </span>
                            </div>
                            <p className="text-[11px] text-slate-400 mt-1">
                                {stats.total_objects || 0} files stored across tenants
                            </p>
                        </div>
                    </Card>

                    {/* S3 Region & Prefix */}
                    <Card className="p-5 border-slate-200 dark:border-slate-800 flex items-start gap-4">
                        <div className="p-3 rounded-xl bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-400">
                            <Server className="w-6 h-6" />
                        </div>
                        <div>
                            <span className="text-xs text-slate-500 font-medium">Region & Scope</span>
                            <div className="flex items-center gap-2 mt-1">
                                <span className="text-sm font-bold text-slate-900 dark:text-white">
                                    {stats.region || 'us-east-1'}
                                </span>
                            </div>
                            <p className="text-[11px] text-slate-400 mt-1">
                                Prefix: {stats.directory_prefix ? stats.directory_prefix : 'workspaces/{id}/'}
                            </p>
                        </div>
                    </Card>

                    {/* Orphan Cleanup */}
                    <Card className="p-5 border-slate-200 dark:border-slate-800 flex items-start gap-4">
                        <div className="p-3 rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400">
                            <Trash2 className="w-6 h-6" />
                        </div>
                        <div className="flex-1">
                            <span className="text-xs text-slate-500 font-medium">Orphan & Trashed Files</span>
                            <div className="flex items-center justify-between gap-2 mt-1">
                                <span className="text-sm font-bold text-slate-900 dark:text-white">
                                    {orphanStats.trashed_formatted || '0 B'}
                                </span>
                                <Button
                                    size="xs"
                                    variant="outline"
                                    onClick={handlePruneOrphans}
                                    disabled={pruning || (orphanStats.trashed_count || 0) === 0}
                                    className="text-[10px] text-rose-600 hover:bg-rose-50 border-rose-200"
                                >
                                    {pruning ? 'Pruning...' : 'Prune'}
                                </Button>
                            </div>
                            <p className="text-[11px] text-slate-400 mt-1">
                                {orphanStats.trashed_count || 0} soft-deleted objects eligible
                            </p>
                        </div>
                    </Card>
                </div>

                {/* Storage Categories Distribution & Architecture */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Categories Breakdown */}
                    <Card className="p-5 border-slate-200 dark:border-slate-800 lg:col-span-1 space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <Folder className="w-4 h-4 text-emerald-600" /> Category Breakdown
                            </h2>
                            <Badge variant="outline" className="text-[10px]">
                                {Object.keys(stats.categories || {}).length} Categories
                            </Badge>
                        </div>

                        <div className="space-y-3 pt-2">
                            {Object.entries(stats.categories || {}).length === 0 ? (
                                <p className="text-xs text-slate-400 py-4 text-center">No files uploaded yet.</p>
                            ) : (
                                Object.entries(stats.categories || {}).map(([catKey, catData]) => (
                                    <div key={catKey} className="space-y-1">
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="font-medium text-slate-700 dark:text-slate-300 capitalize">
                                                {catKey.replace('_', ' ')}
                                            </span>
                                            <span className="text-slate-500 font-semibold">
                                                {catData.formatted} ({catData.count})
                                            </span>
                                        </div>
                                        <div className="w-full bg-slate-100 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                            <div
                                                className="bg-emerald-600 h-full rounded-full"
                                                style={{
                                                    width: `${Math.min(100, Math.max(5, stats.total_bytes > 0 ? (catData.bytes / stats.total_bytes) * 100 : 0))}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </Card>

                    {/* Top Workspaces Storage Usage Table */}
                    <Card className="p-5 border-slate-200 dark:border-slate-800 lg:col-span-2 space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <Users className="w-4 h-4 text-sky-600" /> Top Workspace Storage Usage
                            </h2>
                            <span className="text-xs text-slate-400">Quota Monitoring</span>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="border-b border-slate-100 dark:border-slate-800 text-slate-400 font-semibold">
                                        <th className="pb-2">Workspace</th>
                                        <th className="pb-2">Subscription Plan</th>
                                        <th className="pb-2">Used Storage</th>
                                        <th className="pb-2">Quota Limit</th>
                                        <th className="pb-2">Usage %</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {workspaces.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="py-6 text-center text-slate-400">
                                                No active tenant storage usage recorded yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        workspaces.map((ws) => (
                                            <tr key={ws.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                                <td className="py-2.5 font-semibold text-slate-900 dark:text-white">
                                                    {ws.name}
                                                    <span className="block text-[10px] text-slate-400 font-normal">{ws.client_name}</span>
                                                </td>
                                                <td className="py-2.5 text-slate-600 dark:text-slate-400">{ws.plan_name}</td>
                                                <td className="py-2.5 font-semibold text-emerald-700 dark:text-emerald-400">{ws.used_formatted}</td>
                                                <td className="py-2.5 text-slate-500">{ws.quota_formatted}</td>
                                                <td className="py-2.5">
                                                    <div className="flex items-center gap-2">
                                                        <div className="w-16 bg-slate-100 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                                            <div
                                                                className={`h-full rounded-full ${ws.usage_percentage > 90 ? 'bg-rose-500' : ws.usage_percentage > 75 ? 'bg-amber-500' : 'bg-emerald-600'}`}
                                                                style={{ width: `${Math.min(100, ws.usage_percentage)}%` }}
                                                            />
                                                        </div>
                                                        <span className="text-[11px] text-slate-500 font-medium">
                                                            {ws.usage_percentage}%
                                                        </span>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </div>

                {/* Recent Stored Files Across Platform */}
                <Card className="p-5 border-slate-200 dark:border-slate-800 space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <UploadCloud className="w-4 h-4 text-emerald-600" /> Recent Stored Objects Audit
                        </h2>
                        <span className="text-xs text-slate-400">Live Workspace Uploads</span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead>
                                <tr className="border-b border-slate-100 dark:border-slate-800 text-slate-400 font-semibold">
                                    <th className="pb-2">Original Filename</th>
                                    <th className="pb-2">Workspace</th>
                                    <th className="pb-2">Category</th>
                                    <th className="pb-2">Size</th>
                                    <th className="pb-2">S3 Storage Path</th>
                                    <th className="pb-2">Uploaded At</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {recentFiles.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-6 text-center text-slate-400">
                                            No recent files uploaded.
                                        </td>
                                    </tr>
                                ) : (
                                    recentFiles.map((f) => (
                                        <tr key={f.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                            <td className="py-2.5 font-medium text-slate-900 dark:text-white max-w-[200px] truncate" title={f.original_name}>
                                                {f.original_name}
                                            </td>
                                            <td className="py-2.5 text-slate-600 dark:text-slate-300 font-medium">{f.workspace_name}</td>
                                            <td className="py-2.5">
                                                <Badge variant="outline" className="text-[10px] capitalize">
                                                    {f.category.replace('_', ' ')}
                                                </Badge>
                                            </td>
                                            <td className="py-2.5 font-mono text-[11px] text-slate-500">{f.formatted_size}</td>
                                            <td className="py-2.5 font-mono text-[10px] text-slate-400 max-w-[280px] truncate" title={f.key}>
                                                {f.key}
                                            </td>
                                            <td className="py-2.5 text-slate-400 text-[11px]">{f.created_at}</td>
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
