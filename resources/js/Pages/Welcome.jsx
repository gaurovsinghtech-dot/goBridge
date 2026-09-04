import { useState } from 'react';
import { Link } from '@inertiajs/react';
import LandingLayout from '@/Layouts/LandingLayout';
import SeoHead from '@/Components/SeoHead';
import {
    Sparkles, ArrowRight, CheckCircle2, Calendar, MessageSquare, Bot, Megaphone,
    PhoneCall, BarChart3, Plug, Users, ShieldCheck, Headphones, Layers, Zap,
    TrendingUp, ExternalLink, ChevronRight, Check, ChevronDown, Star, Play
} from 'lucide-react';

export default function Welcome({ auth, canLogin, canRegister, landing = {}, plans = [] }) {
    const s = (key, def = '') => landing[`landing.${key}`] ?? def;
    const metaTitle = s('seo_title') || 'Growbridge Connect — AI-Powered Omnichannel Platform';
    const metaDesc = s('seo_description') || 'Engage customers across WhatsApp, Instagram, Messenger, Email and Voice with AI-powered automation, smart campaigns, and real-time insights.';

    const [billingCycle, setBillingCycle] = useState('monthly');
    const [activeChannelTab, setActiveChannelTab] = useState('whatsapp');
    const [openFaq, setOpenFaq] = useState(null);

    const toggleFaq = (idx) => setOpenFaq(openFaq === idx ? null : idx);

    return (
        <LandingLayout>
            <SeoHead
                title={metaTitle}
                description={metaDesc}
                keywords={s('seo_keywords')}
                image={s('seo_og_image') || undefined}
            />

            {/* ── 1. HERO SECTION ── */}
            <section className="relative pt-8 pb-20 lg:pt-14 lg:pb-32 overflow-hidden">
                {/* Background Ambient Glow & Curves */}
                <div className="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[650px] pointer-events-none -z-10">
                    <div className="absolute -top-24 left-1/4 w-[600px] h-[500px] bg-emerald-500/10 rounded-full blur-3xl" />
                    <div className="absolute top-1/3 right-10 w-[450px] h-[400px] bg-emerald-600/10 rounded-full blur-3xl" />
                </div>

                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
                        {/* Left Column: Value Proposition */}
                        <div className="lg:col-span-5 space-y-6 text-left">
                            {/* Pill Badge */}
                            <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-emerald-500/30 bg-emerald-950/40 backdrop-blur-md shadow-inner">
                                <Sparkles className="h-4 w-4 text-emerald-400 animate-pulse" />
                                <span className="text-xs font-bold uppercase tracking-wider text-emerald-400">
                                    AI-Powered Omnichannel Platform
                                </span>
                            </div>

                            {/* Headline */}
                            <h1 className="text-4xl sm:text-5xl lg:text-[54px] font-extrabold tracking-tight text-white leading-[1.15]">
                                Connect. Automate. <br />
                                Grow Your Business. <br />
                                <span className="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-teal-300 to-emerald-500">
                                    All in One Platform.
                                </span>
                            </h1>

                            {/* Subtitle */}
                            <p className="text-base sm:text-lg text-neutral-300 leading-relaxed max-w-xl">
                                Engage customers across WhatsApp, Instagram, Messenger, Email and Voice with AI-powered automation, smart campaigns, and real-time insights — all in one place.
                            </p>

                            {/* CTA Button Group */}
                            <div className="flex flex-wrap items-center gap-4 pt-2">
                                <Link
                                    href={route('register')}
                                    className="rounded-full bg-emerald-500 hover:bg-emerald-400 text-black font-bold text-base px-7 py-3.5 shadow-xl shadow-emerald-500/25 hover:shadow-emerald-500/40 transition-all duration-200 flex items-center gap-2 group"
                                >
                                    <span>Start 14-Day Free Trial</span>
                                    <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition-transform" />
                                </Link>
                                <a
                                    href="#features"
                                    className="rounded-full border border-neutral-700 hover:border-neutral-500 bg-[#062219]/60 hover:bg-[#082e22] text-white font-semibold text-base px-6 py-3.5 transition-all duration-200 flex items-center gap-2"
                                >
                                    <Calendar className="h-4 w-4 text-emerald-400" />
                                    <span>Book a Demo</span>
                                </a>
                            </div>

                            {/* Trust Badges */}
                            <div className="flex flex-wrap items-center gap-6 pt-3 text-xs sm:text-sm text-neutral-400">
                                <div className="flex items-center gap-1.5">
                                    <CheckCircle2 className="h-4 w-4 text-emerald-400 shrink-0" />
                                    <span>No Credit Card</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <CheckCircle2 className="h-4 w-4 text-emerald-400 shrink-0" />
                                    <span>Easy Setup</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <CheckCircle2 className="h-4 w-4 text-emerald-400 shrink-0" />
                                    <span>Cancel Anytime</span>
                                </div>
                            </div>
                        </div>

                        {/* Right Column: High-Fidelity Floating Dashboard Preview */}
                        <div className="lg:col-span-7 relative">
                            {/* Decorative background aura */}
                            <div className="absolute -inset-1.5 bg-gradient-to-r from-emerald-500/30 to-teal-500/20 rounded-3xl blur-xl -z-10 opacity-75" />

                            {/* Floating Mockup Card */}
                            <div className="rounded-2xl border border-emerald-500/30 bg-[#031c15] shadow-2xl overflow-hidden text-neutral-900 font-sans flex flex-row select-none">
                                {/* Dashboard Mini Sidebar */}
                                <div className="w-36 sm:w-44 bg-[#031c15] text-white p-3 flex flex-col justify-between border-r border-emerald-900/40 shrink-0 hidden sm:flex">
                                    <div className="space-y-4">
                                        <div className="flex items-center gap-2 px-1">
                                            <div className="h-6 w-6 rounded-full bg-white flex items-center justify-center">
                                                <span className="text-xs font-black text-black">G</span>
                                            </div>
                                            <div className="text-xs font-bold leading-tight">
                                                Growbridge <br />
                                                <span className="text-emerald-400 font-medium text-[10px]">Connect</span>
                                            </div>
                                        </div>

                                        <nav className="space-y-1">
                                            <div className="flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-medium">
                                                <Layers className="h-3.5 w-3.5" />
                                                <span>Dashboard</span>
                                            </div>
                                            {[
                                                { icon: MessageSquare, label: 'Inbox' },
                                                { icon: Users, label: 'Contacts' },
                                                { icon: Megaphone, label: 'Campaigns' },
                                                { icon: Zap, label: 'Automations' },
                                                { icon: Bot, label: 'AI Agents' },
                                                { icon: PhoneCall, label: 'Voice Agents' },
                                                { icon: Plug, label: 'Channels' },
                                                { icon: BarChart3, label: 'Analytics' },
                                                { icon: Layers, label: 'Templates' },
                                            ].map((item, i) => (
                                                <div key={i} className="flex items-center gap-2 px-2.5 py-1 rounded-lg text-neutral-400 hover:text-white text-[11px] font-normal">
                                                    <item.icon className="h-3 w-3" />
                                                    <span>{item.label}</span>
                                                </div>
                                            ))}
                                        </nav>
                                    </div>
                                    <div className="text-[10px] text-neutral-500 px-1">Growbridge MVP v1.0</div>
                                </div>

                                {/* Dashboard Main White Canvas Area */}
                                <div className="flex-1 bg-white p-4 sm:p-5 overflow-hidden flex flex-col justify-between">
                                    {/* Top Bar inside mockup */}
                                    <div className="flex items-center justify-between pb-3 border-b border-neutral-100 gap-2">
                                        <div>
                                            <h3 className="text-xs sm:text-sm font-bold text-neutral-900">Dashboard</h3>
                                            <p className="text-[10px] sm:text-xs text-neutral-500">Welcome back, John! Here's what's happening today.</p>
                                        </div>
                                        <div className="flex items-center gap-2 shrink-0">
                                            <span className="text-[10px] text-neutral-600 bg-neutral-100 border border-neutral-200 px-2 py-0.5 rounded-md hidden sm:inline-block">
                                                May 20, 2025 - May 26, 2025 ⌄
                                            </span>
                                            <div className="h-5 w-5 rounded-full bg-emerald-600 text-white font-bold text-[9px] flex items-center justify-center">
                                                J
                                            </div>
                                        </div>
                                    </div>

                                    {/* 6 KPI Cards Grid */}
                                    <div className="grid grid-cols-3 sm:grid-cols-6 gap-2 my-3">
                                        {[
                                            { label: 'Contacts', value: '12,540', delta: '+12.5%', color: 'text-emerald-600' },
                                            { label: 'Messages', value: '8,921', delta: '+15.3%', color: 'text-blue-600' },
                                            { label: 'Conversations', value: '86', delta: '+8.7%', color: 'text-green-600' },
                                            { label: 'Campaigns', value: '12', delta: '+20.0%', color: 'text-rose-600' },
                                            { label: 'Automations', value: '24', delta: '+14.3%', color: 'text-purple-600' },
                                            { label: 'AI Conversations', value: '43', delta: '+18.6%', color: 'text-teal-600' },
                                        ].map((kpi, idx) => (
                                            <div key={idx} className="bg-neutral-50 border border-neutral-200/80 rounded-xl p-2 text-left">
                                                <div className="text-[9px] text-neutral-500 font-medium truncate">{kpi.label}</div>
                                                <div className="text-xs sm:text-sm font-extrabold text-neutral-900">{kpi.value}</div>
                                                <div className="text-[8px] font-semibold text-emerald-600">↑ {kpi.delta}</div>
                                            </div>
                                        ))}
                                    </div>

                                    {/* Middle 3 Widgets Row */}
                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                        {/* Donut Chart Widget */}
                                        <div className="bg-neutral-50 border border-neutral-200/80 rounded-xl p-2.5 flex flex-col justify-between">
                                            <div className="text-[10px] font-bold text-neutral-800">Messages by Channel</div>
                                            <div className="flex items-center justify-center py-1.5">
                                                {/* Mini SVG Donut Chart */}
                                                <svg className="h-16 w-16 -rotate-90 transform" viewBox="0 0 36 36">
                                                    <path className="text-neutral-200" strokeWidth="4.5" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                                    <path className="text-emerald-500" strokeDasharray="58, 100" strokeWidth="4.5" strokeLinecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                                    <path className="text-blue-500" strokeDashoffset="-58" strokeDasharray="21, 100" strokeWidth="4.5" strokeLinecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                                    <path className="text-pink-500" strokeDashoffset="-79" strokeDasharray="11, 100" strokeWidth="4.5" strokeLinecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                                    <path className="text-purple-500" strokeDashoffset="-90" strokeDasharray="9, 100" strokeWidth="4.5" strokeLinecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                                </svg>
                                            </div>
                                            <div className="grid grid-cols-2 gap-1 text-[8px] text-neutral-600">
                                                <div className="flex items-center gap-1"><span className="h-1.5 w-1.5 rounded-full bg-emerald-500" /> WhatsApp (58%)</div>
                                                <div className="flex items-center gap-1"><span className="h-1.5 w-1.5 rounded-full bg-blue-500" /> Messenger (21%)</div>
                                                <div className="flex items-center gap-1"><span className="h-1.5 w-1.5 rounded-full bg-pink-500" /> Instagram (11%)</div>
                                                <div className="flex items-center gap-1"><span className="h-1.5 w-1.5 rounded-full bg-purple-500" /> Email (9%)</div>
                                            </div>
                                        </div>

                                        {/* Recent Activity Widget */}
                                        <div className="bg-neutral-50 border border-neutral-200/80 rounded-xl p-2.5 flex flex-col justify-between">
                                            <div className="text-[10px] font-bold text-neutral-800">Recent Activity</div>
                                            <div className="space-y-1.5 py-1">
                                                <div className="text-[8px] text-neutral-700 flex items-center justify-between">
                                                    <span className="truncate">🟢 WhatsApp msg from +1 234...</span>
                                                    <span className="text-[7px] text-neutral-400 shrink-0">2m</span>
                                                </div>
                                                <div className="text-[8px] text-neutral-700 flex items-center justify-between">
                                                    <span className="truncate">🤖 AI Agent completed chat</span>
                                                    <span className="text-[7px] text-neutral-400 shrink-0">5m</span>
                                                </div>
                                                <div className="text-[8px] text-neutral-700 flex items-center justify-between">
                                                    <span className="truncate">👤 Contact added: Sarah J.</span>
                                                    <span className="text-[7px] text-neutral-400 shrink-0">1h</span>
                                                </div>
                                                <div className="text-[8px] text-neutral-700 flex items-center justify-between">
                                                    <span className="truncate">📢 Summer Sale sent</span>
                                                    <span className="text-[7px] text-neutral-400 shrink-0">2h</span>
                                                </div>
                                            </div>
                                            <div className="text-[8px] text-emerald-600 font-semibold text-right">View all activity →</div>
                                        </div>

                                        {/* Top Channels Progress */}
                                        <div className="bg-neutral-50 border border-neutral-200/80 rounded-xl p-2.5 flex flex-col justify-between">
                                            <div className="text-[10px] font-bold text-neutral-800">Top Channels</div>
                                            <div className="space-y-1.5 py-1 text-[8px]">
                                                <div>
                                                    <div className="flex justify-between text-neutral-600 mb-0.5">
                                                        <span>WhatsApp</span>
                                                        <span className="font-bold">5,216</span>
                                                    </div>
                                                    <div className="w-full bg-neutral-200 rounded-full h-1.5 overflow-hidden">
                                                        <div className="bg-emerald-500 h-1.5 rounded-full w-[100%]" />
                                                    </div>
                                                </div>
                                                <div>
                                                    <div className="flex justify-between text-neutral-600 mb-0.5">
                                                        <span>Messenger</span>
                                                        <span className="font-bold">1,872</span>
                                                    </div>
                                                    <div className="w-full bg-neutral-200 rounded-full h-1.5 overflow-hidden">
                                                        <div className="bg-blue-500 h-1.5 rounded-full w-[36%]" />
                                                    </div>
                                                </div>
                                                <div>
                                                    <div className="flex justify-between text-neutral-600 mb-0.5">
                                                        <span>Instagram</span>
                                                        <span className="font-bold">1,023</span>
                                                    </div>
                                                    <div className="w-full bg-neutral-200 rounded-full h-1.5 overflow-hidden">
                                                        <div className="bg-pink-500 h-1.5 rounded-full w-[20%]" />
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="text-[8px] text-neutral-400 text-right">Updated just now</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ── 2. TRUSTED BY BUSINESSES WORLDWIDE ── */}
            <section className="border-y border-emerald-900/30 bg-[#031510]/80 py-8 backdrop-blur-md">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <p className="text-center text-xs font-bold uppercase tracking-widest text-emerald-400 mb-6">
                        TRUSTED BY BUSINESSES WORLDWIDE
                    </p>
                    <div className="flex flex-wrap items-center justify-center gap-8 md:gap-14 text-neutral-300">
                        {/* WhatsApp */}
                        <div className="flex items-center gap-2.5 grayscale hover:grayscale-0 transition-all opacity-80 hover:opacity-100">
                            <div className="h-7 w-7 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                                <MessageSquare className="h-4 w-4" />
                            </div>
                            <div className="text-left">
                                <div className="text-sm font-bold text-white leading-none">WhatsApp</div>
                                <div className="text-[10px] text-neutral-400">Cloud API</div>
                            </div>
                        </div>

                        {/* Meta */}
                        <div className="flex items-center gap-2.5 grayscale hover:grayscale-0 transition-all opacity-80 hover:opacity-100">
                            <div className="h-7 w-7 rounded-full bg-blue-500/20 text-blue-400 flex items-center justify-center font-bold text-xs">
                                ∞
                            </div>
                            <div className="text-left">
                                <div className="text-sm font-bold text-white leading-none">Meta</div>
                                <div className="text-[10px] text-neutral-400">Business Partners</div>
                            </div>
                        </div>

                        {/* Instagram */}
                        <div className="flex items-center gap-2.5 grayscale hover:grayscale-0 transition-all opacity-80 hover:opacity-100">
                            <div className="h-7 w-7 rounded-full bg-pink-500/20 text-pink-400 flex items-center justify-center font-bold text-xs">
                                📷
                            </div>
                            <div className="text-left">
                                <div className="text-sm font-bold text-white leading-none">Instagram</div>
                                <div className="text-[10px] text-neutral-400">Graph API</div>
                            </div>
                        </div>

                        {/* SendGrid */}
                        <div className="flex items-center gap-2.5 grayscale hover:grayscale-0 transition-all opacity-80 hover:opacity-100">
                            <div className="h-7 w-7 rounded-full bg-cyan-500/20 text-cyan-400 flex items-center justify-center font-bold text-xs">
                                ✉
                            </div>
                            <div className="text-left">
                                <div className="text-sm font-bold text-white leading-none">SendGrid</div>
                                <div className="text-[10px] text-neutral-400">Twilio</div>
                            </div>
                        </div>

                        {/* OpenAI */}
                        <div className="flex items-center gap-2.5 grayscale hover:grayscale-0 transition-all opacity-80 hover:opacity-100">
                            <div className="h-7 w-7 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                                <Sparkles className="h-4 w-4" />
                            </div>
                            <div className="text-left">
                                <div className="text-sm font-bold text-white leading-none">OpenAI</div>
                            </div>
                        </div>

                        {/* Twilio */}
                        <div className="flex items-center gap-2.5 grayscale hover:grayscale-0 transition-all opacity-80 hover:opacity-100">
                            <div className="h-7 w-7 rounded-full bg-red-500/20 text-red-400 flex items-center justify-center">
                                <PhoneCall className="h-4 w-4" />
                            </div>
                            <div className="text-left">
                                <div className="text-sm font-bold text-white leading-none">Twilio</div>
                                <div className="text-[10px] text-neutral-400">Voice & SMS</div>
                            </div>
                        </div>

                        {/* +50 More */}
                        <div className="px-3.5 py-1 rounded-full border border-emerald-500/40 bg-emerald-500/10 text-emerald-400 font-bold text-xs">
                            +50+ More
                        </div>
                    </div>
                </div>
            </section>

            {/* ── 3. POWERFUL FEATURES GRID ── */}
            <section id="features" className="py-24 relative">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold uppercase tracking-widest mb-3">
                        POWERFUL FEATURES
                    </div>
                    <h2 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white tracking-tight">
                        Everything You Need to Engage & Grow
                    </h2>
                    <p className="mt-4 text-base sm:text-lg text-neutral-400 max-w-2xl mx-auto">
                        All the tools you need to manage conversations, automate workflows, and grow your business — in one unified platform.
                    </p>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-14 text-left">
                        {[
                            {
                                icon: MessageSquare,
                                color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
                                title: 'Unified Inbox',
                                desc: 'Manage all conversations from WhatsApp, Instagram, Messenger, Email & more in one shared inbox.',
                            },
                            {
                                icon: Bot,
                                color: 'bg-purple-500/15 text-purple-400 border-purple-500/30',
                                title: 'AI Automation',
                                desc: 'Automate replies, qualify leads, and engage customers 24/7 with intelligent AI agents.',
                            },
                            {
                                icon: Megaphone,
                                color: 'bg-blue-500/15 text-blue-400 border-blue-500/30',
                                title: 'Smart Campaigns',
                                desc: 'Create, schedule and send targeted campaigns across multiple channels with ease.',
                            },
                            {
                                icon: PhoneCall,
                                color: 'bg-amber-500/15 text-amber-400 border-amber-500/30',
                                title: 'AI Voice Agents',
                                desc: 'Deploy AI voice agents with Twilio telephony to handle calls, qualify leads & book appointments.',
                            },
                            {
                                icon: BarChart3,
                                color: 'bg-teal-500/15 text-teal-400 border-teal-500/30',
                                title: 'Analytics & Reports',
                                desc: 'Track performance, monitor conversations and get actionable insights to grow faster.',
                            },
                            {
                                icon: Plug,
                                color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
                                title: 'API & Integrations',
                                desc: 'Connect your favorite tools and automate with powerful API & webhook support.',
                            },
                        ].map((card, idx) => (
                            <div
                                key={idx}
                                className="rounded-2xl border border-emerald-900/40 bg-gradient-to-b from-[#06261c]/70 to-[#031711]/90 p-7 hover:border-emerald-500/50 hover:shadow-2xl hover:shadow-emerald-500/10 transition-all duration-300 group"
                            >
                                <div className={`h-12 w-12 rounded-xl flex items-center justify-center border ${card.color} mb-5 group-hover:scale-110 transition-transform duration-200`}>
                                    <card.icon className="h-6 w-6" />
                                </div>
                                <h3 className="text-xl font-bold text-white mb-2.5 group-hover:text-emerald-300 transition-colors">
                                    {card.title}
                                </h3>
                                <p className="text-sm text-neutral-400 leading-relaxed">
                                    {card.desc}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── 4. STATS COUNTER BAR ── */}
            <section className="py-12 border-y border-emerald-900/30 bg-[#031711]">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-8 text-center">
                        <div className="flex flex-col items-center">
                            <Users className="h-8 w-8 text-emerald-400 mb-2" />
                            <div className="text-3xl sm:text-4xl font-extrabold text-white">50K+</div>
                            <div className="text-xs text-neutral-400 mt-0.5">Businesses</div>
                        </div>
                        <div className="flex flex-col items-center">
                            <MessageSquare className="h-8 w-8 text-emerald-400 mb-2" />
                            <div className="text-3xl sm:text-4xl font-extrabold text-white">10M+</div>
                            <div className="text-xs text-neutral-400 mt-0.5">Conversations</div>
                        </div>
                        <div className="flex flex-col items-center">
                            <ShieldCheck className="h-8 w-8 text-emerald-400 mb-2" />
                            <div className="text-3xl sm:text-4xl font-extrabold text-white">99.9%</div>
                            <div className="text-xs text-neutral-400 mt-0.5">Uptime</div>
                        </div>
                        <div className="flex flex-col items-center">
                            <Headphones className="h-8 w-8 text-emerald-400 mb-2" />
                            <div className="text-3xl sm:text-4xl font-extrabold text-white">24/7</div>
                            <div className="text-xs text-neutral-400 mt-0.5">Support</div>
                        </div>
                        <div className="flex flex-col items-center col-span-2 md:col-span-1">
                            <Calendar className="h-8 w-8 text-emerald-400 mb-2" />
                            <div className="text-3xl sm:text-4xl font-extrabold text-white">14 Days</div>
                            <div className="text-xs text-neutral-400 mt-0.5">Free Trial</div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ── 5. INTERACTIVE CHANNELS SHOWCASE ── */}
            <section id="channels" className="py-24 relative overflow-hidden">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center max-w-3xl mx-auto mb-14">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold uppercase tracking-widest mb-3">
                            OMNICHANNEL SUITE
                        </div>
                        <h2 className="text-3xl sm:text-4xl font-extrabold text-white">
                            Meet Your Customers Where They Live
                        </h2>
                        <p className="mt-3 text-neutral-400">
                            Switch between channels effortlessly. All messages converge into a single, high-velocity inbox.
                        </p>
                    </div>

                    {/* Channel Selector Pills */}
                    <div className="flex flex-wrap justify-center gap-3 mb-10">
                        {[
                            { id: 'whatsapp', name: 'WhatsApp Cloud API', color: 'emerald' },
                            { id: 'instagram', name: 'Instagram DM', color: 'pink' },
                            { id: 'messenger', name: 'Facebook Messenger', color: 'blue' },
                            { id: 'email', name: 'Email Marketing', color: 'purple' },
                            { id: 'voice', name: 'Twilio Voice Agents', color: 'amber' },
                        ].map((tab) => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveChannelTab(tab.id)}
                                className={`px-5 py-2.5 rounded-full text-sm font-bold transition-all duration-200 ${
                                    activeChannelTab === tab.id
                                        ? 'bg-emerald-500 text-black shadow-lg shadow-emerald-500/30 scale-105'
                                        : 'bg-[#06241b] text-neutral-300 border border-emerald-900/40 hover:border-emerald-500/40'
                                }`}
                            >
                                {tab.name}
                            </button>
                        ))}
                    </div>

                    {/* Interactive Tab Visual Box */}
                    <div className="rounded-3xl border border-emerald-800/40 bg-gradient-to-b from-[#052118] to-[#02110c] p-6 sm:p-10 shadow-2xl max-w-4xl mx-auto">
                        {activeChannelTab === 'whatsapp' && (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                                <div className="space-y-4 text-left">
                                    <span className="px-2.5 py-1 rounded bg-emerald-500/20 text-emerald-400 font-mono text-xs font-semibold">Official Meta Cloud API</span>
                                    <h3 className="text-2xl font-bold text-white">WhatsApp Business at Scale</h3>
                                    <p className="text-sm text-neutral-300 leading-relaxed">
                                        Send verified interactive broadcast campaigns, automatic appointment reminders, OTPs, and provide 24/7 AI-powered customer support.
                                    </p>
                                    <ul className="space-y-2 text-sm text-neutral-300">
                                        <li className="flex items-center gap-2"><Check className="h-4 w-4 text-emerald-400" /> Meta approved template synchronization</li>
                                        <li className="flex items-center gap-2"><Check className="h-4 w-4 text-emerald-400" /> Rich media buttons & interactive lists</li>
                                        <li className="flex items-center gap-2"><Check className="h-4 w-4 text-emerald-400" /> Automated Green Tick verification ready</li>
                                    </ul>
                                </div>
                                <div className="bg-[#0b141a] rounded-2xl p-4 border border-neutral-800 text-white font-sans text-xs space-y-3">
                                    <div className="bg-[#202c33] p-2.5 rounded-lg flex items-center gap-2">
                                        <div className="h-7 w-7 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold">G</div>
                                        <div>
                                            <div className="font-semibold text-xs">Growbridge Bot</div>
                                            <div className="text-[10px] text-emerald-400">Official Business Account</div>
                                        </div>
                                    </div>
                                    <div className="bg-[#005c4b] p-3 rounded-xl rounded-tl-none max-w-[85%] text-left space-y-2">
                                        <p>Hello Sarah! 🌿 Your summer order #GB-9821 has shipped and is out for delivery today.</p>
                                        <div className="border-t border-white/20 pt-1.5 flex gap-2">
                                            <button className="bg-white/10 px-2 py-1 rounded text-[10px] font-medium">Track Package 📦</button>
                                            <button className="bg-white/10 px-2 py-1 rounded text-[10px] font-medium">Chat with Agent 💬</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeChannelTab === 'instagram' && (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8 items-center text-left">
                                <div className="space-y-4">
                                    <span className="px-2.5 py-1 rounded bg-pink-500/20 text-pink-400 font-mono text-xs font-semibold">Instagram Direct Graph API</span>
                                    <h3 className="text-2xl font-bold text-white">Convert DMs & Story Replies into Sales</h3>
                                    <p className="text-sm text-neutral-300 leading-relaxed">
                                        Instantly reply to comments, Story mentions, and direct messages. Automatically qualify leads from ad campaigns directly in chat.
                                    </p>
                                </div>
                                <div className="bg-neutral-900 rounded-2xl p-4 border border-neutral-800 text-white font-sans text-xs space-y-2">
                                    <div className="bg-neutral-800 p-2 rounded-lg font-bold">Instagram Story Mention Trigger</div>
                                    <div className="p-2.5 rounded-xl bg-gradient-to-r from-purple-600 to-pink-600 text-left">
                                        "Thanks for tagging us! Here is your exclusive 20% discount code: <span className="font-mono font-bold">SUMMER20</span>"
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeChannelTab === 'voice' && (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8 items-center text-left">
                                <div className="space-y-4">
                                    <span className="px-2.5 py-1 rounded bg-amber-500/20 text-amber-400 font-mono text-xs font-semibold">Twilio Telephony & Voice AI</span>
                                    <h3 className="text-2xl font-bold text-white">AI Voice Agents That Answer Calls</h3>
                                    <p className="text-sm text-neutral-300 leading-relaxed">
                                        Handle customer phone calls autonomously. Twilio AI voice agents answer inquiries, book appointments, and sync full transcripts into your CRM.
                                    </p>
                                </div>
                                <div className="bg-neutral-900 rounded-2xl p-4 border border-amber-900/40 text-neutral-200 text-xs space-y-2">
                                    <div className="flex items-center justify-between border-b border-neutral-800 pb-2">
                                        <span className="font-bold text-amber-400 flex items-center gap-1.5"><PhoneCall className="h-3.5 w-3.5" /> Call Transcript Log</span>
                                        <span className="text-[10px] text-neutral-400">Duration: 1m 42s</span>
                                    </div>
                                    <div className="text-left space-y-1.5 font-mono text-[11px]">
                                        <p><span className="text-emerald-400 font-bold">AI Agent:</span> "Hello, thank you for calling Growbridge. How can I assist you today?"</p>
                                        <p><span className="text-amber-400 font-bold">Customer:</span> "I'd like to book an onboarding demo for tomorrow."</p>
                                        <p><span className="text-emerald-400 font-bold">AI Agent:</span> "I have 2:00 PM available. I have scheduled that for you and sent a confirmation via WhatsApp."</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {(activeChannelTab === 'messenger' || activeChannelTab === 'email') && (
                            <div className="text-center py-6">
                                <h3 className="text-xl font-bold text-white mb-2">{activeChannelTab === 'messenger' ? 'Facebook Messenger Suite' : 'High-Deliverability Email Marketing'}</h3>
                                <p className="text-sm text-neutral-400 max-w-xl mx-auto">
                                    Full native support with visual template builders, automated triggers, open/click tracking, and complete webhook synchronization.
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            </section>

            {/* ── 6. PRICING PREVIEW (Razorpay Native) ── */}
            <section id="pricing" className="py-24 border-t border-emerald-900/30 bg-[#020e0a]">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold uppercase tracking-widest mb-3">
                        TRANSPARENT PRICING
                    </div>
                    <h2 className="text-3xl sm:text-4xl font-extrabold text-white">
                        Simple, Predictable Plans for Every Stage
                    </h2>
                    <p className="mt-3 text-neutral-400 max-w-xl mx-auto">
                        Powered securely by Razorpay. Upgrade, downgrade, or cancel anytime with zero lock-in.
                    </p>

                    {/* Toggle */}
                    <div className="inline-flex items-center p-1 rounded-full bg-[#06241b] border border-emerald-900/40 mt-8">
                        <button
                            onClick={() => setBillingCycle('monthly')}
                            className={`px-5 py-2 rounded-full text-xs font-bold transition-all ${
                                billingCycle === 'monthly' ? 'bg-emerald-500 text-black shadow-md' : 'text-neutral-300'
                            }`}
                        >
                            Monthly Billing
                        </button>
                        <button
                            onClick={() => setBillingCycle('yearly')}
                            className={`px-5 py-2 rounded-full text-xs font-bold transition-all flex items-center gap-1.5 ${
                                billingCycle === 'yearly' ? 'bg-emerald-500 text-black shadow-md' : 'text-neutral-300'
                            }`}
                        >
                            <span>Yearly Billing</span>
                            <span className="px-1.5 py-0.5 rounded-full bg-emerald-950 text-emerald-300 text-[10px] font-extrabold">SAVE 20%</span>
                        </button>
                    </div>

                    {/* Plan Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mt-14 text-left max-w-6xl mx-auto">
                        {[
                            {
                                name: 'Starter',
                                desc: 'Perfect for small businesses getting started with omnichannel automation.',
                                monthly: '$29',
                                yearly: '$24',
                                features: ['1 WhatsApp Cloud API number', 'Unified Inbox (All channels)', 'Up to 2,500 contacts', '5 Automated Workflows', 'Standard Email Support'],
                                popular: false,
                            },
                            {
                                name: 'Growth',
                                desc: 'For scaling brands needing AI agents, voice telephony & smart campaigns.',
                                monthly: '$79',
                                yearly: '$64',
                                features: ['3 WhatsApp numbers + Instagram + Messenger', 'AI Agent (Knowledge Base + Auto-reply)', 'Twilio Voice Agent Integration', 'Up to 25,000 contacts', 'Unlimited Visual Automations', 'Priority 24/7 Support'],
                                popular: true,
                            },
                            {
                                name: 'Enterprise',
                                desc: 'Maximum throughput, custom LLM fine-tuning, and dedicated account manager.',
                                monthly: '$199',
                                yearly: '$159',
                                features: ['Unlimited Connected Channels', 'Dedicated AI Voice & Chatbot Instances', 'Unlimited Contacts & Messages', 'Custom Webhooks & REST API v1', 'Dedicated Account Manager & SLA'],
                                popular: false,
                            },
                        ].map((plan, i) => (
                            <div
                                key={i}
                                className={`rounded-3xl p-8 transition-all relative flex flex-col justify-between ${
                                    plan.popular
                                        ? 'bg-gradient-to-b from-[#083023] to-[#041d15] border-2 border-emerald-400 shadow-2xl shadow-emerald-500/20 scale-105'
                                        : 'bg-[#041a13] border border-emerald-900/50 hover:border-emerald-500/40'
                                }`}
                            >
                                {plan.popular && (
                                    <div className="absolute -top-3.5 left-1/2 -translate-x-1/2 px-4 py-1 rounded-full bg-emerald-500 text-black text-xs font-black uppercase tracking-wider shadow-lg">
                                        Most Popular
                                    </div>
                                )}
                                <div>
                                    <h3 className="text-xl font-bold text-white mb-2">{plan.name}</h3>
                                    <p className="text-xs text-neutral-400 mb-6 leading-relaxed">{plan.desc}</p>
                                    <div className="flex items-baseline gap-1 mb-6">
                                        <span className="text-4xl font-extrabold text-white">
                                            {billingCycle === 'monthly' ? plan.monthly : plan.yearly}
                                        </span>
                                        <span className="text-xs text-neutral-400">/ month</span>
                                    </div>

                                    <ul className="space-y-3 text-xs text-neutral-300 mb-8">
                                        {plan.features.map((feat, idx) => (
                                            <li key={idx} className="flex items-center gap-2">
                                                <CheckCircle2 className="h-4 w-4 text-emerald-400 shrink-0" />
                                                <span>{feat}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>

                                <Link
                                    href={route('register')}
                                    className={`w-full text-center py-3 rounded-full text-sm font-bold transition-all ${
                                        plan.popular
                                            ? 'bg-emerald-500 hover:bg-emerald-400 text-black shadow-lg shadow-emerald-500/30'
                                            : 'bg-[#062e22] hover:bg-emerald-600 text-white'
                                    }`}
                                >
                                    Start 14-Day Free Trial
                                </Link>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── 7. FREQUENTLY ASKED QUESTIONS ── */}
            <section id="faq" className="py-24 border-t border-emerald-900/30 bg-[#031510]">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center mb-14">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold uppercase tracking-widest mb-3">
                            FREQUENTLY ASKED QUESTIONS
                        </div>
                        <h2 className="text-3xl font-extrabold text-white">Got Questions? We Have Answers.</h2>
                    </div>

                    <div className="space-y-3">
                        {[
                            {
                                q: 'Do I need my own WhatsApp Business API account?',
                                a: 'No technical setup required. We connect directly through the official Meta Embedded Signup flow in less than 2 minutes using your Facebook account.',
                            },
                            {
                                q: 'How do the AI Agents work?',
                                a: 'You can upload your company documents, website links, or text instructions. The AI agent indexes your knowledge and responds autonomously across WhatsApp, Instagram, Messenger, and Email with human handoff support.',
                            },
                            {
                                q: 'How does AI Voice Agent calling work?',
                                a: 'Growbridge Connect integrates with Twilio to handle inbound/outbound calls, generate live neural transcripts, summarize intent, and automatically update contact records.',
                            },
                            {
                                q: 'What payment methods do you support?',
                                a: 'We accept Credit/Debit Cards, UPI, Net Banking, and Wallets natively through Razorpay.',
                            },
                            {
                                q: 'Can I cancel anytime?',
                                a: 'Yes! There are no long-term contracts. You can cancel your subscription with a single click from your workspace settings.',
                            },
                        ].map((faq, idx) => (
                            <div
                                key={idx}
                                className="rounded-2xl border border-emerald-900/40 bg-[#052118]/60 overflow-hidden transition-colors"
                            >
                                <button
                                    onClick={() => toggleFaq(idx)}
                                    className="w-full p-5 text-left flex items-center justify-between text-white font-semibold text-sm sm:text-base"
                                >
                                    <span>{faq.q}</span>
                                    <ChevronDown className={`h-5 w-5 text-emerald-400 transition-transform duration-200 ${openFaq === idx ? 'rotate-180' : ''}`} />
                                </button>
                                {openFaq === idx && (
                                    <div className="px-5 pb-5 text-sm text-neutral-300 leading-relaxed border-t border-emerald-900/30 pt-3">
                                        {faq.a}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── 8. BOTTOM CONVERSION BANNER ── */}
            <section className="py-20 relative">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="rounded-3xl border border-emerald-500/40 bg-gradient-to-r from-[#063023] via-[#04241a] to-[#063023] p-10 sm:p-14 text-center shadow-2xl relative overflow-hidden">
                        <div className="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-emerald-500/20 rounded-full blur-3xl" />
                        <h2 className="text-3xl sm:text-4xl font-extrabold text-white tracking-tight mb-4">
                            Ready to Transform Your Customer Experience?
                        </h2>
                        <p className="text-base text-neutral-300 max-w-xl mx-auto mb-8">
                            Join 50,000+ businesses automating their sales, support, and marketing with Growbridge Connect today.
                        </p>
                        <div className="flex flex-wrap justify-center items-center gap-4">
                            <Link
                                href={route('register')}
                                className="rounded-full bg-emerald-500 hover:bg-emerald-400 text-black font-bold text-base px-8 py-3.5 shadow-xl shadow-emerald-500/30 transition-all flex items-center gap-2 group"
                            >
                                <span>Start 14-Day Free Trial</span>
                                <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition-transform" />
                            </Link>
                            <Link
                                href={route('login')}
                                className="rounded-full border border-neutral-600 bg-neutral-900/60 hover:bg-neutral-800 text-white font-semibold text-base px-7 py-3.5 transition-all"
                            >
                                Sign In
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </LandingLayout>
    );
}
