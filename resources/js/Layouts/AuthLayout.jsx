import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useBranding } from '@/hooks/useBranding';
import {
    ShieldCheck, Bot, Layers, Rocket, CreditCard, Headphones, Lock, CheckCircle2
} from 'lucide-react';

export default function AuthLayout({
    variant = 'login', // 'login' | 'register' | 'admin' | 'simple'
    title,
    subtitle,
    status,
    error,
    children,
}) {
    const { t } = useTranslation();
    const { appName, logoUrl } = useBranding();

    const isLogin = variant === 'login' || variant === 'admin';
    const isRegister = variant === 'register';

    return (
        <div className="min-h-screen bg-[#020f0b] text-neutral-100 flex flex-col justify-between relative overflow-x-hidden font-sans selection:bg-emerald-500 selection:text-black">
            {/* Ambient Background Aura & Curved Grid */}
            <div className="fixed inset-0 pointer-events-none -z-10 overflow-hidden">
                <div className="absolute -top-32 left-1/2 -translate-x-1/2 w-[700px] h-[500px] bg-emerald-500/10 rounded-full blur-[140px]" />
                <div className="absolute top-1/2 -left-48 w-[500px] h-[500px] bg-emerald-600/10 rounded-full blur-[160px]" />
                <div className="absolute -bottom-32 -right-48 w-[600px] h-[600px] bg-teal-600/10 rounded-full blur-[160px]" />

                {/* Subtle SVG Wave Line Art */}
                <svg className="absolute inset-0 w-full h-full opacity-20" preserveAspectRatio="none" viewBox="0 0 1440 900">
                    <path
                        d="M-100,200 C300,50 600,350 1000,100 C1300,-100 1500,300 1600,250"
                        fill="none"
                        stroke="#10b981"
                        strokeWidth="1.5"
                        strokeDasharray="4 8"
                    />
                    <path
                        d="M-50,700 C400,550 800,850 1200,600 C1400,450 1550,750 1650,700"
                        fill="none"
                        stroke="#059669"
                        strokeWidth="1"
                    />
                </svg>
            </div>

            {/* ── Top Header / Brand Logo ── */}
            <header className="pt-8 pb-4 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full flex justify-center items-center">
                <Link href={route('home')} className="flex items-center gap-3 group transition-transform duration-200 hover:scale-[1.02]" aria-label="Growbridge Connect">
                    <img
                        src={logoUrl || '/images/brand/logo-full.png'}
                        alt={appName || 'Growbridge Connect'}
                        className="h-11 w-auto max-w-[260px] object-contain drop-shadow-md"
                    />
                    {variant === 'admin' && (
                        <span className="ms-2 inline-flex items-center gap-1 rounded-full bg-emerald-500/20 border border-emerald-500/40 px-2.5 py-0.5 text-[11px] font-bold text-emerald-400 uppercase tracking-wider shadow-sm">
                            Admin Panel
                        </span>
                    )}
                </Link>
            </header>

            {/* ── Main Content Area with Side Highlight Pills ── */}
            <main className="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8 w-full flex items-center justify-center">
                <div className="w-full grid grid-cols-1 lg:grid-cols-12 gap-8 items-center max-w-6xl">
                    {/* Left Feature Column (Shown on Login / Admin on desktop) */}
                    {isLogin && (
                        <div className="hidden lg:flex lg:col-span-3 flex-col gap-6 justify-center">
                            {[
                                {
                                    icon: ShieldCheck,
                                    title: 'Secure',
                                    subtitle: 'Enterprise-grade security',
                                },
                                {
                                    icon: Bot,
                                    title: 'AI-Powered',
                                    subtitle: 'Smarter automation for your business',
                                },
                                {
                                    icon: Layers,
                                    title: 'All-in-One',
                                    subtitle: 'Manage everything from one place',
                                },
                            ].map((item, idx) => (
                                <div
                                    key={idx}
                                    className="flex items-center gap-4 p-4 rounded-2xl bg-[#041d15]/60 border border-emerald-900/40 backdrop-blur-md hover:border-emerald-500/40 transition-all duration-300 group"
                                >
                                    <div className="h-12 w-12 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center shrink-0 group-hover:scale-110 group-hover:bg-emerald-500/20 transition-all">
                                        <item.icon className="h-6 w-6" />
                                    </div>
                                    <div className="text-left">
                                        <h4 className="text-sm font-bold text-white leading-tight mb-0.5">{item.title}</h4>
                                        <p className="text-xs text-neutral-400 leading-snug">{item.subtitle}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Empty placeholder spacer if register page on desktop left side */}
                    {isRegister && <div className="hidden lg:block lg:col-span-1" />}

                    {/* Center Column: The Authentication Form Card */}
                    <div className={isLogin ? 'lg:col-span-6 w-full max-w-md mx-auto' : isRegister ? 'lg:col-span-7 w-full max-w-xl mx-auto' : 'lg:col-span-6 lg:col-start-4 w-full max-w-md mx-auto'}>
                        {/* Title & Subtitle above the card */}
                        <div className="text-center mb-6">
                            <h2 className="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                                {title}
                            </h2>
                            {subtitle && (
                                <p className="mt-2 text-xs sm:text-sm text-neutral-400 max-w-md mx-auto leading-relaxed">
                                    {subtitle}
                                </p>
                            )}
                        </div>

                        {/* Status Messages */}
                        {status && (
                            <div className="mb-5 rounded-2xl bg-emerald-950/60 border border-emerald-500/40 p-4 text-xs font-medium text-emerald-300 flex items-center gap-2.5 shadow-lg">
                                <CheckCircle2 className="h-4 w-4 text-emerald-400 shrink-0" />
                                <span>{status}</span>
                            </div>
                        )}

                        {error && (
                            <div className="mb-5 rounded-2xl bg-rose-950/60 border border-rose-500/40 p-4 text-xs font-medium text-rose-300 shadow-lg">
                                {error}
                            </div>
                        )}

                        {/* Form Card Container */}
                        <div className="rounded-3xl border border-emerald-900/50 bg-[#051f17]/85 backdrop-blur-2xl p-6 sm:p-8 shadow-2xl shadow-black/80">
                            {children}
                        </div>
                    </div>

                    {/* Right Feature Column (Shown on Signup on desktop) */}
                    {isRegister && (
                        <div className="hidden lg:flex lg:col-span-4 flex-col gap-6 justify-center ps-4">
                            {[
                                {
                                    icon: Rocket,
                                    title: '14-Day Free Trial',
                                    subtitle: 'Explore all features risk-free',
                                },
                                {
                                    icon: CreditCard,
                                    title: 'No Credit Card',
                                    subtitle: 'Start your trial without payment',
                                },
                                {
                                    icon: Headphones,
                                    title: '24/7 Support',
                                    subtitle: "We're here to help you succeed",
                                },
                            ].map((item, idx) => (
                                <div
                                    key={idx}
                                    className="flex items-center gap-4 p-4 rounded-2xl bg-[#041d15]/60 border border-emerald-900/40 backdrop-blur-md hover:border-emerald-500/40 transition-all duration-300 group"
                                >
                                    <div className="h-12 w-12 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center shrink-0 group-hover:scale-110 group-hover:bg-emerald-500/20 transition-all">
                                        <item.icon className="h-6 w-6" />
                                    </div>
                                    <div className="text-left">
                                        <h4 className="text-sm font-bold text-white leading-tight mb-0.5">{item.title}</h4>
                                        <p className="text-xs text-neutral-400 leading-snug">{item.subtitle}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Empty placeholder spacer if login page on desktop right side */}
                    {isLogin && <div className="hidden lg:block lg:col-span-3" />}
                </div>
            </main>

            {/* ── Bottom Trust Footer ── */}
            <footer className="py-6 px-4 sm:px-6 lg:px-8 border-t border-emerald-900/30 bg-[#020e0a]">
                <div className="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-neutral-400">
                    <div className="flex items-center gap-2 text-neutral-300">
                        <div className="h-6 w-6 rounded-full bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                            <ShieldCheck className="h-3.5 w-3.5" />
                        </div>
                        <span>Your data is protected with enterprise-grade security</span>
                    </div>
                    <div>
                        &copy; {new Date().getFullYear()} {appName || 'Growbridge Connect'}. All rights reserved.
                    </div>
                </div>
            </footer>
        </div>
    );
}
