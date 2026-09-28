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
            <section className="relative pt-12 pb-20 lg:pt-20 lg:pb-32 bg-[#FAFAF9] overflow-hidden">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
                        {/* Left Column: Value Proposition */}
                        <div className="lg:col-span-5 space-y-6 text-left">
                            {/* Minimal Pill Badge */}
                            <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-blue-200 bg-blue-50/80">
                                <Sparkles className="h-4 w-4 text-[#2563EB]" />
                                <span className="text-xs font-semibold tracking-wide text-[#2563EB]">
                                    AI-Powered Omnichannel Platform
                                </span>
                            </div>

                            {/* Main Headline */}
                            <h1 className="text-4xl sm:text-5xl lg:text-[56px] font-bold tracking-tight text-[#171717] leading-[1.15]">
                                Connect. Automate. <br />
                                Grow Your Business. <br />
                                <span className="text-[#2563EB]">
                                    All in One Platform.
                                </span>
                            </h1>

                            {/* Subtitle */}
                            <p className="text-base sm:text-lg text-[#737373] leading-relaxed max-w-xl">
                                Engage customers across WhatsApp, Instagram, Messenger, Email and Voice with AI-powered automation, smart campaigns, and real-time insights — all in one place.
                            </p>

                            {/* CTA Button Group */}
                            <div className="flex flex-wrap items-center gap-4 pt-2">
                                <Link
                                    href={route('register')}
                                    className="rounded-xl bg-[#2563EB] hover:bg-[#1D4ED8] text-white font-semibold text-base px-7 py-3.5 shadow-sm hover:shadow transition-all duration-200 flex items-center gap-2 group"
                                >
                                    <span>Start 14-Day Free Trial</span>
                                    <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition-transform" />
                                </Link>
                                <a
                                    href="#features"
                                    className="rounded-xl border border-[#E5E5E5] bg-white hover:bg-neutral-50 text-[#171717] font-semibold text-base px-6 py-3.5 transition-all duration-200 flex items-center gap-2 shadow-sm"
                                >
                                    <Calendar className="h-4 w-4 text-[#2563EB]" />
                                    <span>Book a Demo</span>
                                </a>
                            </div>

                            {/* Trust Badges */}
                            <div className="flex flex-wrap items-center gap-6 pt-3 text-xs sm:text-sm text-[#737373]">
                                <div className="flex items-center gap-1.5">
                                    <CheckCircle2 className="h-4 w-4 text-[#2563EB] shrink-0" />
                                    <span>No Credit Card</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <CheckCircle2 className="h-4 w-4 text-[#2563EB] shrink-0" />
                                    <span>Easy Setup</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <CheckCircle2 className="h-4 w-4 text-[#2563EB] shrink-0" />
                                    <span>Cancel Anytime</span>
                                </div>
                            </div>
                        </div>

                        {/* Right Column: Clean Floating Dashboard Preview */}
                        <div className="lg:col-span-7 relative">
                            <div className="rounded-2xl border border-[#E5E5E5] bg-white shadow-xl overflow-hidden text-neutral-900 font-sans flex flex-row select-none">
                                {/* Dashboard Mini Sidebar */}
                                <div className="w-36 sm:w-44 bg-[#FAFAF9] text-[#171717] p-3 flex flex-col justify-between border-r border-[#E5E5E5] shrink-0 hidden sm:flex">
                                    <div className="space-y-4">
                                        <div className="flex items-center gap-2 px-1">
                                            <div className="h-6 w-6 rounded-md bg-[#2563EB] flex items-center justify-center">
                                                <span className="text-xs font-bold text-white">G</span>
                                            </div>
                                            <div className="text-xs font-bold leading-tight text-[#171717]">
                                                Growbridge <br />
                                                <span className="text-[#2563EB] font-medium text-[10px]">Connect</span>
                                            </div>
                                        </div>

                                        <nav className="space-y-1">
                                            <div className="flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-[#2563EB] text-white text-xs font-medium">
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
                                                <div key={i} className="flex items-center gap-2 px-2.5 py-1 rounded-lg text-[#737373] hover:text-[#171717] hover:bg-neutral-200/50 text-[11px] font-normal transition">
                                                    <item.icon className="h-3 w-3" />
                                                    <span>{item.label}</span>
                                                </div>
                                            ))}
                                        </nav>
                                    </div>
                                    <div className="text-[10px] text-[#737373] px-1">Growbridge v1.0</div>
                                </div>

                                {/* Dashboard Main White Canvas Area */}
                                <div className="flex-1 bg-white p-4 sm:p-5 overflow-hidden flex flex-col justify-between">
                                    {/* Top Bar inside mockup */}
                                    <div className="flex items-center justify-between pb-3 border-b border-[#E5E5E5] gap-2">
                                        <div>
                                            <h3 className="text-xs sm:text-sm font-bold text-[#171717]">Dashboard</h3>
                                            <p className="text-[10px] sm:text-xs text-[#737373]">Overview of your active automation channels.</p>
                                        </div>
                                        <div className="flex items-center gap-2 shrink-0">
                                            <span className="text-[10px] text-[#737373] bg-[#FAFAF9] border border-[#E5E5E5] px-2 py-0.5 rounded-md hidden sm:inline-block">
                                                May 2025 ⌄
                                            </span>
                                            <div className="h-5 w-5 rounded-full bg-[#2563EB] text-white font-bold text-[9px] flex items-center justify-center">
                                                J
                                            </div>
                                        </div>
                                    </div>

                                    {/* 6 KPI Cards Grid */}
                                    <div className="grid grid-cols-3 sm:grid-cols-6 gap-2 my-3">
                                        {[
                                            { label: 'Contacts', value: '12,540', delta: '+12.5%', color: 'text-blue-600' },
                                            { label: 'Messages', value: '8,921', delta: '+15.3%', color: 'text-blue-600' },
                                            { label: 'Conversations', value: '86', delta: '+8.7%', color: 'text-emerald-600' },
                                            { label: 'Campaigns', value: '12', delta: '+20.0%', color: 'text-indigo-600' },
                                            { label: 'Automations', value: '24', delta: '+14.3%', color: 'text-blue-600' },
                                            { label: 'AI Chats', value: '43', delta: '+18.6%', color: 'text-teal-600' },
                                        ].map((kpi, idx) => (
                                            <div key={idx} className="bg-[#FAFAF9] border border-[#E5E5E5] rounded-lg p-2 text-left">
                                                <div className="text-[9px] text-[#737373] font-medium truncate">{kpi.label}</div>
                                                <div className="text-xs sm:text-sm font-bold text-[#171717]">{kpi.value}</div>
                                                <div className="text-[8px] font-semibold text-[#2563EB]">↑ {kpi.delta}</div>
                                            </div>
                                        ))}
                                    </div>

                                    {/* Middle 3 Widgets Row */}
                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                        {/* Donut Chart Widget */}
                                        <div className="bg-[#FAFAF9] border border-[#E5E5E5] rounded-lg p-2.5 flex flex-col justify-between">
                                            <div className="text-[10px] font-bold text-[#171717]">Messages by Channel</div>
                                            <div className="flex items-center justify-center py-1.5">
                                                <svg className="h-16 w-16 -rotate-90 transform" viewBox="0 0 36 36">
                                                    <path className="text-neutral-200" strokeWidth="4.5" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                                    <path className="text-blue-600" strokeDasharray="58, 100" strokeWidth="4.5" strokeLinecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                                    <path className="text-[#2563EB]" strokeDashoffset="-58" strokeDasharray="21, 100" strokeWidth="4.5" strokeLinecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                                    <path className="text-sky-400" strokeDashoffset="-79" strokeDasharray="11, 100" strokeWidth="4.5" strokeLinecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                                </svg>
                                            </div>
                                            <div className="grid grid-cols-2 gap-1 text-[8px] text-[#737373]">
                                                <div className="flex items-center gap-1"><span className="h-1.5 w-1.5 rounded-full bg-blue-600" /> WhatsApp (58%)</div>
                                                <div className="flex items-center gap-1"><span className="h-1.5 w-1.5 rounded-full bg-[#2563EB]" /> Messenger (21%)</div>
                                            </div>
                                        </div>

                                        {/* Recent Activity Widget */}
                                        <div className="bg-[#FAFAF9] border border-[#E5E5E5] rounded-lg p-2.5 flex flex-col justify-between">
                                            <div className="text-[10px] font-bold text-[#171717]">Recent Activity</div>
                                            <div className="space-y-1.5 py-1">
                                                <div className="text-[8px] text-[#171717] flex items-center justify-between">
                                                    <span className="truncate">WhatsApp msg from +1 234...</span>
                                                    <span className="text-[7px] text-[#737373] shrink-0">2m</span>
                                                </div>
                                                <div className="text-[8px] text-[#171717] flex items-center justify-between">
                                                    <span className="truncate">AI Agent completed chat</span>
                                                    <span className="text-[7px] text-[#737373] shrink-0">5m</span>
                                                </div>
                                                <div className="text-[8px] text-[#171717] flex items-center justify-between">
                                                    <span className="truncate">Contact added: Sarah J.</span>
                                                    <span className="text-[7px] text-[#737373] shrink-0">1h</span>
                                                </div>
                                            </div>
                                            <div className="text-[8px] text-[#2563EB] font-semibold text-right">View all →</div>
                                        </div>

                                        {/* Top Channels Progress */}
                                        <div className="bg-[#FAFAF9] border border-[#E5E5E5] rounded-lg p-2.5 flex flex-col justify-between">
                                            <div className="text-[10px] font-bold text-[#171717]">Top Channels</div>
                                            <div className="space-y-1.5 py-1 text-[8px]">
                                                <div>
                                                    <div className="flex justify-between text-[#737373] mb-0.5">
                                                        <span>WhatsApp</span>
                                                        <span className="font-bold text-[#171717]">5,216</span>
                                                    </div>
                                                    <div className="w-full bg-neutral-200 rounded-full h-1.5 overflow-hidden">
                                                        <div className="bg-[#2563EB] h-1.5 rounded-full w-[100%]" />
                                                    </div>
                                                </div>
                                                <div>
                                                    <div className="flex justify-between text-[#737373] mb-0.5">
                                                        <span>Messenger</span>
                                                        <span className="font-bold text-[#171717]">1,872</span>
                                                    </div>
                                                    <div className="w-full bg-neutral-200 rounded-full h-1.5 overflow-hidden">
                                                        <div className="bg-sky-500 h-1.5 rounded-full w-[36%]" />
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="text-[8px] text-[#737373] text-right">Updated just now</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ── 2. TRUSTED BY BUSINESSES ── */}
            <section className="border-y border-[#E5E5E5] bg-white py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <p className="text-center text-xs font-semibold uppercase tracking-widest text-[#737373] mb-6">
                        TRUSTED BY BUSINESSES WORLDWIDE
                    </p>
                    <div className="flex flex-wrap items-center justify-center gap-8 md:gap-14 text-[#737373]">
                        <div className="flex items-center gap-2 font-semibold text-sm text-[#171717]">
                            <MessageSquare className="h-4 w-4 text-[#2563EB]" /> WhatsApp Cloud API
                        </div>
                        <div className="flex items-center gap-2 font-semibold text-sm text-[#171717]">
                            <span className="text-[#2563EB]">∞</span> Meta Business Partner
                        </div>
                        <div className="flex items-center gap-2 font-semibold text-sm text-[#171717]">
                            <Sparkles className="h-4 w-4 text-[#2563EB]" /> OpenAI Integration
                        </div>
                        <div className="flex items-center gap-2 font-semibold text-sm text-[#171717]">
                            <PhoneCall className="h-4 w-4 text-[#2563EB]" /> Twilio Voice
                        </div>
                        <div className="px-3 py-1 rounded-full border border-[#E5E5E5] bg-[#FAFAF9] text-[#2563EB] font-semibold text-xs">
                            +50+ Integrations
                        </div>
                    </div>
                </div>
            </section>

            {/* ── 3. POWERFUL FEATURES GRID ── */}
            <section id="features" className="py-24 bg-[#FAFAF9]">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 border border-blue-200 text-[#2563EB] text-xs font-semibold uppercase tracking-wider mb-3">
                        FEATURES
                    </div>
                    <h2 className="text-3xl sm:text-4xl lg:text-5xl font-semibold text-[#171717] tracking-tight">
                        Everything You Need to Engage & Grow
                    </h2>
                    <p className="mt-4 text-base sm:text-lg text-[#737373] max-w-2xl mx-auto leading-relaxed">
                        All the tools you need to manage conversations, automate workflows, and grow your business — in one minimalist platform.
                    </p>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-14 text-left">
                        {[
                            {
                                icon: MessageSquare,
                                title: 'Unified Inbox',
                                desc: 'Manage all conversations from WhatsApp, Instagram, Messenger, Email & more in one shared inbox.',
                            },
                            {
                                icon: Bot,
                                title: 'AI Automation',
                                desc: 'Automate replies, qualify leads, and engage customers 24/7 with intelligent AI agents.',
                            },
                            {
                                icon: Megaphone,
                                title: 'Smart Campaigns',
                                desc: 'Create, schedule and send targeted campaigns across multiple channels with ease.',
                            },
                            {
                                icon: PhoneCall,
                                title: 'AI Voice Agents',
                                desc: 'Deploy AI voice agents with Twilio telephony to handle calls, qualify leads & book appointments.',
                            },
                            {
                                icon: BarChart3,
                                title: 'Analytics & Reports',
                                desc: 'Track performance, monitor conversations and get actionable insights to grow faster.',
                            },
                            {
                                icon: Plug,
                                title: 'API & Integrations',
                                desc: 'Connect your favorite tools and automate with powerful API & webhook support.',
                            },
                        ].map((card, idx) => (
                            <div
                                key={idx}
                                className="rounded-xl border border-[#E5E5E5] bg-white p-7 hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
                            >
                                <div className="h-11 w-11 rounded-lg bg-blue-50 text-[#2563EB] flex items-center justify-center mb-5 group-hover:bg-[#2563EB] group-hover:text-white transition-colors duration-200">
                                    <card.icon className="h-5 w-5" />
                                </div>
                                <h3 className="text-xl font-semibold text-[#171717] mb-2.5">
                                    {card.title}
                                </h3>
                                <p className="text-base text-[#737373] leading-relaxed">
                                    {card.desc}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── 4. STATS COUNTER BAR ── */}
            <section className="py-16 border-y border-[#E5E5E5] bg-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-8 text-center">
                        <div className="flex flex-col items-center">
                            <Users className="h-7 w-7 text-[#2563EB] mb-2" />
                            <div className="text-3xl sm:text-4xl font-bold text-[#171717]">50K+</div>
                            <div className="text-sm text-[#737373] mt-1">Businesses</div>
                        </div>
                        <div className="flex flex-col items-center">
                            <MessageSquare className="h-7 w-7 text-[#2563EB] mb-2" />
                            <div className="text-3xl sm:text-4xl font-bold text-[#171717]">10M+</div>
                            <div className="text-sm text-[#737373] mt-1">Messages</div>
                        </div>
                        <div className="flex flex-col items-center">
                            <ShieldCheck className="h-7 w-7 text-[#2563EB] mb-2" />
                            <div className="text-3xl sm:text-4xl font-bold text-[#171717]">99.9%</div>
                            <div className="text-sm text-[#737373] mt-1">Uptime</div>
                        </div>
                        <div className="flex flex-col items-center">
                            <Headphones className="h-7 w-7 text-[#2563EB] mb-2" />
                            <div className="text-3xl sm:text-4xl font-bold text-[#171717]">24/7</div>
                            <div className="text-sm text-[#737373] mt-1">Support</div>
                        </div>
                        <div className="flex flex-col items-center col-span-2 md:col-span-1">
                            <Calendar className="h-7 w-7 text-[#2563EB] mb-2" />
                            <div className="text-3xl sm:text-4xl font-bold text-[#171717]">14 Days</div>
                            <div className="text-sm text-[#737373] mt-1">Free Trial</div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ── 5. INTERACTIVE CHANNELS SHOWCASE ── */}
            <section id="channels" className="py-24 bg-[#FAFAF9]">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center max-w-3xl mx-auto mb-14">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 border border-blue-200 text-[#2563EB] text-xs font-semibold uppercase tracking-wider mb-3">
                            CHANNELS
                        </div>
                        <h2 className="text-3xl sm:text-4xl font-semibold text-[#171717]">
                            Meet Your Customers Where They Live
                        </h2>
                        <p className="mt-3 text-base text-[#737373]">
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
                                        ? 'bg-[#2563EB] text-white shadow-sm'
                                        : 'bg-white text-[#737373] border border-[#E5E5E5] hover:text-[#171717] hover:bg-neutral-50'
                                }`}
                            >
                                {tab.name}
                            </button>
                        ))}
                    </div>

                    {/* Interactive Tab Visual Box */}
                    <div className="rounded-xl border border-[#E5E5E5] bg-white p-6 sm:p-10 shadow-sm max-w-4xl mx-auto">
                        {activeChannelTab === 'whatsapp' && (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                                <div className="space-y-4 text-left">
                                    <span className="px-2.5 py-1 rounded bg-blue-50 text-[#2563EB] text-xs font-semibold">Official Meta Cloud API</span>
                                    <h3 className="text-2xl font-semibold text-[#171717]">WhatsApp Business at Scale</h3>
                                    <p className="text-base text-[#737373] leading-relaxed">
                                        Send verified interactive broadcast campaigns, automatic appointment reminders, OTPs, and provide 24/7 AI-powered customer support.
                                    </p>
                                    <ul className="space-y-2 text-sm text-[#737373]">
                                        <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#2563EB]" /> Meta approved template synchronization</li>
                                        <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#2563EB]" /> Rich media buttons & interactive lists</li>
                                        <li className="flex items-center gap-2"><Check className="h-4 w-4 text-[#2563EB]" /> Green Tick verification ready</li>
                                    </ul>
                                </div>
                                <div className="bg-[#FAFAF9] rounded-xl p-4 border border-[#E5E5E5] text-[#171717] text-xs space-y-3">
                                    <div className="bg-white p-2.5 rounded-lg border border-[#E5E5E5] flex items-center gap-2">
                                        <div className="h-7 w-7 rounded-md bg-[#2563EB] text-white flex items-center justify-center font-bold">G</div>
                                        <div>
                                            <div className="font-semibold text-xs text-[#171717]">Growbridge Assistant</div>
                                            <div className="text-[10px] text-[#2563EB]">Verified Business Account</div>
                                        </div>
                                    </div>
                                    <div className="bg-blue-50 border border-blue-100 p-3 rounded-xl max-w-[90%] text-left space-y-2 text-[#171717]">
                                        <p>Hello Sarah! Your order #GB-9821 has shipped and is out for delivery today.</p>
                                        <div className="border-t border-blue-200/60 pt-1.5 flex gap-2">
                                            <button className="bg-white px-2 py-1 rounded border border-blue-200 text-[10px] font-semibold text-[#2563EB]">Track Package 📦</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeChannelTab === 'instagram' && (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8 items-center text-left">
                                <div className="space-y-4">
                                    <span className="px-2.5 py-1 rounded bg-blue-50 text-[#2563EB] text-xs font-semibold">Instagram Direct Graph API</span>
                                    <h3 className="text-2xl font-semibold text-[#171717]">Convert DMs into Sales</h3>
                                    <p className="text-base text-[#737373] leading-relaxed">
                                        Instantly reply to comments, Story mentions, and direct messages. Automatically qualify leads from ad campaigns directly in chat.
                                    </p>
                                </div>
                                <div className="bg-[#FAFAF9] rounded-xl p-4 border border-[#E5E5E5] text-[#171717] text-xs space-y-2">
                                    <div className="bg-white p-2 rounded-lg border border-[#E5E5E5] font-bold">Story Mention Automation</div>
                                    <div className="p-3 rounded-lg bg-blue-50 border border-blue-100 text-left text-[#171717]">
                                        "Thanks for tagging us! Here is your exclusive discount code: <span className="font-mono font-bold text-[#2563EB]">WELCOME20</span>"
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeChannelTab === 'voice' && (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8 items-center text-left">
                                <div className="space-y-4">
                                    <span className="px-2.5 py-1 rounded bg-blue-50 text-[#2563EB] text-xs font-semibold">Twilio Telephony & Voice AI</span>
                                    <h3 className="text-2xl font-semibold text-[#171717]">AI Voice Agents That Answer Calls</h3>
                                    <p className="text-base text-[#737373] leading-relaxed">
                                        Handle customer phone calls autonomously. Voice agents answer inquiries, book appointments, and sync full transcripts into your CRM.
                                    </p>
                                </div>
                                <div className="bg-[#FAFAF9] rounded-xl p-4 border border-[#E5E5E5] text-[#171717] text-xs space-y-2">
                                    <div className="flex items-center justify-between border-b border-[#E5E5E5] pb-2">
                                        <span className="font-bold text-[#2563EB] flex items-center gap-1.5"><PhoneCall className="h-3.5 w-3.5" /> Call Transcript Log</span>
                                        <span className="text-[10px] text-[#737373]">1m 42s</span>
                                    </div>
                                    <div className="text-left space-y-1.5 font-mono text-[11px] text-[#737373]">
                                        <p><span className="text-[#2563EB] font-bold">AI Agent:</span> "Hello, thank you for calling Growbridge. How can I assist you today?"</p>
                                        <p><span className="text-[#171717] font-bold">Customer:</span> "I'd like to book an onboarding demo for tomorrow."</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeChannelTab === 'messenger' && (
                            <div className="text-center py-6">
                                <h3 className="text-xl font-semibold text-[#171717] mb-2">Facebook Messenger Suite</h3>
                                <p className="text-base text-[#737373] max-w-xl mx-auto">
                                    Native support with visual template builders, automated triggers, and real-time webhook sync.
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            </section>

            {/* ── 6. PRICING PREVIEW ── */}
            <section id="pricing" className="py-24 bg-white border-t border-[#E5E5E5]">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 border border-blue-200 text-[#2563EB] text-xs font-semibold uppercase tracking-wider mb-3">
                        PRICING
                    </div>
                    <h2 className="text-3xl sm:text-4xl font-semibold text-[#171717]">
                        Simple, Predictable Plans for Every Stage
                    </h2>
                    <p className="mt-3 text-base text-[#737373] max-w-xl mx-auto">
                        Upgrade, downgrade, or cancel anytime with zero lock-in.
                    </p>

                    {/* Toggle */}
                    <div className="inline-flex items-center p-1 rounded-xl bg-[#FAFAF9] border border-[#E5E5E5] mt-8">
                        <button
                            onClick={() => setBillingCycle('monthly')}
                            className={`px-5 py-2 rounded-lg text-xs font-semibold transition-all ${
                                billingCycle === 'monthly' ? 'bg-[#2563EB] text-white shadow-sm' : 'text-[#737373]'
                            }`}
                        >
                            Monthly Billing
                        </button>
                        <button
                            onClick={() => setBillingCycle('yearly')}
                            className={`px-5 py-2 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5 ${
                                billingCycle === 'yearly' ? 'bg-[#2563EB] text-white shadow-sm' : 'text-[#737373]'
                            }`}
                        >
                            <span>Yearly Billing</span>
                            <span className="px-1.5 py-0.5 rounded bg-blue-100 text-[#2563EB] text-[10px] font-bold">SAVE 20%</span>
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
                                        ? 'border-2 border-[#2563EB] shadow-md'
                                        : 'border border-[#E5E5E5] hover:border-neutral-300'
                                }`}
                            >
                                {plan.popular && (
                                    <div className="absolute -top-3.5 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full bg-[#2563EB] text-white text-xs font-semibold uppercase tracking-wider shadow-sm">
                                        Most Popular
                                    </div>
                                )}
                                <div>
                                    <h3 className="text-xl font-semibold text-[#171717] mb-2">{plan.name}</h3>
                                    <p className="text-sm text-[#737373] mb-6 leading-relaxed">{plan.desc}</p>
                                    <div className="flex items-baseline gap-1 mb-6">
                                        <span className="text-4xl font-bold text-[#171717]">
                                            {billingCycle === 'monthly' ? plan.monthly : plan.yearly}
                                        </span>
                                        <span className="text-sm text-[#737373]">/ month</span>
                                    </div>

                                    <ul className="space-y-3 text-sm text-[#737373] mb-8">
                                        {plan.features.map((feat, idx) => (
                                            <li key={idx} className="flex items-center gap-2">
                                                <CheckCircle2 className="h-4 w-4 text-[#2563EB] shrink-0" />
                                                <span>{feat}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>

                                <Link
                                    href={route('register')}
                                    className={`w-full text-center py-3 rounded-lg text-sm font-semibold transition-all ${
                                        plan.popular
                                            ? 'bg-[#2563EB] hover:bg-[#1D4ED8] text-white shadow-sm'
                                            : 'border border-[#E5E5E5] bg-white hover:bg-neutral-50 text-[#171717]'
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
            <section id="faq" className="py-24 bg-[#FAFAF9] border-t border-[#E5E5E5]">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center mb-14">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 border border-blue-200 text-[#2563EB] text-xs font-semibold uppercase tracking-wider mb-3">
                            FAQ
                        </div>
                        <h2 className="text-3xl font-semibold text-[#171717]">Frequently Asked Questions</h2>
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
                                className="rounded-xl border border-[#E5E5E5] bg-white overflow-hidden transition-colors"
                            >
                                <button
                                    onClick={() => toggleFaq(idx)}
                                    className="w-full p-5 text-left flex items-center justify-between text-[#171717] font-semibold text-base"
                                >
                                    <span>{faq.q}</span>
                                    <ChevronDown className={`h-5 w-5 text-[#737373] transition-transform duration-200 ${openFaq === idx ? 'rotate-180' : ''}`} />
                                </button>
                                {openFaq === idx && (
                                    <div className="px-5 pb-5 text-base text-[#737373] leading-relaxed border-t border-[#E5E5E5] pt-3">
                                        {faq.a}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── 8. BOTTOM CONVERSION BANNER ── */}
            <section className="py-20 bg-[#FAFAF9]">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="rounded-2xl border border-[#E5E5E5] bg-white p-10 sm:p-14 text-center shadow-sm relative overflow-hidden">
                        <h2 className="text-3xl sm:text-4xl font-semibold text-[#171717] tracking-tight mb-4">
                            Ready to Transform Your Customer Experience?
                        </h2>
                        <p className="text-base text-[#737373] max-w-xl mx-auto mb-8 leading-relaxed">
                            Join 50,000+ businesses automating their sales, support, and marketing with Growbridge Connect today.
                        </p>
                        <div className="flex flex-wrap justify-center items-center gap-4">
                            <Link
                                href={route('register')}
                                className="rounded-xl bg-[#2563EB] hover:bg-[#1D4ED8] text-white font-semibold text-base px-8 py-3.5 shadow-sm transition-all flex items-center gap-2 group"
                            >
                                <span>Start 14-Day Free Trial</span>
                                <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition-transform" />
                            </Link>
                            <Link
                                href={route('login')}
                                className="rounded-xl border border-[#E5E5E5] bg-white hover:bg-neutral-50 text-[#171717] font-semibold text-base px-7 py-3.5 transition-all"
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
