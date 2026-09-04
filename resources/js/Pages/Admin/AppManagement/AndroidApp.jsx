import React, { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    Smartphone,
    Upload,
    Download,
    QrCode,
    CheckCircle2,
    AlertTriangle,
    ShieldAlert,
    Save,
    History,
    FileText,
    ExternalLink,
} from 'lucide-react';
import { toast } from 'sonner';

export default function AndroidAppManagement({ release, stats, releases_history = [] }) {
    const { t } = useTranslation();

    // Release Metadata Form
    const settingsForm = useForm({
        version: release.version ?? '1.0.0',
        version_code: release.version_code ?? 100,
        min_supported_version: release.min_supported_version ?? '1.0.0',
        download_url: release.download_url ?? '',
        file_size_mb: release.file_size_mb ?? 28.50,
        release_notes: release.release_notes ?? '',
        force_update_required: Boolean(release.force_update_required),
        is_active: Boolean(release.is_active),
    });

    // Upload Form
    const uploadForm = useForm({
        apk_file: null,
    });

    const handleSaveSettings = (e) => {
        e.preventDefault();
        settingsForm.post(route('admin.app-management.android.update'), {
            onSuccess: () => toast.success('Android release configuration updated!'),
            onError: () => toast.error('Failed to update release configuration'),
        });
    };

    const handleUploadApk = (e) => {
        e.preventDefault();
        if (!uploadForm.data.apk_file) {
            toast.error('Please select an .apk file to upload');
            return;
        }

        uploadForm.post(route('admin.app-management.android.upload'), {
            onSuccess: () => {
                toast.success('APK file uploaded and published successfully!');
                uploadForm.reset('apk_file');
            },
            onError: () => toast.error('Failed to upload APK file'),
        });
    };

    return (
        <AdminLayout title="Android App Management">
            <Head title="Admin — Android App Management" />

            <div className="max-w-6xl mx-auto space-y-8 pb-12">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                            <Smartphone className="h-7 w-7 text-emerald-500" />
                            Android App Distribution & Updates
                        </h1>
                        <p className="text-sm text-neutral-500 dark:text-neutral-400">
                            Manage version releases, force-updates, APK file hosting, and QR codes for the Growbridge Connect mobile app.
                        </p>
                    </div>

                    <a
                        href={release.effective_download_url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/10 transition shadow-sm self-start sm:self-auto"
                    >
                        <Download className="h-4 w-4" />
                        Test APK Download
                        <ExternalLink className="h-3.5 w-3.5" />
                    </a>
                </div>

                {/* KPI Metrics */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm space-y-1">
                        <div className="text-xs font-semibold text-neutral-400 uppercase tracking-wider">
                            Total Downloads
                        </div>
                        <div className="text-3xl font-extrabold text-neutral-900 dark:text-white">
                            {stats.total_downloads.toLocaleString()}
                        </div>
                        <div className="text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                            Across all releases
                        </div>
                    </div>

                    <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm space-y-1">
                        <div className="text-xs font-semibold text-neutral-400 uppercase tracking-wider">
                            Active Version
                        </div>
                        <div className="text-3xl font-extrabold text-neutral-900 dark:text-white flex items-center gap-2">
                            v{stats.active_version}
                            <span className="text-xs font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-500/15 px-2.5 py-0.5 rounded-full">
                                Code {release.version_code}
                            </span>
                        </div>
                        <div className="text-xs text-neutral-500">
                            Size: {release.file_size_mb} MB
                        </div>
                    </div>

                    <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm space-y-1">
                        <div className="text-xs font-semibold text-neutral-400 uppercase tracking-wider">
                            Force Update Status
                        </div>
                        <div className="text-2xl font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                            {stats.force_update_active ? (
                                <span className="text-rose-500 flex items-center gap-1.5 text-lg">
                                    <AlertTriangle className="h-5 w-5" /> Enabled (Strict)
                                </span>
                            ) : (
                                <span className="text-emerald-500 flex items-center gap-1.5 text-lg">
                                    <CheckCircle2 className="h-5 w-5" /> Optional Update
                                </span>
                            )}
                        </div>
                        <div className="text-xs text-neutral-500">
                            Min supported: v{release.min_supported_version}
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    {/* Left: Configuration Form */}
                    <div className="lg:col-span-8 space-y-6">
                        <form onSubmit={handleSaveSettings} className="rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-6 sm:p-8 shadow-sm space-y-6">
                            <h2 className="text-lg font-bold text-neutral-900 dark:text-white flex items-center gap-2 border-b border-neutral-100 dark:border-neutral-800 pb-4">
                                <FileText className="h-5 w-5 text-emerald-500" />
                                Release Version & Download Settings
                            </h2>

                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">
                                        Release Version *
                                    </label>
                                    <input
                                        type="text"
                                        value={settingsForm.data.version}
                                        onChange={e => settingsForm.setData('version', e.target.value)}
                                        placeholder="1.0.0"
                                        required
                                        className="w-full rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3.5 py-2.5 text-sm text-neutral-900 dark:text-white"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">
                                        Version Code (Build #) *
                                    </label>
                                    <input
                                        type="number"
                                        value={settingsForm.data.version_code}
                                        onChange={e => settingsForm.setData('version_code', e.target.value)}
                                        placeholder="100"
                                        required
                                        className="w-full rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3.5 py-2.5 text-sm text-neutral-900 dark:text-white"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">
                                        Min Supported Version
                                    </label>
                                    <input
                                        type="text"
                                        value={settingsForm.data.min_supported_version}
                                        onChange={e => settingsForm.setData('min_supported_version', e.target.value)}
                                        placeholder="1.0.0"
                                        required
                                        className="w-full rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3.5 py-2.5 text-sm text-neutral-900 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">
                                        APK File Size (MB)
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={settingsForm.data.file_size_mb}
                                        onChange={e => settingsForm.setData('file_size_mb', e.target.value)}
                                        placeholder="28.50"
                                        className="w-full rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3.5 py-2.5 text-sm text-neutral-900 dark:text-white"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">
                                        External Download URL (Optional)
                                    </label>
                                    <input
                                        type="url"
                                        value={settingsForm.data.download_url}
                                        onChange={e => settingsForm.setData('download_url', e.target.value)}
                                        placeholder="https://cdn.example.com/growbridge-v1.0.0.apk"
                                        className="w-full rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3.5 py-2.5 text-sm text-neutral-900 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">
                                    Release Notes & Changelog
                                </label>
                                <textarea
                                    value={settingsForm.data.release_notes}
                                    onChange={e => settingsForm.setData('release_notes', e.target.value)}
                                    rows={4}
                                    placeholder="• Feature 1...&#10;• Feature 2..."
                                    className="w-full rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3.5 py-2.5 text-sm text-neutral-900 dark:text-white"
                                />
                            </div>

                            {/* Toggles */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                <label className="flex items-center gap-3 p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/40 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={settingsForm.data.force_update_required}
                                        onChange={e => settingsForm.setData('force_update_required', e.target.checked)}
                                        className="h-4 w-4 rounded border-gray-300 text-rose-600 focus:ring-rose-500"
                                    />
                                    <div>
                                        <div className="text-xs font-bold text-neutral-800 dark:text-neutral-200">
                                            Force Update Required
                                        </div>
                                        <div className="text-[11px] text-neutral-500">
                                            Blocks outdated mobile versions
                                        </div>
                                    </div>
                                </label>

                                <label className="flex items-center gap-3 p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/40 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={settingsForm.data.is_active}
                                        onChange={e => settingsForm.setData('is_active', e.target.checked)}
                                        className="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                    />
                                    <div>
                                        <div className="text-xs font-bold text-neutral-800 dark:text-neutral-200">
                                            Enable Public Download
                                        </div>
                                        <div className="text-[11px] text-neutral-500">
                                            Shows download card in User Panel
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <button
                                type="submit"
                                disabled={settingsForm.processing}
                                className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/25 transition"
                            >
                                <Save className="h-4 w-4" />
                                Save Release Settings
                            </button>
                        </form>
                    </div>

                    {/* Right: Upload APK & Live QR */}
                    <div className="lg:col-span-4 space-y-6">
                        {/* Direct File Upload */}
                        <div className="rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-6 shadow-sm space-y-4">
                            <h3 className="text-base font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                <Upload className="h-4 w-4 text-emerald-500" />
                                Direct APK Upload
                            </h3>

                            <form onSubmit={handleUploadApk} className="space-y-4">
                                <div className="border-2 border-dashed border-neutral-300 dark:border-neutral-700 rounded-xl p-4 text-center hover:border-emerald-500 transition">
                                    <input
                                        type="file"
                                        accept=".apk"
                                        onChange={e => uploadForm.setData('apk_file', e.target.files[0])}
                                        className="w-full text-xs text-neutral-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
                                    />
                                    <p className="text-[11px] text-neutral-400 mt-2">
                                        Upload signed release .apk file (Max 150 MB)
                                    </p>
                                </div>

                                <button
                                    type="submit"
                                    disabled={uploadForm.processing || !uploadForm.data.apk_file}
                                    className="w-full py-2.5 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition disabled:opacity-50"
                                >
                                    Upload & Publish APK
                                </button>
                            </form>
                        </div>

                        {/* Live QR Code Preview */}
                        <div className="rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-6 text-center space-y-3 shadow-sm">
                            <div className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">
                                📱 Scannable Download QR Code
                            </div>

                            <div className="w-40 h-40 mx-auto bg-white p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 shadow-inner flex items-center justify-center">
                                <img
                                    src={release.qr_code_url}
                                    alt="Live APK download QR Code"
                                    className="w-full h-full object-contain"
                                />
                            </div>

                            <div className="text-[11px] text-neutral-400">
                                Scan with any mobile device camera to download the active APK.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
