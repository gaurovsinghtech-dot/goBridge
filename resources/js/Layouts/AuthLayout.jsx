import { useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useBranding } from '@/hooks/useBranding';
import {
    ShieldCheck, Bot, Layers, Rocket, CreditCard, Headphones, CheckCircle2
} from 'lucide-react';

function FloatingOrbs({ count = 4, colors = ['#E8D44D', '#C4A83A', '#F5E6A3', '#D4B82E'] }) {
    const orbs = useMemo(() => Array.from({ length: count }, (_, i) => ({
        size: 160 + Math.random() * 300,
        x: Math.random() * 100,
        y: Math.random() * 100,
        color: colors[i % colors.length],
        opacity: 0.10 + Math.random() * 0.12,
        duration: 14 + Math.random() * 10,
        delay: Math.random() * -15,
        reverse: i % 2 === 0,
    })), []);
    return (
        <div className="absolute inset-0 overflow-hidden pointer-events-none z-0">
            {orbs.map((orb, i) => (
                <div
                    key={i}
                    className={orb.reverse ? 'animate-float-slow' : 'animate-float-reverse'}
                    style={{
                        position: 'absolute',
                        left: `${orb.x}%`,
                        top: `${orb.y}%`,
                        width: orb.size,
                        height: orb.size,
                        borderRadius: '50%',
                        background: `radial-gradient(circle, ${orb.color} 0%, transparent 70%)`,
                        opacity: orb.opacity,
                        animationDuration: `${orb.duration}s`,
                        animationDelay: `${orb.delay}s`,
                        filter: 'blur(30px)',
                    }}
                />
            ))}
        </div>
    );
}

export default function AuthLayout({
    variant = 'login',
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

    const featureIcons = {
        login: [
            { icon: ShieldCheck, title: 'Secure Platform', subtitle: 'Enterprise-grade encryption', color: '#E8D44D' },
            { icon: Bot, title: 'AI-Powered', subtitle: 'Smarter automation for your business', color: '#C4A83A' },
            { icon: Layers, title: 'All-in-One', subtitle: 'Manage everything from one place', color: '#D4B82E' },
        ],
        register: [
            { icon: Rocket, title: '14-Day Free Trial', subtitle: 'Explore all features risk-free', color: '#E8D44D' },
            { icon: CreditCard, title: 'No Credit Card', subtitle: 'Start your trial without payment', color: '#C4A83A' },
            { icon: Headphones, title: '24/7 Support', subtitle: "We're here to help you succeed", color: '#D4B82E' },
        ],
    };

    const features = isLogin ? featureIcons.login : isRegister ? featureIcons.register : [];

    return (
        <div className="min-h-screen text-[#1A1A1A] flex flex-col justify-between relative overflow-x-hidden font-sans selection:bg-[#E8D44D] selection:text-[#1A1A1A]" style={{ background: 'linear-gradient(180deg, #FFF8E1 0%, #FFFDF5 30%, #FFFDF5 70%, #FFF8E1 100%)' }}>
            <FloatingOrbs count={5} />

            <header className="pt-8 pb-4 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full flex justify-center items-center relative z-10">
                <Link href={route('home')} className="flex items-center gap-3 group transition-transform duration-200 hover:scale-[1.02]" aria-label="Growbridge Connect">
                    <img src={logoUrl || '/images/brand/logo-full.png'} alt={appName || 'Growbridge Connect'} className="h-10 w-auto max-w-[260px] object-contain drop-shadow-sm dark:[filter:invert(1)_hue-rotate(180deg)_brightness(1.15)_saturate(1.5)]" />
                    {variant === 'admin' && (
                        <span className="ms-2 inline-flex items-center gap-1 rounded-full bg-[#FFF8E1] border border-[#E8D44D]/30 px-2.5 py-0.5 text-[11px] font-bold text-[#C4A83A] uppercase tracking-wider shadow-sm">
                            Admin Panel
                        </span>
                    )}
                </Link>
            </header>

            <main className="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8 w-full flex items-center justify-center relative z-10">
                <div className="w-full grid grid-cols-1 lg:grid-cols-12 gap-8 items-center max-w-6xl">
                    {isLogin && (
                        <div className="hidden lg:flex lg:col-span-3 flex-col gap-6 justify-center">
                            {features.map((item, idx) => (
                                <div
                                    key={idx}
                                    className="flex items-center gap-4 p-4 rounded-2xl bg-white/70 backdrop-blur-sm border border-[#E8D44D]/15 shadow-sm hover:shadow-md hover:border-[#E8D44D]/30 transition-all duration-300"
                                >
                                    <div className="h-10 w-10 rounded-xl flex items-center justify-center shrink-0" style={{ background: `linear-gradient(135deg, #FFF8E1, ${item.color}30)` }}>
                                        <item.icon className="h-5 w-5 text-[#C4A83A]" />
                                    </div>
                                    <div className="text-left">
                                        <h4 className="text-sm font-semibold text-[#1A1A1A] leading-tight mb-0.5">{item.title}</h4>
                                        <p className="text-xs text-[#6A6A6A] leading-snug">{item.subtitle}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {isRegister && <div className="hidden lg:block lg:col-span-1" />}

                    <div className={isLogin ? 'lg:col-span-6 w-full max-w-md mx-auto' : isRegister ? 'lg:col-span-7 w-full max-w-xl mx-auto' : 'lg:col-span-6 lg:col-start-4 w-full max-w-md mx-auto'}>
                        <div className="text-center mb-6">
                            <h2 className="text-2xl sm:text-3xl font-bold text-[#1A1A1A] tracking-tight">
                                {title}
                            </h2>
                            {subtitle && (
                                <p className="mt-2 text-xs sm:text-sm text-[#6A6A6A] max-w-md mx-auto leading-relaxed">
                                    {subtitle}
                                </p>
                            )}
                        </div>

                        {status && (
                            <div className="mb-5 rounded-xl bg-[#FFF8E1] border border-[#E8D44D]/30 p-4 text-xs font-semibold text-[#C4A83A] flex items-center gap-2.5 shadow-sm">
                                <CheckCircle2 className="h-4 w-4 text-[#C4A83A] shrink-0" />
                                <span>{status}</span>
                            </div>
                        )}

                        {error && (
                            <div className="mb-5 rounded-xl bg-red-50 border border-red-200 p-4 text-xs font-semibold text-red-600 shadow-sm">
                                {error}
                            </div>
                        )}

                        <div className="rounded-2xl border border-[#E8D44D]/15 bg-white/80 backdrop-blur-sm p-6 sm:p-8 shadow-sm">
                            {children}
                        </div>
                    </div>

                    {isRegister && (
                        <div className="hidden lg:flex lg:col-span-4 flex-col gap-6 justify-center ps-4">
                            {features.map((item, idx) => (
                                <div
                                    key={idx}
                                    className="flex items-center gap-4 p-4 rounded-2xl bg-white/70 backdrop-blur-sm border border-[#E8D44D]/15 shadow-sm hover:shadow-md hover:border-[#E8D44D]/30 transition-all duration-300"
                                >
                                    <div className="h-10 w-10 rounded-xl flex items-center justify-center shrink-0" style={{ background: `linear-gradient(135deg, #FFF8E1, ${item.color}30)` }}>
                                        <item.icon className="h-5 w-5 text-[#C4A83A]" />
                                    </div>
                                    <div className="text-left">
                                        <h4 className="text-sm font-semibold text-[#1A1A1A] leading-tight mb-0.5">{item.title}</h4>
                                        <p className="text-xs text-[#6A6A6A] leading-snug">{item.subtitle}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {isLogin && <div className="hidden lg:block lg:col-span-3" />}
                </div>
            </main>

            <footer className="py-6 px-4 sm:px-6 lg:px-8 border-t border-[#E8D44D]/10 bg-[#FFFDF5] relative z-10">
                <div className="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-[#6A6A6A]">
                    <div className="flex items-center gap-2">
                        <div className="h-6 w-6 rounded-full bg-[#FFF8E1] border border-[#E8D44D]/20 flex items-center justify-center text-[#C4A83A]">
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
