import React, { useState, useRef } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import {
    Building2,
    Globe,
    Clock,
    Upload,
    Trash2,
    CheckCircle2,
    Shield,
    Users,
    Sparkles,
    Briefcase,
    Calendar,
    Save,
    AlertCircle,
} from 'lucide-react';
import { toast } from 'sonner';

export default function WorkspaceSettings({ workspace, industries = [] }) {
    const fileInputRef = useRef(null);
    const [uploadingLogo, setUploadingLogo] = useState(false);

    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({
        name: workspace.name || '',
        industry: workspace.industry || 'Retail & E-commerce',
        website: workspace.website || '',
        country: workspace.country || 'India',
        timezone: workspace.timezone || 'Asia/Kolkata',
        business_hours: workspace.business_hours || {
            monday: { open: '09:00', close: '18:00', closed: false },
            tuesday: { open: '09:00', close: '18:00', closed: false },
            wednesday: { open: '09:00', close: '18:00', closed: false },
            thursday: { open: '09:00', close: '18:00', closed: false },
            friday: { open: '09:00', close: '18:00', closed: false },
            saturday: { open: '10:00', close: '16:00', closed: false },
            sunday: { open: '00:00', close: '00:00', closed: true },
        },
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('client.settings.workspace.update'), {
            preserveScroll: true,
            onSuccess: () => toast.success('Workspace profile updated successfully.'),
            onError: () => toast.error('Failed to update workspace. Please check the form errors.'),
        });
    };

    const handleLogoUpload = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;

        if (file.size > 5 * 1024 * 1024) {
            toast.error('Logo file size must be less than 5MB.');
            return;
        }

        const formData = new FormData();
        formData.append('logo', file);

        setUploadingLogo(true);
        router.post(route('client.settings.workspace.logo.upload'), formData, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Workspace logo uploaded to S3 successfully.');
                setUploadingLogo(false);
            },
            onError: () => {
                toast.error('Failed to upload logo.');
                setUploadingLogo(false);
            },
        });
    };

    const handleRemoveLogo = () => {
        if (!confirm('Are you sure you want to remove the workspace logo?')) return;

        router.delete(route('client.settings.workspace.logo.delete'), {
            preserveScroll: true,
            onSuccess: () => toast.success('Workspace logo removed.'),
        });
    };

    const days = [
        { key: 'monday', label: 'Monday' },
        { key: 'tuesday', label: 'Tuesday' },
        { key: 'wednesday', label: 'Wednesday' },
        { key: 'thursday', label: 'Thursday' },
        { key: 'friday', label: 'Friday' },
        { key: 'saturday', label: 'Saturday' },
        { key: 'sunday', label: 'Sunday' },
    ];

    const updateDayHours = (dayKey, field, value) => {
        setData('business_hours', {
            ...data.business_hours,
            [dayKey]: {
                ...data.business_hours[dayKey],
                [field]: value,
            },
        });
    };

    return (
        <ClientLayout title="Workspace Settings">
            <Head title="Workspace Settings — Growbridge Connect" />

            <div className="max-w-6xl mx-auto space-y-6 pb-12">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="w-14 h-14 rounded-2xl bg-gradient-to-tr from-[#011B40] to-[#064E3B] flex items-center justify-center text-white shadow-md">
                            <Building2 className="w-7 h-7 text-[#FEB51B]" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                                    {workspace.name}
                                </h1>
                                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                    <Shield className="w-3 h-3" />
                                    {workspace.current_user_role?.toUpperCase()}
                                </span>
                            </div>
                            <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                Manage your multi-tenant business identity, brand logo, industry profile, and operational schedules.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <div className="flex items-center gap-2 px-3 py-2 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 text-xs text-slate-600 dark:text-slate-300">
                            <Users className="w-4 h-4 text-emerald-600" />
                            <span><strong>{workspace.members_count}</strong> Team Members</span>
                        </div>
                    </div>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Brand & Business Identity */}
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm p-6 space-y-6">
                        <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                            <div>
                                <h2 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <Briefcase className="w-5 h-5 text-[#064E3B]" />
                                    Business Profile & Identity
                                </h2>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Configure your company name, brand assets, and target vertical.
                                </p>
                            </div>
                        </div>

                        {/* Logo Upload */}
                        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-6 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-800">
                            <div className="relative group">
                                <div className="w-24 h-24 rounded-2xl bg-white dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-700 flex items-center justify-center overflow-hidden shadow-inner">
                                    {workspace.logo_url ? (
                                        <img
                                            src={workspace.logo_url}
                                            alt={workspace.name}
                                            className="w-full h-full object-cover"
                                        />
                                    ) : (
                                        <Building2 className="w-10 h-10 text-slate-400" />
                                    )}
                                </div>
                            </div>

                            <div className="space-y-2 flex-1">
                                <h4 className="text-sm font-semibold text-slate-900 dark:text-white">
                                    Workspace Brand Logo
                                </h4>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    PNG, JPG, WEBP, or SVG up to 5MB. Stored privately on AWS S3 with encrypted workspace-scoped isolation.
                                </p>

                                <div className="flex items-center gap-2 pt-1">
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                        className="hidden"
                                        onChange={handleLogoUpload}
                                    />
                                    <button
                                        type="button"
                                        disabled={uploadingLogo}
                                        onClick={() => fileInputRef.current?.click()}
                                        className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-[#011B40] text-white hover:bg-slate-800 transition shadow-sm disabled:opacity-50"
                                    >
                                        <Upload className="w-3.5 h-3.5" />
                                        {uploadingLogo ? 'Uploading to S3...' : 'Upload Logo'}
                                    </button>

                                    {workspace.logo_url && (
                                        <button
                                            type="button"
                                            onClick={handleRemoveLogo}
                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition border border-rose-200 dark:border-rose-900"
                                        >
                                            <Trash2 className="w-3.5 h-3.5" />
                                            Remove
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Fields Grid */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                                    Business / Company Name <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-[#064E3B] focus:border-transparent outline-none transition"
                                    placeholder="e.g. Acme Corporation"
                                />
                                {errors.name && (
                                    <p className="text-xs text-rose-500 mt-1">{errors.name}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                                    Industry Vertical
                                </label>
                                <select
                                    value={data.industry}
                                    onChange={(e) => setData('industry', e.target.value)}
                                    className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-[#064E3B] focus:border-transparent outline-none transition"
                                >
                                    {industries.map((ind) => (
                                        <option key={ind} value={ind}>
                                            {ind}
                                        </option>
                                    ))}
                                </select>
                                {errors.industry && (
                                    <p className="text-xs text-rose-500 mt-1">{errors.industry}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                                    Website URL
                                </label>
                                <div className="relative">
                                    <Globe className="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                                    <input
                                        type="url"
                                        value={data.website}
                                        onChange={(e) => setData('website', e.target.value)}
                                        className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-[#064E3B] focus:border-transparent outline-none transition"
                                        placeholder="https://example.com"
                                    />
                                </div>
                                {errors.website && (
                                    <p className="text-xs text-rose-500 mt-1">{errors.website}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                                    Country / Region
                                </label>
                                <input
                                    type="text"
                                    value={data.country}
                                    onChange={(e) => setData('country', e.target.value)}
                                    className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-[#064E3B] focus:border-transparent outline-none transition"
                                    placeholder="e.g. India, United States, UAE"
                                />
                                {errors.country && (
                                    <p className="text-xs text-rose-500 mt-1">{errors.country}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                                    Primary Timezone
                                </label>
                                <div className="relative">
                                    <Clock className="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                                    <input
                                        type="text"
                                        value={data.timezone}
                                        onChange={(e) => setData('timezone', e.target.value)}
                                        className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-[#064E3B] focus:border-transparent outline-none transition"
                                        placeholder="e.g. Asia/Kolkata, UTC, America/New_York"
                                    />
                                </div>
                                {errors.timezone && (
                                    <p className="text-xs text-rose-500 mt-1">{errors.timezone}</p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Operational & Business Hours */}
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm p-6 space-y-6">
                        <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                            <div>
                                <h2 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <Calendar className="w-5 h-5 text-[#011B40] dark:text-[#FEB51B]" />
                                    Weekly Operational Hours
                                </h2>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Defines operating schedules for automated CRM routing, AI agent availability, and off-hour auto-responses.
                                </p>
                            </div>
                        </div>

                        <div className="space-y-3">
                            {days.map(({ key, label }) => {
                                const schedule = data.business_hours?.[key] || { open: '09:00', close: '18:00', closed: false };
                                return (
                                    <div
                                        key={key}
                                        className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800 text-sm"
                                    >
                                        <div className="w-32 font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                                            <span>{label}</span>
                                        </div>

                                        <div className="flex items-center gap-4 flex-1">
                                            {!schedule.closed ? (
                                                <div className="flex items-center gap-2">
                                                    <input
                                                        type="time"
                                                        value={schedule.open}
                                                        onChange={(e) => updateDayHours(key, 'open', e.target.value)}
                                                        className="px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-white focus:ring-1 focus:ring-[#064E3B] outline-none"
                                                    />
                                                    <span className="text-xs text-slate-400">to</span>
                                                    <input
                                                        type="time"
                                                        value={schedule.close}
                                                        onChange={(e) => updateDayHours(key, 'close', e.target.value)}
                                                        className="px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-white focus:ring-1 focus:ring-[#064E3B] outline-none"
                                                    />
                                                </div>
                                            ) : (
                                                <span className="text-xs font-semibold text-slate-400 italic">
                                                    Closed / Out of Office
                                                </span>
                                            )}
                                        </div>

                                        <div>
                                            <label className="inline-flex items-center gap-2 cursor-pointer text-xs text-slate-600 dark:text-slate-400">
                                                <input
                                                    type="checkbox"
                                                    checked={schedule.closed}
                                                    onChange={(e) => updateDayHours(key, 'closed', e.target.checked)}
                                                    className="rounded text-[#064E3B] focus:ring-[#064E3B] dark:bg-slate-800 dark:border-slate-700"
                                                />
                                                <span>Mark Closed</span>
                                            </label>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Submit Bar */}
                    <div className="flex items-center justify-end gap-3 pt-2">
                        {recentlySuccessful && (
                            <span className="text-xs font-semibold text-emerald-600 flex items-center gap-1">
                                <CheckCircle2 className="w-4 h-4" /> Saved!
                            </span>
                        )}

                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold bg-[#064E3B] hover:bg-[#043327] text-white transition shadow-sm disabled:opacity-50"
                        >
                            <Save className="w-4 h-4" />
                            {processing ? 'Saving Changes...' : 'Save Workspace Profile'}
                        </button>
                    </div>
                </form>
            </div>
        </ClientLayout>
    );
}
