import { Head, Link, router, useForm } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import {
    Bot, ArrowLeft, Save, Play, CheckCircle2, AlertCircle, Sparkles,
    BookOpen, MessageSquare, Phone, Mail, Instagram, Zap, ShieldCheck,
    Sliders, Users, Check, X, Clock, HelpCircle, Layers, Eye, RefreshCw,
    Send, ChevronRight, AlertTriangle, ShieldAlert, Cpu, Award, Globe, ToggleLeft, ToggleRight
} from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import axios from 'axios';

export default function ChatbotStudio({
    mode = 'create', // create or edit
    agent = null,
    template = null,
    templateKey = 'sales_assistant',
    templates = {},
    knowledgeBases = [],
    teamMembers = [],
    initialTab = 'basic',
}) {
    const { t } = useTranslation();
    const [activeTab, setActiveTab] = useState(initialTab); // basic, personality, instructions, knowledge, channels, automation, testing, publish
    const [instructionMode, setInstructionMode] = useState('basic'); // basic, advanced
    const [isSaving, setIsSaving] = useState(false);

    // AI Simulator State
    const [simulatorMessages, setSimulatorMessages] = useState([]);
    const [simulatorInput, setSimulatorInput] = useState('');
    const [simulatorLoading, setSimulatorLoading] = useState(false);
    const [debugInfo, setDebugInfo] = useState(null);
    const [showDebug, setShowDebug] = useState(true);
    const simBottomRef = useRef(null);

    useEffect(() => {
        simBottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [simulatorMessages, simulatorLoading]);

    // Initial state based on agent or template
    const initialValues = agent ? {
        name: agent.name || '',
        agent_type: agent.agent_type || 'sales',
        purpose: agent.purpose || '',
        description: agent.description || agent.purpose || '',
        tone: agent.tone || 'professional',
        response_style: agent.response_style || 'balanced',
        emoji_style: agent.emoji_style || 'sometimes',
        response_delay_mode: agent.response_delay_mode || 'natural',
        response_delay_seconds: agent.response_delay_seconds || 2,
        language: agent.language || 'auto',
        languages: agent.languages || ['en', 'hi'],
        objectives: agent.objectives || ['answer_questions', 'generate_leads', 'collect_customer_info', 'create_crm_lead'],
        guardrails: agent.guardrails || ['no_hallucinations', 'protect_internal_data', 'protect_system_prompt', 'no_unauthorized_promises', 'escalate_complaints'],
        system_prompt: agent.system_prompt || '',
        ai_kb_id: agent.ai_kb_id || (knowledgeBases[0]?.id || null),
        knowledge_source_ids: agent.knowledge_source_ids || [],
        strict_knowledge_mode: agent.strict_knowledge_mode !== undefined ? Boolean(agent.strict_knowledge_mode) : true,
        fallback_reply: agent.fallback_reply || "I don't have enough verified information to answer that accurately. Would you like me to connect you with a specialist?",
        confidence_threshold: agent.confidence_threshold || 75,
        channels: agent.channels || ['whatsapp'],
        business_hours_mode: agent.business_hours_mode || 'always',
        business_hours_schedule: agent.business_hours_schedule || { days: ['mon', 'tue', 'wed', 'thu', 'fri'], start: '09:00', end: '18:00' },
        outside_hours_action: agent.outside_hours_action || 'message_only',
        human_handoff_enabled: agent.human_handoff_enabled !== undefined ? Boolean(agent.human_handoff_enabled) : true,
        human_handoff_user_id: agent.human_handoff_user_id || null,
        human_handoff_message: agent.human_handoff_message || "Thanks. I'll connect you with a member of our team right away.",
        handoff_conditions: agent.handoff_conditions || ['customer_asks_human', 'low_confidence', 'complaint_detected'],
        handoff_target_type: agent.handoff_target_type || 'team',
        handoff_target_team: agent.handoff_target_team || 'sales',
        lead_qualification_fields: agent.lead_qualification_fields || ['name', 'phone', 'requirement', 'budget'],
        crm_actions: agent.crm_actions || ['create_lead', 'update_customer', 'add_tag', 'update_lead_score'],
        crm_tag: agent.crm_tag || 'AI Inbound Lead',
        crm_lead_score_boost: agent.crm_lead_score_boost || 15,
        voice_config: agent.voice_config || { voice_id: 'en-US-Standard-C', greeting: 'Hello! Thank you for calling Growbridge.', interruption: true },
        status: agent.status || 'draft',
    } : {
        name: template?.name || 'Sales Assistant',
        agent_type: template?.agent_type || 'sales',
        purpose: template?.purpose || 'Helps customers understand products and generate qualified leads.',
        description: template?.purpose || 'Helps customers understand products and generate qualified leads.',
        tone: template?.tone || 'professional',
        response_style: 'balanced',
        emoji_style: 'sometimes',
        response_delay_mode: 'natural',
        response_delay_seconds: 2,
        language: 'auto',
        languages: ['en', 'hi', 'or', 'bn'],
        objectives: ['answer_questions', 'generate_leads', 'collect_customer_info', 'create_crm_lead', 'schedule_callback'],
        guardrails: ['no_hallucinations', 'protect_internal_data', 'protect_system_prompt', 'no_unauthorized_promises', 'escalate_complaints', 'escalate_low_confidence'],
        system_prompt: template?.system_prompt || "You are the helpful sales assistant for Growbridge Connect. Always use the verified Knowledge Base to answer business questions accurately. Never invent pricing or policies.",
        ai_kb_id: knowledgeBases[0]?.id || null,
        knowledge_source_ids: [],
        strict_knowledge_mode: true,
        fallback_reply: "I don't have enough verified information to answer that accurately. Would you like me to connect you with a specialist?",
        confidence_threshold: 75,
        channels: template?.channels || ['whatsapp'],
        business_hours_mode: 'always',
        business_hours_schedule: { days: ['mon', 'tue', 'wed', 'thu', 'fri'], start: '09:00', end: '18:00' },
        outside_hours_action: 'message_only',
        human_handoff_enabled: true,
        human_handoff_user_id: null,
        human_handoff_message: "Thanks. I'll connect you with a member of our team right away.",
        handoff_conditions: ['customer_asks_human', 'low_confidence', 'complaint_detected', 'callback_request'],
        handoff_target_type: 'team',
        handoff_target_team: 'sales',
        lead_qualification_fields: ['name', 'phone', 'requirement', 'budget'],
        crm_actions: ['create_lead', 'update_customer', 'add_tag', 'update_lead_score'],
        crm_tag: 'AI Inbound Lead',
        crm_lead_score_boost: 15,
        voice_config: { voice_id: 'en-US-Standard-C', greeting: 'Hello! Thank you for calling Growbridge.', interruption: true },
        status: 'draft',
    };

    const { data, setData, post, put, processing, errors } = useForm(initialValues);

    const toggleArrayItem = (field, value) => {
        const current = data[field] || [];
        if (current.includes(value)) {
            setData(field, current.filter(x => x !== value));
        } else {
            setData(field, [...current, value]);
        }
    };

    const handleSave = (targetStatus = null) => {
        setIsSaving(true);
        const payload = { ...data };
        if (targetStatus) payload.status = targetStatus;

        if (mode === 'create') {
            post(route('client.ai-agents.store'), {
                onSuccess: () => toast.success('AI Agent created.'),
                onError: () => toast.error('Failed to create AI agent. Please check form errors.'),
                onFinish: () => setIsSaving(false),
            });
        } else {
            put(route('client.ai-agents.update', agent.uuid), {
                onSuccess: () => toast.success('AI Agent saved.'),
                onError: () => toast.error('Failed to update AI agent.'),
                onFinish: () => setIsSaving(false),
            });
        }
    };

    const handlePublish = async () => {
        if (!agent) {
            handleSave('published');
            return;
        }
        try {
            const res = await axios.post(route('client.ai-agents.publish', agent.uuid));
            toast.success(res.data?.message || 'Agent published.');
            router.reload({ preserveScroll: true });
        } catch (e) {
            toast.error(e.response?.data?.message || 'Cannot publish agent. Check validation checklist in Publish tab.');
        }
    };

    // AI Simulator Message Send
    const handleSendSimulator = async () => {
        if (!simulatorInput.trim() || simulatorLoading) return;
        const userMsg = simulatorInput.trim();
        setSimulatorMessages(prev => [...prev, { role: 'user', content: userMsg }]);
        setSimulatorInput('');
        setSimulatorLoading(true);

        try {
            const targetUuid = agent?.uuid;
            let resData;
            if (targetUuid) {
                const res = await axios.post(route('client.ai-agents.simulate', targetUuid), { message: userMsg });
                resData = res.data;
            } else {
                // Mock test run for unsaved draft
                resData = {
                    ok: true,
                    draft_response: `[Draft Simulation] Hello! I received your inquiry about "${userMsg}". As the ${data.name}, I am configured in strict knowledge mode.`,
                    detected_intent: userMsg.toLowerCase().includes('price') ? 'pricing' : (userMsg.toLowerCase().includes('human') ? 'human_request' : 'general'),
                    confidence: 'High',
                    confidence_score: 88,
                    sources_used: [{ title: 'Company Knowledge Base', category: 'general', score: 0.9 }],
                    human_handoff: userMsg.toLowerCase().includes('human') || userMsg.toLowerCase().includes('talk to agent'),
                    handoff_reason: userMsg.toLowerCase().includes('human') ? 'Customer requested human' : null,
                    latency_ms: 240,
                    tokens: 65,
                };
            }

            setDebugInfo(resData);
            setSimulatorMessages(prev => [...prev, {
                role: 'assistant',
                content: resData.draft_response || resData.reply || 'Response generated.',
                details: resData
            }]);
        } catch (e) {
            setSimulatorMessages(prev => [...prev, { role: 'assistant', content: 'Simulation test failed.' }]);
        } finally {
            setSimulatorLoading(false);
        }
    };

    // Validation checks for publishing
    const validationChecks = [
        { label: 'Agent Name Defined', passed: Boolean(data.name?.trim()) },
        { label: 'At least one Active Channel Selected', passed: (data.channels?.length || 0) > 0 },
        { label: 'Instructions / Purpose Configured', passed: Boolean(data.purpose?.trim() || data.system_prompt?.trim()) },
        { label: 'Knowledge Base Connected', passed: Boolean(data.ai_kb_id) },
        { label: 'Human Handoff Fallback Defined', passed: Boolean(data.human_handoff_message?.trim()) },
    ];

    const allPassed = validationChecks.every(c => c.passed);

    return (
        <ClientLayout>
            <Head title={`${mode === 'create' ? 'Create AI Agent' : `Edit: ${data.name}`} — Studio`} />

            <div className="space-y-5">
                {/* Studio Top Navigation Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-2xl bg-white dark:bg-neutral-800/70 border border-neutral-200 dark:border-neutral-700/60 shadow-2xs">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('client.ai-agents.index')}
                            className="p-2 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-700 text-neutral-600 transition"
                        >
                            <ArrowLeft className="w-4 h-4" />
                        </Link>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-base font-bold text-neutral-900 dark:text-white">
                                    {data.name || 'New AI Agent'}
                                </h1>
                                <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                                    data.status === 'published' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' :
                                    data.status === 'testing' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' :
                                    data.status === 'paused' ? 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700' :
                                    'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
                                }`}>
                                    {data.status}
                                </span>
                                {agent && (
                                    <span className="font-mono text-[11px] font-semibold text-neutral-400">
                                        v{agent.version || 1}
                                    </span>
                                )}
                            </div>
                            <p className="text-[11px] text-neutral-400 mt-0.5">
                                No-Code AI Prompt Studio & Multi-Channel Orchestrator
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => handleSave('draft')}
                            disabled={isSaving || processing}
                            className="px-3.5 py-1.5 rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 text-xs font-bold hover:bg-neutral-50 transition"
                        >
                            Save Draft
                        </button>
                        <button
                            onClick={() => setActiveTab('testing')}
                            className="px-3.5 py-1.5 rounded-xl bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 text-xs font-bold hover:bg-purple-100 transition flex items-center gap-1.5"
                        >
                            <Play className="w-3.5 h-3.5" /> Test Agent
                        </button>
                        <button
                            onClick={handlePublish}
                            disabled={isSaving || processing}
                            className="px-4 py-1.5 rounded-xl bg-brand-600 text-white text-xs font-bold hover:bg-brand-700 shadow-xs transition flex items-center gap-1.5"
                        >
                            <Check className="w-4 h-4" /> Publish Agent
                        </button>
                    </div>
                </div>

                {/* 8-Step Studio Navigation Tabs */}
                <div className="flex items-center gap-1 overflow-x-auto pb-1 border-b border-neutral-200 dark:border-neutral-800 text-xs">
                    {[
                        { id: 'basic', label: '1 Basic Info', icon: Bot },
                        { id: 'personality', label: '2 Personality', icon: Sliders },
                        { id: 'instructions', label: '3 Instructions', icon: Sparkles },
                        { id: 'knowledge', label: '4 Knowledge', icon: BookOpen },
                        { id: 'channels', label: '5 Channels', icon: Zap },
                        { id: 'automation', label: '6 Automation & CRM', icon: Layers },
                        { id: 'testing', label: '7 Test Simulator', icon: Play },
                        { id: 'publish', label: '8 Publish & Version', icon: CheckCircle2 },
                    ].map(step => {
                        const Icon = step.icon;
                        return (
                            <button
                                key={step.id}
                                onClick={() => setActiveTab(step.id)}
                                className={`px-3 py-2 rounded-xl font-bold whitespace-nowrap transition flex items-center gap-1.5 ${
                                    activeTab === step.id
                                        ? 'bg-brand-600 text-white shadow-2xs'
                                        : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                                }`}
                            >
                                <Icon className="w-3.5 h-3.5" />
                                <span>{step.label}</span>
                            </button>
                        );
                    })}
                </div>

                {/* ─── STEP 1: Basic Information ─── */}
                {activeTab === 'basic' && (
                    <div className="bg-white dark:bg-neutral-800/70 rounded-2xl border border-neutral-200 dark:border-neutral-700/60 p-6 space-y-5 text-xs max-w-3xl">
                        <div>
                            <h2 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                <Bot className="w-4 h-4 text-brand-600" /> Basic Agent Information
                            </h2>
                            <p className="text-neutral-500 mt-0.5">Name your agent and declare its primary functional role.</p>
                        </div>

                        <div className="space-y-4">
                            <div>
                                <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Agent Name</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={e => setData('name', e.target.value)}
                                    placeholder="e.g. Sales Assistant"
                                    className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 font-semibold text-neutral-900 dark:text-white focus:outline-none"
                                    required
                                />
                                {errors.name && <p className="text-red-500 text-[11px] mt-1">{errors.name}</p>}
                            </div>

                            <div>
                                <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Description / Primary Purpose</label>
                                <textarea
                                    value={data.purpose}
                                    onChange={e => setData('purpose', e.target.value)}
                                    rows={3}
                                    placeholder="e.g. Helps customers understand products, answers pricing inquiries, and qualifies leads."
                                    className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 text-neutral-900 dark:text-white focus:outline-none"
                                />
                            </div>

                            <div>
                                <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-2">Agent Type</label>
                                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    {[
                                        { id: 'sales', label: '💼 Sales & Inbound', desc: 'Qualify leads, share pricing, book demos' },
                                        { id: 'support', label: '🛠️ Customer Support', desc: 'Troubleshoot, policy & order help' },
                                        { id: 'receptionist', label: '📞 Receptionist', desc: 'Front-desk routing & business hours' },
                                        { id: 'appointment', label: '📅 Appointment', desc: 'Book consultations & demo slots' },
                                        { id: 'custom', label: '⚡ Custom Agent', desc: 'Custom configured workflow' },
                                    ].map(type => (
                                        <button
                                            key={type.id}
                                            type="button"
                                            onClick={() => setData('agent_type', type.id)}
                                            className={`p-3 rounded-xl border text-left transition ${
                                                data.agent_type === type.id
                                                    ? 'border-brand-500 bg-brand-50/40 dark:bg-brand-950/40 text-brand-900 dark:text-white'
                                                    : 'border-neutral-200 dark:border-neutral-700 bg-neutral-50/50 dark:bg-neutral-900/30 text-neutral-600 dark:text-neutral-400'
                                            }`}
                                        >
                                            <span className="font-bold block text-xs">{type.label}</span>
                                            <span className="text-[10px] text-neutral-500 block mt-0.5">{type.desc}</span>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-end pt-3">
                            <button
                                type="button"
                                onClick={() => setActiveTab('personality')}
                                className="px-4 py-2 rounded-xl bg-brand-600 text-white font-bold hover:bg-brand-700 flex items-center gap-1"
                            >
                                Next: Personality <ChevronRight className="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                )}

                {/* ─── STEP 2: Personality & Response Style ─── */}
                {activeTab === 'personality' && (
                    <div className="bg-white dark:bg-neutral-800/70 rounded-2xl border border-neutral-200 dark:border-neutral-700/60 p-6 space-y-5 text-xs max-w-3xl">
                        <div>
                            <h2 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                <Sliders className="w-4 h-4 text-brand-600" /> Personality & Style Controls
                            </h2>
                            <p className="text-neutral-500 mt-0.5">Define conversational tone, brevity, language, and emoji usage without complex sliders.</p>
                        </div>

                        <div className="space-y-4">
                            {/* Tone */}
                            <div>
                                <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-2">Conversational Tone</label>
                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    {[
                                        { id: 'professional', label: 'Professional', desc: 'Polite, clear, business-grade' },
                                        { id: 'friendly', label: 'Friendly', desc: 'Warm, welcoming, conversational' },
                                        { id: 'casual', label: 'Casual', desc: 'Relaxed, modern, upbeat' },
                                        { id: 'formal', label: 'Formal', desc: 'Strictly official, enterprise' },
                                    ].map(t => (
                                        <button
                                            key={t.id}
                                            type="button"
                                            onClick={() => setData('tone', t.id)}
                                            className={`p-3 rounded-xl border text-left transition ${
                                                data.tone === t.id
                                                    ? 'border-brand-500 bg-brand-50/50 dark:bg-brand-950/40 text-brand-900 dark:text-white font-bold'
                                                    : 'border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 text-neutral-600 dark:text-neutral-400'
                                            }`}
                                        >
                                            <span className="block">{t.label}</span>
                                            <span className="text-[10px] text-neutral-400 font-normal">{t.desc}</span>
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Response Style */}
                            <div>
                                <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-2">Response Brevity & Length</label>
                                <div className="grid grid-cols-3 gap-2">
                                    {[
                                        { id: 'short', label: 'Short & Direct', desc: '1-2 sentences. One question at a time' },
                                        { id: 'balanced', label: 'Balanced', desc: '2-4 sentences. Natural conversation' },
                                        { id: 'detailed', label: 'Detailed', desc: 'Explanatory, structured bullet points' },
                                    ].map(s => (
                                        <button
                                            key={s.id}
                                            type="button"
                                            onClick={() => setData('response_style', s.id)}
                                            className={`p-3 rounded-xl border text-left transition ${
                                                data.response_style === s.id
                                                    ? 'border-brand-500 bg-brand-50/50 dark:bg-brand-950/40 text-brand-900 dark:text-white font-bold'
                                                    : 'border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 text-neutral-600 dark:text-neutral-400'
                                            }`}
                                        >
                                            <span className="block">{s.label}</span>
                                            <span className="text-[10px] text-neutral-400 font-normal">{s.desc}</span>
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Languages */}
                            <div>
                                <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-2">Active Languages</label>
                                <div className="flex flex-wrap gap-2">
                                    {[
                                        { id: 'en', label: 'English' },
                                        { id: 'hi', label: 'Hindi' },
                                        { id: 'or', label: 'Odia' },
                                        { id: 'bn', label: 'Bengali' },
                                        { id: 'es', label: 'Spanish' },
                                        { id: 'ar', label: 'Arabic' },
                                    ].map(lang => {
                                        const isSelected = (data.languages || []).includes(lang.id);
                                        return (
                                            <button
                                                key={lang.id}
                                                type="button"
                                                onClick={() => toggleArrayItem('languages', lang.id)}
                                                className={`px-3 py-1.5 rounded-xl border text-xs font-semibold transition ${
                                                    isSelected
                                                        ? 'bg-brand-600 text-white border-brand-600'
                                                        : 'border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 text-neutral-700 dark:text-neutral-300'
                                                }`}
                                            >
                                                {isSelected ? `✓ ${lang.label}` : `+ ${lang.label}`}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Emojis & Response Delay */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Emoji Usage</label>
                                    <select
                                        value={data.emoji_style}
                                        onChange={e => setData('emoji_style', e.target.value)}
                                        className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 text-neutral-900 dark:text-white"
                                    >
                                        <option value="never">Never (Strictly no emojis)</option>
                                        <option value="sometimes">Sometimes (Subtle & natural)</option>
                                        <option value="often">Often (Friendly & expressive)</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">AI Response Delay</label>
                                    <select
                                        value={data.response_delay_mode}
                                        onChange={e => setData('response_delay_mode', e.target.value)}
                                        className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 text-neutral-900 dark:text-white"
                                    >
                                        <option value="instant">Instant (Immediate reply)</option>
                                        <option value="natural">Natural (~2s human pacing)</option>
                                        <option value="custom">Custom configured delay</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-between pt-3">
                            <button
                                type="button"
                                onClick={() => setActiveTab('basic')}
                                className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-600 hover:bg-neutral-100"
                            >
                                Back
                            </button>
                            <button
                                type="button"
                                onClick={() => setActiveTab('instructions')}
                                className="px-4 py-2 rounded-xl bg-brand-600 text-white font-bold hover:bg-brand-700 flex items-center gap-1"
                            >
                                Next: Instructions <ChevronRight className="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                )}

                {/* ─── STEP 3: Instructions, Objectives & Guardrails ─── */}
                {activeTab === 'instructions' && (
                    <div className="bg-white dark:bg-neutral-800/70 rounded-2xl border border-neutral-200 dark:border-neutral-700/60 p-6 space-y-6 text-xs max-w-3xl">
                        <div className="flex items-center justify-between">
                            <div>
                                <h2 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                    <Sparkles className="w-4 h-4 text-brand-600" /> Objectives & Guardrails
                                </h2>
                                <p className="text-neutral-500 mt-0.5">Control what the agent accomplishes and enforce strict safety guardrails.</p>
                            </div>
                            <div className="flex items-center gap-1 bg-neutral-100 dark:bg-neutral-900 p-1 rounded-xl">
                                <button
                                    type="button"
                                    onClick={() => setInstructionMode('basic')}
                                    className={`px-3 py-1 rounded-lg font-bold text-xs ${
                                        instructionMode === 'basic' ? 'bg-white dark:bg-neutral-800 shadow-2xs text-brand-600' : 'text-neutral-500'
                                    }`}
                                >
                                    Basic Mode
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setInstructionMode('advanced')}
                                    className={`px-3 py-1 rounded-lg font-bold text-xs ${
                                        instructionMode === 'advanced' ? 'bg-white dark:bg-neutral-800 shadow-2xs text-brand-600' : 'text-neutral-500'
                                    }`}
                                >
                                    Advanced Mode
                                </button>
                            </div>
                        </div>

                        {instructionMode === 'basic' ? (
                            <div className="space-y-5">
                                {/* Objectives */}
                                <div>
                                    <label className="block font-bold text-neutral-800 dark:text-neutral-200 mb-2">Core Agent Objectives</label>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        {[
                                            { id: 'answer_questions', label: 'Answer business questions accurately' },
                                            { id: 'generate_leads', label: 'Identify customer interest & generate leads' },
                                            { id: 'collect_customer_info', label: 'Collect customer name & contact details' },
                                            { id: 'offer_demo', label: 'Offer product demo or consultation' },
                                            { id: 'create_crm_lead', label: 'Create CRM contact & pipeline lead' },
                                            { id: 'schedule_callback', label: 'Schedule human callback' },
                                        ].map(obj => {
                                            const isChecked = (data.objectives || []).includes(obj.id);
                                            return (
                                                <label key={obj.id} className="flex items-center gap-2.5 p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900/40 cursor-pointer hover:bg-neutral-100">
                                                    <input
                                                        type="checkbox"
                                                        checked={isChecked}
                                                        onChange={() => toggleArrayItem('objectives', obj.id)}
                                                        className="rounded text-brand-600"
                                                    />
                                                    <span className="font-semibold text-neutral-800 dark:text-neutral-200">{obj.label}</span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                </div>

                                {/* Guardrails */}
                                <div>
                                    <label className="block font-bold text-neutral-800 dark:text-neutral-200 mb-2">AI Safety & Guardrails</label>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        {[
                                            { id: 'no_hallucinations', label: "Don't invent pricing or policies" },
                                            { id: 'protect_internal_data', label: "Don't expose internal company data" },
                                            { id: 'protect_system_prompt', label: "Don't reveal system instructions" },
                                            { id: 'no_unauthorized_promises', label: "Don't make unauthorized commitments" },
                                            { id: 'escalate_complaints', label: "Escalate angry complaints to human" },
                                            { id: 'escalate_low_confidence', label: "Escalate low-confidence questions" },
                                        ].map(g => {
                                            const isChecked = (data.guardrails || []).includes(g.id);
                                            return (
                                                <label key={g.id} className="flex items-center gap-2.5 p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900/40 cursor-pointer hover:bg-neutral-100">
                                                    <input
                                                        type="checkbox"
                                                        checked={isChecked}
                                                        onChange={() => toggleArrayItem('guardrails', g.id)}
                                                        className="rounded text-brand-600"
                                                    />
                                                    <span className="font-semibold text-neutral-800 dark:text-neutral-200">{g.label}</span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                <label className="block font-bold text-neutral-800 dark:text-neutral-200">
                                    Direct System Prompt (Advanced)
                                </label>
                                <textarea
                                    value={data.system_prompt}
                                    onChange={e => setData('system_prompt', e.target.value)}
                                    rows={8}
                                    className="w-full p-3 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 font-mono text-xs text-neutral-900 dark:text-white focus:outline-none"
                                />
                                <p className="text-[11px] text-neutral-400">
                                    In advanced mode, these explicit instructions take priority while retaining anti-prompt-injection defense.
                                </p>
                            </div>
                        )}

                        <div className="flex justify-between pt-3">
                            <button
                                type="button"
                                onClick={() => setActiveTab('personality')}
                                className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-600 hover:bg-neutral-100"
                            >
                                Back
                            </button>
                            <button
                                type="button"
                                onClick={() => setActiveTab('knowledge')}
                                className="px-4 py-2 rounded-xl bg-brand-600 text-white font-bold hover:bg-brand-700 flex items-center gap-1"
                            >
                                Next: Knowledge Base <ChevronRight className="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                )}

                {/* ─── STEP 4: Knowledge Base (Task #80 Integration) ─── */}
                {activeTab === 'knowledge' && (
                    <div className="bg-white dark:bg-neutral-800/70 rounded-2xl border border-neutral-200 dark:border-neutral-700/60 p-6 space-y-5 text-xs max-w-3xl">
                        <div>
                            <h2 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                <BookOpen className="w-4 h-4 text-brand-600" /> Knowledge Connection
                            </h2>
                            <p className="text-neutral-500 mt-0.5">
                                Select which business knowledge base and documents this agent is authorized to search.
                            </p>
                        </div>

                        <div className="space-y-4">
                            <div>
                                <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Knowledge Base</label>
                                <select
                                    value={data.ai_kb_id || ''}
                                    onChange={e => setData('ai_kb_id', Number(e.target.value) || null)}
                                    className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 text-neutral-900 dark:text-white"
                                >
                                    {knowledgeBases.map(kb => (
                                        <option key={kb.id} value={kb.id}>
                                            {kb.name} ({kb.documents_count || kb.documents?.length || 0} Sources)
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50/50 dark:bg-neutral-900/30 space-y-3">
                                <label className="flex items-center justify-between cursor-pointer">
                                    <div>
                                        <span className="font-bold text-neutral-900 dark:text-white block">Strict Anti-Hallucination Mode</span>
                                        <span className="text-[11px] text-neutral-500 block">
                                            The agent will exclusively answer from verified sources and politely escalate if answer is missing.
                                        </span>
                                    </div>
                                    <input
                                        type="checkbox"
                                        checked={data.strict_knowledge_mode}
                                        onChange={e => setData('strict_knowledge_mode', e.target.checked)}
                                        className="rounded text-brand-600 scale-125"
                                    />
                                </label>

                                {data.strict_knowledge_mode && (
                                    <div>
                                        <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">No-Knowledge Fallback Response</label>
                                        <textarea
                                            value={data.fallback_reply}
                                            onChange={e => setData('fallback_reply', e.target.value)}
                                            rows={2}
                                            className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white"
                                        />
                                    </div>
                                )}
                            </div>

                            <div className="flex items-center justify-between p-3 rounded-xl border border-blue-200 dark:border-blue-900/40 bg-blue-50/40 dark:bg-blue-950/20 text-[11px]">
                                <span className="text-blue-700 dark:text-blue-300">
                                    Need to upload new PDFs, FAQs, or websites?
                                </span>
                                <Link
                                    href={route('client.ai.knowledge.index')}
                                    className="font-bold text-blue-600 hover:underline flex items-center gap-1"
                                >
                                    Open Knowledge Base <ExternalLink className="w-3 h-3" />
                                </Link>
                            </div>
                        </div>

                        <div className="flex justify-between pt-3">
                            <button
                                type="button"
                                onClick={() => setActiveTab('instructions')}
                                className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-600 hover:bg-neutral-100"
                            >
                                Back
                            </button>
                            <button
                                type="button"
                                onClick={() => setActiveTab('channels')}
                                className="px-4 py-2 rounded-xl bg-brand-600 text-white font-bold hover:bg-brand-700 flex items-center gap-1"
                            >
                                Next: Channels <ChevronRight className="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                )}

                {/* ─── STEP 5: Channels Assignment ─── */}
                {activeTab === 'channels' && (
                    <div className="bg-white dark:bg-neutral-800/70 rounded-2xl border border-neutral-200 dark:border-neutral-700/60 p-6 space-y-5 text-xs max-w-3xl">
                        <div>
                            <h2 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                <Zap className="w-4 h-4 text-brand-600" /> Channel Deployment
                            </h2>
                            <p className="text-neutral-500 mt-0.5">Select the channels where this agent will actively listen and reply.</p>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {[
                                { id: 'whatsapp', name: 'WhatsApp Official API', icon: MessageSquare, desc: 'High-volume messaging & interactive buttons' },
                                { id: 'voice', name: 'AI Voice & Phone (Twilio)', icon: Phone, desc: 'Inbound & Outbound phone call conversations' },
                                { id: 'messenger', name: 'Facebook Messenger', icon: Zap, desc: 'Page inbox direct messaging' },
                                { id: 'instagram', name: 'Instagram Direct', icon: Instagram, desc: 'DMs & story mentions support' },
                                { id: 'email', name: 'Email Inbound & Outbound', icon: Mail, desc: 'Customer support tickets & email replies' },
                            ].map(ch => {
                                const isChecked = (data.channels || []).includes(ch.id);
                                const Icon = ch.icon;
                                return (
                                    <label
                                        key={ch.id}
                                        className={`flex items-start gap-3 p-4 rounded-xl border cursor-pointer transition ${
                                            isChecked
                                                ? 'border-brand-500 bg-brand-50/40 dark:bg-brand-950/40'
                                                : 'border-neutral-200 dark:border-neutral-700 bg-neutral-50/50 dark:bg-neutral-900/30'
                                        }`}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={isChecked}
                                            onChange={() => toggleArrayItem('channels', ch.id)}
                                            className="mt-0.5 rounded text-brand-600 scale-110"
                                        />
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <Icon className="w-4 h-4 text-brand-600" />
                                                <span className="font-bold text-neutral-900 dark:text-white">{ch.name}</span>
                                            </div>
                                            <span className="text-[11px] text-neutral-500 block mt-1">{ch.desc}</span>
                                        </div>
                                    </label>
                                );
                            })}
                        </div>

                        <div className="flex justify-between pt-3">
                            <button
                                type="button"
                                onClick={() => setActiveTab('knowledge')}
                                className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-600 hover:bg-neutral-100"
                            >
                                Back
                            </button>
                            <button
                                type="button"
                                onClick={() => setActiveTab('automation')}
                                className="px-4 py-2 rounded-xl bg-brand-600 text-white font-bold hover:bg-brand-700 flex items-center gap-1"
                            >
                                Next: Automation & CRM <ChevronRight className="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                )}

                {/* ─── STEP 6: Automation, Business Hours, Human Handoff & CRM ─── */}
                {activeTab === 'automation' && (
                    <div className="bg-white dark:bg-neutral-800/70 rounded-2xl border border-neutral-200 dark:border-neutral-700/60 p-6 space-y-6 text-xs max-w-3xl">
                        <div>
                            <h2 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                <Layers className="w-4 h-4 text-brand-600" /> Business Hours & Human Handoff
                            </h2>
                            <p className="text-neutral-500 mt-0.5">Configure operational schedules, human transfer triggers, and lead qualification.</p>
                        </div>

                        {/* Business Hours */}
                        <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50/50 dark:bg-neutral-900/30 space-y-3">
                            <label className="block font-bold text-neutral-900 dark:text-white">AI Availability</label>
                            <div className="grid grid-cols-2 gap-2">
                                <label className="flex items-center gap-2 p-2.5 rounded-lg border border-neutral-200 dark:border-neutral-700 cursor-pointer">
                                    <input
                                        type="radio"
                                        name="business_hours_mode"
                                        value="always"
                                        checked={data.business_hours_mode === 'always'}
                                        onChange={e => setData('business_hours_mode', e.target.value)}
                                        className="text-brand-600"
                                    />
                                    <span className="font-semibold text-neutral-800 dark:text-neutral-200">Always Available (24/7)</span>
                                </label>
                                <label className="flex items-center gap-2 p-2.5 rounded-lg border border-neutral-200 dark:border-neutral-700 cursor-pointer">
                                    <input
                                        type="radio"
                                        name="business_hours_mode"
                                        value="business_hours"
                                        checked={data.business_hours_mode === 'business_hours'}
                                        onChange={e => setData('business_hours_mode', e.target.value)}
                                        className="text-brand-600"
                                    />
                                    <span className="font-semibold text-neutral-800 dark:text-neutral-200">Business Hours Only (Mon-Fri)</span>
                                </label>
                            </div>
                        </div>

                        {/* Human Handoff */}
                        <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50/50 dark:bg-neutral-900/30 space-y-3">
                            <label className="block font-bold text-neutral-900 dark:text-white">Human Handoff Triggers</label>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                {[
                                    { id: 'customer_asks_human', label: 'Customer asks for human agent' },
                                    { id: 'low_confidence', label: 'AI confidence score is low' },
                                    { id: 'complaint_detected', label: 'Customer grievance or complaint' },
                                    { id: 'high_value_lead', label: 'High-value lead budget identified' },
                                    { id: 'callback_request', label: 'Customer requests callback' },
                                ].map(h => (
                                    <label key={h.id} className="flex items-center gap-2 p-2.5 rounded-lg border border-neutral-200 dark:border-neutral-700 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={(data.handoff_conditions || []).includes(h.id)}
                                            onChange={() => toggleArrayItem('handoff_conditions', h.id)}
                                            className="rounded text-brand-600"
                                        />
                                        <span className="font-semibold text-neutral-800 dark:text-neutral-200">{h.label}</span>
                                    </label>
                                ))}
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                                <div>
                                    <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Route Handoff To</label>
                                    <select
                                        value={data.handoff_target_team}
                                        onChange={e => setData('handoff_target_team', e.target.value)}
                                        className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white"
                                    >
                                        <option value="sales">Sales Team</option>
                                        <option value="support">Support Team</option>
                                        <option value="general">General Team</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Handoff Message</label>
                                    <input
                                        type="text"
                                        value={data.human_handoff_message}
                                        onChange={e => setData('human_handoff_message', e.target.value)}
                                        className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Lead Qualification & CRM */}
                        <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50/50 dark:bg-neutral-900/30 space-y-3">
                            <label className="block font-bold text-neutral-900 dark:text-white">Lead Qualification Target Fields</label>
                            <div className="flex flex-wrap gap-2">
                                {['name', 'phone', 'company', 'requirement', 'budget', 'timeline'].map(f => {
                                    const isChecked = (data.lead_qualification_fields || []).includes(f);
                                    return (
                                        <button
                                            key={f}
                                            type="button"
                                            onClick={() => toggleArrayItem('lead_qualification_fields', f)}
                                            className={`px-3 py-1.5 rounded-xl border text-xs font-semibold capitalize transition ${
                                                isChecked
                                                    ? 'bg-purple-600 text-white border-purple-600'
                                                    : 'border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300'
                                            }`}
                                        >
                                            {isChecked ? `✓ ${f}` : `+ ${f}`}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="flex justify-between pt-3">
                            <button
                                type="button"
                                onClick={() => setActiveTab('channels')}
                                className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-600 hover:bg-neutral-100"
                            >
                                Back
                            </button>
                            <button
                                type="button"
                                onClick={() => setActiveTab('testing')}
                                className="px-4 py-2 rounded-xl bg-brand-600 text-white font-bold hover:bg-brand-700 flex items-center gap-1"
                            >
                                Next: AI Simulator <ChevronRight className="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                )}

                {/* ─── STEP 7: AI Simulator & Debug Mode ─── */}
                {activeTab === 'testing' && (
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        {/* Chat Simulator Window */}
                        <div className="lg:col-span-2 bg-white dark:bg-neutral-800/70 rounded-2xl border border-neutral-200 dark:border-neutral-700/60 p-4 flex flex-col h-[520px]">
                            <div className="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800">
                                <div className="flex items-center gap-2">
                                    <Sparkles className="w-4 h-4 text-purple-500" />
                                    <h3 className="text-xs font-bold text-neutral-900 dark:text-white">AI Agent Simulator</h3>
                                    <span className="px-2 py-0.5 rounded-full text-[10px] bg-purple-50 dark:bg-purple-950/40 text-purple-600 font-bold">
                                        🔒 Zero-Production Safe
                                    </span>
                                </div>
                                <button
                                    onClick={() => setSimulatorMessages([])}
                                    className="text-[11px] text-neutral-400 hover:text-neutral-600 flex items-center gap-1"
                                >
                                    <RefreshCw className="w-3 h-3" /> Clear
                                </button>
                            </div>

                            {/* Chat Messages */}
                            <div className="flex-1 overflow-y-auto p-3 space-y-3 text-xs">
                                {simulatorMessages.length === 0 ? (
                                    <div className="text-center py-16 space-y-2 text-neutral-400">
                                        <Bot className="w-8 h-8 mx-auto text-neutral-300" />
                                        <p className="font-semibold text-neutral-600 dark:text-neutral-300">Test Your AI Agent</p>
                                        <p className="text-[11px]">Type sample customer inquiries like "What is your pricing?" or "I want to speak with a human."</p>
                                    </div>
                                ) : (
                                    simulatorMessages.map((m, idx) => (
                                        <div
                                            key={idx}
                                            className={`flex ${m.role === 'user' ? 'justify-end' : 'justify-start'}`}
                                        >
                                            <div className={`p-3 rounded-2xl max-w-[80%] space-y-1 ${
                                                m.role === 'user'
                                                    ? 'bg-brand-600 text-white rounded-tr-xs'
                                                    : 'bg-neutral-100 dark:bg-neutral-900 text-neutral-800 dark:text-neutral-200 rounded-tl-xs'
                                            }`}>
                                                <p className="whitespace-pre-wrap">{m.content}</p>
                                                {m.details?.sources_used?.length > 0 && (
                                                    <div className="text-[10px] opacity-75 pt-1 border-t border-black/10 dark:border-white/10 flex items-center gap-1">
                                                        <BookOpen className="w-3 h-3" /> Source: {m.details.sources_used[0].title}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                )}
                                {simulatorLoading && (
                                    <div className="flex justify-start">
                                        <div className="p-3 rounded-2xl bg-neutral-100 dark:bg-neutral-900 text-neutral-500 rounded-tl-xs flex items-center gap-2">
                                            <span className="w-2 h-2 rounded-full bg-brand-500 animate-bounce"></span>
                                            <span className="w-2 h-2 rounded-full bg-brand-500 animate-bounce delay-100"></span>
                                            <span className="w-2 h-2 rounded-full bg-brand-500 animate-bounce delay-200"></span>
                                            <span className="text-[11px]">AI is formulating response...</span>
                                        </div>
                                    </div>
                                )}
                                <div ref={simBottomRef} />
                            </div>

                            {/* Chat Input */}
                            <div className="pt-3 border-t border-neutral-100 dark:border-neutral-800 flex items-center gap-2">
                                <input
                                    type="text"
                                    value={simulatorInput}
                                    onChange={e => setSimulatorInput(e.target.value)}
                                    onKeyDown={e => { if (e.key === 'Enter') handleSendSimulator(); }}
                                    placeholder="Type a test customer message..."
                                    className="flex-1 p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 text-xs focus:outline-none"
                                />
                                <button
                                    onClick={handleSendSimulator}
                                    disabled={simulatorLoading || !simulatorInput.trim()}
                                    className="p-2.5 rounded-xl bg-brand-600 text-white font-bold hover:bg-brand-700 disabled:opacity-50"
                                >
                                    <Send className="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        {/* Debug Information Panel */}
                        <div className="bg-white dark:bg-neutral-800/70 rounded-2xl border border-neutral-200 dark:border-neutral-700/60 p-4 flex flex-col h-[520px] overflow-y-auto space-y-4 text-xs">
                            <div className="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-2">
                                <h3 className="font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                                    <Cpu className="w-4 h-4 text-brand-600" /> Live Debug Insights
                                </h3>
                                <span className="text-[10px] text-neutral-400 font-bold uppercase">Internal Audit</span>
                            </div>

                            {debugInfo ? (
                                <div className="space-y-3">
                                    <div className="p-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-1">
                                        <span className="text-[10px] font-bold text-neutral-400 uppercase">Detected Intent</span>
                                        <div className="flex items-center justify-between font-bold text-neutral-900 dark:text-white">
                                            <span className="capitalize">{debugInfo.detected_intent}</span>
                                            <span className="text-brand-600">{debugInfo.confidence} ({debugInfo.confidence_score}%)</span>
                                        </div>
                                    </div>

                                    <div className="p-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-1">
                                        <span className="text-[10px] font-bold text-neutral-400 uppercase">Knowledge Chunks Retrieved</span>
                                        <div className="font-mono font-bold text-neutral-800 dark:text-neutral-200">
                                            {debugInfo.knowledge_used?.length || 0} Chunks
                                        </div>
                                        {debugInfo.sources_used?.map((s, i) => (
                                            <p key={i} className="text-[11px] text-neutral-500 truncate" title={s.title}>
                                                📄 {s.title}
                                            </p>
                                        ))}
                                    </div>

                                    <div className="p-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-1">
                                        <span className="text-[10px] font-bold text-neutral-400 uppercase">Human Handoff Trigger</span>
                                        <div className="flex items-center gap-1.5 font-bold">
                                            {debugInfo.human_handoff ? (
                                                <span className="text-red-600 flex items-center gap-1">⚠️ Yes ({debugInfo.handoff_reason})</span>
                                            ) : (
                                                <span className="text-emerald-600 flex items-center gap-1">✓ No (Handled by AI)</span>
                                            )}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-2 text-center text-[11px]">
                                        <div className="p-2 rounded-lg bg-neutral-50 dark:bg-neutral-900">
                                            <span className="text-neutral-400 block">Latency</span>
                                            <span className="font-bold text-neutral-900 dark:text-white">{debugInfo.latency_ms}ms</span>
                                        </div>
                                        <div className="p-2 rounded-lg bg-neutral-50 dark:bg-neutral-900">
                                            <span className="text-neutral-400 block">Tokens</span>
                                            <span className="font-bold text-neutral-900 dark:text-white">~{debugInfo.tokens}</span>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <p className="text-neutral-400 text-center py-12">
                                    Send a message in the simulator to view intent classification, chunk retrieval, and handoff triggers.
                                </p>
                            )}
                        </div>
                    </div>
                )}

                {/* ─── STEP 8: Publish & Versioning ─── */}
                {activeTab === 'publish' && (
                    <div className="bg-white dark:bg-neutral-800/70 rounded-2xl border border-neutral-200 dark:border-neutral-700/60 p-6 space-y-6 text-xs max-w-2xl">
                        <div>
                            <h2 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                <Award className="w-4 h-4 text-brand-600" /> Pre-Publish Validation Checklist
                            </h2>
                            <p className="text-neutral-500 mt-0.5">Ensure all essential configurations are ready before going live.</p>
                        </div>

                        <div className="space-y-2">
                            {validationChecks.map((chk, i) => (
                                <div key={i} className="flex items-center justify-between p-3 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50/50 dark:bg-neutral-900/30">
                                    <span className="font-semibold text-neutral-800 dark:text-neutral-200">{chk.label}</span>
                                    {chk.passed ? (
                                        <span className="text-emerald-600 font-bold flex items-center gap-1">✓ Ready</span>
                                    ) : (
                                        <span className="text-amber-600 font-bold flex items-center gap-1">⚠️ Required</span>
                                    )}
                                </div>
                            ))}
                        </div>

                        <div className="p-4 rounded-xl border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/40 dark:bg-emerald-950/20 space-y-2">
                            <h4 className="font-bold text-emerald-900 dark:text-emerald-200">Lifecycle & Versioning</h4>
                            <p className="text-[11px] text-emerald-700 dark:text-emerald-300">
                                Publishing transitions the agent to <strong>Published (v{agent ? (agent.version || 1) : 1})</strong> and activates it on connected channels. You can pause or create drafts at any time.
                            </p>
                        </div>

                        <div className="flex justify-end gap-3 pt-2">
                            <button
                                type="button"
                                onClick={() => handleSave('draft')}
                                className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 font-bold hover:bg-neutral-50"
                            >
                                Save as Draft
                            </button>
                            <button
                                type="button"
                                onClick={handlePublish}
                                disabled={!allPassed}
                                className="px-5 py-2 rounded-xl bg-brand-600 text-white font-bold hover:bg-brand-700 disabled:opacity-50 shadow-sm"
                            >
                                Publish AI Agent
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </ClientLayout>
    );
}
