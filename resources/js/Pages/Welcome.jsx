import { useState } from 'react';
import { Link } from '@inertiajs/react';
import LandingLayout from '@/Layouts/LandingLayout';
import SeoHead from '@/Components/SeoHead';
import {
    Sparkles, ArrowRight, CheckCircle2, Calendar, MessageSquare, Bot, Megaphone,
    PhoneCall, BarChart3, Plug, Users, ShieldCheck, Headphones, Layers, Zap,
    ChevronDown, Check
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
            <section className="relative pt-12 pb-20 lg:pt-20 lg:pb-32 overflow-hidden" style={{ background: 'linear-gradient(135deg, #FFF8E1 0%, #FEFCF3 25%, #FFF3C4 50%, #FFF8E1 75%, #FFFDF5 100%)' }}>
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
                        {/* Left Column: Value Proposition */}
                        <div className="lg:col-span-5 space-y-6 text-left">
                            {/* Minimal Pill Badge */}
                            <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-[#E8D44D]/40 bg-[#FFF8E1]">
                                <Sparkles className="h-4 w-4 text-[#C4A83A]" />
                                <span className="text-xs font-semibold tracking-wide text-[#1A1A1A]">
                                    AI-Powered Omnichannel Platform
                                </span>
                            </div>

                            {/* Main Headline */}
                            <h1 className="text-4xl sm:text-5xl lg:text-[56px] font-bold tracking-tight text-[#1A1A1A] leading-[1.15]">
                                Connect. Automate. <br />
                                Grow Your Business. <br />
                                <span className="text-[#C4A83A]">
                                    All in One Platform.
                                </span>
                            </h1>

                            {/* Subtitle */}
                            <p className="text-base sm:text-lg text-[#4A4A4A] leading-relaxed max-w-xl">
                                Engage customers across WhatsApp, Instagram, Messenger, Email and Voice with AI-powered automation, smart campaigns, and real-time insights — all in one place.
                            </p>

                            {/* CTA Button Group */}
                            <div className="flex flex-wrap items-center gap-4 pt-2">
                                <Link
                                    href={route('register')}
                                    className="rounded-full bg-[#1A1A1A] hover:bg-[#333333] text-white font-semibold text-base px-7 py-3.5 shadow-sm hover:shadow transition-all duration-200 flex items-center gap-2 group"
                                >
                                    <span>Start 14-Day Free Trial</span>
                                    <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition-transform" />
                                </Link>
                                <a
                                    href="#features"
                                    className="rounded-full border border-[#E5E5E0] bg-white hover:bg-[#FFF8E1] text-[#1A1A1A] font-semibold text-base px-6 py-3.5 transition-all duration-200 flex items-center gap-2 shadow-sm"
                                >
                                    <Calendar className="h-4 w-4 text-[#C4A83A]" />
                                    <span>Book a Demo</span>
                                </a>
                            </div>

                            {/* Trust Badges */}
                            <div className="flex flex-wrap items-center gap-6 pt-3 text-xs sm:text-sm text-[#4A4A4A]">
                                <div className="flex items-center gap-1.5">
                                    <CheckCircle2 className="h-4 w-4 text-[#6BBF59] shrink-0" />
                                    <span>No Credit Card</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <CheckCircle2 className="h-4 w-4 text-[#6BBF59] shrink-0" />
                                    <span>Easy Setup</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <CheckCircle2 className="h-4 w-4 text-[#6BBF59] shrink-0" />
                                    <span>Cancel Anytime</span>
                                </div>
                            </div>
                        </div>

                        {/* Right Column: Clean Floating Dashboard Preview */}
                        <div className="lg:col-span-7 relative">
                            <div className="rounded-[24px] border border-[#E5E5E0] bg-[#FAFAF5] shadow-xl overflow-hidden text-neutral-900 font-sans flex flex-row select-none">
                                {/* Dashboard Mini Sidebar */}
                                <div className="w-36 sm:w-44 bg-[#FFF8E1] text-[#1A1A1A] p-3 flex flex-col justify-between border-r border-[#E5E5E0] shrink-0 hidden sm:flex">
                                    <div className="space-y-4">
                                        <div className="flex items-center gap-2 px-1">
                                            <div className="h-6 w-6 rounded-md bg-[#1A1A1A] flex items-center justify-center">
                                                <span className="text-xs font-bold text-white">G</span>
                                            </div>
                                            <div className="text-xs font-bold leading-tight text-[#1A1A1A]">
                                                Growbridge <br />
                                                <span className="text-[#C4A83A] font-medium text-[10px]">Connect</span>
                                            </div>
                                        </div>

                                        <nav className="space-y-1">
                                            <div className="flex items-center gap-2 px-2.5 py-1.5 rounded-full bg-[#1A1A1A] text-white text-xs font-medium">
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
                                            ].map((item, i) => (
                                                <div key={i} className="flex items-center gap-2 px-2.5 py-1 rounded-full text-[#6A6A6A] hover:text-[#1A1A1A] hover:bg-[#E8D44D]/15 text-[11px] font-normal transition">
                                                    <item.icon className="h-3 w-3" />
                                                    <span>{item.label}</span>
                                                </div>
                                            ))}
                                        </nav>
                                    </div>
                                    <div className="text-[10px] text-[#6A6A6A] px-1">Growbridge v1.0</div>
                                </div>

                                {/* Dashboard Main White Canvas Area */}
                                <div className="flex-1 bg-[#FAFAF5] p-4 sm:p-5 overflow-hidden flex flex-col justify-between">
                                    {/* Top Bar inside mockup */}
                                    <div className="flex items-center justify-between pb-3 border-b border-[#E5E5E0] gap-2">
                                        <div>
                                            <h3 className="text-xs sm:text-sm font-bold text-[#1A1A1A]">Dashboard</h3>
                                            <p className="text-[10px] sm:text-xs text-[#6A6A6A]">Overview of your active automation channels.</p>
                                        </div>
                                        <div className="flex items-center gap-2 shrink-0">
                                            <span className="text-[10px] text-[#6A6A6A] bg-[#FFF8E1] border border-[#E5E5E0] px-2 py-0.5 rounded-full hidden sm:inline-block">
                                                May 2025 ⌄
                                            </span>
                                            <div className="h-5 w-5 rounded-full bg-[#1A1A1A] text-white font-bold text-[9px] flex items-center justify-center">
                                                J
                                            </div>
                                        </div>
                                    </div>

                                    {/* 6 KPI Cards Grid */}
                                    <div className="grid grid-cols-3 sm:grid-cols-6 gap-2 my-3">
                                        {[
                                            { label: 'Contacts', value: '12,540', delta: '+12.5%' },
                                            { label: 'Messages', value: '8,921', delta: '+15.3%' },
                                            { label: 'Conversations', value: '86', delta: '+8.7%' },
                                            { label: 'Campaigns', value: '12', delta: '+20.0%' },
                                            { label: 'Automations', value: '24', delta: '+14.3%' },
                                            { label: 'AI Chats', value: '43', delta: '+18.6%' },
                                        ].map((kpi, idx) => (
                                            <div key={idx} className="bg-[#FFF8E1] border border-[#E5E5E0] rounded-[12px] p-2 text-left">
                                                <div className="text-[9px] text-[#6A6A6A] font-medium truncate">{kpi.label}</div>
                                                <div className="text-xs sm:text-sm font-bold text-[#1A1A1A]">{kpi.value}</div>
                                                <div className="text-[8px] font-semibold text-[#6BBF59]">↑ {kpi.delta}</div>
                                            </div>
                                        ))}
                                    </div>

                                    {/* Middle 3 Widgets Row */}
                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                        {/* Donut Chart Widget */}
                                        <div className="bg-[#FFF8E1] border border-[#E5E5E0] rounded-[12px] p-2.5 flex flex-col justify-between">
                                            <div className="text-[10px] font-bold text-[#1A1A1A]">Messages by Channel</div>
                                            <div className="flex items-center justify-center py-1.5">
                                                <svg className="h-16 w-16 -rotate-90 transform" viewBox="0 0 36 36">
                                                    <path className="text-neutral-200" strokeWidth="4.5" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                                    <path className="text-[#E8D44D]" strokeDasharray="58, 100" strokeWidth="4.5" strokeLinecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                                    <path className="text-[#1A1A1A]" strokeDashoffset="-58" strokeDasharray="21, 100" strokeWidth="4.5" strokeLinecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                                    <path className="text-[#D4E4C1]" strokeDashoffset="-79" strokeDasharray="11, 100" strokeWidth="4.5" strokeLinecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                                </svg>
                                            </div>
                                            <div className="grid grid-cols-2 gap-1 text-[8px] text-[#6A6A6A]">
                                                <div className="flex items-center gap-1"><span className="h-1.5 w-1.5 rounded-full bg-[#E8D44D]" /> WhatsApp (58%)</div>
                                                <div className="flex items-center gap-1"><span className="h-1.5 w-1.5 rounded-full bg-[#1A1A1A]" /> Messenger (21%)</div>
                                            </div>
                                        </div>

                                        {/* Recent Activity Widget */}
                                        <div className="bg-[#FFF8E1] border border-[#E5E5E0] rounded-[12px] p-2.5 flex flex-col justify-between">
                                            <div className="text-[10px] font-bold text-[#1A1A1A]">Recent Activity</div>
                                            <div className="space-y-1.5 py-1">
                                                <div className="text-[8px] text-[#1A1A1A] flex items-center justify-between">
                                                    <span className="truncate">WhatsApp msg from +1 234...</span>
                                                    <span className="text-[7px] text-[#6A6A6A] shrink-0">2m</span>
                                                </div>
                                                <div className="text-[8px] text-[#1A1A1A] flex items-center justify-between">
                                                    <span className="truncate">AI Agent completed chat</span>
                                                    <span className="text-[7px] text-[#6A6A6A] shrink-0">5m</span>
                                                </div>
                                                <div className="text-[8px] text-[#1A1A1A] flex items-center justify-between">
                                                    <span className="truncate">Contact added: Sarah J.</span>
                                                    <span className="text-[7px] text-[#6A6A6A] shrink-0">1h</span>
                                                </div>
                                            </div>
                                            <div className="text-[8px] text-[#C4A83A] font-semibold text-right">View all →</div>
                                        </div>

                                        {/* Top Channels Progress */}
                                        <div className="bg-[#FFF8E1] border border-[#E5E5E0] rounded-[12px] p-2.5 flex flex-col justify-between">
                                            <div className="text-[10px] font-bold text-[#1A1A1A]">Top Channels</div>
                                            <div className="space-y-1.5 py-1 text-[8px]">
                                                <div>
                                                    <div className="flex justify-between text-[#6A6A6A] mb-0.5">
                                                        <span>WhatsApp</span>
                                                        <span className="font-bold text-[#1A1A1A]">5,216</span>
                                                    </div>
                                                    <div className="w-full bg-neutral-200 rounded-full h-1.5 overflow-hidden">
                                                        <div className="bg-[#E8D44D] h-1.5 rounded-full w-[100%]" />
                                                    </div>
                                                </div>
                                                <div>
                                                    <div className="flex justify-between text-[#6A6A6A] mb-0.5">
                                                        <span>Messenger</span>
                                                        <span className="font-bold text-[#1A1A1A]">1,872</span>
                                                    </div>
                                                    <div className="w-full bg-neutral-200 rounded-full h-1.5 overflow-hidden">
                                                        <div className="bg-[#1A1A1A] h-1.5 rounded-full w-[36%]" />
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="text-[8px] text-[#6A6A6A] text-right">Updated just now</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ── 2. TRUSTED BY BUSINESSES ── */}
            <section className="relative py-14 overflow-hidden" style={{ background: 'linear-gradient(180deg, #FFFDF5 0%, #FFF8E1 50%, #FFFDF5 100%)' }}>
                <div className="absolute inset-0 opacity-[0.03]" style={{ backgroundImage: 'radial-gradient(circle at 1px 1px, #C4A83A 1px, transparent 0)', backgroundSize: '32px 32px' }} />
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
                    <div className="flex items-center justify-center gap-3 mb-8">
                        <div className="h-px flex-1 max-w-[80px] bg-gradient-to-r from-transparent to-[#E8D44D]/40" />
                        <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#C4A83A]">
                            Trusted by Businesses Worldwide
                        </p>
                        <div className="h-px flex-1 max-w-[80px] bg-gradient-to-l from-transparent to-[#E8D44D]/40" />
                    </div>
                    <div className="flex flex-wrap items-center justify-center gap-4 md:gap-5">
                        {[
                            { icon: MessageSquare, label: 'WhatsApp Cloud API' },
                            { icon: null, label: 'Meta Business Partner', symbol: '∞' },
                            { icon: Sparkles, label: 'OpenAI Integration' },
                            { icon: PhoneCall, label: 'Twilio Voice' },
                        ].map((item, idx) => (
                            <div key={idx} className="group flex items-center gap-2.5 px-5 py-3 rounded-2xl bg-white/80 backdrop-blur-sm border border-[#E8D44D]/15 hover:border-[#E8D44D]/40 hover:shadow-lg hover:shadow-[#E8D44D]/10 hover:-translate-y-0.5 transition-all duration-300">
                                <div className="h-8 w-8 rounded-lg bg-gradient-to-br from-[#FFF8E1] to-[#F5E6A3] flex items-center justify-center group-hover:from-[#1A1A1A] group-hover:to-[#333] transition-all duration-300">
                                    {item.icon ? <item.icon className="h-4 w-4 text-[#C4A83A] group-hover:text-[#E8D44D] transition-colors" /> : <span className="text-sm font-bold text-[#C4A83A] group-hover:text-[#E8D44D] transition-colors">{item.symbol}</span>}
                                </div>
                                <span className="text-sm font-semibold text-[#1A1A1A]">{item.label}</span>
                            </div>
                        ))}
                        <div className="group flex items-center gap-2 px-5 py-3 rounded-2xl bg-gradient-to-r from-[#1A1A1A] to-[#333] text-white hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 cursor-pointer">
                            <Plug className="h-4 w-4 text-[#E8D44D]" />
                            <span className="text-sm font-semibold">+50 Integrations</span>
                            <ArrowRight className="h-3.5 w-3.5 text-[#E8D44D] group-hover:translate-x-0.5 transition-transform" />
                        </div>
                    </div>
                </div>
            </section>

            {/* ── 3. POWERFUL FEATURES GRID ── */}
            <section id="features" className="py-24 relative overflow-hidden" style={{ background: 'linear-gradient(165deg, #FFFDF5 0%, #FFF8E1 30%, #FFF3C4 60%, #FFF8E1 100%)' }}>
                <div className="absolute top-0 right-0 w-[500px] h-[500px] rounded-full bg-[#E8D44D]/[0.04] blur-3xl -translate-y-1/2 translate-x-1/4" />
                <div className="absolute bottom-0 left-0 w-[400px] h-[400px] rounded-full bg-[#C4A83A]/[0.05] blur-3xl translate-y-1/2 -translate-x-1/4" />
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative">
                    <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/70 backdrop-blur-sm border border-[#E8D44D]/25 text-[#C4A83A] text-xs font-bold uppercase tracking-[0.15em] mb-4 shadow-sm">
                        <span className="h-1.5 w-1.5 rounded-full bg-[#E8D44D] animate-pulse" />
                        FEATURES
                    </div>
                    <h2 className="text-3xl sm:text-4xl lg:text-5xl font-bold text-[#1A1A1A] tracking-tight">
                        Everything You Need to{' '}
                        <span className="relative inline-block">
                            <span className="relative z-10">Engage & Grow</span>
                            <span className="absolute bottom-1 left-0 right-0 h-3 bg-[#E8D44D]/20 -skew-x-3 rounded" />
                        </span>
                    </h2>
                    <p className="mt-5 text-base sm:text-lg text-[#6A6A6A] max-w-2xl mx-auto leading-relaxed">
                        All the tools you need to manage conversations, automate workflows, and grow your business — in one minimalist platform.
                    </p>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-16 text-left">
                        {[
                            {
                                icon: MessageSquare,
                                title: 'Unified Inbox',
                                desc: 'Manage all conversations from WhatsApp, Instagram, Messenger, Email & more in one shared inbox.',
                                accent: 'from-[#E8D44D] to-[#C4A83A]',
                            },
                            {
                                icon: Bot,
                                title: 'AI Automation',
                                desc: 'Automate replies, qualify leads, and engage customers 24/7 with intelligent AI agents.',
                                accent: 'from-[#1A1A1A] to-[#3D3A1E]',
                            },
                            {
                                icon: Megaphone,
                                title: 'Smart Campaigns',
                                desc: 'Create, schedule and send targeted campaigns across multiple channels with ease.',
                                accent: 'from-[#C4A83A] to-[#E8D44D]',
                            },
                            {
                                icon: PhoneCall,
                                title: 'AI Voice Agents',
                                desc: 'Deploy AI voice agents with Twilio telephony to handle calls, qualify leads & book appointments.',
                                accent: 'from-[#3D3A1E] to-[#1A1A1A]',
                            },
                            {
                                icon: BarChart3,
                                title: 'Analytics & Reports',
                                desc: 'Track performance, monitor conversations and get actionable insights to grow faster.',
                                accent: 'from-[#E8D44D] to-[#F5E6A3]',
                            },
                            {
                                icon: Plug,
                                title: 'API & Integrations',
                                desc: 'Connect your favorite tools and automate with powerful API & webhook support.',
                                accent: 'from-[#1A1A1A] to-[#C4A83A]',
                            },
                        ].map((card, idx) => (
                            <div
                                key={idx}
                                className="group relative rounded-2xl bg-white/80 backdrop-blur-sm p-8 hover:-translate-y-1 transition-all duration-300 overflow-hidden border border-white/60 shadow-[0_1px_3px_rgba(0,0,0,0.04)] hover:shadow-[0_20px_40px_rgba(200,180,60,0.12)]"
                            >
                                <div className="absolute inset-0 rounded-2xl border border-[#E8D44D]/0 group-hover:border-[#E8D44D]/30 transition-colors duration-300" />
                                <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-t-2xl" style={{ backgroundImage: `linear-gradient(to right, var(--tw-gradient-stops))` }}>
                                </div>
                                <div className={`h-12 w-12 rounded-xl bg-gradient-to-br ${card.accent} flex items-center justify-center mb-6 shadow-sm group-hover:scale-110 group-hover:shadow-md transition-all duration-300`}>
                                    <card.icon className="h-5.5 w-5.5 text-white" />
                                </div>
                                <h3 className="text-xl font-bold text-[#1A1A1A] mb-3">
                                    {card.title}
                                </h3>
                                <p className="text-[15px] text-[#6A6A6A] leading-relaxed">
                                    {card.desc}
                                </p>
                                <div className="mt-5 flex items-center gap-1.5 text-sm font-semibold text-[#C4A83A] opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                                    <span>Learn more</span>
                                    <ArrowRight className="h-3.5 w-3.5" />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── 4. STATS COUNTER BAR ── */}
            <section className="py-16 border-y border-[#E5E5E0] bg-[#FAFAF5]">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-8 text-center">
                        <div className="flex flex-col items-center">
                            <Users className="h-7 w-7 text-[#C4A83A] mb-2" />
                            <div className="text-3xl sm:text-4xl font-bold text-[#1A1A1A]">50K+</div>
                            <div className="text-sm text-[#6A6A6A] mt-1">Businesses</div>
                        </div>
                        <div className="flex flex-col items-center">
                            <MessageSquare className="h-7 w-7 text-[#C4A83A] mb-2" />
                            <div className="text-3xl sm:text-4xl font-bold text-[#1A1A1A]">10M+</div>
                            <div className="text-sm text-[#6A6A6A] mt-1">Messages</div>
                        </div>
                        <div className="flex flex-col items-center">
                            <ShieldCheck className="h-7 w-7 text-[#C4A83A] mb-2" />
                            <div className="text-3xl sm:text-4xl font-bold text-[#1A1A1A]">99.9%</div>
                            <div className="text-sm text-[#6A6A6A] mt-1">Uptime</div>
                        </div>
                        <div className="flex flex-col items-center">
                            <Headphones className="h-7 w-7 text-[#C4A83A] mb-2" />
                            <div className="text-3xl sm:text-4xl font-bold text-[#1A1A1A]">24/7</div>
                            <div className="text-sm text-[#6A6A6A] mt-1">Support</div>
                        </div>
                        <div className="flex flex-col items-center col-span-2 md:col-span-1">
                            <Calendar className="h-7 w-7 text-[#C4A83A] mb-2" />
                            <div className="text-3xl sm:text-4xl font-bold text-[#1A1A1A]">14 Days</div>
                            <div className="text-sm text-[#6A6A6A] mt-1">Free Trial</div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ── 5. INTERACTIVE CHANNELS SHOWCASE ── */}
            <section id="channels" className="py-24 bg-[#FFF8E1]">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center max-w-3xl mx-auto mb-14">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#FFF8E1] border border-[#E8D44D]/30 text-[#C4A83A] text-xs font-semibold uppercase tracking-wider mb-3">
                            CHANNELS
                        </div>
                        <h2 className="text-3xl sm:text-4xl font-semibold text-[#1A1A1A]">
                            Meet Your Customers Where They Live
                        </h2>
                        <p className="mt-3 text-base text-[#6A6A6A]">
                            Switch between channels effortlessly. All messages converge into a single, clean inbox.
                        </p>
                    </div>

                    {/* Channel Selector Pills */}
                    <div className="flex flex-wrap justify-center gap-2 mb-10">
                        {[
                            { id: 'whatsapp', name: 'WhatsApp Cloud API' },
                            { id: 'instagram', name: 'Instagram DM' },
                            { id: 'messenger', name: 'Facebook Messenger' },
                            { id: 'voice', name: 'Twilio Voice Agents' },
                        ].map((tab) => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveChannelTab(tab.id)}
                                className={`px-5 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 ${
                                    activeChannelTab === tab.id
                                        ? 'bg-[#1A1A1A] text-white shadow-sm'
                                        : 'bg-white text-[#6A6A6A] border border-[#E5E5E0] hover:text-[#1A1A1A] hover:bg-neutral-50'
                                }`}
                            >
                                {tab.name}
                            </button>
                        ))}
                    </div>

                    {/* Interactive Tab Visual Box */}
                    <div className="rounded-xl border border-[#E5E5E0] bg-white p-6 sm:p-10 shadow-sm max-w-4xl mx-auto">
                        {activeChannelTab === 'whatsapp' && (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                                <div className="space-y-4 text-left">
                                    <span className="px-2.5 py-1 rounded bg-[#FFF8E1] text-[#C4A83A] text-xs font-semibold">Official Meta Cloud API</span>
                                    <h3 className="text-2xl font-semibold text-[#1A1A1A]">WhatsApp Business at Scale</h3>
                                    <p className="text-base text-[#6A6A6A] leading-relaxed">
                                        Send verified interactive broadcast campaigns, automatic appointment reminders, OTPs, and provide 24/7 AI-powered customer support.
                                    </p>
                                    <ul className="space-y-2 text-sm text-[#6A6A6A]">
                                        <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#C4A83A]" /> Meta approved template synchronization</li>
                                        <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#C4A83A]" /> Rich media buttons & interactive lists</li>
                                        <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#C4A83A]" /> Green Tick verification ready</li>
                                    </ul>
                                </div>
                                <div className="bg-[#FFF8E1] rounded-xl p-4 border border-[#E5E5E0] text-[#1A1A1A] text-xs space-y-3">
                                    <div className="bg-white p-2.5 rounded-lg border border-[#E5E5E0] flex items-center gap-2">
                                        <div className="h-7 w-7 rounded-md bg-[#1A1A1A] text-white flex items-center justify-center font-bold">G</div>
                                        <div>
                                            <div className="font-semibold text-xs text-[#1A1A1A]">Growbridge Assistant</div>
                                            <div className="text-[10px] text-[#C4A83A]">Verified Business Account</div>
                                        </div>
                                    </div>
                                    <div className="bg-[#FFF8E1] border border-[#E8D44D]/20 p-3 rounded-xl max-w-[90%] text-left space-y-2 text-[#1A1A1A]">
                                        <p>Hello Sarah! Your order #GB-9821 has shipped and is out for delivery today.</p>
                                        <div className="border-t border-[#E8D44D]/30/60 pt-1.5 flex gap-2">
                                            <button className="bg-white px-2 py-1 rounded border border-[#E8D44D]/30 text-[10px] font-semibold text-[#C4A83A]">Track Package 📦</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeChannelTab === 'instagram' && (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8 items-center text-left">
                                <div className="space-y-4">
                                    <span className="px-2.5 py-1 rounded bg-[#FFF8E1] text-[#C4A83A] text-xs font-semibold">Instagram Direct Graph API</span>
                                    <h3 className="text-2xl font-semibold text-[#1A1A1A]">Convert DMs into Sales</h3>
                                    <p className="text-base text-[#6A6A6A] leading-relaxed">
                                        Instantly reply to comments, Story mentions, and direct messages. Automatically qualify leads from ad campaigns directly in chat.
                                    </p>
                                </div>
                                <div className="bg-[#FFF8E1] rounded-xl p-4 border border-[#E5E5E0] text-[#1A1A1A] text-xs space-y-2">
                                    <div className="bg-white p-2 rounded-lg border border-[#E5E5E0] font-bold">Story Mention Automation</div>
                                    <div className="p-3 rounded-lg bg-[#FFF8E1] border border-[#E8D44D]/20 text-left text-[#1A1A1A]">
                                        "Thanks for tagging us! Here is your exclusive discount code: <span className="font-mono font-bold text-[#C4A83A]">WELCOME20</span>"
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeChannelTab === 'voice' && (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8 items-center text-left">
                                <div className="space-y-4">
                                    <span className="px-2.5 py-1 rounded bg-[#FFF8E1] text-[#C4A83A] text-xs font-semibold">Twilio Telephony & Voice AI</span>
                                    <h3 className="text-2xl font-semibold text-[#1A1A1A]">AI Voice Agents That Answer Calls</h3>
                                    <p className="text-base text-[#6A6A6A] leading-relaxed">
                                        Handle customer phone calls autonomously. Voice agents answer inquiries, book appointments, and sync full transcripts into your CRM.
                                    </p>
                                </div>
                                <div className="bg-[#FFF8E1] rounded-xl p-4 border border-[#E5E5E0] text-[#1A1A1A] text-xs space-y-2">
                                    <div className="flex items-center justify-between border-b border-[#E5E5E0] pb-2">
                                        <span className="font-bold text-[#C4A83A] flex items-center gap-1.5"><PhoneCall className="h-3.5 w-3.5" /> Call Transcript Log</span>
                                        <span className="text-[10px] text-[#6A6A6A]">1m 42s</span>
                                    </div>
                                    <div className="text-left space-y-1.5 font-mono text-[11px] text-[#6A6A6A]">
                                        <p><span className="text-[#C4A83A] font-bold">AI Agent:</span> "Hello, thank you for calling Growbridge. How can I assist you today?"</p>
                                        <p><span className="text-[#1A1A1A] font-bold">Customer:</span> "I'd like to book an onboarding demo for tomorrow."</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeChannelTab === 'messenger' && (
                            <div className="text-center py-6">
                                <h3 className="text-xl font-semibold text-[#1A1A1A] mb-2">Facebook Messenger Suite</h3>
                                <p className="text-base text-[#6A6A6A] max-w-xl mx-auto">
                                    Native support with visual template builders, automated triggers, and real-time webhook sync.
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            </section>

            {/* ── 6. PRICING PREVIEW ── */}
            <section id="pricing" className="py-24 bg-[#FAFAF5] border-t border-[#E5E5E0]">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#FFF8E1] border border-[#E8D44D]/30 text-[#C4A83A] text-xs font-semibold uppercase tracking-wider mb-3">
                        PRICING
                    </div>
                    <h2 className="text-3xl sm:text-4xl font-semibold text-[#1A1A1A]">
                        Simple, Predictable Plans for Every Stage
                    </h2>
                    <p className="mt-3 text-base text-[#6A6A6A] max-w-xl mx-auto">
                        Upgrade, downgrade, or cancel anytime with zero lock-in.
                    </p>

                    {/* Toggle */}
                    <div className="inline-flex items-center p-1 rounded-full bg-[#FFF8E1] border border-[#E5E5E0] mt-8">
                        <button
                            onClick={() => setBillingCycle('monthly')}
                            className={`px-5 py-2 rounded-lg text-xs font-semibold transition-all ${
                                billingCycle === 'monthly' ? 'bg-[#1A1A1A] text-white shadow-sm' : 'text-[#6A6A6A]'
                            }`}
                        >
                            Monthly Billing
                        </button>
                        <button
                            onClick={() => setBillingCycle('yearly')}
                            className={`px-5 py-2 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5 ${
                                billingCycle === 'yearly' ? 'bg-[#1A1A1A] text-white shadow-sm' : 'text-[#6A6A6A]'
                            }`}
                        >
                            <span>Yearly Billing</span>
                            <span className="px-1.5 py-0.5 rounded bg-[#E8D44D]/20 text-[#C4A83A] text-[10px] font-bold">SAVE 20%</span>
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
                                features: ['3 WhatsApp numbers + Instagram + Messenger', 'AI Agent (Knowledge Base + Auto-reply)', 'Twilio Voice Agent Integration', 'Up to 25,000 contacts', 'Unlimited Workflows', 'Priority 24/7 Support'],
                                popular: true,
                            },
                            {
                                name: 'Enterprise',
                                desc: 'Maximum throughput, custom LLM fine-tuning, and dedicated account manager.',
                                monthly: '$199',
                                yearly: '$159',
                                features: ['Unlimited Connected Channels', 'Dedicated AI Voice & Chatbot Instances', 'Unlimited Contacts & Messages', 'Custom Webhooks & REST API v1', 'Dedicated Account Manager'],
                                popular: false,
                            },
                        ].map((plan, i) => (
                            <div
                                key={i}
                                className={`rounded-xl p-8 transition-all relative flex flex-col justify-between bg-white ${
                                    plan.popular
                                        ? 'border-2 border-[#E8D44D] shadow-md'
                                        : 'border border-[#E5E5E0] hover:border-neutral-300'
                                }`}
                            >
                                {plan.popular && (
                                    <div className="absolute -top-3.5 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full bg-[#1A1A1A] text-white text-xs font-semibold uppercase tracking-wider shadow-sm">
                                        Most Popular
                                    </div>
                                )}
                                <div>
                                    <h3 className="text-xl font-semibold text-[#1A1A1A] mb-2">{plan.name}</h3>
                                    <p className="text-sm text-[#6A6A6A] mb-6 leading-relaxed">{plan.desc}</p>
                                    <div className="flex items-baseline gap-1 mb-6">
                                        <span className="text-4xl font-bold text-[#1A1A1A]">
                                            {billingCycle === 'monthly' ? plan.monthly : plan.yearly}
                                        </span>
                                        <span className="text-sm text-[#6A6A6A]">/ month</span>
                                    </div>

                                    <ul className="space-y-3 text-sm text-[#6A6A6A] mb-8">
                                        {plan.features.map((feat, idx) => (
                                            <li key={idx} className="flex items-center gap-2">
                                                <CheckCircle2 className="h-4 w-4 text-[#C4A83A] shrink-0" />
                                                <span>{feat}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>

                                <Link
                                    href={route('register')}
                                    className={`w-full text-center py-3 rounded-lg text-sm font-semibold transition-all ${
                                        plan.popular
                                            ? 'bg-[#1A1A1A] hover:bg-[#333333] text-white shadow-sm'
                                            : 'border border-[#E5E5E0] bg-white hover:bg-neutral-50 text-[#1A1A1A]'
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
            <section id="faq" className="py-24 bg-[#FFF8E1] border-t border-[#E5E5E0]">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center mb-14">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#FFF8E1] border border-[#E8D44D]/30 text-[#C4A83A] text-xs font-semibold uppercase tracking-wider mb-3">
                            FAQ
                        </div>
                        <h2 className="text-3xl font-semibold text-[#1A1A1A]">Frequently Asked Questions</h2>
                    </div>

                    <div className="space-y-3">
                        {[
                            {
                                q: 'Do I need my own WhatsApp Business API account?',
                                a: 'No technical setup required. We connect directly through the official Meta Embedded Signup flow in less than 2 minutes using your Facebook account.',
                            },
                            {
                                q: 'How do the AI Agents work?',
                                a: 'You can upload your company documents, website links, or text instructions. The AI agent indexes your knowledge and responds autonomously across WhatsApp, Instagram, Messenger, and Email.',
                            },
                            {
                                q: 'How does AI Voice Agent calling work?',
                                a: 'Growbridge Connect integrates with Twilio to handle inbound/outbound calls, generate live neural transcripts, summarize intent, and automatically update contact records.',
                            },
                            {
                                q: 'Can I cancel anytime?',
                                a: 'Yes! There are no long-term contracts. You can cancel your subscription with a single click from your workspace settings.',
                            },
                        ].map((faq, idx) => (
                            <div
                                key={idx}
                                className="rounded-xl border border-[#E5E5E0] bg-white overflow-hidden transition-colors"
                            >
                                <button
                                    onClick={() => toggleFaq(idx)}
                                    className="w-full p-5 text-left flex items-center justify-between text-[#1A1A1A] font-semibold text-base"
                                >
                                    <span>{faq.q}</span>
                                    <ChevronDown className={`h-5 w-5 text-[#6A6A6A] transition-transform duration-200 ${openFaq === idx ? 'rotate-180' : ''}`} />
                                </button>
                                {openFaq === idx && (
                                    <div className="px-5 pb-5 text-base text-[#6A6A6A] leading-relaxed border-t border-[#E5E5E0] pt-3">
                                        {faq.a}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── 8. BOTTOM CONVERSION BANNER ── */}
            <section className="py-20 bg-[#FFF8E1]">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="rounded-[24px] border border-[#E5E5E0] bg-[#FAFAF5] p-10 sm:p-14 text-center shadow-sm relative overflow-hidden">
                        <h2 className="text-3xl sm:text-4xl font-semibold text-[#1A1A1A] tracking-tight mb-4">
                            Ready to Transform Your Customer Experience?
                        </h2>
                        <p className="text-base text-[#6A6A6A] max-w-xl mx-auto mb-8 leading-relaxed">
                            Join 50,000+ businesses automating their sales, support, and marketing with Growbridge Connect today.
                        </p>
                        <div className="flex flex-wrap justify-center items-center gap-4">
                            <Link
                                href={route('register')}
                                className="rounded-full bg-[#1A1A1A] hover:bg-[#333333] text-white font-semibold text-base px-8 py-3.5 shadow-sm transition-all flex items-center gap-2 group"
                            >
                                <span>Start 14-Day Free Trial</span>
                                <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition-transform" />
                            </Link>
                            <Link
                                href={route('login')}
                                className="rounded-full border border-[#E5E5E0] bg-white hover:bg-[#FFF8E1] text-[#1A1A1A] font-semibold text-base px-7 py-3.5 transition-all"
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
