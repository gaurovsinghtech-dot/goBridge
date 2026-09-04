import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Dropdown } from '@/Components/ui';
import { useTheme } from '@/context/ThemeContext';
import { useLocale } from '@/hooks/useLocale';
import { useBranding } from '@/hooks/useBranding';
import { Globe, ChevronDown, Sparkles, MessageSquare, Bot, PhoneCall, Mail, BarChart3, Plug, ArrowRight } from 'lucide-react';

function SunIcon({ className }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
        </svg>
    );
}

function MoonIcon({ className }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
        </svg>
    );
}

function MenuIcon({ className }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth={1.5} viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
    );
}

function XIcon({ className }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth={1.5} viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    );
}

export default function LandingLayout({ children }) {
    const { t } = useTranslation();
    const page = usePage();
    const auth = page.props.auth;
    const { theme, setTheme } = useTheme();
    const { locale: currentLocale, setLocale } = useLocale();
    const supportedLocales = page.props.supportedLocales ?? { en: 'English' };
    const localeEntries = Object.entries(supportedLocales);
    const { appName, logoUrl } = useBranding();

    const [mobileOpen, setMobileOpen] = useState(false);
    const [activeDropdown, setActiveDropdown] = useState(null);
    const [isSigningOut, setIsSigningOut] = useState(false);
    const landing = page.props.landing ?? {};

    const signinLabel = landing['landing.signin_label'] || 'Log in';
    const signinHref = landing['landing.signin_link_type'] === 'static' && landing['landing.signin_link_url']
        ? landing['landing.signin_link_url']
        : route('login');

    const getStartedLabel = landing['landing.getstarted_label'] || 'Start Free Trial';
    const getStartedHref = landing['landing.getstarted_link_type'] === 'static' && landing['landing.getstarted_link_url']
        ? landing['landing.getstarted_link_url']
        : route('register');

    const handleThemeToggle = () => {
        const next = theme === 'dark' ? 'light' : 'dark';
        setTheme(next);
        if (auth?.user) {
            router.post(route('theme.update'), { theme: next }, { preserveScroll: true });
        }
    };

    const handleSignOut = (e) => {
        e?.preventDefault();
        if (isSigningOut) return;
        setIsSigningOut(true);

        router.post(route('logout'), {}, {
            onFinish: () => setIsSigningOut(false),
            onError: () => {
                window.location.href = route('login');
            },
            onSuccess: () => {
                window.location.replace(route('home'));
            },
        });
    };

    return (
        <div className="min-h-screen bg-[#020f0b] text-neutral-100 flex flex-col w-full max-w-full overflow-x-hidden font-sans selection:bg-emerald-500 selection:text-black">
            {/* ── Header / Navbar ── */}
            <header className="sticky top-0 z-50 w-full border-b border-emerald-900/30 bg-[#03130e]/90 backdrop-blur-xl transition-colors">
                <nav className="w-full">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between gap-4 box-border">
                        {/* Brand Logo */}
                        <Link href={route('home')} className="flex items-center gap-3 group shrink-0" aria-label="Growbridge Connect">
                            {logoUrl ? (
                                <img src={logoUrl} alt={appName} className="h-9 w-auto max-w-[180px] object-contain" />
                            ) : (
                                <div className="flex items-center gap-2.5">
                                    <div className="h-9 w-9 rounded-full bg-white flex items-center justify-center shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform duration-200">
                                        <span className="text-xl font-black text-black leading-none tracking-tight">G</span>
                                    </div>
                                    <span className="text-lg font-bold text-white tracking-tight">
                                        Growbridge <span className="text-emerald-400 font-semibold">Connect</span>
                                    </span>
                                </div>
                            )}
                        </Link>

                        {/* Desktop Navigation Links */}
                        <div className="hidden lg:flex items-center gap-1 xl:gap-2">
                            {/* Features dropdown */}
                            <div className="relative group" onMouseEnter={() => setActiveDropdown('features')} onMouseLeave={() => setActiveDropdown(null)}>
                                <button className="flex items-center gap-1 px-3 py-2 text-sm text-neutral-300 hover:text-white font-medium transition-colors">
                                    <span>Features</span>
                                    <ChevronDown className="h-3.5 w-3.5 text-neutral-400 group-hover:text-emerald-400 transition-transform group-hover:rotate-180 duration-200" />
                                </button>
                                {activeDropdown === 'features' && (
                                    <div className="absolute top-full left-0 w-72 bg-[#062219] border border-emerald-800/40 rounded-2xl p-2 shadow-2xl shadow-black/80 backdrop-blur-2xl animate-in fade-in slide-in-from-top-2 duration-150">
                                        <Link href="/#features" className="flex items-center gap-3 p-2.5 rounded-xl hover:bg-emerald-900/30 text-neutral-200 hover:text-white transition">
                                            <div className="p-2 rounded-lg bg-emerald-500/10 text-emerald-400"><MessageSquare className="h-4 w-4" /></div>
                                            <div>
                                                <div className="text-sm font-semibold">Unified Inbox</div>
                                                <div className="text-xs text-neutral-400">All channels in one place</div>
                                            </div>
                                        </Link>
                                        <Link href="/#features" className="flex items-center gap-3 p-2.5 rounded-xl hover:bg-emerald-900/30 text-neutral-200 hover:text-white transition">
                                            <div className="p-2 rounded-lg bg-purple-500/10 text-purple-400"><Bot className="h-4 w-4" /></div>
                                            <div>
                                                <div className="text-sm font-semibold">AI Automation</div>
                                                <div className="text-xs text-neutral-400">24/7 intelligent workflows</div>
                                            </div>
                                        </Link>
                                        <Link href="/#features" className="flex items-center gap-3 p-2.5 rounded-xl hover:bg-emerald-900/30 text-neutral-200 hover:text-white transition">
                                            <div className="p-2 rounded-lg bg-amber-500/10 text-amber-400"><PhoneCall className="h-4 w-4" /></div>
                                            <div>
                                                <div className="text-sm font-semibold">AI Voice Agents</div>
                                                <div className="text-xs text-neutral-400">Twilio voice calling</div>
                                            </div>
                                        </Link>
                                    </div>
                                )}
                            </div>

                            {/* Channels dropdown */}
                            <div className="relative group" onMouseEnter={() => setActiveDropdown('channels')} onMouseLeave={() => setActiveDropdown(null)}>
                                <button className="flex items-center gap-1 px-3 py-2 text-sm text-neutral-300 hover:text-white font-medium transition-colors">
                                    <span>Channels</span>
                                    <ChevronDown className="h-3.5 w-3.5 text-neutral-400 group-hover:text-emerald-400 transition-transform group-hover:rotate-180 duration-200" />
                                </button>
                                {activeDropdown === 'channels' && (
                                    <div className="absolute top-full left-0 w-64 bg-[#062219] border border-emerald-800/40 rounded-2xl p-2 shadow-2xl shadow-black/80 backdrop-blur-2xl">
                                        <Link href="/#channels" className="flex items-center gap-2.5 p-2 rounded-xl hover:bg-emerald-900/30 text-neutral-200 hover:text-white text-sm font-medium transition">
                                            <span className="h-2 w-2 rounded-full bg-emerald-400" />
                                            WhatsApp Cloud API
                                        </Link>
                                        <Link href="/#channels" className="flex items-center gap-2.5 p-2 rounded-xl hover:bg-emerald-900/30 text-neutral-200 hover:text-white text-sm font-medium transition">
                                            <span className="h-2 w-2 rounded-full bg-pink-400" />
                                            Instagram Graph API
                                        </Link>
                                        <Link href="/#channels" className="flex items-center gap-2.5 p-2 rounded-xl hover:bg-emerald-900/30 text-neutral-200 hover:text-white text-sm font-medium transition">
                                            <span className="h-2 w-2 rounded-full bg-blue-400" />
                                            Facebook Messenger
                                        </Link>
                                        <Link href="/#channels" className="flex items-center gap-2.5 p-2 rounded-xl hover:bg-emerald-900/30 text-neutral-200 hover:text-white text-sm font-medium transition">
                                            <span className="h-2 w-2 rounded-full bg-purple-400" />
                                            Email (SMTP / SendGrid)
                                        </Link>
                                    </div>
                                )}
                            </div>

                            {/* Solutions dropdown */}
                            <div className="relative group" onMouseEnter={() => setActiveDropdown('solutions')} onMouseLeave={() => setActiveDropdown(null)}>
                                <button className="flex items-center gap-1 px-3 py-2 text-sm text-neutral-300 hover:text-white font-medium transition-colors">
                                    <span>Solutions</span>
                                    <ChevronDown className="h-3.5 w-3.5 text-neutral-400 group-hover:text-emerald-400 transition-transform group-hover:rotate-180 duration-200" />
                                </button>
                                {activeDropdown === 'solutions' && (
                                    <div className="absolute top-full left-0 w-64 bg-[#062219] border border-emerald-800/40 rounded-2xl p-2 shadow-2xl shadow-black/80 backdrop-blur-2xl">
                                        <Link href="/use-cases" className="flex items-center gap-2.5 p-2 rounded-xl hover:bg-emerald-900/30 text-neutral-200 hover:text-white text-sm font-medium transition">
                                            Lead Generation & Sales
                                        </Link>
                                        <Link href="/use-cases" className="flex items-center gap-2.5 p-2 rounded-xl hover:bg-emerald-900/30 text-neutral-200 hover:text-white text-sm font-medium transition">
                                            Customer Support 24/7
                                        </Link>
                                        <Link href="/use-cases" className="flex items-center gap-2.5 p-2 rounded-xl hover:bg-emerald-900/30 text-neutral-200 hover:text-white text-sm font-medium transition">
                                            E-commerce Notifications
                                        </Link>
                                    </div>
                                )}
                            </div>

                            <Link href="/pricing" className="px-3 py-2 text-sm text-neutral-300 hover:text-white font-medium transition-colors">
                                Pricing
                            </Link>

                            {/* Resources dropdown */}
                            <div className="relative group" onMouseEnter={() => setActiveDropdown('resources')} onMouseLeave={() => setActiveDropdown(null)}>
                                <button className="flex items-center gap-1 px-3 py-2 text-sm text-neutral-300 hover:text-white font-medium transition-colors">
                                    <span>Resources</span>
                                    <ChevronDown className="h-3.5 w-3.5 text-neutral-400 group-hover:text-emerald-400 transition-transform group-hover:rotate-180 duration-200" />
                                </button>
                                {activeDropdown === 'resources' && (
                                    <div className="absolute top-full left-0 w-56 bg-[#062219] border border-emerald-800/40 rounded-2xl p-2 shadow-2xl shadow-black/80 backdrop-blur-2xl">
                                        <Link href="/integrations" className="flex items-center gap-2.5 p-2 rounded-xl hover:bg-emerald-900/30 text-neutral-200 hover:text-white text-sm font-medium transition">
                                            Integrations
                                        </Link>
                                        <Link href="/faq" className="flex items-center gap-2.5 p-2 rounded-xl hover:bg-emerald-900/30 text-neutral-200 hover:text-white text-sm font-medium transition">
                                            FAQ & Guides
                                        </Link>
                                    </div>
                                )}
                            </div>

                            <Link href="/about" className="px-3 py-2 text-sm text-neutral-300 hover:text-white font-medium transition-colors">
                                About
                            </Link>
                            <Link href="/contact" className="px-3 py-2 text-sm text-neutral-300 hover:text-white font-medium transition-colors">
                                Contact
                            </Link>
                        </div>

                        {/* Right-Side Action Controls */}
                        <div className="flex items-center gap-3 shrink-0">
                            {auth?.user ? (
                                <div className="flex items-center gap-2.5">
                                    <Link
                                        href={route('client.dashboard')}
                                        className="rounded-full bg-emerald-500 hover:bg-emerald-400 text-black font-bold text-sm px-5 py-2.5 shadow-lg shadow-emerald-500/20 transition-all flex items-center gap-1.5"
                                    >
                                        <span>Dashboard</span>
                                        <ArrowRight className="h-4 w-4" />
                                    </Link>
                                    <button
                                        type="button"
                                        onClick={handleSignOut}
                                        disabled={isSigningOut}
                                        className="rounded-full border border-neutral-700 hover:border-neutral-500 bg-neutral-900/60 px-4 py-2 text-sm text-neutral-300 hover:text-white transition font-medium"
                                    >
                                        {isSigningOut ? 'Signing out...' : 'Sign Out'}
                                    </button>
                                </div>
                            ) : (
                                <div className="flex items-center gap-2.5">
                                    <Link
                                        href={signinHref}
                                        className="rounded-full border border-neutral-700/80 hover:border-emerald-500/50 bg-[#062219]/60 hover:bg-[#082e22] px-5 py-2 text-sm font-medium text-white transition-all duration-200"
                                    >
                                        {signinLabel}
                                    </Link>
                                    <Link
                                        href={getStartedHref}
                                        className="rounded-full bg-emerald-500 hover:bg-emerald-400 text-black font-bold text-sm px-5 py-2 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 transition-all duration-200 flex items-center gap-1.5 group"
                                    >
                                        <span>{getStartedLabel}</span>
                                        <ArrowRight className="h-4 w-4 group-hover:translate-x-0.5 transition-transform" />
                                    </Link>
                                </div>
                            )}

                            {/* Mobile Hamburger */}
                            <button
                                type="button"
                                className="lg:hidden flex items-center justify-center rounded-xl p-2 text-neutral-300 hover:text-white hover:bg-white/10 transition"
                                onClick={() => setMobileOpen(!mobileOpen)}
                                aria-label="Toggle Menu"
                            >
                                {mobileOpen ? <XIcon className="h-6 w-6" /> : <MenuIcon className="h-6 w-6" />}
                            </button>
                        </div>
                    </div>

                    {/* Mobile & Tablet Drawer Menu */}
                    {mobileOpen && (
                        <div className="lg:hidden border-t border-white/10 bg-brand-950/98 backdrop-blur-xl px-4 py-4 space-y-1 shadow-2xl">
                            {NAV_LINKS.map((link) => (
                                <Link
                                    key={link.href}
                                    href={link.href}
                                    onClick={() => setMobileOpen(false)}
                                    className="block rounded-soft px-3.5 py-2.5 text-sm font-medium text-white/85 hover:text-white hover:bg-white/10 transition"
                                >
                                    {link.label}
                                </Link>
                            ))}

                            {/* Mobile Language Switcher */}
                            {localeEntries.length > 1 && (
                                <div className="pt-2 pb-1 border-t border-white/10">
                                    <div className="px-3.5 py-1 text-xs font-semibold text-white/50 uppercase tracking-wider">
                                        {t('topbar.language', { defaultValue: 'Language' })}
                                    </div>
                                    <div className="grid grid-cols-2 gap-1 px-2 mt-1">
                                        {localeEntries.map(([code, label]) => (
                                            <button
                                                key={code}
                                                type="button"
                                                onClick={() => {
                                                    setLocale(code);
                                                    setMobileOpen(false);
                                                }}
                                                className={`text-left rounded px-2.5 py-1.5 text-xs font-medium transition ${
                                                    currentLocale === code
                                                        ? 'bg-brand-600 text-white font-semibold'
                                                        : 'text-white/70 hover:bg-white/10 hover:text-white'
                                                }`}
                                            >
                                                {label}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Mobile Auth & Action Links */}
                            <div className="pt-3 border-t border-white/10 space-y-1.5">
                                {auth?.user ? (
                                    <>
                                        <Link
                                            href={route('client.dashboard')}
                                            onClick={() => setMobileOpen(false)}
                                            className="block rounded-soft px-3.5 py-2.5 text-sm font-medium text-white/90 hover:text-white hover:bg-white/10 transition"
                                        >
                                            {t('nav.dashboard', { defaultValue: 'Dashboard' })}
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={(e) => {
                                                setMobileOpen(false);
                                                handleSignOut(e);
                                            }}
                                            disabled={isSigningOut}
                                            className="block w-full text-left rounded-soft px-3.5 py-2.5 text-sm font-medium text-red-300 hover:bg-red-500/20 hover:text-red-200 transition disabled:opacity-50"
                                        >
                                            {isSigningOut ? t('nav.signing_out', { defaultValue: 'Signing out...' }) : t('nav.sign_out', { defaultValue: 'Sign Out' })}
                                        </button>
                                    </>
                                ) : (
                                    <>
                                        <Link
                                            href={route('client.dashboard')}
                                            onClick={() => setMobileOpen(false)}
                                            className="block rounded-soft px-3.5 py-2.5 text-sm font-medium text-white/90 hover:text-white hover:bg-white/10 transition"
                                        >
                                            {t('nav.dashboard', { defaultValue: 'Dashboard' })}
                                        </Link>
                                        {signinIsExternal ? (
                                            <a
                                                href={signinHref}
                                                onClick={() => setMobileOpen(false)}
                                                className="block rounded-soft px-3.5 py-2.5 text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition"
                                            >
                                                {signinLabel}
                                            </a>
                                        ) : (
                                            <Link
                                                href={signinHref}
                                                onClick={() => setMobileOpen(false)}
                                                className="block rounded-soft px-3.5 py-2.5 text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition"
                                            >
                                                {signinLabel}
                                            </Link>
                                        )}
                                        {getStartedIsExternal ? (
                                            <a
                                                href={getStartedHref}
                                                onClick={() => setMobileOpen(false)}
                                                className="block rounded-soft bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition text-center shadow-sm"
                                            >
                                                {getStartedLabel}
                                            </a>
                                        ) : (
                                            <Link
                                                href={getStartedHref}
                                                onClick={() => setMobileOpen(false)}
                                                className="block rounded-soft bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition text-center shadow-sm"
                                            >
                                                {getStartedLabel}
                                            </Link>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>
                    )}
                </nav>
            </header>

            {/* ── Main content ── */}
            <main className="flex-1 w-full max-w-full">{children}</main>

            {/* ── Footer ── */}
            <footer className="w-full" style={{ background: 'rgb(var(--brand-950))', borderTop: '1px solid rgba(255,255,255,0.08)' }}>
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 box-border">
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-8 mb-12">
                        {/* Brand */}
                        <div className="col-span-2 sm:col-span-1">
                            <Link href={route('home')} className="flex items-center mb-4">
                                {logoUrl ? (
                                    <img src={logoUrl} alt={appName} className="h-9 w-auto max-w-[180px] object-contain" />
                                ) : (
                                    <div className="flex items-center gap-2.5">
                                        <div className="h-8 w-8 rounded-full bg-white flex items-center justify-center shadow-md shadow-emerald-500/20">
                                            <span className="text-lg font-black text-black leading-none tracking-tight">G</span>
                                        </div>
                                        <span className="text-base font-bold text-white tracking-tight">
                                            Growbridge <span className="text-emerald-400 font-semibold">Connect</span>
                                        </span>
                                    </div>
                                )}
                            </Link>
                            <p className="text-sm text-neutral-400 leading-relaxed max-w-xs">
                                {t('landing.footer_tagline', { defaultValue: 'Connect. Engage. Automate. Grow.' })}
                            </p>
                            {/* Social icons */}
                            <div className="flex items-center gap-3 mt-5">
                                {[
                                    { label: 'Twitter', path: 'M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84' },
                                    { label: 'Facebook', path: 'M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z' },
                                    { label: 'Instagram', path: 'M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z M4 6a2 2 0 100-4 2 2 0 000 4z' },
                                ].map((s) => (
                                    <a key={s.label} href="#" aria-label={s.label} className="h-8 w-8 rounded-lg flex items-center justify-center text-neutral-400 hover:text-white transition-colors" style={{ background: 'rgba(255,255,255,0.06)' }}>
                                        <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth={1.5} viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" d={s.path} />
                                        </svg>
                                    </a>
                                ))}
                            </div>
                        </div>

                        {/* Company */}
                        <div>
                            <h4 className="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-4">{t('landing_page_admin.footer_company', { defaultValue: 'Company' })}</h4>
                            <ul className="space-y-2.5">
                                {[
                                    { label: t('landing_page_admin.footer_about', { defaultValue: 'About' }), href: '/about' },
                                    { label: t('nav.integrations', { defaultValue: 'Integrations' }), href: '/integrations' },
                                    { label: t('nav.use_cases', { defaultValue: 'Use Cases' }), href: '/use-cases' },
                                    { label: t('nav.contact', { defaultValue: 'Contact' }), href: '/contact' },
                                ].map((l) => (
                                    <li key={l.href}>
                                        <Link href={l.href} className="text-sm text-neutral-400 hover:text-white transition">{l.label}</Link>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        {/* Legal */}
                        <div>
                            <h4 className="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-4">{t('landing_page_admin.footer_legal', { defaultValue: 'Legal' })}</h4>
                            <ul className="space-y-2.5">
                                {[
                                    { label: t('landing_page_admin.footer_privacy', { defaultValue: 'Privacy Policy' }), href: '/p/privacy' },
                                    { label: t('landing_page_admin.footer_terms', { defaultValue: 'Terms of Service' }), href: '/p/terms' },
                                    { label: t('landing_page_admin.footer_cookies', { defaultValue: 'Cookie Policy' }), href: '/p/cookies' },
                                    { label: t('landing_page_admin.footer_gdpr', { defaultValue: 'GDPR' }), href: '/p/gdpr' },
                                ].map((l) => (
                                    <li key={l.href}>
                                        <Link href={l.href} className="text-sm text-neutral-400 hover:text-white transition">{l.label}</Link>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        {/* Product */}
                        <div>
                            <h4 className="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-4">{t('landing_page_admin.footer_product', { defaultValue: 'Product' })}</h4>
                            <ul className="space-y-2.5">
                                {[
                                    { label: t('nav.features', { defaultValue: 'Features' }), href: '/#features' },
                                    { label: t('nav.integrations', { defaultValue: 'Integrations' }), href: '/integrations' },
                                    { label: t('nav.pricing', { defaultValue: 'Pricing' }), href: '/pricing' },
                                    { label: t('nav.faq', { defaultValue: 'FAQ' }), href: '/faq' },
                                ].map((l) => (
                                    <li key={l.href}>
                                        <Link href={l.href} className="text-sm text-neutral-400 hover:text-white transition">{l.label}</Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>

                    <div className="pt-6 flex flex-col sm:flex-row items-center justify-between gap-4" style={{ borderTop: '1px solid rgba(255,255,255,0.08)' }}>
                        <p className="text-xs text-neutral-500">
                            &copy; {new Date().getFullYear()} {appName}. {t('nav.all_rights_reserved', { defaultValue: 'All rights reserved.' })}
                        </p>
                        <button
                            type="button"
                            onClick={handleThemeToggle}
                            className="text-xs text-neutral-500 hover:text-neutral-300 flex items-center gap-1.5 transition"
                        >
                            {theme === 'dark' ? <SunIcon className="h-3.5 w-3.5" /> : <MoonIcon className="h-3.5 w-3.5" />}
                            {theme === 'dark' ? t('nav.light_mode', { defaultValue: 'Light mode' }) : t('nav.dark_mode', { defaultValue: 'Dark mode' })}
                        </button>
                    </div>
                </div>
            </footer>
        </div>
    );
}
