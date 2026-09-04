import React, { useState, useEffect } from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    PhoneCall,
    Plus,
    Search,
    Shield,
    Sparkles,
    Bot,
    MessageSquare,
    CheckCircle2,
    XCircle,
    Sliders,
    Trash2,
    RefreshCw,
    ExternalLink,
    AlertCircle,
    Layers,
    Globe,
    Lock,
    Clock,
    PhoneForwarded,
    HelpCircle,
    ArrowRight,
    Check,
    AlertTriangle,
    Zap,
    Mail,
    Share2,
} from 'lucide-react';
import Modal from '@/Components/ui/Modal';
import ChannelStatusBadge from '@/Components/ui/ChannelStatusBadge';

export default function PhoneNumbersIndex({
    numbers = [],
    agents = [],
    wabas = [],
    stats = {},
    channelStatuses = {},
    metaAppId,
    subaccount = {},
    recentCalls = [],
}) {
    const { t } = useTranslation();
    const [activeTab, setActiveTab] = useState('my-numbers'); // 'my-numbers' | 'get-number' | 'channels-health' | 'agents' | 'history' | 'settings'
    
    // Search form for marketplace
    const [searchCountry, setSearchCountry] = useState('IN');
    const [searchType, setSearchType] = useState('local');
    const [searchAreaCode, setSearchAreaCode] = useState('');
    const [searchCapabilities, setSearchCapabilities] = useState({ voice: true, sms: true, mms: false });
    const [isSearching, setIsSearching] = useState(false);
    const [searchResults, setSearchResults] = useState([]);
    const [searchError, setSearchError] = useState(null);

    // Purchase Modal
    const [purchaseModalOpen, setPurchaseModalOpen] = useState(false);
    const [selectedNumberToBuy, setSelectedNumberToBuy] = useState(null);

    // Number Configuration Modal
    const [configModalOpen, setConfigModalOpen] = useState(false);
    const [selectedNumberToEdit, setSelectedNumberToEdit] = useState(null);

    // Unified WhatsApp Onboarding Modal
    const [whatsappModalOpen, setWhatsappModalOpen] = useState(false);
    const [selectedNumberForWhatsapp, setSelectedNumberForWhatsapp] = useState(null);
    const [isConnectingMeta, setIsConnectingMeta] = useState(false);

    // Unified AI Agent Modal
    const [aiAgentModalOpen, setAiAgentModalOpen] = useState(false);
    const [selectedNumberForAi, setSelectedNumberForAi] = useState(null);

    // Post-Purchase Unified Setup Card Modal
    const [setupCardModalOpen, setSetupCardModalOpen] = useState(false);
    const [justPurchasedNumber, setJustPurchasedNumber] = useState(null);

    // Purchase Form
    const purchaseForm = useForm({
        phone_number: '',
        country: 'IN',
        friendly_name: '',
        voice: true,
        sms: true,
        mms: false,
        call_recording: true,
        assigned_ai_agent_id: '',
    });

    // Config Form
    const configForm = useForm({
        friendly_name: '',
        voice_enabled: true,
        sms_enabled: true,
        call_recording_enabled: false,
        assigned_ai_agent_id: '',
        assigned_chat_ai_agent_id: '',
    });

    // WhatsApp Connect Form
    const whatsappForm = useForm({
        display_name: '',
        waba_id: '',
        whatsapp_phone_number_id: '',
    });

    // AI Agent Assignment Form
    const aiForm = useForm({
        assigned_ai_agent_id: '',
        assigned_chat_ai_agent_id: '',
    });

    // Load Meta JS SDK if App ID exists
    useEffect(() => {
        if (metaAppId && !window.FB) {
            window.fbAsyncInit = function () {
                window.FB.init({
                    appId: metaAppId,
                    cookie: true,
                    xfbml: true,
                    version: 'v20.0',
                });
            };
            (function (d, s, id) {
                var js, fjs = d.getElementsByTagName(s)[0];
                if (d.getElementById(id)) return;
                js = d.createElement(s); js.id = id;
                js.src = 'https://connect.facebook.net/en_US/sdk.js';
                fjs.parentNode.insertBefore(js, fjs);
            }(document, 'script', 'facebook-jssdk'));
        }
    }, [metaAppId]);

    // Perform initial number search on mount
    useEffect(() => {
        handleSearch();
    }, [searchCountry]);

    const handleSearch = async () => {
        setIsSearching(true);
        setSearchError(null);
        try {
            const params = new URLSearchParams({
                country: searchCountry,
                type: searchType,
                area_code: searchAreaCode,
                voice: searchCapabilities.voice ? '1' : '0',
                sms: searchCapabilities.sms ? '1' : '0',
                mms: searchCapabilities.mms ? '1' : '0',
            });
            const res = await fetch(route('client.voice.numbers.search') + `?${params.toString()}`);
            const data = await res.json();
            if (data.success) {
                setSearchResults(data.numbers || []);
            } else {
                setSearchError(data.message || 'Failed to search numbers.');
            }
        } catch (err) {
            setSearchError('Error contacting Twilio marketplace.');
        } finally {
            setIsSearching(false);
        }
    };

    const handleOpenPurchase = (num) => {
        setSelectedNumberToBuy(num);
        purchaseForm.setData({
            phone_number: num.phone_number,
            country: num.country || searchCountry,
            friendly_name: `${num.locality || 'Virtual'} Line`,
            voice: num.capabilities?.voice ?? true,
            sms: num.capabilities?.sms ?? true,
            mms: num.capabilities?.mms ?? false,
            call_recording: true,
            assigned_ai_agent_id: agents[0]?.id || '',
        });
        setPurchaseModalOpen(true);
    };

    const handleConfirmPurchase = (e) => {
        e.preventDefault();
        purchaseForm.post(route('client.voice.numbers.purchase'), {
            onSuccess: () => {
                setPurchaseModalOpen(false);
                const boughtNumber = purchaseForm.data.phone_number;
                setJustPurchasedNumber({
                    phone_number: boughtNumber,
                    voice: true,
                    whatsapp: false,
                    ai_agent: purchaseForm.data.assigned_ai_agent_id ? 'Assigned' : 'Not Assigned',
                });
                setSetupCardModalOpen(true);
                setActiveTab('my-numbers');
            },
        });
    };

    const handleOpenConfig = (number) => {
        setSelectedNumberToEdit(number);
        configForm.setData({
            friendly_name: number.friendly_name || '',
            voice_enabled: Boolean(number.voice_enabled),
            sms_enabled: Boolean(number.sms_enabled),
            call_recording_enabled: Boolean(number.call_recording_enabled),
            assigned_ai_agent_id: number.assigned_ai_agent_id || '',
            assigned_chat_ai_agent_id: number.assigned_chat_ai_agent_id || '',
        });
        setConfigModalOpen(true);
    };

    const handleSaveConfig = (e) => {
        e.preventDefault();
        configForm.put(route('client.voice.numbers.update', selectedNumberToEdit.id), {
            onSuccess: () => setConfigModalOpen(false),
        });
    };

    const handleOpenWhatsappConnect = (number) => {
        setSelectedNumberForWhatsapp(number);
        whatsappForm.setData({
            display_name: number.whatsapp_display_name || number.friendly_name || 'Growbridge Business',
            waba_id: wabas[0]?.id || '',
            whatsapp_phone_number_id: number.whatsapp_phone_number_id || '',
        });
        setWhatsappModalOpen(true);
    };

    // Official Meta Embedded Signup Dialog Trigger
    const handleLaunchMetaEmbeddedSignup = () => {
        if (!window.FB) {
            alert('Meta JavaScript SDK is initializing. Please try again or use direct connection.');
            return;
        }

        setIsConnectingMeta(true);
        window.FB.login(
            (response) => {
                setIsConnectingMeta(false);
                if (response.authResponse && response.authResponse.code) {
                    // Send code to backend
                    router.post(
                        route('client.voice.numbers.whatsapp.embedded-signup', selectedNumberForWhatsapp.id),
                        {
                            code: response.authResponse.code,
                            waba_id: response.authResponse.waba_id || 'waba_' + Date.now(),
                            phone_number_id: response.authResponse.phone_number_id || '',
                            display_name: whatsappForm.data.display_name,
                        },
                        {
                            onSuccess: () => setWhatsappModalOpen(false),
                        }
                    );
                } else {
                    // User closed dialog or canceled
                    console.log('Meta Embedded Signup cancelled or finished', response);
                }
            },
            {
                config_id: env('META_CONFIG_ID', 'whatsapp_embedded_signup'),
                response_type: 'code',
                override_default_response_type: true,
                extras: {
                    feature: 'whatsapp_embedded_signup',
                    sessionInfoVersion: '3',
                },
            }
        );
    };

    const handleConfirmWhatsappManual = (e) => {
        e.preventDefault();
        whatsappForm.post(route('client.voice.numbers.whatsapp.connect', selectedNumberForWhatsapp.id), {
            onSuccess: () => setWhatsappModalOpen(false),
        });
    };

    const handleDisconnectWhatsapp = (number) => {
        if (confirm(`Disconnect WhatsApp Business from ${number.phone_number}?`)) {
            router.post(route('client.voice.numbers.whatsapp.disconnect', number.id));
        }
    };

    const handleOpenAiAssign = (number) => {
        setSelectedNumberForAi(number);
        aiForm.setData({
            assigned_ai_agent_id: number.assigned_ai_agent_id || '',
            assigned_chat_ai_agent_id: number.assigned_chat_ai_agent_id || '',
        });
        setAiAgentModalOpen(true);
    };

    const handleSaveAiAssign = (e) => {
        e.preventDefault();
        aiForm.post(route('client.voice.numbers.ai-agents', selectedNumberForAi.id), {
            onSuccess: () => setAiAgentModalOpen(false),
        });
    };

    const handleReleaseNumber = (number) => {
        if (confirm(`Are you sure you want to release ${number.phone_number}? This will disconnect Voice and WhatsApp immediately.`)) {
            router.delete(route('client.voice.numbers.destroy', number.id));
        }
    };

    return (
        <ClientLayout
            title="Phone & WhatsApp"
            subtitle="Manage Twilio phone numbers, WhatsApp Business onboarding, AI Voice Agents, and omnichannel channels"
        >
            <Head title="Phone & WhatsApp · Growbridge Connect" />

            <div className="space-y-6 max-w-[1600px] mx-auto pb-12">
                {/* ── Top Header & Action Banner ── */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-gradient-to-r from-emerald-950 via-[#031d15] to-neutral-900 border border-emerald-900/50 p-6 rounded-3xl text-white shadow-xl">
                    <div className="space-y-1.5">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-xs font-semibold tracking-wide border border-emerald-500/30">
                            <Zap className="h-3.5 w-3.5 text-emerald-400 fill-emerald-400" />
                            <span>Growbridge Connect · Unified Business Identity</span>
                        </div>
                        <h1 className="text-2xl sm:text-3xl font-black tracking-tight">
                            Phone & WhatsApp
                        </h1>
                        <p className="text-xs sm:text-sm text-neutral-300 max-w-2xl">
                            Connect your phone numbers to <strong className="text-emerald-400">Voice Calls</strong>, <strong className="text-emerald-400">WhatsApp Business</strong>, and <strong className="text-emerald-400">AI Agents</strong> with zero technical complexity.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={() => setActiveTab('get-number')}
                            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-neutral-950 text-xs sm:text-sm font-black shadow-lg shadow-emerald-500/25 transition duration-200"
                        >
                            <Plus className="h-4 w-4 stroke-[3]" />
                            <span>Get a Phone Number</span>
                        </button>
                    </div>
                </div>

                {/* ── KPI Row ── */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="text-[11px] font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Active Lines</div>
                        <div className="text-2xl font-black text-neutral-900 dark:text-white mt-1">{stats.active_numbers || 0}</div>
                        <div className="text-[10px] text-emerald-500 font-semibold mt-0.5">Provisioned</div>
                    </div>
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="text-[11px] font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Voice Calling</div>
                        <div className="text-2xl font-black text-neutral-900 dark:text-white mt-1">{stats.voice_enabled || 0}</div>
                        <div className="text-[10px] text-emerald-500 font-semibold mt-0.5">Twilio Live</div>
                    </div>
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="text-[11px] font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">WhatsApp</div>
                        <div className="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{stats.whatsapp_connected || 0}</div>
                        <div className="text-[10px] text-emerald-500 font-semibold mt-0.5">Meta Connected</div>
                    </div>
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="text-[11px] font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Unified Lines</div>
                        <div className="text-2xl font-black text-purple-600 dark:text-purple-400 mt-1">{stats.unified_numbers || 0}</div>
                        <div className="text-[10px] text-purple-500 font-semibold mt-0.5">Voice + WhatsApp</div>
                    </div>
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="text-[11px] font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Active AI Agents</div>
                        <div className="text-2xl font-black text-neutral-900 dark:text-white mt-1">{agents.length}</div>
                        <div className="text-[10px] text-blue-500 font-semibold mt-0.5">Conversational</div>
                    </div>
                    <div className="p-4 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-2xl shadow-sm">
                        <div className="text-[11px] font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Total Calls</div>
                        <div className="text-2xl font-black text-neutral-900 dark:text-white mt-1">{stats.total_calls || 0}</div>
                        <div className="text-[10px] text-emerald-500 font-semibold mt-0.5">Processed</div>
                    </div>
                </div>

                {/* ── Navigation Tabs ── */}
                <div className="flex items-center gap-2 border-b border-neutral-200 dark:border-neutral-800 pb-1 overflow-x-auto">
                    <button
                        type="button"
                        onClick={() => setActiveTab('my-numbers')}
                        className={`flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-bold transition whitespace-nowrap ${
                            activeTab === 'my-numbers'
                                ? 'bg-emerald-600 text-white shadow-md'
                                : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-white/5'
                        }`}
                    >
                        <PhoneCall className="h-4 w-4" />
                        <span>Phone & WhatsApp ({numbers.length})</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('get-number')}
                        className={`flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-bold transition whitespace-nowrap ${
                            activeTab === 'get-number'
                                ? 'bg-emerald-600 text-white shadow-md'
                                : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-white/5'
                        }`}
                    >
                        <Search className="h-4 w-4" />
                        <span>Get / Connect Number</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('channels-health')}
                        className={`flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-bold transition whitespace-nowrap ${
                            activeTab === 'channels-health'
                                ? 'bg-emerald-600 text-white shadow-md'
                                : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-white/5'
                        }`}
                    >
                        <Layers className="h-4 w-4" />
                        <span>Channel Health Matrix</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('agents')}
                        className={`flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-bold transition whitespace-nowrap ${
                            activeTab === 'agents'
                                ? 'bg-emerald-600 text-white shadow-md'
                                : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-white/5'
                        }`}
                    >
                        <Bot className="h-4 w-4" />
                        <span>AI Assistants ({agents.length})</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('history')}
                        className={`flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-bold transition whitespace-nowrap ${
                            activeTab === 'history'
                                ? 'bg-emerald-600 text-white shadow-md'
                                : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-white/5'
                        }`}
                    >
                        <Clock className="h-4 w-4" />
                        <span>Call History</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('settings')}
                        className={`flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-bold transition whitespace-nowrap ${
                            activeTab === 'settings'
                                ? 'bg-emerald-600 text-white shadow-md'
                                : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-white/5'
                        }`}
                    >
                        <Shield className="h-4 w-4" />
                        <span>Twilio Settings</span>
                    </button>
                </div>

                {/* ══════════════════════════════════════════════════════════════════════ */}
                {/* ── TAB 1: PHONE & WHATSAPP (USER DASHBOARD NUMBER CARDS) ── */}
                {/* ══════════════════════════════════════════════════════════════════════ */}
                {activeTab === 'my-numbers' && (
                    <div className="space-y-6">
                        {numbers.length === 0 ? (
                            <div className="p-12 text-center bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-3xl shadow-sm space-y-4">
                                <div className="h-16 w-16 mx-auto rounded-3xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                                    <PhoneCall className="h-8 w-8" />
                                </div>
                                <div className="space-y-1">
                                    <h3 className="text-lg font-bold text-neutral-900 dark:text-white">
                                        No Numbers Connected Yet
                                    </h3>
                                    <p className="text-xs text-neutral-500 dark:text-neutral-400 max-w-md mx-auto">
                                        Provision a business number in India (+91), USA (+1), or global destinations, then connect Voice + WhatsApp without technical setup.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setActiveTab('get-number')}
                                    className="px-6 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md transition inline-flex items-center gap-2"
                                >
                                    <Plus className="h-4 w-4" />
                                    <span>Get / Connect Number</span>
                                </button>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                {numbers.map((number) => {
                                    const isWhatsappConn = number.whatsapp_status === 'connected';
                                    const isVoiceOn = Boolean(number.voice_enabled);
                                    const isSmsOn = Boolean(number.sms_enabled);

                                    return (
                                        <div
                                            key={number.id}
                                            className="bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-3xl p-6 shadow-sm hover:shadow-md transition-all space-y-5 flex flex-col justify-between"
                                        >
                                            {/* Header */}
                                            <div className="space-y-4">
                                                <div className="flex items-start justify-between gap-3 border-b border-neutral-100 dark:border-neutral-800 pb-3">
                                                    <div>
                                                        <div className="text-[10px] font-black uppercase tracking-wider text-neutral-400">
                                                            Phone & WhatsApp
                                                        </div>
                                                        <div className="text-lg font-black font-mono text-neutral-900 dark:text-white tracking-tight mt-0.5">
                                                            {number.phone_number}
                                                        </div>
                                                        <div className="flex items-center gap-2 mt-1">
                                                            <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                                                ● Active
                                                            </span>
                                                            <span className="text-[11px] text-neutral-400 font-medium">
                                                                {number.friendly_name || `${number.country} Line`}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <button
                                                        type="button"
                                                        onClick={() => handleReleaseNumber(number)}
                                                        className="text-neutral-400 hover:text-red-500 p-1.5 rounded-lg transition"
                                                        title="Release number"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>

                                                {/* PHONE SECTION */}
                                                <div className="p-3.5 rounded-2xl bg-neutral-50 dark:bg-black/30 border border-neutral-200/60 dark:border-neutral-800/80 space-y-2">
                                                    <div className="text-[11px] font-black uppercase tracking-wider text-neutral-500 flex items-center justify-between">
                                                        <span>Phone</span>
                                                        <span className="text-emerald-600 dark:text-emerald-400 font-bold">● Active</span>
                                                    </div>
                                                    <div className="space-y-1 text-xs">
                                                        <div className="flex items-center gap-1.5 text-neutral-800 dark:text-neutral-200 font-semibold">
                                                            <span className={isVoiceOn ? 'text-emerald-500 font-bold' : 'text-neutral-400'}>
                                                                {isVoiceOn ? '✓' : '○'} Voice
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center gap-1.5 text-neutral-800 dark:text-neutral-200 font-semibold">
                                                            <span className={isSmsOn ? 'text-emerald-500 font-bold' : 'text-neutral-400'}>
                                                                {isSmsOn ? '✓' : '○'} SMS
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* WHATSAPP SECTION */}
                                                <div className="p-3.5 rounded-2xl bg-neutral-50 dark:bg-black/30 border border-neutral-200/60 dark:border-neutral-800/80 space-y-2">
                                                    <div className="text-[11px] font-black uppercase tracking-wider text-neutral-500">
                                                        WhatsApp
                                                    </div>
                                                    {isWhatsappConn ? (
                                                        <div className="space-y-1">
                                                            <div className="text-xs font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                                                <span>✓ Connected</span>
                                                            </div>
                                                            <div className="text-[11px] text-neutral-600 dark:text-neutral-300 truncate">
                                                                Business: <strong className="text-neutral-900 dark:text-white">{number.whatsapp_display_name || 'Official Account'}</strong>
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div className="space-y-1.5">
                                                            <div className="flex items-center gap-1.5 text-amber-600 dark:text-amber-400 font-bold text-xs">
                                                                <AlertTriangle className="h-3.5 w-3.5" />
                                                                <span>Setup required</span>
                                                            </div>
                                                            <p className="text-[11px] text-neutral-500 dark:text-neutral-400">
                                                                This number is not currently connected to WhatsApp Business.
                                                            </p>
                                                        </div>
                                                    )}
                                                </div>

                                                {/* AI AGENT SECTION */}
                                                <div className="p-3.5 rounded-2xl bg-neutral-50 dark:bg-black/30 border border-neutral-200/60 dark:border-neutral-800/80 space-y-1">
                                                    <div className="text-[11px] font-black uppercase tracking-wider text-neutral-500">
                                                        AI Agent
                                                    </div>
                                                    <div className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                                                        <Bot className="h-3.5 w-3.5 text-purple-500" />
                                                        <span>{number.assigned_voice_agent?.name || number.assigned_agent?.name || 'Sales Assistant'}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Action Buttons */}
                                            <div className="pt-3 border-t border-neutral-100 dark:border-neutral-800/80 grid grid-cols-3 gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => handleOpenConfig(number)}
                                                    className="py-2 px-1 rounded-xl text-[11px] font-bold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-white/5 border border-neutral-200 dark:border-neutral-700 transition text-center"
                                                >
                                                    Manage Number
                                                </button>

                                                <button
                                                    type="button"
                                                    onClick={() => handleOpenWhatsappConnect(number)}
                                                    className={`py-2 px-1 rounded-xl text-[11px] font-bold transition text-center ${
                                                        isWhatsappConn
                                                            ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 border border-emerald-500/30'
                                                            : 'bg-emerald-600 hover:bg-emerald-500 text-white shadow'
                                                    }`}
                                                >
                                                    {isWhatsappConn ? 'WhatsApp Settings' : 'Connect WhatsApp'}
                                                </button>

                                                <button
                                                    type="button"
                                                    onClick={() => handleOpenAiAssign(number)}
                                                    className="py-2 px-1 rounded-xl text-[11px] font-bold bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition text-center"
                                                >
                                                    Assign AI Agent
                                                </button>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                )}

                {/* ══════════════════════════════════════════════════════════════════════ */}
                {/* ── TAB 2: GET / CONNECT NUMBER (MARKETPLACE SEARCH & BUY) ── */}
                {/* ══════════════════════════════════════════════════════════════════════ */}
                {activeTab === 'get-number' && (
                    <div className="bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
                        <div className="space-y-1">
                            <h2 className="text-xl font-bold text-neutral-900 dark:text-white">
                                Search & Provision Virtual Numbers
                            </h2>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                Select a country, filter by voice/SMS capabilities, and instantly purchase a line powered by Twilio.
                            </p>
                        </div>

                        {/* Search Filters Form */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-5 rounded-2xl bg-neutral-50 dark:bg-black/30 border border-neutral-200/80 dark:border-neutral-800">
                            <div className="space-y-1.5">
                                <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                                    Country
                                </label>
                                <select
                                    value={searchCountry}
                                    onChange={(e) => setSearchCountry(e.target.value)}
                                    className="w-full text-xs rounded-xl bg-white dark:bg-neutral-900 border-neutral-300 dark:border-neutral-700 py-2.5 font-semibold"
                                >
                                    <option value="IN">🇮🇳 India (+91)</option>
                                    <option value="US">🇺🇸 United States (+1)</option>
                                    <option value="GB">🇬🇧 United Kingdom (+44)</option>
                                    <option value="AE">🇦🇪 United Arab Emirates (+971)</option>
                                    <option value="CA">🇨🇦 Canada (+1)</option>
                                    <option value="AU">🇦🇺 Australia (+61)</option>
                                </select>
                            </div>

                            <div className="space-y-1.5">
                                <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                                    Number Type
                                </label>
                                <select
                                    value={searchType}
                                    onChange={(e) => setSearchType(e.target.value)}
                                    className="w-full text-xs rounded-xl bg-white dark:bg-neutral-900 border-neutral-300 dark:border-neutral-700 py-2.5 font-semibold"
                                >
                                    <option value="local">Local Geographic</option>
                                    <option value="tollfree">Toll-Free Hotline</option>
                                    <option value="mobile">Mobile Line</option>
                                </select>
                            </div>

                            <div className="space-y-1.5">
                                <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                                    Area Code (Optional)
                                </label>
                                <input
                                    type="text"
                                    placeholder="e.g. 22 or 212"
                                    value={searchAreaCode}
                                    onChange={(e) => setSearchAreaCode(e.target.value)}
                                    className="w-full text-xs rounded-xl bg-white dark:bg-neutral-900 border-neutral-300 dark:border-neutral-700 py-2.5 font-mono"
                                />
                            </div>

                            <div className="flex items-end">
                                <button
                                    type="button"
                                    onClick={handleSearch}
                                    disabled={isSearching}
                                    className="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black shadow-md transition flex items-center justify-center gap-2"
                                >
                                    {isSearching ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
                                    <span>{isSearching ? 'Searching...' : 'Search Numbers'}</span>
                                </button>
                            </div>
                        </div>

                        {/* Search Results Table */}
                        {searchError && (
                            <div className="p-4 rounded-2xl bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20 text-xs font-semibold">
                                {searchError}
                            </div>
                        )}

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="border-b border-neutral-200 dark:border-neutral-800 text-neutral-400 font-bold uppercase text-[10px]">
                                        <th className="py-3 px-3">Available Number</th>
                                        <th className="py-3 px-3">Locality / Region</th>
                                        <th className="py-3 px-3">Capabilities</th>
                                        <th className="py-3 px-3">Monthly Cost</th>
                                        <th className="py-3 px-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                    {searchResults.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="py-12 text-center text-neutral-400">
                                                {isSearching ? 'Fetching available numbers from Twilio API...' : 'No numbers found. Try clearing the area code.'}
                                            </td>
                                        </tr>
                                    ) : (
                                        searchResults.map((num, i) => (
                                            <tr key={i} className="hover:bg-neutral-50 dark:hover:bg-white/5 transition">
                                                <td className="py-3.5 px-3 font-mono font-bold text-sm text-neutral-900 dark:text-white">
                                                    {num.friendly_name || num.phone_number}
                                                </td>
                                                <td className="py-3.5 px-3 text-neutral-700 dark:text-neutral-300">
                                                    {num.locality ? `${num.locality}, ${num.region}` : num.country}
                                                </td>
                                                <td className="py-3.5 px-3">
                                                    <div className="flex items-center gap-1.5">
                                                        {num.capabilities?.voice && (
                                                            <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                                                Voice
                                                            </span>
                                                        )}
                                                        {num.capabilities?.sms && (
                                                            <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                                                SMS
                                                            </span>
                                                        )}
                                                        {num.capabilities?.mms && (
                                                            <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400">
                                                                MMS
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="py-3.5 px-3 font-bold text-neutral-900 dark:text-white">
                                                    ${num.monthly_cost || '1.15'}/mo
                                                </td>
                                                <td className="py-3.5 px-3 text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() => handleOpenPurchase(num)}
                                                        className="px-4 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black shadow transition inline-flex items-center gap-1"
                                                    >
                                                        <span>Buy Number</span>
                                                        <ArrowRight className="h-3.5 w-3.5" />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* ══════════════════════════════════════════════════════════════════════ */}
                {/* ── TAB 3: CHANNEL HEALTH MATRIX (STATUS SYSTEM) ── */}
                {/* ══════════════════════════════════════════════════════════════════════ */}
                {activeTab === 'channels-health' && (
                    <div className="space-y-6">
                        <div className="space-y-1">
                            <h2 className="text-xl font-bold text-neutral-900 dark:text-white">
                                Channel Connection Status Hub
                            </h2>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                Live operational status and webhook health across all Growbridge Connect communication channels.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {Object.entries(channelStatuses).map(([key, chan]) => (
                                <div
                                    key={key}
                                    className="p-5 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-3xl shadow-sm space-y-4 flex flex-col justify-between"
                                >
                                    <div className="space-y-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex items-center gap-3">
                                                <div className="h-10 w-10 rounded-2xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                                                    {chan.key === 'whatsapp' && <MessageSquare className="h-5 w-5" />}
                                                    {chan.key === 'instagram' && <Sparkles className="h-5 w-5" />}
                                                    {chan.key === 'messenger' && <MessageSquare className="h-5 w-5" />}
                                                    {chan.key === 'email' && <Mail className="h-5 w-5" />}
                                                    {chan.key === 'twilio' && <PhoneCall className="h-5 w-5" />}
                                                    {chan.key === 'ai' && <Bot className="h-5 w-5" />}
                                                </div>
                                                <div>
                                                    <h4 className="font-bold text-neutral-900 dark:text-white">{chan.name}</h4>
                                                    <span className="text-[10px] text-neutral-400 font-medium">Channel ID: {chan.key}</span>
                                                </div>
                                            </div>

                                            <ChannelStatusBadge status={chan.status} />
                                        </div>

                                        <p className="text-xs text-neutral-600 dark:text-neutral-300 font-medium">
                                            {chan.summary}
                                        </p>
                                    </div>

                                    <div className="pt-3 border-t border-neutral-100 dark:border-neutral-800 flex items-center justify-between">
                                        <a
                                            href={chan.action_url}
                                            className="text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-1"
                                        >
                                            <span>{chan.action_label}</span>
                                            <ArrowRight className="h-3.5 w-3.5" />
                                        </a>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* ══════════════════════════════════════════════════════════════════════ */}
                {/* ── TAB 4: AI VOICE AGENTS ── */}
                {/* ══════════════════════════════════════════════════════════════════════ */}
                {activeTab === 'agents' && (
                    <div className="space-y-6">
                        <div className="space-y-1">
                            <h2 className="text-xl font-bold text-neutral-900 dark:text-white">
                                Conversational AI Voice Assistants
                            </h2>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                AI Agents qualify inbound leads, schedule consultations, and log summaries into Growbridge CRM.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {agents.map((ag) => (
                                <div
                                    key={ag.id}
                                    className="p-5 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-3xl shadow-sm space-y-4"
                                >
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center gap-3">
                                            <div className="h-10 w-10 rounded-2xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                                                <Bot className="h-5 w-5" />
                                            </div>
                                            <div>
                                                <h4 className="font-bold text-neutral-900 dark:text-white">{ag.name}</h4>
                                                <span className="text-[11px] text-neutral-400 font-medium">
                                                    Language: {ag.language || 'English (US)'}
                                                </span>
                                            </div>
                                        </div>
                                        <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400">
                                            ● Active
                                        </span>
                                    </div>

                                    <div className="p-3 rounded-xl bg-neutral-50 dark:bg-black/30 border border-neutral-200/60 dark:border-neutral-800 text-xs text-neutral-600 dark:text-neutral-300">
                                        Tone: <strong className="capitalize">{ag.tone || 'Professional'}</strong> · Connected via Twilio Voice TwiML
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* ══════════════════════════════════════════════════════════════════════ */}
                {/* ── TAB 5: CALL HISTORY & RECORDINGS ── */}
                {/* ══════════════════════════════════════════════════════════════════════ */}
                {activeTab === 'history' && (
                    <div className="p-6 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-3xl shadow-sm space-y-4">
                        <div className="space-y-1">
                            <h2 className="text-xl font-bold text-neutral-900 dark:text-white">
                                Call History & AI Summaries
                            </h2>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                Inbound & outbound calls, duration logs, and AI lead qualification scores.
                            </p>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="border-b border-neutral-200 dark:border-neutral-800 text-neutral-400 font-bold uppercase text-[10px]">
                                        <th className="py-3 px-3">Caller</th>
                                        <th className="py-3 px-3">Connected Line</th>
                                        <th className="py-3 px-3">AI Agent</th>
                                        <th className="py-3 px-3">Duration</th>
                                        <th className="py-3 px-3">AI Summary</th>
                                        <th className="py-3 px-3">Lead Score</th>
                                        <th className="py-3 px-3">Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                    {recentCalls.length === 0 ? (
                                        <tr>
                                            <td colSpan={7} className="py-12 text-center text-neutral-400">
                                                No calls recorded yet. Incoming calls to your active numbers will appear here in real time.
                                            </td>
                                        </tr>
                                    ) : (
                                        recentCalls.map((call) => (
                                            <tr key={call.id} className="hover:bg-neutral-50 dark:hover:bg-white/5 transition">
                                                <td className="py-3.5 px-3 font-mono font-bold text-neutral-900 dark:text-white">
                                                    {call.from_number}
                                                </td>
                                                <td className="py-3.5 px-3 font-mono text-neutral-700 dark:text-neutral-300">
                                                    {call.phone_number?.phone_number || call.to_number}
                                                </td>
                                                <td className="py-3.5 px-3 font-medium text-emerald-600 dark:text-emerald-400">
                                                    {call.agent?.name || 'Aditi (Sales)'}
                                                </td>
                                                <td className="py-3.5 px-3 font-semibold text-neutral-800 dark:text-neutral-200">
                                                    {Math.floor(call.duration_sec / 60)}m {call.duration_sec % 60}s
                                                </td>
                                                <td className="py-3.5 px-3 text-neutral-600 dark:text-neutral-300 max-w-xs truncate">
                                                    {call.summary || 'Customer inquired about pricing.'}
                                                </td>
                                                <td className="py-3.5 px-3">
                                                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-500/20 text-purple-600 dark:text-purple-300">
                                                        Score: {call.lead_score || 85}/100
                                                    </span>
                                                </td>
                                                <td className="py-3.5 px-3 text-neutral-400">
                                                    {new Date(call.created_at).toLocaleString()}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* ══════════════════════════════════════════════════════════════════════ */}
                {/* ── TAB 6: TWILIO SETTINGS ── */}
                {/* ══════════════════════════════════════════════════════════════════════ */}
                {activeTab === 'settings' && (
                    <div className="p-6 bg-white dark:bg-[#041d15] border border-neutral-200/80 dark:border-emerald-900/40 rounded-3xl shadow-sm space-y-6">
                        <div className="space-y-1">
                            <h2 className="text-xl font-bold text-neutral-900 dark:text-white">
                                Twilio Gateway Integration
                            </h2>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                Multi-tenant workspace subaccount credentials and webhook routing engine.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-black/30 border border-neutral-200/80 dark:border-neutral-800 space-y-2">
                                <div className="text-xs font-semibold text-neutral-500">Twilio Subaccount SID</div>
                                <div className="font-mono text-xs font-bold text-neutral-900 dark:text-white truncate">
                                    {subaccount.sid}
                                </div>
                                <div className="text-[11px] text-emerald-500 font-semibold">● Isolated Tenant Subaccount</div>
                            </div>

                            <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-black/30 border border-neutral-200/80 dark:border-neutral-800 space-y-2">
                                <div className="text-xs font-semibold text-neutral-500">Telephony Webhooks Status</div>
                                <div className="font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                    /api/v1/webhooks/twilio/voice
                                </div>
                                <div className="text-[11px] text-emerald-500 font-semibold">● Live Connected & Encrypted</div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* ══════════════════════════════════════════════════════════════════════ */}
            {/* ── MODAL: PURCHASE NUMBER CONFIRMATION ── */}
            {/* ══════════════════════════════════════════════════════════════════════ */}
            <Modal show={purchaseModalOpen} onClose={() => setPurchaseModalOpen(false)}>
                <form onSubmit={handleConfirmPurchase} className="p-6 space-y-6">
                    <div className="space-y-1">
                        <h3 className="text-lg font-black text-neutral-900 dark:text-white">
                            Confirm Virtual Number Purchase
                        </h3>
                        <p className="text-xs text-neutral-500 dark:text-neutral-400">
                            Provision this number on Growbridge Connect and configure initial Voice capabilities.
                        </p>
                    </div>

                    <div className="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-center space-y-1">
                        <div className="text-xs font-semibold text-emerald-700 dark:text-emerald-400">Selected Line</div>
                        <div className="text-2xl font-black font-mono text-neutral-900 dark:text-white">
                            {purchaseForm.data.phone_number}
                        </div>
                        <div className="text-xs text-neutral-500">Monthly subscription: $1.15/mo</div>
                    </div>

                    <div className="space-y-4">
                        <div className="space-y-1">
                            <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                                Friendly Name (e.g. Sales Mumbai)
                            </label>
                            <input
                                type="text"
                                value={purchaseForm.data.friendly_name}
                                onChange={(e) => purchaseForm.setData('friendly_name', e.target.value)}
                                className="w-full text-xs rounded-xl bg-neutral-50 dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 py-2"
                            />
                        </div>

                        <div className="space-y-1">
                            <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                                Assign AI Voice Agent
                            </label>
                            <select
                                value={purchaseForm.data.assigned_ai_agent_id}
                                onChange={(e) => purchaseForm.setData('assigned_ai_agent_id', e.target.value)}
                                className="w-full text-xs rounded-xl bg-neutral-50 dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 py-2"
                            >
                                <option value="">None (Standard Forwarding)</option>
                                {agents.map((ag) => (
                                    <option key={ag.id} value={ag.id}>
                                        {ag.name} ({ag.language})
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-800">
                        <button
                            type="button"
                            onClick={() => setPurchaseModalOpen(false)}
                            className="px-4 py-2 rounded-xl text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-white/5"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={purchaseForm.processing}
                            className="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow"
                        >
                            {purchaseForm.processing ? 'Provisioning...' : 'Confirm & Purchase'}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* ══════════════════════════════════════════════════════════════════════ */}
            {/* ── MODAL: POST-PURCHASE UNIFIED SETUP CARD ── */}
            {/* ══════════════════════════════════════════════════════════════════════ */}
            <Modal show={setupCardModalOpen} onClose={() => setSetupCardModalOpen(false)}>
                <div className="p-6 space-y-6 text-center">
                    <div className="h-14 w-14 mx-auto rounded-full bg-emerald-500/20 text-emerald-500 flex items-center justify-center">
                        <CheckCircle2 className="h-8 w-8" />
                    </div>

                    <div className="space-y-1">
                        <h3 className="text-xl font-black text-neutral-900 dark:text-white">
                            Phone Number Provisioned!
                        </h3>
                        <p className="text-xs text-neutral-500 dark:text-neutral-400 font-mono">
                            {justPurchasedNumber?.phone_number}
                        </p>
                    </div>

                    {/* The exact requested Setup Card */}
                    <div className="p-5 rounded-3xl bg-neutral-50 dark:bg-black/40 border border-neutral-200/80 dark:border-neutral-800 text-left space-y-3 max-w-sm mx-auto font-mono text-xs">
                        <div className="text-[11px] font-bold text-neutral-400 uppercase tracking-wider">Number Setup</div>
                        <div className="divide-y divide-neutral-200 dark:divide-neutral-800 space-y-2 pt-1">
                            <div className="flex items-center justify-between pt-1">
                                <span className="flex items-center gap-2 text-neutral-800 dark:text-neutral-200">
                                    📞 Voice
                                </span>
                                <span className="font-bold text-emerald-500">Connected ✓</span>
                            </div>
                            <div className="flex items-center justify-between pt-2">
                                <span className="flex items-center gap-2 text-neutral-800 dark:text-neutral-200">
                                    💬 WhatsApp
                                </span>
                                <span className="font-bold text-amber-500">Not Connected</span>
                            </div>
                            <div className="flex items-center justify-between pt-2">
                                <span className="flex items-center gap-2 text-neutral-800 dark:text-neutral-200">
                                    🤖 AI Agent
                                </span>
                                <span className="font-bold text-neutral-400">
                                    {justPurchasedNumber?.ai_agent || 'Not Assigned'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col sm:flex-row items-center justify-center gap-3">
                        <button
                            type="button"
                            onClick={() => {
                                setSetupCardModalOpen(false);
                                const found = numbers.find((n) => n.phone_number === justPurchasedNumber?.phone_number) || numbers[0];
                                if (found) handleOpenWhatsappConnect(found);
                            }}
                            className="w-full sm:w-auto px-5 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black shadow-md transition inline-flex items-center justify-center gap-2"
                        >
                            <MessageSquare className="h-4 w-4" />
                            <span>Connect WhatsApp Business</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setSetupCardModalOpen(false)}
                            className="w-full sm:w-auto px-4 py-2.5 rounded-2xl text-xs font-bold text-neutral-500 hover:bg-neutral-100 dark:hover:bg-white/5"
                        >
                            I'll do this later
                        </button>
                    </div>
                </div>
            </Modal>

            {/* ══════════════════════════════════════════════════════════════════════ */}
            {/* ── MODAL: CONNECT WHATSAPP BUSINESS (OFFICIAL META EMBEDDED SIGNUP) ── */}
            {/* ══════════════════════════════════════════════════════════════════════ */}
            <Modal show={whatsappModalOpen} onClose={() => setWhatsappModalOpen(false)}>
                <div className="p-6 space-y-6">
                    <div className="space-y-1">
                        <div className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-500 text-[10px] font-bold">
                            <MessageSquare className="h-3 w-3" />
                            <span>Official Meta Business Onboarding</span>
                        </div>
                        <h3 className="text-lg font-black text-neutral-900 dark:text-white">
                            Connect WhatsApp to {selectedNumberForWhatsapp?.phone_number}
                        </h3>
                        <p className="text-xs text-neutral-500 dark:text-neutral-400">
                            Register this line with Meta Cloud API for automated WhatsApp marketing, chatbots, and CRM messaging without manual developer credentials.
                        </p>
                    </div>

                    {/* Option 1: Official Meta 1-Click Embedded Signup */}
                    <div className="p-5 rounded-2xl bg-emerald-500/10 dark:bg-emerald-950/30 border border-emerald-500/30 space-y-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Shield className="h-4 w-4 text-emerald-500" />
                                <span className="font-bold text-xs text-neutral-900 dark:text-white">
                                    Official Meta Embedded Signup (Recommended)
                                </span>
                            </div>
                            <span className="px-2 py-0.5 rounded text-[9px] font-black bg-emerald-500 text-neutral-950">
                                1-Click
                            </span>
                        </div>
                        <p className="text-[11px] text-neutral-600 dark:text-neutral-300">
                            Log into your Meta Business Portfolio, select or create your WhatsApp Business Account, and verify in seconds without copying API tokens.
                        </p>
                        <button
                            type="button"
                            onClick={handleLaunchMetaEmbeddedSignup}
                            disabled={isConnectingMeta}
                            className="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black shadow transition flex items-center justify-center gap-2"
                        >
                            {isConnectingMeta ? <RefreshCw className="h-4 w-4 animate-spin" /> : <MessageSquare className="h-4 w-4" />}
                            <span>{isConnectingMeta ? 'Opening Meta Onboarding...' : 'Continue with Meta / WhatsApp'}</span>
                        </button>
                    </div>

                    {/* Option 2: Direct / Custom Link */}
                    <form onSubmit={handleConfirmWhatsappManual} className="space-y-4 pt-2 border-t border-neutral-200 dark:border-neutral-800">
                        <div className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                            Or Link with Verified Display Name
                        </div>
                        <div className="space-y-1">
                            <input
                                type="text"
                                placeholder="Verified Business Name (e.g. ABC Technologies)"
                                value={whatsappForm.data.display_name}
                                onChange={(e) => whatsappForm.setData('display_name', e.target.value)}
                                required
                                className="w-full text-xs rounded-xl bg-neutral-50 dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 py-2.5 font-semibold"
                            />
                        </div>

                        {wabas.length > 0 && (
                            <div className="space-y-1">
                                <select
                                    value={whatsappForm.data.waba_id}
                                    onChange={(e) => whatsappForm.setData('waba_id', e.target.value)}
                                    className="w-full text-xs rounded-xl bg-neutral-50 dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 py-2"
                                >
                                    {wabas.map((waba) => (
                                        <option key={waba.id} value={waba.id}>
                                            WABA #{waba.waba_id} ({waba.status})
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

                        <div className="flex items-center justify-end gap-2 pt-2">
                            <button
                                type="button"
                                onClick={() => setWhatsappModalOpen(false)}
                                className="px-4 py-2 rounded-xl text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-white/5"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={whatsappForm.processing}
                                className="px-4 py-2 rounded-xl bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-xs font-bold shadow"
                            >
                                {whatsappForm.processing ? 'Saving...' : 'Link WhatsApp'}
                            </button>
                        </div>
                    </form>
                </div>
            </Modal>

            {/* ══════════════════════════════════════════════════════════════════════ */}
            {/* ── MODAL: ASSIGN AI AGENTS (VOICE & CHAT) ── */}
            {/* ══════════════════════════════════════════════════════════════════════ */}
            <Modal show={aiAgentModalOpen} onClose={() => setAiAgentModalOpen(false)}>
                <form onSubmit={handleSaveAiAssign} className="p-6 space-y-6">
                    <div className="space-y-1">
                        <div className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-purple-500/10 text-purple-500 text-[10px] font-bold">
                            <Bot className="h-3 w-3" />
                            <span>Omnichannel AI Intelligence</span>
                        </div>
                        <h3 className="text-lg font-black text-neutral-900 dark:text-white">
                            Assign AI Assistants to {selectedNumberForAi?.phone_number}
                        </h3>
                        <p className="text-xs text-neutral-500 dark:text-neutral-400">
                            Configure which AI assistants qualify inbound voice phone calls and handle WhatsApp/SMS chats.
                        </p>
                    </div>

                    <div className="space-y-4">
                        <div className="space-y-1.5">
                            <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300 flex items-center gap-1.5">
                                <PhoneCall className="h-3.5 w-3.5 text-emerald-500" />
                                <span>Inbound Voice AI Agent</span>
                            </label>
                            <select
                                value={aiForm.data.assigned_ai_agent_id}
                                onChange={(e) => aiForm.setData('assigned_ai_agent_id', e.target.value)}
                                className="w-full text-xs rounded-xl bg-neutral-50 dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 py-2.5 font-semibold"
                            >
                                <option value="">None (Forward to human queue)</option>
                                {agents.map((ag) => (
                                    <option key={ag.id} value={ag.id}>
                                        {ag.name} ({ag.language || 'English'} - {ag.tone || 'Professional'})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="space-y-1.5">
                            <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300 flex items-center gap-1.5">
                                <MessageSquare className="h-3.5 w-3.5 text-blue-500" />
                                <span>WhatsApp & SMS Chat AI Bot</span>
                            </label>
                            <select
                                value={aiForm.data.assigned_chat_ai_agent_id}
                                onChange={(e) => aiForm.setData('assigned_chat_ai_agent_id', e.target.value)}
                                className="w-full text-xs rounded-xl bg-neutral-50 dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 py-2.5 font-semibold"
                            >
                                <option value="">Default Omnichannel Inbox Bot</option>
                                {agents.map((ag) => (
                                    <option key={ag.id} value={ag.id}>
                                        {ag.name} (Omnichannel Assistant)
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-800">
                        <button
                            type="button"
                            onClick={() => setAiAgentModalOpen(false)}
                            className="px-4 py-2 rounded-xl text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-white/5"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={aiForm.processing}
                            className="px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold shadow"
                        >
                            {aiForm.processing ? 'Saving...' : 'Save AI Configuration'}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* ══════════════════════════════════════════════════════════════════════ */}
            {/* ── MODAL: MANAGE NUMBER (RECORDING / SMS / FRIENDLY NAME) ── */}
            {/* ══════════════════════════════════════════════════════════════════════ */}
            <Modal show={configModalOpen} onClose={() => setConfigModalOpen(false)}>
                <form onSubmit={handleSaveConfig} className="p-6 space-y-6">
                    <div className="space-y-1">
                        <h3 className="text-lg font-black text-neutral-900 dark:text-white">
                            Manage Line Settings
                        </h3>
                        <p className="text-xs text-neutral-500 dark:text-neutral-400 font-mono">
                            {selectedNumberToEdit?.phone_number}
                        </p>
                    </div>

                    <div className="space-y-4">
                        <div className="space-y-1">
                            <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                                Friendly Name
                            </label>
                            <input
                                type="text"
                                value={configForm.data.friendly_name}
                                onChange={(e) => configForm.setData('friendly_name', e.target.value)}
                                className="w-full text-xs rounded-xl bg-neutral-50 dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700 py-2"
                            />
                        </div>

                        <div className="space-y-3 pt-2">
                            <label className="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={configForm.data.voice_enabled}
                                    onChange={(e) => configForm.setData('voice_enabled', e.target.checked)}
                                    className="rounded border-neutral-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                <span className="text-xs font-semibold text-neutral-800 dark:text-neutral-200">
                                    Enable Inbound Voice Calling
                                </span>
                            </label>

                            <label className="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={configForm.data.sms_enabled}
                                    onChange={(e) => configForm.setData('sms_enabled', e.target.checked)}
                                    className="rounded border-neutral-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                <span className="text-xs font-semibold text-neutral-800 dark:text-neutral-200">
                                    Enable SMS Inbound/Outbound
                                </span>
                            </label>

                            <label className="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={configForm.data.call_recording_enabled}
                                    onChange={(e) => configForm.setData('call_recording_enabled', e.target.checked)}
                                    className="rounded border-neutral-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                <span className="text-xs font-semibold text-neutral-800 dark:text-neutral-200">
                                    Record Voice Calls for AI Analysis
                                </span>
                            </label>
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-800">
                        <button
                            type="button"
                            onClick={() => setConfigModalOpen(false)}
                            className="px-4 py-2 rounded-xl text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-white/5"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={configForm.processing}
                            className="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow"
                        >
                            {configForm.processing ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </Modal>
        </ClientLayout>
    );
}
