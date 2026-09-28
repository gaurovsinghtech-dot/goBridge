import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useBranding } from '@/hooks/useBranding';
import {
    ShieldCheck, Bot, Layers, Rocket, CreditCard, Headphones, CheckCircle2
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
        <div className="min-h-screen bg-[#FAFAF9] text-[#171717] flex flex-col justify-between relative overflow-x-hidden font-sans selection:bg-[#2563EB] selection:text-white">
            {/* ── Top Header / Brand Logo ── */}
            <header className="pt-8 pb-4 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full flex justify-center items-center">
                <Link href={route('home')} className="flex items-center gap-3 group transition-transform duration-200 hover:scale-[1.02]" aria-label="Growbridge Connect">
                    {logoUrl ? (
                        <img
                            src={logoUrl}
                            alt={appName || 'Growbridge Connect'}
                            className="h-10 w-auto max-w-[260px] object-contain drop-shadow-sm"
                        />
                    ) : (
                        <div className="flex items-center gap-2.5">
                            <div className="h-9 w-9 rounded-lg bg-[#2563EB] flex items-center justify-center shadow-sm">
                                <span className="text-xl font-bold text-white leading-none tracking-tight">G</span>
                            </div>
                            <span className="text-lg font-bold text-[#171717] tracking-tight">
                                Growbridge <span className="text-[#2563EB]">Connect</span>
                            </span>
                        </div>
                    )}
                    {variant === 'admin' && (
                        <span className="ms-2 inline-flex items-center gap-1 rounded-full bg-blue-50 border border-blue-200 px-2.5 py-0.5 text-[11px] font-bold text-[#2563EB] uppercase tracking-wider shadow-sm">
                            Admin Panel
                        </span>
                    )}
                </Link>
            </header>

            {/* ── Main Content Area ── */}
            <main className="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8 w-full flex items-center justify-center">
                <div className="w-full grid grid-cols-1 lg:grid-cols-12 gap-8 items-center max-w-6xl">
                    {/* Left Feature Column (Desktop Login) */}
                    {isLogin && (
                        <div className="hidden lg:flex lg:col-span-3 flex-col gap-6 justify-center">
                            {[
                                {
                                    icon: ShieldCheck,
                                    title: 'Secure Platform',
                                    subtitle: 'Enterprise-grade encryption',
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
                                    className="flex items-center gap-4 p-4 rounded-xl bg-white border border-[#E5E5E5] shadow-sm hover:border-blue-200 transition-all duration-200"
                                >
                                    <div className="h-10 w-10 rounded-lg bg-blue-50 text-[#2563EB] flex items-center justify-center shrink-0">
                                        <item.icon className="h-5 w-5" />
                                    </div>
                                    <div className="text-left">
                                        <h4 className="text-sm font-semibold text-[#171717] leading-tight mb-0.5">{item.title}</h4>
                                        <p className="text-xs text-[#737373] leading-snug">{item.subtitle}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Spacer */}
                    {isRegister && <div className="hidden lg:block lg:col-span-1" />}

                    {/* Form Card Container */}
                    <div className={isLogin ? 'lg:col-span-6 w-full max-w-md mx-auto' : isRegister ? 'lg:col-span-7 w-full max-w-xl mx-auto' : 'lg:col-span-6 lg:col-start-4 w-full max-w-md mx-auto'}>
                        <div className="text-center mb-6">
                            <h2 className="text-2xl sm:text-3xl font-bold text-[#171717] tracking-tight">
                                {title}
                            </h2>
                            {subtitle && (
                                <p className="mt-2 text-xs sm:text-sm text-[#737373] max-w-md mx-auto leading-relaxed">
                                    {subtitle}
                                </p>
                            )}
                        </div>

                        {/* Status Messages */}
                        {status && (
                            <div className="mb-5 rounded-xl bg-blue-50 border border-blue-200 p-4 text-xs font-semibold text-[#2563EB] flex items-center gap-2.5 shadow-sm">
                                <CheckCircle2 className="h-4 w-4 text-[#2563EB] shrink-0" />
                                <span>{status}</span>
                            </div>
                        )}

                        {error && (
                            <div className="mb-5 rounded-xl bg-red-50 border border-red-200 p-4 text-xs font-semibold text-red-600 shadow-sm">
                                {error}
                            </div>
                        )}

                        {/* Form Card */}
                        <div className="rounded-2xl border border-[#E5E5E5] bg-white p-6 sm:p-8 shadow-sm">
                            {children}
                        </div>
                    </div>

                    {/* Right Feature Column (Desktop Signup) */}
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
                                    className="flex items-center gap-4 p-4 rounded-xl bg-white border border-[#E5E5E5] shadow-sm hover:border-blue-200 transition-all duration-200"
                                >
                                    <div className="h-10 w-10 rounded-lg bg-blue-50 text-[#2563EB] flex items-center justify-center shrink-0">
                                        <item.icon className="h-5 w-5" />
                                    </div>
                                    <div className="text-left">
                                        <h4 className="text-sm font-semibold text-[#171717] leading-tight mb-0.5">{item.title}</h4>
                                        <p className="text-xs text-[#737373] leading-snug">{item.subtitle}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {isLogin && <div className="hidden lg:block lg:col-span-3" />}
                </div>
            </main>

            {/* ── Bottom Trust Footer ── */}
            <footer className="py-6 px-4 sm:px-6 lg:px-8 border-t border-[#E5E5E5] bg-white">
                <div className="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-[#737373]">
                    <div className="flex items-center gap-2 text-[#737373]">
                        <div className="h-6 w-6 rounded-full bg-blue-50 border border-blue-200 flex items-center justify-center text-[#2563EB]">
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
