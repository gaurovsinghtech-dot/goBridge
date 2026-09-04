import React from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    Smartphone,
    Download,
    QrCode,
    CheckCircle2,
    ShieldCheck,
    MessageSquare,
    PhoneCall,
    Bot,
    Sparkles,
    ArrowRight,
    HelpCircle,
} from 'lucide-react';

export default function MobileAppDownload({ release }) {
    const { t } = useTranslation();

    return (
        <ClientLayout title="Growbridge Connect Mobile App">
            <Head title="Growbridge Connect Mobile App — Download Android APK" />

            <div className="max-w-6xl mx-auto space-y-8 pb-12">
                {/* Hero Header */}
                <div className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#062c22] via-[#091b16] to-[#04100c] border border-emerald-500/30 p-8 sm:p-12 text-white shadow-2xl">
                    <div className="absolute -top-24 -right-24 w-96 h-96 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none" />
                    <div className="absolute -bottom-24 -left-24 w-96 h-96 bg-teal-500/15 rounded-full blur-3xl pointer-events-none" />

                    <div className="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                        <div className="lg:col-span-7 space-y-6">
                            <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 text-xs font-semibold uppercase tracking-wider">
                                <Sparkles className="h-3.5 w-3.5" />
                                Official Android Release
                            </div>

                            <h1 className="text-3xl sm:text-5xl font-extrabold tracking-tight leading-tight">
                                WhatsApp Chat + Business Calling
                                <span className="block text-emerald-400">In One Mobile App.</span>
                            </h1>

                            <p className="text-base sm:text-lg text-emerald-100/80 max-w-xl leading-relaxed">
                                Never miss a lead again. Manage WhatsApp customer conversations, make VoIP business calls, and leverage real-time AI reply assistance directly from your phone.
                            </p>

                            <div className="flex flex-wrap items-center gap-4 pt-2">
                                <a
                                    href={release.download_url}
                                    className="inline-flex items-center gap-3 px-8 py-4 rounded-2xl text-base font-bold text-neutral-900 bg-emerald-400 hover:bg-emerald-300 shadow-xl shadow-emerald-500/30 transition transform hover:-translate-y-0.5 active:translate-y-0"
                                >
                                    <Download className="h-5 w-5" />
                                    Download APK (v{release.version})
                                </a>

                                <div className="text-xs text-neutral-400 flex flex-col">
                                    <span>Version {release.version} ({release.file_size_mb} MB)</span>
                                    <span>Released on {release.published_at}</span>
                                </div>
                            </div>
                        </div>

                        {/* QR Code Card */}
                        <div className="lg:col-span-5 flex justify-center lg:justify-end">
                            <div className="w-full max-w-xs rounded-3xl bg-white/10 dark:bg-black/40 backdrop-blur-xl border border-white/20 p-6 text-center shadow-2xl space-y-4">
                                <div className="flex items-center justify-center gap-2 text-sm font-semibold text-emerald-300">
                                    <QrCode className="h-4 w-4" />
                                    Scan to Install on Mobile
                                </div>

                                <div className="w-48 h-48 mx-auto bg-white p-3 rounded-2xl shadow-inner flex items-center justify-center">
                                    <img
                                        src={release.qr_code_url}
                                        alt="Scan QR to download APK"
                                        className="w-full h-full object-contain"
                                    />
                                </div>

                                <p className="text-xs text-neutral-300">
                                    Open your phone camera to scan and start the instant download.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Mobile App Core Features Grid */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900/60 p-6 space-y-3 shadow-sm">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                            <MessageSquare className="h-6 w-6" />
                        </div>
                        <h3 className="text-lg font-bold text-neutral-900 dark:text-white">
                            Unified WhatsApp Inbox
                        </h3>
                        <p className="text-sm text-neutral-600 dark:text-neutral-400 leading-relaxed">
                            Organize conversations with All, Unread, Assigned, and AI tabs. Reply instantly with approved templates and media attachments.
                        </p>
                    </div>

                    <div className="rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900/60 p-6 space-y-3 shadow-sm">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-teal-500/10 text-teal-600 dark:text-teal-400 border border-teal-500/20">
                            <PhoneCall className="h-6 w-6" />
                        </div>
                        <h3 className="text-lg font-bold text-neutral-900 dark:text-white">
                            In-App VoIP Business Calling
                        </h3>
                        <p className="text-sm text-neutral-600 dark:text-neutral-400 leading-relaxed">
                            Dial customers directly from their chat screen. Supports Mute, Speaker, Keypad, Call Hold, and seamless call recording.
                        </p>
                    </div>

                    <div className="rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900/60 p-6 space-y-3 shadow-sm">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
                            <Bot className="h-6 w-6" />
                        </div>
                        <h3 className="text-lg font-bold text-neutral-900 dark:text-white">
                            Real-Time AI Copilot & 360° CRM
                        </h3>
                        <p className="text-sm text-neutral-600 dark:text-neutral-400 leading-relaxed">
                            Generate intelligent replies with one tap, summarize voice calls, and view complete customer history across chat and voice.
                        </p>
                    </div>
                </div>

                {/* Step-by-Step Installation Guide */}
                <div className="rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900/40 p-6 sm:p-8 space-y-6 shadow-sm">
                    <h2 className="text-xl font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                        <ShieldCheck className="h-6 w-6 text-emerald-500" />
                        Easy 3-Step Installation Guide
                    </h2>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div className="p-5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/80 dark:border-neutral-800 space-y-2">
                            <div className="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">
                                Step 1
                            </div>
                            <h4 className="text-base font-semibold text-neutral-900 dark:text-white">
                                Download APK
                            </h4>
                            <p className="text-xs text-neutral-600 dark:text-neutral-400">
                                Click the "Download APK" button or scan the QR code with your phone camera to download the installer file.
                            </p>
                        </div>

                        <div className="p-5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/80 dark:border-neutral-800 space-y-2">
                            <div className="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">
                                Step 2
                            </div>
                            <h4 className="text-base font-semibold text-neutral-900 dark:text-white">
                                Allow Unknown Apps
                            </h4>
                            <p className="text-xs text-neutral-600 dark:text-neutral-400">
                                When prompted by Android, tap "Settings" and toggle "Allow from this source" to permit the installation.
                            </p>
                        </div>

                        <div className="p-5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/80 dark:border-neutral-800 space-y-2">
                            <div className="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">
                                Step 3
                            </div>
                            <h4 className="text-base font-semibold text-neutral-900 dark:text-white">
                                Sign In & Start
                            </h4>
                            <p className="text-xs text-neutral-600 dark:text-neutral-400">
                                Open Growbridge Connect and log in with your existing account email and password to access all conversations and calling.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </ClientLayout>
    );
}
