import React, { useState, useEffect } from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, useForm, router, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    Mic, Bot, Volume2, Sparkles, PhoneCall, PhoneForwarded,
    Clock, Shield, Database, Sliders, CheckCircle2, AlertCircle,
    Play, Pause, Plus, RefreshCw, Check, ArrowRight, ExternalLink,
    MessageSquare, AlertTriangle, Layers, Send, HelpCircle, Flame,
    Globe, Lock, UserCheck, ShieldAlert
} from 'lucide-react';
import { Card, Button, Badge, Modal } from '@/Components/ui';
import { toast } from 'sonner';

export default function VoiceStudioIndex({
    agents = [],
    selectedAgent = null,
    phoneNumbers = [],
    knowledgeBases = [],
    supportedVoices = [],
    providers = {},
    checklist = {},
    analyticsSummary = {},
}) {
    const { t } = useTranslation();
    const [activeSection, setActiveSection] = useState('basic');
    const [isPlayingGreeting, setIsPlayingGreeting] = useState(false);
    const [testSimulatorModal, setTestSimulatorModal] = useState(false);
    const [simMessages, setSimMessages] = useState([]);
    const [simInput, setSimInput] = useState('');
    const [simLoading, setSimLoading] = useState(false);

    // Initial form state
    const form = useForm({
        id: selectedAgent?.id || null,
        name: selectedAgent?.name || 'Sales Voice Assistant',
        description: selectedAgent?.description || 'Qualify incoming leads and answer questions about our products.',
        status: selectedAgent?.status || 'draft',
        provider: selectedAgent?.provider || 'twilio',
        voice_id: selectedAgent?.voice_id || 'Polly.Aditi',
        language: selectedAgent?.language || 'en-US',
        tone: selectedAgent?.tone || 'professional',
        greeting_message: selectedAgent?.greeting_message || 'Hello! Thank you for calling Growbridge Connect. How can I help you today?',
        system_prompt: selectedAgent?.system_prompt || '',
        ai_kb_id: selectedAgent?.ai_kb_id || (knowledgeBases[0]?.id || ''),
        human_transfer_number: selectedAgent?.human_transfer_number || '',
        max_duration_sec: selectedAgent?.max_duration_sec || 600,
        phone_number_id: selectedAgent?.assigned_phone_number_id || (phoneNumbers[0]?.id || ''),
        call_flow: selectedAgent?.call_flow || {
            purpose: 'Qualify incoming leads and answer questions.',
            primary_language: 'en-US',
            additional_languages: ['hi-IN'],
            detect_language: true,
            ai_disclosure: true,
            personality: 'professional',
            objective: 'lead_qualification',
            objective_description: 'Qualify leads interested in WhatsApp API and AI automation solutions.',
            response_style: 'balanced',
            allow_interruption: true,
            ask_one_question: true,
            confirm_important_info: true,
            max_ai_turns: 50,
            recording_enabled: true,
            recording_notice: 'Please note that this call may be recorded for quality and training purposes.',
            handoff_triggers: ['customer_request', 'low_confidence', 'complaint', 'payment_issue', 'high_value_lead'],
            handoff_sales_number: selectedAgent?.human_transfer_number || '',
            handoff_support_number: '',
            fallback_message: "I don't have that specific information in my business knowledge. Would you like me to connect you with our human team?",
            fallback_action: 'whatsapp_callback',
            knowledge_categories: ['business', 'products', 'services', 'pricing', 'faq', 'policies'],
        },
        working_hours: selectedAgent?.working_hours || {
            schedule: [
                { day: 'Monday', enabled: true, start: '09:00', end: '18:00' },
                { day: 'Tuesday', enabled: true, start: '09:00', end: '18:00' },
                { day: 'Wednesday', enabled: true, start: '09:00', end: '18:00' },
                { day: 'Thursday', enabled: true, start: '09:00', end: '18:00' },
                { day: 'Friday', enabled: true, start: '09:00', end: '18:00' },
                { day: 'Saturday', enabled: true, start: '10:00', end: '14:00' },
                { day: 'Sunday', enabled: false, start: '09:00', end: '18:00' },
            ],
            outside_action: 'whatsapp_callback',
        },
    });

    // Update form when selectedAgent changes
    useEffect(() => {
        if (selectedAgent) {
            form.setData({
                id: selectedAgent.id,
                name: selectedAgent.name,
                description: selectedAgent.description || '',
                status: selectedAgent.status,
                provider: selectedAgent.provider,
                voice_id: selectedAgent.voice_id || 'Polly.Aditi',
                language: selectedAgent.language,
                tone: selectedAgent.tone,
                greeting_message: selectedAgent.greeting_message,
                system_prompt: selectedAgent.system_prompt || '',
                ai_kb_id: selectedAgent.ai_kb_id || (knowledgeBases[0]?.id || ''),
                human_transfer_number: selectedAgent.human_transfer_number || '',
                max_duration_sec: selectedAgent.max_duration_sec || 600,
                phone_number_id: selectedAgent.assigned_phone_number_id || (phoneNumbers[0]?.id || ''),
                call_flow: selectedAgent.call_flow,
                working_hours: selectedAgent.working_hours,
            });
        }
    }, [selectedAgent?.id]);

    const handleSave = (e) => {
        e?.preventDefault();
        form.post(route('client.ai.voice-studio.save'), {
            onSuccess: () => toast.success('AI Voice Agent saved successfully.'),
            onError: () => toast.error('Please check the form for errors.'),
        });
    };

    const handleActivate = () => {
        if (!selectedAgent) return;
        router.post(route('client.ai.voice-studio.activate', selectedAgent.uuid), {}, {
            onSuccess: () => toast.success('AI Voice Agent is now LIVE!'),
            onError: (err) => toast.error(Object.values(err)[0] || 'Cannot activate agent.'),
        });
    };

    const handlePause = () => {
        if (!selectedAgent) return;
        router.post(route('client.ai.voice-studio.pause', selectedAgent.uuid), {}, {
            onSuccess: () => toast.success('AI Voice Agent paused.'),
        });
    };

    // Synthetic greeting audio preview using Web Speech Synthesis API
    const handlePlayGreeting = () => {
        if (!('speechSynthesis' in window)) {
            toast.info(form.data.greeting_message);
            return;
        }

        if (isPlayingGreeting) {
            window.speechSynthesis.cancel();
            setIsPlayingGreeting(false);
            return;
        }

        window.speechSynthesis.cancel();
        const text = (form.data.call_flow?.ai_disclosure ? "Hello, I am the AI assistant. " : "") + form.data.greeting_message;
        const utterance = new SpeechSynthesisUtterance(text);
        
        utterance.lang = form.data.language === 'hi-IN' ? 'hi-IN' : 'en-US';
        utterance.rate = 0.95;
        utterance.pitch = 1.0;

        utterance.onstart = () => setIsPlayingGreeting(true);
        utterance.onend = () => setIsPlayingGreeting(false);
        utterance.onerror = () => setIsPlayingGreeting(false);

        window.speechSynthesis.speak(utterance);
    };

    // Test simulation handler
    const handleSimulateMessage = (e) => {
        e.preventDefault();
        if (!simInput.trim() || !selectedAgent) return;

        const userMsg = simInput.trim();
        setSimMessages(prev => [...prev, { sender: 'caller', text: userMsg }]);
        setSimInput('');
        setSimLoading(true);

        window.axios.post(route('client.ai.voice-studio.simulate', selectedAgent.uuid), {
            message: userMsg,
        }).then(res => {
            setSimMessages(prev => [...prev, {
                sender: 'ai',
                text: res.data.response,
                isHandoff: res.data.is_handoff,
                handoffReason: res.data.handoff_reason,
            }]);
        }).catch(err => {
            setSimMessages(prev => [...prev, {
                sender: 'system',
                text: 'Error generating response: ' + (err.response?.data?.message || err.message),
            }]);
        }).finally(() => {
            setSimLoading(false);
        });
    };

    const navSections = [
        { id: 'basic', label: '1. Basic Settings', icon: Sliders },
        { id: 'voice', label: '2. Voice & Provider', icon: Mic },
        { id: 'language', label: '3. Language & Region', icon: Globe },
        { id: 'greeting', label: '4. Greeting & Disclosure', icon: MessageSquare },
        { id: 'personality', label: '5. Personality & Objective', icon: Sparkles },
        { id: 'behavior', label: '6. Conversation Style', icon: Bot },
        { id: 'knowledge', label: '7. Knowledge Base', icon: Database },
        { id: 'handoff', label: '8. Human Handoff & Fallback', icon: PhoneForwarded },
        { id: 'hours', label: '9. Working Hours', icon: Clock },
        { id: 'limits', label: '10. Limits & Recording', icon: Shield },
    ];

    return (
        <ClientLayout>
            <Head title="AI Voice Studio — Growbridge Connect" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* ─── Studio Top Header Bar ───────────────────────────────── */}
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div className="flex items-center gap-3.5">
                        <div className="h-11 w-11 rounded-2xl bg-brand-500/10 text-brand-600 dark:text-brand-400 flex items-center justify-center">
                            <Mic className="w-6 h-6" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold text-neutral-900 dark:text-white">AI Voice Studio</h1>
                                <Badge variant={
                                    form.data.status === 'active' ? 'success' :
                                    form.data.status === 'paused' ? 'warning' : 'neutral'
                                } className="capitalize">
                                    ● {form.data.status}
                                </Badge>
                            </div>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                Configure natural conversational voice flow, knowledge retrieval, and human handoff for your phone lines.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        {/* Agent Switcher Dropdown */}
                        {agents.length > 1 && (
                            <select
                                value={selectedAgent?.uuid || ''}
                                onChange={(e) => router.get(route('client.ai.voice-studio.show', e.target.value))}
                                className="text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-2 text-neutral-900 dark:text-white"
                            >
                                {agents.map((a) => (
                                    <option key={a.id} value={a.uuid}>
                                        {a.name} ({a.status})
                                    </option>
                                ))}
                            </select>
                        )}

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                setSimMessages([
                                    { sender: 'ai', text: form.data.greeting_message }
                                ]);
                                setTestSimulatorModal(true);
                            }}
                            className="text-xs font-semibold gap-1.5"
                        >
                            <Sparkles className="w-3.5 h-3.5 text-amber-500" />
                            🧪 Test Voice Agent
                        </Button>

                        <Button
                            type="button"
                            variant="brand"
                            size="sm"
                            onClick={handleSave}
                            disabled={form.processing}
                            className="text-xs font-bold"
                        >
                            {form.processing ? 'Saving...' : 'Save Agent'}
                        </Button>

                        {form.data.status === 'active' ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handlePause}
                                className="text-xs text-amber-600 border-amber-300 dark:border-amber-800"
                            >
                                <Pause className="w-3.5 h-3.5 mr-1" /> Pause Agent
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                size="sm"
                                onClick={handleActivate}
                                disabled={!checklist.is_ready}
                                className="text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white"
                            >
                                <Check className="w-3.5 h-3.5 mr-1" /> Activate AI Voice
                            </Button>
                        )}
                    </div>
                </div>

                {/* ─── Main 2-Column Grid ──────────────────────────────────── */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    {/* ─── Left Sidebar Navigation ─────────────────────────── */}
                    <div className="lg:col-span-3 space-y-2 bg-white dark:bg-neutral-900 p-3 rounded-2xl border border-neutral-200 dark:border-neutral-800">
                        <span className="text-[11px] font-bold text-neutral-400 px-3 uppercase tracking-wider block mb-1">
                            Configuration
                        </span>
                        {navSections.map((sec) => {
                            const Icon = sec.icon;
                            const active = activeSection === sec.id;
                            return (
                                <button
                                    key={sec.id}
                                    type="button"
                                    onClick={() => setActiveSection(sec.id)}
                                    className={`w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all ${
                                        active
                                            ? 'bg-brand-50 text-brand-700 dark:bg-neutral-800 dark:text-brand-400 shadow-xs'
                                            : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800/50'
                                    }`}
                                >
                                    <div className="flex items-center gap-2.5">
                                        <Icon className={`w-4 h-4 ${active ? 'text-brand-600 dark:text-brand-400' : 'text-neutral-400'}`} />
                                        <span>{sec.label}</span>
                                    </div>
                                    {active && <div className="h-1.5 w-1.5 rounded-full bg-brand-600 dark:bg-brand-400" />}
                                </button>
                            );
                        })}
                    </div>

                    {/* ─── Center Settings Content ─────────────────────────── */}
                    <div className="lg:col-span-6 space-y-6">
                        {/* 1. Basic Settings */}
                        {activeSection === 'basic' && (
                            <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                                <div>
                                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Basic Agent Information</h3>
                                    <p className="text-xs text-neutral-500">Name and core mission for your automated voice assistant.</p>
                                </div>

                                <div className="space-y-4">
                                    <div className="space-y-1">
                                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Agent Name</label>
                                        <input
                                            type="text"
                                            value={form.data.name}
                                            onChange={(e) => form.setData('name', e.target.value)}
                                            placeholder="Sales Voice Agent"
                                            className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500"
                                        />
                                    </div>

                                    <div className="space-y-1">
                                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Purpose / Mission</label>
                                        <textarea
                                            rows={3}
                                            value={form.data.description}
                                            onChange={(e) => form.setData('description', e.target.value)}
                                            placeholder="Qualify incoming leads and answer questions about our services."
                                            className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500"
                                        />
                                    </div>

                                    <div className="space-y-1">
                                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Bind Phone Number</label>
                                        <select
                                            value={form.data.phone_number_id}
                                            onChange={(e) => form.setData('phone_number_id', e.target.value)}
                                            className="w-full text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500"
                                        >
                                            <option value="">-- No number assigned (Assign Number) --</option>
                                            {phoneNumbers.map((p) => (
                                                <option key={p.id} value={p.id}>
                                                    {p.phone_number} {p.friendly_name ? `(${p.friendly_name})` : ''} — {p.country}
                                                </option>
                                            ))}
                                        </select>
                                        <p className="text-[11px] text-neutral-400">
                                            Incoming calls on this number will be handled directly by this AI agent.
                                        </p>
                                    </div>
                                </div>
                            </Card>
                        )}

                        {/* 2. Voice & Provider */}
                        {activeSection === 'voice' && (
                            <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                                <div>
                                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Voice & Telephony Provider</h3>
                                    <p className="text-xs text-neutral-500">Choose a natural neural voice supported by your connected telephony provider.</p>
                                </div>

                                <div className="space-y-4">
                                    <div className="space-y-1">
                                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Voice Provider</label>
                                        <div className="grid grid-cols-1 gap-3">
                                            {['twilio'].map((p) => (
                                                <button
                                                    key={p}
                                                    type="button"
                                                    onClick={() => form.setData('provider', p)}
                                                    className={`p-3 rounded-xl border text-left transition ${
                                                        form.data.provider === p
                                                            ? 'border-brand-500 bg-brand-50/40 dark:bg-neutral-800'
                                                            : 'border-neutral-200 dark:border-neutral-700'
                                                    }`}
                                                >
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-xs font-bold text-neutral-900 dark:text-white capitalize">Twilio Voice & Call Infrastructure</span>
                                                        <Badge variant={providers[p]?.connected ? 'success' : 'neutral'}>
                                                            {providers[p]?.connected ? 'Connected' : 'Not Setup'}
                                                        </Badge>
                                                    </div>
                                                </button>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Available Neural Voices</label>
                                        <div className="grid grid-cols-1 gap-2.5 max-h-72 overflow-y-auto pr-1">
                                            {supportedVoices.map((v) => {
                                                const selected = form.data.voice_id === v.id;
                                                return (
                                                    <div
                                                        key={v.id}
                                                        onClick={() => form.setData('voice_id', v.id)}
                                                        className={`p-3 rounded-xl border cursor-pointer transition flex items-center justify-between gap-3 ${
                                                            selected
                                                                ? 'border-brand-500 bg-brand-50/50 dark:bg-neutral-800'
                                                                : 'border-neutral-200 dark:border-neutral-700 hover:border-neutral-300'
                                                        }`}
                                                    >
                                                        <div className="space-y-0.5">
                                                            <div className="flex items-center gap-2">
                                                                <span className="text-xs font-bold text-neutral-900 dark:text-white">{v.name}</span>
                                                                <span className="text-[10px] px-1.5 py-0.5 rounded bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300 font-semibold">
                                                                    {v.gender} • {v.language}
                                                                </span>
                                                            </div>
                                                            <p className="text-[11px] text-neutral-500 leading-tight">{v.description}</p>
                                                        </div>
                                                        {selected && <CheckCircle2 className="w-4 h-4 text-brand-600 shrink-0" />}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        )}

                        {/* 3. Language & Region */}
                        {activeSection === 'language' && (
                            <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                                <div>
                                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Language & Regional Setup</h3>
                                    <p className="text-xs text-neutral-500">Configure spoken languages and auto-detection behavior.</p>
                                </div>

                                <div className="space-y-4">
                                    <div className="space-y-1">
                                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Primary Language</label>
                                        <select
                                            value={form.data.language}
                                            onChange={(e) => form.setData('language', e.target.value)}
                                            className="w-full text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                        >
                                            <option value="en-US">English (US)</option>
                                            <option value="en-IN">English (India)</option>
                                            <option value="hi-IN">Hindi (India)</option>
                                            <option value="hinglish">Hinglish (Conversational Mix)</option>
                                            <option value="es-ES">Spanish</option>
                                        </select>
                                    </div>

                                    <div className="pt-3 border-t border-neutral-100 dark:border-neutral-800">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <span className="text-xs font-bold text-neutral-900 dark:text-white block">Caller Language Auto-Detection</span>
                                                <span className="text-[11px] text-neutral-500">Detect if caller speaks Hindi or English and adapt naturally.</span>
                                            </div>
                                            <label className="relative inline-flex items-center cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    checked={form.data.call_flow?.detect_language}
                                                    onChange={(e) => form.setData('call_flow', { ...form.data.call_flow, detect_language: e.target.checked })}
                                                    className="sr-only peer"
                                                />
                                                <div className="w-10 h-5 bg-neutral-200 peer-focus:outline-none rounded-full peer dark:bg-neutral-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-neutral-600 peer-checked:bg-brand-600"></div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        )}

                        {/* 4. Greeting & Disclosure */}
                        {activeSection === 'greeting' && (
                            <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                                <div>
                                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Greeting & AI Disclosure</h3>
                                    <p className="text-xs text-neutral-500">First words spoken to callers when the phone call connects.</p>
                                </div>

                                <div className="space-y-4">
                                    <div className="space-y-1">
                                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Greeting Message</label>
                                        <textarea
                                            rows={3}
                                            value={form.data.greeting_message}
                                            onChange={(e) => form.setData('greeting_message', e.target.value)}
                                            placeholder="Hello! Welcome to Growbridge Connect. How can I assist you today?"
                                            className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                        />
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={handlePlayGreeting}
                                            className="text-xs gap-1.5"
                                        >
                                            {isPlayingGreeting ? <Pause className="w-3.5 h-3.5 text-amber-500" /> : <Play className="w-3.5 h-3.5 text-emerald-500" />}
                                            {isPlayingGreeting ? 'Stop Greeting' : '▶ Play Greeting Preview'}
                                        </Button>
                                    </div>

                                    <div className="pt-3 border-t border-neutral-100 dark:border-neutral-800 flex items-center justify-between">
                                        <div>
                                            <span className="text-xs font-bold text-neutral-900 dark:text-white block">AI Disclosure</span>
                                            <span className="text-[11px] text-neutral-500">Tell caller they are speaking with an AI assistant.</span>
                                        </div>
                                        <label className="relative inline-flex items-center cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={form.data.call_flow?.ai_disclosure}
                                                onChange={(e) => form.setData('call_flow', { ...form.data.call_flow, ai_disclosure: e.target.checked })}
                                                className="sr-only peer"
                                            />
                                            <div className="w-10 h-5 bg-neutral-200 peer-focus:outline-none rounded-full peer dark:bg-neutral-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-neutral-600 peer-checked:bg-brand-600"></div>
                                        </label>
                                    </div>
                                </div>
                            </Card>
                        )}

                        {/* 5. Personality & Objective */}
                        {activeSection === 'personality' && (
                            <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                                <div>
                                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Personality & Business Objective</h3>
                                    <p className="text-xs text-neutral-500">Define the conversational tone and primary goal of your voice agent.</p>
                                </div>

                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Tone Preset</label>
                                        <div className="grid grid-cols-3 gap-2">
                                            {['professional', 'friendly', 'sales', 'support', 'concise', 'custom'].map((t) => (
                                                <button
                                                    key={t}
                                                    type="button"
                                                    onClick={() => form.setData('tone', t)}
                                                    className={`p-2.5 rounded-xl border text-xs font-bold capitalize transition ${
                                                        form.data.tone === t
                                                            ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-neutral-800 dark:text-brand-400'
                                                            : 'border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300'
                                                    }`}
                                                >
                                                    {t}
                                                </button>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Primary Objective</label>
                                        <div className="grid grid-cols-2 gap-2">
                                            {[
                                                { id: 'lead_qualification', label: 'Lead Qualification' },
                                                { id: 'customer_support', label: 'Customer Support' },
                                                { id: 'sales', label: 'Sales & Inquiries' },
                                                { id: 'appointment_booking', label: 'Appointment Booking' },
                                            ].map((obj) => (
                                                <button
                                                    key={obj.id}
                                                    type="button"
                                                    onClick={() => form.setData('call_flow', { ...form.data.call_flow, objective: obj.id })}
                                                    className={`p-2.5 rounded-xl border text-xs font-bold text-left transition ${
                                                        form.data.call_flow?.objective === obj.id
                                                            ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-neutral-800 dark:text-brand-400'
                                                            : 'border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300'
                                                    }`}
                                                >
                                                    {obj.label}
                                                </button>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="space-y-1">
                                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Objective Instructions</label>
                                        <textarea
                                            rows={2}
                                            value={form.data.call_flow?.objective_description || ''}
                                            onChange={(e) => form.setData('call_flow', { ...form.data.call_flow, objective_description: e.target.value })}
                                            placeholder="Qualify leads interested in WhatsApp API and AI automation solutions."
                                            className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                        />
                                    </div>
                                </div>
                            </Card>
                        )}

                        {/* 6. Conversation Style */}
                        {activeSection === 'behavior' && (
                            <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                                <div>
                                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Conversation Behavior</h3>
                                    <p className="text-xs text-neutral-500">Control pacing, interruption handling, and response length.</p>
                                </div>

                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Response Style</label>
                                        <div className="grid grid-cols-3 gap-2">
                                            {['short', 'balanced', 'detailed'].map((s) => (
                                                <button
                                                    key={s}
                                                    type="button"
                                                    onClick={() => form.setData('call_flow', { ...form.data.call_flow, response_style: s })}
                                                    className={`p-2.5 rounded-xl border text-xs font-bold capitalize transition ${
                                                        form.data.call_flow?.response_style === s
                                                            ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-neutral-800 dark:text-brand-400'
                                                            : 'border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300'
                                                    }`}
                                                >
                                                    {s}
                                                </button>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="space-y-3 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                                        {[
                                            { key: 'allow_interruption', label: 'Allow Caller Interruption (Barge-in)', desc: 'Stop speaking when caller interrupts.' },
                                            { key: 'ask_one_question', label: 'Ask One Question at a Time', desc: 'Prevents overwhelming the caller with multiple prompts.' },
                                            { key: 'confirm_important_info', label: 'Confirm Important Information', desc: 'Rephrase important details like dates & phone numbers.' },
                                        ].map(({ key, label, desc }) => (
                                            <div key={key} className="flex items-center justify-between">
                                                <div>
                                                    <span className="text-xs font-bold text-neutral-900 dark:text-white block">{label}</span>
                                                    <span className="text-[11px] text-neutral-500">{desc}</span>
                                                </div>
                                                <input
                                                    type="checkbox"
                                                    checked={form.data.call_flow?.[key] ?? true}
                                                    onChange={(e) => form.setData('call_flow', { ...form.data.call_flow, [key]: e.target.checked })}
                                                    className="rounded text-brand-600 focus:ring-brand-500 h-4 w-4"
                                                />
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </Card>
                        )}

                        {/* 7. Knowledge Base */}
                        {activeSection === 'knowledge' && (
                            <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                                <div>
                                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Knowledge Base Linkage</h3>
                                    <p className="text-xs text-neutral-500">Connect verified business information from Task #68 Knowledge Base.</p>
                                </div>

                                <div className="space-y-4">
                                    <div className="space-y-1">
                                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Select Knowledge Base</label>
                                        <select
                                            value={form.data.ai_kb_id}
                                            onChange={(e) => form.setData('ai_kb_id', e.target.value)}
                                            className="w-full text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                        >
                                            <option value="">-- No Knowledge Base Linked --</option>
                                            {knowledgeBases.map((kb) => (
                                                <option key={kb.id} value={kb.id}>
                                                    {kb.name} ({kb.category})
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700 text-xs space-y-2">
                                        <div className="flex items-center justify-between font-bold text-neutral-800 dark:text-neutral-200">
                                            <span>Active Knowledge Sources:</span>
                                            <Link href={route('client.ai.knowledge.index')} className="text-brand-600 hover:underline flex items-center gap-1">
                                                Manage Knowledge <ExternalLink className="w-3 h-3" />
                                            </Link>
                                        </div>
                                        <div className="flex flex-wrap gap-1.5">
                                            {['Business Profile', 'Products', 'Services', 'Pricing & Plans', 'FAQs', 'Return Policies'].map((src) => (
                                                <span key={src} className="text-[10px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 dark:bg-neutral-700 dark:text-emerald-400 font-bold">
                                                    ✓ {src}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        )}

                        {/* 8. Human Handoff & Fallback */}
                        {activeSection === 'handoff' && (
                            <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                                <div>
                                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Human Handoff & Fallback Routing</h3>
                                    <p className="text-xs text-neutral-500">Live agent transfer numbers and after-hours callbacks.</p>
                                </div>

                                <div className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="space-y-1">
                                            <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Sales Transfer Number</label>
                                            <input
                                                type="text"
                                                value={form.data.human_transfer_number}
                                                onChange={(e) => form.setData('human_transfer_number', e.target.value)}
                                                placeholder="+91 98765 43210"
                                                className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                            />
                                        </div>

                                        <div className="space-y-1">
                                            <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">No Agent Available Action</label>
                                            <select
                                                value={form.data.call_flow?.fallback_action || 'whatsapp_callback'}
                                                onChange={(e) => form.setData('call_flow', { ...form.data.call_flow, fallback_action: e.target.value })}
                                                className="w-full text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                            >
                                                <option value="whatsapp_callback">Send WhatsApp Follow-up (Recommended)</option>
                                                <option value="take_message">Take Message & Notify Team</option>
                                                <option value="schedule_callback">Schedule Callback</option>
                                                <option value="end_call">Friendly Closing & Hangup</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div className="space-y-1">
                                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Unknown Question Fallback Message</label>
                                        <textarea
                                            rows={2}
                                            value={form.data.call_flow?.fallback_message || ''}
                                            onChange={(e) => form.setData('call_flow', { ...form.data.call_flow, fallback_message: e.target.value })}
                                            className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                        />
                                    </div>
                                </div>
                            </Card>
                        )}

                        {/* 9. Working Hours */}
                        {activeSection === 'hours' && (
                            <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                                <div>
                                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Weekly Operating Schedule</h3>
                                    <p className="text-xs text-neutral-500">Configure business hours when AI answers vs after-hours actions.</p>
                                </div>

                                <div className="space-y-3">
                                    {(form.data.working_hours?.schedule || []).map((item, idx) => (
                                        <div key={item.day} className="flex items-center justify-between p-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700/60 text-xs">
                                            <div className="flex items-center gap-3">
                                                <input
                                                    type="checkbox"
                                                    checked={item.enabled}
                                                    onChange={(e) => {
                                                        const newSched = [...form.data.working_hours.schedule];
                                                        newSched[idx].enabled = e.target.checked;
                                                        form.setData('working_hours', { ...form.data.working_hours, schedule: newSched });
                                                    }}
                                                    className="rounded text-brand-600 focus:ring-brand-500 h-4 w-4"
                                                />
                                                <span className="font-bold text-neutral-800 dark:text-neutral-200 w-24">{item.day}</span>
                                            </div>

                                            {item.enabled ? (
                                                <div className="flex items-center gap-2">
                                                    <input
                                                        type="time"
                                                        value={item.start}
                                                        onChange={(e) => {
                                                            const newSched = [...form.data.working_hours.schedule];
                                                            newSched[idx].start = e.target.value;
                                                            form.setData('working_hours', { ...form.data.working_hours, schedule: newSched });
                                                        }}
                                                        className="text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 px-2 py-1"
                                                    />
                                                    <span className="text-neutral-400">to</span>
                                                    <input
                                                        type="time"
                                                        value={item.end}
                                                        onChange={(e) => {
                                                            const newSched = [...form.data.working_hours.schedule];
                                                            newSched[idx].end = e.target.value;
                                                            form.setData('working_hours', { ...form.data.working_hours, schedule: newSched });
                                                        }}
                                                        className="text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 px-2 py-1"
                                                    />
                                                </div>
                                            ) : (
                                                <span className="text-neutral-400 font-semibold text-xs">Closed</span>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </Card>
                        )}

                        {/* 10. Limits & Recording */}
                        {activeSection === 'limits' && (
                            <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                                <div>
                                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Call Limits & Recording Notice</h3>
                                    <p className="text-xs text-neutral-500">Cost safeguards against infinite calls & legal compliance settings.</p>
                                </div>

                                <div className="space-y-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="space-y-1">
                                            <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Max Duration (Seconds)</label>
                                            <input
                                                type="number"
                                                value={form.data.max_duration_sec}
                                                onChange={(e) => form.setData('max_duration_sec', parseInt(e.target.value) || 600)}
                                                className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                            />
                                            <span className="text-[10px] text-neutral-400">Default: 600s (10 minutes)</span>
                                        </div>

                                        <div className="space-y-1">
                                            <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Max AI Turns</label>
                                            <input
                                                type="number"
                                                value={form.data.call_flow?.max_ai_turns || 50}
                                                onChange={(e) => form.setData('call_flow', { ...form.data.call_flow, max_ai_turns: parseInt(e.target.value) || 50 })}
                                                className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                            />
                                            <span className="text-[10px] text-neutral-400">Default: 50 turns</span>
                                        </div>
                                    </div>

                                    <div className="space-y-1 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                                        <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Call Recording Legal Notice</label>
                                        <textarea
                                            rows={2}
                                            value={form.data.call_flow?.recording_notice || ''}
                                            onChange={(e) => form.setData('call_flow', { ...form.data.call_flow, recording_notice: e.target.value })}
                                            className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                        />
                                    </div>
                                </div>
                            </Card>
                        )}
                    </div>

                    {/* ─── Right Column: Preview & Activation Checklist ─────── */}
                    <div className="lg:col-span-3 space-y-6">
                        {/* Live Agent Preview Card */}
                        <Card className="p-5 border-neutral-200 dark:border-neutral-800 space-y-4">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-bold text-neutral-900 dark:text-white">Live Voice Preview</span>
                                <Badge variant="brand" className="text-[10px]">
                                    {form.data.voice_id}
                                </Badge>
                            </div>

                            {/* Simulated Voice Bubble */}
                            <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 space-y-3">
                                <div className="flex items-center gap-2">
                                    <div className="h-7 w-7 rounded-full bg-brand-600 text-white flex items-center justify-center">
                                        <Bot className="w-4 h-4" />
                                    </div>
                                    <span className="text-xs font-bold text-neutral-800 dark:text-neutral-200">{form.data.name}</span>
                                </div>
                                <p className="text-xs text-neutral-600 dark:text-neutral-300 leading-relaxed italic">
                                    "{form.data.greeting_message}"
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handlePlayGreeting}
                                    className="w-full text-xs font-bold gap-1.5"
                                >
                                    {isPlayingGreeting ? <Pause className="w-3.5 h-3.5 text-amber-500" /> : <Play className="w-3.5 h-3.5 text-emerald-500" />}
                                    {isPlayingGreeting ? 'Stop Audio' : '▶ Preview Voice Audio'}
                                </Button>
                            </div>

                            {/* Metrics snippet */}
                            <div className="grid grid-cols-2 gap-2 pt-2 text-xs">
                                <div className="p-2.5 rounded-lg bg-neutral-50 dark:bg-neutral-800 border border-neutral-100 dark:border-neutral-700/60">
                                    <span className="text-neutral-400 block text-[10px]">Calls Today</span>
                                    <span className="text-sm font-bold text-neutral-900 dark:text-white">{analyticsSummary.calls_today ?? 0}</span>
                                </div>
                                <div className="p-2.5 rounded-lg bg-neutral-50 dark:bg-neutral-800 border border-neutral-100 dark:border-neutral-700/60">
                                    <span className="text-neutral-400 block text-[10px]">AI Resolution</span>
                                    <span className="text-sm font-bold text-emerald-600">{analyticsSummary.resolution_rate ?? 100}%</span>
                                </div>
                            </div>
                        </Card>

                        {/* Activation Readiness Checklist */}
                        <Card className="p-5 border-neutral-200 dark:border-neutral-800 space-y-4">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-bold text-neutral-900 dark:text-white">Activation Checklist</span>
                                {checklist.is_ready ? (
                                    <Badge variant="success" className="text-[10px]">Ready</Badge>
                                ) : (
                                    <Badge variant="warning" className="text-[10px]">Incomplete</Badge>
                                )}
                            </div>

                            <div className="space-y-2 text-xs">
                                {[
                                    { check: checklist.has_name, label: 'Agent Name' },
                                    { check: checklist.has_voice, label: 'Voice Selected' },
                                    { check: checklist.has_provider, label: 'Voice Provider Connected' },
                                    { check: checklist.has_phone_number, label: 'Phone Number Assigned' },
                                    { check: checklist.has_knowledge, label: 'Knowledge Base Linked' },
                                    { check: checklist.has_greeting, label: 'Greeting Configured' },
                                    { check: checklist.has_handoff, label: 'Human Handoff Set' },
                                    { check: checklist.has_working_hours, label: 'Working Hours Set' },
                                ].map(({ check, label }) => (
                                    <div key={label} className="flex items-center justify-between py-1">
                                        <span className={check ? 'text-neutral-700 dark:text-neutral-300' : 'text-neutral-400'}>
                                            {label}
                                        </span>
                                        {check ? (
                                            <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0" />
                                        ) : (
                                            <AlertCircle className="w-4 h-4 text-amber-500 shrink-0" />
                                        )}
                                    </div>
                                ))}
                            </div>

                            {checklist.blocking_reason && (
                                <div className="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-800 dark:text-amber-300 font-medium">
                                    ⚠️ {checklist.blocking_reason}
                                </div>
                            )}

                            {form.data.status === 'active' ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handlePause}
                                    className="w-full text-xs font-bold"
                                >
                                    Pause Voice Agent
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    variant="brand"
                                    size="md"
                                    onClick={handleActivate}
                                    disabled={!checklist.is_ready}
                                    className="w-full text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs"
                                >
                                    Activate AI Voice Agent
                                </Button>
                            )}
                        </Card>
                    </div>
                </div>
            </div>

            {/* ─── Test Simulator Modal ─────────────────────────────────────── */}
            <Modal
                show={testSimulatorModal}
                onClose={() => setTestSimulatorModal(false)}
                title={`🧪 Voice Agent Simulator — ${form.data.name}`}
            >
                <div className="space-y-4">
                    <div className="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-300 flex items-center gap-2">
                        <Sparkles className="w-4 h-4 text-amber-500 shrink-0" />
                        <span><strong>TEST MODE:</strong> Simulates live phone speech & knowledge answers without incurring telephony costs.</span>
                    </div>

                    <div className="h-64 overflow-y-auto space-y-3 p-4 rounded-xl bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 text-xs">
                        {simMessages.map((m, i) => (
                            <div
                                key={i}
                                className={`flex flex-col ${m.sender === 'caller' ? 'items-end' : 'items-start'}`}
                            >
                                <div
                                    className={`max-w-[85%] p-3 rounded-2xl ${
                                        m.sender === 'caller'
                                            ? 'bg-brand-600 text-white rounded-br-none'
                                            : 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white border border-neutral-200 dark:border-neutral-700 rounded-bl-none shadow-xs'
                                    }`}
                                >
                                    <span className="text-[10px] font-bold block mb-1 opacity-75">
                                        {m.sender === 'caller' ? 'Caller' : form.data.name}
                                    </span>
                                    <p className="leading-relaxed">{m.text}</p>

                                    {m.isHandoff && (
                                        <div className="mt-2 pt-2 border-t border-amber-200 dark:border-amber-700 flex items-center gap-1.5 text-[11px] text-amber-600 dark:text-amber-400 font-bold">
                                            <PhoneForwarded className="w-3 h-3" />
                                            Handoff Triggered: {m.handoffReason}
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                        {simLoading && (
                            <div className="flex items-center gap-2 text-xs text-neutral-400">
                                <RefreshCw className="w-3.5 h-3.5 animate-spin text-brand-500" />
                                AI Assistant is speaking...
                            </div>
                        )}
                    </div>

                    {/* Quick prompts */}
                    <div className="flex flex-wrap gap-1.5">
                        {['What are your business hours?', 'What is your pricing?', 'I want to speak with a human manager'].map((q) => (
                            <button
                                key={q}
                                type="button"
                                onClick={() => setSimInput(q)}
                                className="text-[11px] px-2.5 py-1 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-200 transition"
                            >
                                {q}
                            </button>
                        ))}
                    </div>

                    <form onSubmit={handleSimulateMessage} className="flex gap-2">
                        <input
                            type="text"
                            value={simInput}
                            onChange={(e) => setSimInput(e.target.value)}
                            placeholder="Type what the caller says..."
                            className="flex-1 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3.5 py-2 text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500"
                        />
                        <Button type="submit" variant="brand" size="sm" disabled={simLoading || !simInput.trim()} className="text-xs font-bold">
                            <Send className="w-3.5 h-3.5" />
                        </Button>
                    </form>
                </div>
            </Modal>
        </ClientLayout>
    );
}
