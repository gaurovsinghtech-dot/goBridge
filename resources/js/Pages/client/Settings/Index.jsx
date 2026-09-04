import ClientLayout from '@/Layouts/ClientLayout';
import { Button } from '@/Components/ui';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Settings as SettingsIcon, Bell, Download, HelpCircle, Compass, Smartphone } from 'lucide-react';
import { toast } from 'sonner';
import { browserTz } from '@/Utils/datetime';
import TimezonePicker from '@/Components/TimezonePicker';

export default function ClientSettingsIndex({
    preferences = {},
    supportedLocales = [],
    supportedCurrencies = [],
    client = null,
    digestEnabled = true,
    android_app = null,
}) {
    const { t } = useTranslation();
    const { flash = {} } = usePage().props;
    const form = useForm({
        locale: preferences.locale ?? 'en',
        display_currency: preferences.display_currency ?? 'USD',
        theme: preferences.theme ?? 'light',
        timezone: preferences.timezone ?? browserTz() ?? 'Asia/Dhaka',
        client_name: client?.name ?? '',
        client_email: client?.email ?? '',
        client_phone: client?.phone ?? '',
        client_address: client?.address ?? '',
        weekly_digest_enabled: digestEnabled,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        form.put(route('client.settings.update'));
    };

    const handleRestartTour = async () => {
        try {
            await fetch(route('client.tour.reset'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ tour_key: 'dashboard_tour' }),
            });
            toast.success(t('tour.restarted', 'Product tour restarted!'));
            window.dispatchEvent(new CustomEvent('start-dashboard-tour'));
        } catch {
            toast.error(t('tour.restart_failed', 'Failed to restart tour'));
        }
    };

    return (
        <ClientLayout title={t('settings.page_title') || 'Settings'}>
            <Head title={t('settings.page_title') || 'Settings'} />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-neutral-900 dark:text-white">
                        {t('settings.page_title') || 'Settings'}
                    </h1>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        {t('client.settings_subtitle') || 'Preferences and organization'}
                    </p>
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200 px-4 py-3 text-sm">
                        {flash.success}
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-8">
                    <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800/50 p-6">
                        <h2 className="text-lg font-semibold text-neutral-900 dark:text-white mb-4 flex items-center gap-2">
                            <SettingsIcon className="h-5 w-5" />
                            {t('client.preferences') || 'Preferences'}
                        </h2>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                    {t('client.language') || 'Language'}
                                </label>
                                <select
                                    value={form.data.locale}
                                    onChange={e => form.setData('locale', e.target.value)}
                                    className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                >
                                    {supportedLocales.map((l) => (
                                        <option key={l.code} value={l.code}>{l.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                    {t('client.display_currency') || 'Display currency'}
                                </label>
                                <select
                                    value={form.data.display_currency}
                                    onChange={e => form.setData('display_currency', e.target.value)}
                                    className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                >
                                    {supportedCurrencies.map((c) => (
                                        <option key={c.code} value={c.code}>
                                            {c.code} {c.symbol ? `(${c.symbol})` : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                    {t('client.theme') || 'Theme'}
                                </label>
                                <select
                                    value={form.data.theme}
                                    onChange={e => form.setData('theme', e.target.value)}
                                    className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                >
                                    <option value="light">{t('client.theme_light') || 'Light'}</option>
                                    <option value="dark">{t('client.theme_dark') || 'Dark'}</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                    {t('settings.timezone')}
                                </label>
                                <TimezonePicker
                                    value={form.data.timezone}
                                    onChange={tz => form.setData('timezone', tz)}
                                />
                                {form.errors.timezone && <p className="mt-1 text-xs text-red-500">{form.errors.timezone}</p>}
                                <p className="mt-1 text-xs text-neutral-400">{t('settings.timezone_hint')}</p>
                            </div>
                        </div>
                    </div>

                    {client && (
                        <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800/50 p-6">
                            <h2 className="text-lg font-semibold text-neutral-900 dark:text-white mb-4">
                                {t('client.organization') || 'Organization'}
                            </h2>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="sm:col-span-2">
                                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                        {t('client.organization_name') || 'Organization name'}
                                    </label>
                                    <input
                                        type="text"
                                        value={form.data.client_name}
                                        onChange={e => form.setData('client_name', e.target.value)}
                                        className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                        {t('client.organization_email') || 'Email'}
                                    </label>
                                    <input
                                        type="email"
                                        value={form.data.client_email}
                                        onChange={e => form.setData('client_email', e.target.value)}
                                        className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                        {t('client.phone') || 'Phone'}
                                    </label>
                                    <input
                                        type="text"
                                        value={form.data.client_phone}
                                        onChange={e => form.setData('client_phone', e.target.value)}
                                        className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                    />
                                </div>
                                <div className="sm:col-span-2">
                                    <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                        {t('client.address') || 'Address'}
                                    </label>
                                    <textarea
                                        value={form.data.client_address}
                                        onChange={e => form.setData('client_address', e.target.value)}
                                        rows={2}
                                        className="w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Email Digest */}
                    <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 sm:p-5">
                        <h3 className="text-sm font-semibold text-neutral-600 dark:text-neutral-300 uppercase tracking-wide mb-3">{t('settings.email_reports')}</h3>
                        <label className="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={!!form.data.weekly_digest_enabled}
                                onChange={e => form.setData('weekly_digest_enabled', e.target.checked)}
                                className="h-4 w-4 rounded border-gray-300 text-indigo-600"
                            />
                            <span className="text-sm text-neutral-700 dark:text-neutral-300">
                                {t('settings.weekly_digest_label')}
                            </span>
                        </label>
                    </div>

                    {(
                        <Button type="submit" variant="primary" disabled={form.processing}>
                            {t('client.save_settings') || 'Save settings'}
                        </Button>
                    )}
                </form>

                {/* Growbridge Connect Android App */}
                {android_app && android_app.is_active && (
                    <div className="rounded-2xl border border-emerald-500/30 dark:border-emerald-500/20 bg-gradient-to-br from-emerald-500/5 via-white to-teal-500/5 dark:from-[#08201a] dark:via-[#091814] dark:to-[#041510] shadow-lg p-6 sm:p-8 space-y-6">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-emerald-500/15 dark:border-emerald-500/10 pb-5">
                            <div className="flex items-center gap-3.5">
                                <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/25 shadow-inner">
                                    <Smartphone className="h-6 w-6" />
                                </div>
                                <div>
                                    <h2 className="text-xl font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                        📱 {t('mobile.app_title', 'Growbridge Connect Android App')}
                                    </h2>
                                    <p className="text-sm text-neutral-600 dark:text-neutral-300">
                                        {t('mobile.app_subtitle', 'Chat with customers. Make business calls. Manage your business anywhere.')}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2 self-start sm:self-auto">
                                <span className="text-xs font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-500/15 px-3 py-1 rounded-full border border-emerald-500/25">
                                    v{android_app.version}
                                </span>
                                <span className="text-xs font-medium text-neutral-500 dark:text-neutral-400 bg-neutral-100 dark:bg-neutral-800 px-2.5 py-1 rounded-full">
                                    {android_app.file_size_mb} MB
                                </span>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
                            {/* Left Col: Features & Install Guide */}
                            <div className="md:col-span-2 space-y-4">
                                <div className="rounded-xl bg-white/70 dark:bg-black/30 border border-neutral-200/70 dark:border-neutral-800/80 p-4 space-y-2.5">
                                    <h3 className="text-xs font-bold text-neutral-700 dark:text-neutral-300 uppercase tracking-wider">
                                        ✨ {t('mobile.features_heading', 'What you can do with the mobile app')}
                                    </h3>
                                    <ul className="text-xs sm:text-sm text-neutral-600 dark:text-neutral-300 space-y-2">
                                        <li className="flex items-start gap-2">
                                            <span className="text-emerald-500 font-bold">✓</span>
                                            <span><strong>Unified WhatsApp Inbox:</strong> Real-time chatting, filters, and AI suggested replies.</span>
                                        </li>
                                        <li className="flex items-start gap-2">
                                            <span className="text-emerald-500 font-bold">✓</span>
                                            <span><strong>In-App Business Calling:</strong> Make and receive VoIP calls with virtual numbers.</span>
                                        </li>
                                        <li className="flex items-start gap-2">
                                            <span className="text-emerald-500 font-bold">✓</span>
                                            <span><strong>360° Customer Profile:</strong> Integrated chat history, call logs, CRM status & AI memory.</span>
                                        </li>
                                    </ul>
                                </div>

                                <div className="flex flex-wrap items-center gap-3 pt-2">
                                    <a
                                        href={android_app.download_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-2.5 px-6 py-3 rounded-xl text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/25 transition active:scale-[0.98]"
                                    >
                                        <Download className="h-4 w-4" />
                                        {t('mobile.download_apk_btn', 'Download Android APK')}
                                    </a>
                                    <Link
                                        href={route('client.mobile-app.index')}
                                        className="inline-flex items-center gap-1.5 px-4 py-3 rounded-xl text-sm font-semibold border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                                    >
                                        {t('mobile.view_details_btn', 'View Installation Guide')} →
                                    </Link>
                                </div>
                            </div>

                            {/* Right Col: Live Scannable QR Code */}
                            <div className="flex flex-col items-center justify-center p-5 rounded-2xl bg-white dark:bg-black/40 border border-emerald-500/20 shadow-inner text-center space-y-3">
                                <div className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">
                                    📱 {t('mobile.scan_to_download', 'Scan to Download on Phone')}
                                </div>
                                <div className="w-36 h-36 bg-white p-2 rounded-xl border border-neutral-200 dark:border-neutral-700 shadow-sm flex items-center justify-center">
                                    <img
                                        src={android_app.qr_code_url}
                                        alt="Scan QR code to download Growbridge Connect Android APK"
                                        className="w-full h-full object-contain"
                                    />
                                </div>
                                <div className="text-[11px] text-neutral-400 dark:text-neutral-500">
                                    Point camera to download APK directly
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Help & Support / Product Tour */}
                <div className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800/50 p-6">
                    <h2 className="text-lg font-semibold text-neutral-900 dark:text-white mb-2 flex items-center gap-2">
                        <HelpCircle className="h-5 w-5 text-emerald-500" />
                        {t('settings.help_and_support') || 'Help & Support'}
                    </h2>
                    <p className="text-sm text-neutral-500 dark:text-neutral-400 mb-4">
                        {t('settings.tour_description') || 'Need a refresher on navigating the platform? You can restart the interactive dashboard product tour at any time.'}
                    </p>
                    <button
                        type="button"
                        onClick={handleRestartTour}
                        className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/10 transition shadow-sm"
                    >
                        <Compass className="h-4 w-4" />
                        {t('settings.restart_tour') || 'Restart Product Tour'}
                    </button>
                </div>

                <div className="mt-6 pt-6 border-t border-neutral-200 dark:border-neutral-700 space-y-3">
                    <Link
                        href={route('client.settings.workspace')}
                        className="flex items-center gap-2 text-sm text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 font-semibold"
                    >
                        <SettingsIcon className="h-4 w-4" />
                        Workspace Profile & Operational Hours →
                    </Link>
                    <Link
                        href={route('client.settings.notifications')}
                        className="flex items-center gap-2 text-sm text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"
                    >
                        <Bell className="h-4 w-4" />
                        {t('settings.manage_notifications_link')} →
                    </Link>
                    <Link
                        href={route('client.settings.data-export')}
                        className="flex items-center gap-2 text-sm text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"
                    >
                        <Download className="h-4 w-4" />
                        {t('settings.export_data_link')} →
                    </Link>
                </div>
            </div>
        </ClientLayout>
    );
}
