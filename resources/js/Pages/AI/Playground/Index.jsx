import { Head, router, useForm } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import {
    Bot, Sparkles, Send, RefreshCw, ThumbsUp, ThumbsDown, CheckCircle2,
    AlertCircle, Clock, Zap, Shield, BookOpen, Layers, Check, X,
    ChevronDown, ChevronUp, ArrowRight, MessageSquare, Phone, Mail,
    HelpCircle, Building2, Package, Globe, FileText, CheckCircle
} from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';

const QUICK_TESTS = [
    'What are your business hours?',
    'What products do you sell?',
    'What is the price?',
    'Do you provide delivery?',
    'Where are you located?',
    'I want to talk to a human.',
    'Who is the Prime Minister of Canada?',
];

export default function PlaygroundIndex({
    chatbots = [],
    knowledgeBases = [],
    defaultAgentId = null,
}) {
    const { t } = useTranslation();
    const [selectedAgentId, setSelectedAgentId] = useState(defaultAgentId || chatbots[0]?.id || '');
    const [selectedChannel, setSelectedChannel] = useState('whatsapp');
    const [messages, setMessages] = useState([]);
    const [inputMessage, setInputMessage] = useState('');
    const [isSending, setIsSending] = useState(false);
    const [lastTrace, setLastTrace] = useState(null);
    const [showAdvancedTrace, setShowAdvancedTrace] = useState(false);
    const [showActivateModal, setShowActivateModal] = useState(false);

    // Feedback state
    const [activeFeedbackMsgIndex, setActiveFeedbackMsgIndex] = useState(null);
    const [feedbackNotes, setFeedbackNotes] = useState('');
    const [selectedFixes, setSelectedFixes] = useState([]);

    const messagesEndRef = useRef(null);

    const activeAgent = chatbots.find(c => String(c.id) === String(selectedAgentId)) || chatbots[0];

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const handleSend = async (messageText) => {
        const textToSend = (messageText || inputMessage).trim();
        if (!textToSend || !activeAgent || isSending) return;

        setInputMessage('');

        const userMsg = {
            id: Date.now(),
            role: 'user',
            text: textToSend,
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        };

        setMessages(prev => [...prev, userMsg]);
        setIsSending(true);

        try {
            const res = await window.axios.post(route('client.ai.playground.test'), {
                ai_agent_id: activeAgent.id,
                message: textToSend,
                channel: selectedChannel,
            });

            if (res.data && res.data.ok) {
                const aiMsg = {
                    id: Date.now() + 1,
                    role: 'ai',
                    text: res.data.draft_response,
                    sources: res.data.sources_used || [],
                    confidence: res.data.confidence,
                    latency_sec: res.data.latency_sec,
                    tokens: res.data.tokens,
                    handoff: res.data.human_handoff,
                    handoff_reason: res.data.handoff_reason,
                    is_unknown: res.data.is_unknown_fallback,
                    intent: res.data.detected_intent,
                    timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                    feedback: null,
                };

                setMessages(prev => [...prev, aiMsg]);
                setLastTrace(res.data);
            }
        } catch (err) {
            toast.error(err.response?.data?.error || 'AI simulation failed to respond.');
        } finally {
            setIsSending(false);
        }
    };

    const handleResetTest = () => {
        setMessages([]);
        setLastTrace(null);
        toast.info('Test session reset. Ready for new simulation.');
    };

    const handleFeedbackSubmit = async (msgIndex, rating) => {
        const msg = messages[msgIndex];
        if (!msg) return;

        try {
            await window.axios.post(route('client.ai.playground.feedback'), {
                ai_agent_id: activeAgent.id,
                question: messages[msgIndex - 1]?.text || 'Test question',
                answer: msg.text,
                rating,
                improvement_notes: feedbackNotes,
                suggested_fixes: selectedFixes,
            });

            setMessages(prev => {
                const copy = [...prev];
                copy[msgIndex].feedback = rating;
                return copy;
            });

            setActiveFeedbackMsgIndex(null);
            setFeedbackNotes('');
            setSelectedFixes([]);
            toast.success(rating === 'good' ? 'Marked as Good Response 👍' : 'Feedback logged for AI improvement 📝');
        } catch (err) {
            toast.error('Failed to log feedback.');
        }
    };

    const handleActivateAgent = () => {
        if (!activeAgent) return;
        router.post(route('client.ai.playground.activate', activeAgent.id), {}, {
            onSuccess: () => {
                setShowActivateModal(false);
                toast.success(`AI Agent '${activeAgent.name}' activated for live channels!`);
            },
            onError: (err) => {
                toast.error(err.activation || 'Activation requirements not met.');
            },
        });
    };

    // Checklist criteria
    const hasInstructions = Boolean(activeAgent?.system_prompt || activeAgent?.purpose);
    const hasKnowledge = Boolean(activeAgent?.ai_kb_id || activeAgent?.knowledgeBase?.documents?.length > 0);
    const hasHandoff = Boolean(activeAgent?.human_handoff_enabled);
    const hasTested = messages.length > 0;
    const canActivate = hasInstructions && hasKnowledge && hasHandoff;

    return (
        <ClientLayout>
            <Head title="AI Agent Playground — Growbridge Connect" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
                {/* ─── Header & Mode Banner ───────────────────────────────────── */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold">
                            🧪
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-lg font-bold text-neutral-900 dark:text-white">AI Agent Playground</h1>
                                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">
                                    TEST MODE · SIMULATION ONLY
                                </span>
                            </div>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                                Safely test prompts, knowledge retrieval, and handoff rules in isolation. No real external messages are sent.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2.5">
                        <button
                            onClick={handleResetTest}
                            className="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-200 text-xs font-bold transition"
                        >
                            <RefreshCw className="w-3.5 h-3.5" /> New Test
                        </button>
                        <button
                            onClick={() => setShowActivateModal(true)}
                            className="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold shadow-sm transition"
                        >
                            <Sparkles className="w-3.5 h-3.5" /> Activate AI Agent
                        </button>
                    </div>
                </div>

                {/* ─── 3-Column Playground Layout ─────────────────────────────── */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">
                    {/* ── Col 1: Agent & Channel Settings (3 cols) ── */}
                    <div className="lg:col-span-3 space-y-4">
                        <div className="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 space-y-4 shadow-xs">
                            <div>
                                <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300 mb-1.5">
                                    Select AI Agent
                                </label>
                                <select
                                    value={selectedAgentId}
                                    onChange={e => setSelectedAgentId(e.target.value)}
                                    className="w-full text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 p-2.5 text-neutral-800 dark:text-neutral-200 focus:outline-none"
                                >
                                    {chatbots.map(agent => (
                                        <option key={agent.id} value={agent.id}>
                                            {agent.name} ({agent.status || 'draft'})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {activeAgent && (
                                <div className="space-y-2 pt-2 border-t border-neutral-100 dark:border-neutral-800 text-xs">
                                    <div className="flex items-center justify-between">
                                        <span className="text-neutral-500">Status</span>
                                        <span className={`capitalize font-bold text-[11px] px-2 py-0.5 rounded-full ${
                                            activeAgent.status === 'active'
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
                                        }`}>
                                            ● {activeAgent.status || 'draft'}
                                        </span>
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <span className="text-neutral-500">Knowledge Base</span>
                                        <span className="font-semibold text-neutral-800 dark:text-neutral-200 truncate max-w-[130px]">
                                            {activeAgent.knowledgeBase?.name || 'Primary Base'}
                                        </span>
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <span className="text-neutral-500">Strict Knowledge</span>
                                        <span className="font-semibold text-neutral-800 dark:text-neutral-200">
                                            {activeAgent.strict_knowledge_mode ? 'Enabled' : 'Disabled'}
                                        </span>
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <span className="text-neutral-500">Human Handoff</span>
                                        <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                            {activeAgent.human_handoff_enabled ? 'Active (Auto)' : 'Manual'}
                                        </span>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Channel Simulation Selector */}
                        <div className="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 space-y-2.5 shadow-xs">
                            <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300">
                                Simulate Channel
                            </label>
                            <div className="grid grid-cols-2 gap-1.5 text-xs font-semibold">
                                {[
                                    { id: 'whatsapp', label: 'WhatsApp', icon: MessageSquare },
                                    { id: 'instagram', label: 'Instagram', icon: Sparkles },
                                    { id: 'messenger', label: 'Messenger', icon: MessageSquare },
                                    { id: 'email', label: 'Email', icon: Mail },
                                    { id: 'phone', label: 'Phone', icon: Phone },
                                ].map(({ id, label, icon: Icon }) => (
                                    <button
                                        key={id}
                                        type="button"
                                        onClick={() => setSelectedChannel(id)}
                                        className={`flex items-center gap-1.5 p-2 rounded-xl border text-[11px] transition ${
                                            selectedChannel === id
                                                ? 'bg-brand-50/50 dark:bg-brand-950/40 border-brand-500 text-brand-700 dark:text-brand-300'
                                                : 'border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50'
                                        }`}
                                    >
                                        <Icon className="w-3 h-3" />
                                        <span>{label}</span>
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* ── Col 2: Chat Stream & Composer (6 cols) ── */}
                    <div className="lg:col-span-6 bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col h-[650px] overflow-hidden">
                        {/* Chat Header */}
                        <div className="p-3.5 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between text-xs bg-neutral-50/50 dark:bg-neutral-800/20">
                            <div className="flex items-center gap-2">
                                <Bot className="w-4 h-4 text-brand-600" />
                                <span className="font-bold text-neutral-900 dark:text-white">{activeAgent?.name || 'AI Assistant'}</span>
                                <span className="text-[11px] text-neutral-400">({selectedChannel} simulation)</span>
                            </div>
                            <span className="text-[10px] text-neutral-400 font-semibold">{messages.length} messages</span>
                        </div>

                        {/* Messages Area */}
                        <div className="flex-1 p-4 overflow-y-auto space-y-3.5">
                            {messages.length === 0 ? (
                                <div className="py-10 text-center space-y-4">
                                    <Bot className="w-10 h-10 text-brand-500 mx-auto" />
                                    <div>
                                        <p className="text-xs font-bold text-neutral-800 dark:text-neutral-200">Start testing {activeAgent?.name}</p>
                                        <p className="text-[11px] text-neutral-400 mt-0.5">Click a quick test prompt below or type your custom customer message.</p>
                                    </div>

                                    {/* Quick Prompts */}
                                    <div className="flex flex-wrap justify-center gap-1.5 max-w-md mx-auto pt-2">
                                        {QUICK_TESTS.map((q, idx) => (
                                            <button
                                                key={idx}
                                                onClick={() => handleSend(q)}
                                                className="px-2.5 py-1.5 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:border-brand-500 bg-neutral-50 dark:bg-neutral-800 text-[11px] text-neutral-700 dark:text-neutral-300 transition"
                                            >
                                                {q}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            ) : (
                                messages.map((m, idx) => (
                                    <div key={m.id} className={`flex flex-col ${m.role === 'user' ? 'items-end' : 'items-start'}`}>
                                        <div className={`max-w-[85%] rounded-2xl p-3 text-xs leading-relaxed ${
                                            m.role === 'user'
                                                ? 'bg-brand-600 text-white rounded-br-none'
                                                : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 rounded-bl-none border border-neutral-200 dark:border-neutral-700'
                                        }`}>
                                            {m.handoff && (
                                                <div className="mb-2 p-2 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800/50 text-[11px] text-red-700 dark:text-red-300 font-semibold flex items-center gap-1.5">
                                                    <AlertCircle className="w-3.5 h-3.5 shrink-0" />
                                                    <span>🚨 Handoff Triggered: {m.handoff_reason}</span>
                                                </div>
                                            )}

                                            {m.is_unknown && (
                                                <div className="mb-2 p-2 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/50 text-[11px] text-amber-700 dark:text-amber-300 font-semibold flex items-center gap-1.5">
                                                    <HelpCircle className="w-3.5 h-3.5 shrink-0" />
                                                    <span>ℹ️ Non-Hallucination Fallback (Knowledge Not Found)</span>
                                                </div>
                                            )}

                                            <p className="whitespace-pre-wrap">{m.text}</p>
                                        </div>

                                        {/* AI Response Sub-bar */}
                                        {m.role === 'ai' && (
                                            <div className="mt-1 flex items-center gap-3 text-[10px] text-neutral-400 pl-1">
                                                <span>⏱ {m.latency_sec}s</span>
                                                <span>Confidence: <strong className="text-neutral-600 dark:text-neutral-300">{m.confidence}</strong></span>

                                                <div className="flex items-center gap-1 ml-2">
                                                    <button
                                                        onClick={() => handleFeedbackSubmit(idx, 'good')}
                                                        className={`p-1 hover:text-emerald-600 ${m.feedback === 'good' ? 'text-emerald-600 font-bold' : ''}`}
                                                        title="Good response"
                                                    >
                                                        <ThumbsUp className="w-3 h-3" />
                                                    </button>
                                                    <button
                                                        onClick={() => setActiveFeedbackMsgIndex(activeFeedbackMsgIndex === idx ? null : idx)}
                                                        className={`p-1 hover:text-red-500 ${m.feedback === 'wrong' ? 'text-red-500 font-bold' : ''}`}
                                                        title="Wrong / Needs improvement"
                                                    >
                                                        <ThumbsDown className="w-3 h-3" />
                                                    </button>
                                                </div>
                                            </div>
                                        )}

                                        {/* Collapsible Feedback Improvement Box */}
                                        {activeFeedbackMsgIndex === idx && (
                                            <div className="mt-2 p-3 bg-neutral-50 dark:bg-neutral-800/60 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs space-y-2 max-w-[85%]">
                                                <p className="font-bold text-neutral-800 dark:text-neutral-200">What should be improved?</p>
                                                <input
                                                    type="text"
                                                    placeholder="Explain what the AI answered incorrectly..."
                                                    value={feedbackNotes}
                                                    onChange={e => setFeedbackNotes(e.target.value)}
                                                    className="w-full text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-2 text-neutral-800 dark:text-neutral-200"
                                                />
                                                <div className="flex items-center justify-between pt-1">
                                                    <span className="text-[10px] text-neutral-400">Suggest Fix: Update Knowledge / Instructions</span>
                                                    <button
                                                        onClick={() => handleFeedbackSubmit(idx, 'wrong')}
                                                        className="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold"
                                                    >
                                                        Save Feedback
                                                    </button>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ))
                            )}
                            <div ref={messagesEndRef} />
                        </div>

                        {/* Quick Prompts Footer Bar */}
                        {messages.length > 0 && (
                            <div className="p-2 border-t border-neutral-100 dark:border-neutral-800 flex gap-1.5 overflow-x-auto text-[11px] bg-neutral-50/50 dark:bg-neutral-800/20">
                                {QUICK_TESTS.slice(0, 4).map((q, idx) => (
                                    <button
                                        key={idx}
                                        onClick={() => handleSend(q)}
                                        className="whitespace-nowrap px-2.5 py-1 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 hover:border-brand-500"
                                    >
                                        {q}
                                    </button>
                                ))}
                            </div>
                        )}

                        {/* Composer Input */}
                        <form onSubmit={(e) => { e.preventDefault(); handleSend(); }} className="p-3 border-t border-neutral-200 dark:border-neutral-800 flex gap-2">
                            <input
                                type="text"
                                placeholder="Type a test customer message..."
                                value={inputMessage}
                                onChange={e => setInputMessage(e.target.value)}
                                disabled={isSending}
                                className="flex-1 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3.5 py-2 text-xs text-neutral-800 dark:text-neutral-200 focus:outline-none"
                            />
                            <button
                                type="submit"
                                disabled={isSending || !inputMessage.trim()}
                                className="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold transition disabled:opacity-50 flex items-center gap-1.5"
                            >
                                <Send className="w-3.5 h-3.5" />
                                <span>{isSending ? 'Simulating...' : 'Send'}</span>
                            </button>
                        </form>
                    </div>

                    {/* ── Col 3: AI Trace & Knowledge Sources (3 cols) ── */}
                    <div className="lg:col-span-3 space-y-4">
                        <div className="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 space-y-3.5 shadow-xs">
                            <div className="flex items-center justify-between">
                                <h3 className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                                    <Sparkles className="w-3.5 h-3.5 text-brand-600" />
                                    <span>AI Response Trace</span>
                                </h3>
                                {lastTrace && (
                                    <span className="text-[10px] font-bold text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded-full">
                                        ✓ Verified
                                    </span>
                                )}
                            </div>

                            {lastTrace ? (
                                <div className="space-y-3 text-xs">
                                    <div className="p-3 bg-neutral-50 dark:bg-neutral-800/40 rounded-xl space-y-2">
                                        <div className="flex items-center justify-between text-[11px]">
                                            <span className="text-neutral-500">Confidence</span>
                                            <span className="font-bold text-neutral-800 dark:text-neutral-200">{lastTrace.confidence}</span>
                                        </div>
                                        <div className="flex items-center justify-between text-[11px]">
                                            <span className="text-neutral-500">Response Latency</span>
                                            <span className="font-bold text-neutral-800 dark:text-neutral-200">{lastTrace.latency_sec}s</span>
                                        </div>
                                        <div className="flex items-center justify-between text-[11px]">
                                            <span className="text-neutral-500">Tokens Estimate</span>
                                            <span className="font-bold text-neutral-800 dark:text-neutral-200">{lastTrace.tokens}</span>
                                        </div>
                                    </div>

                                    {/* Sources Trace */}
                                    <div>
                                        <h4 className="font-bold text-[11px] text-neutral-700 dark:text-neutral-300 mb-1.5">
                                            Knowledge Sources Used:
                                        </h4>
                                        {lastTrace.sources_used && lastTrace.sources_used.length > 0 ? (
                                            <div className="space-y-1.5">
                                                {lastTrace.sources_used.map((s, idx) => (
                                                    <div key={idx} className="p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50/50 dark:bg-neutral-800/30 text-[11px]">
                                                        <div className="font-bold text-brand-600 dark:text-brand-400">{s.title}</div>
                                                        <p className="text-neutral-500 dark:text-neutral-400 text-[10px] mt-0.5 line-clamp-2">
                                                            {s.excerpt}
                                                        </p>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="text-[11px] text-neutral-400 italic">No direct knowledge chunk match.</p>
                                        )}
                                    </div>

                                    <button
                                        onClick={() => setShowAdvancedTrace(!showAdvancedTrace)}
                                        className="w-full py-1.5 text-center text-[11px] font-semibold text-neutral-500 hover:text-neutral-800 flex items-center justify-center gap-1"
                                    >
                                        <span>{showAdvancedTrace ? 'Hide AI Details' : 'Show AI Details'}</span>
                                        {showAdvancedTrace ? <ChevronUp className="w-3 h-3" /> : <ChevronDown className="w-3 h-3" />}
                                    </button>

                                    {showAdvancedTrace && (
                                        <div className="p-2.5 bg-neutral-900 text-neutral-200 rounded-xl font-mono text-[10px] space-y-1 max-h-40 overflow-y-auto">
                                            <p>Intent: {lastTrace.detected_intent}</p>
                                            <p>Handoff: {String(lastTrace.human_handoff)}</p>
                                            <p>Handoff Reason: {lastTrace.handoff_reason || 'none'}</p>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <p className="text-xs text-neutral-400 italic py-6 text-center">
                                    Send a message in the playground to view knowledge retrieval trace and latency details.
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* ─── Activate AI Agent Checklist Modal ───────────────────────── */}
            {showActivateModal && (
                <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden p-6 space-y-4">
                        <div className="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-3">
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                <Sparkles className="w-4 h-4 text-brand-600" />
                                <span>Activate AI Agent</span>
                            </h3>
                            <button onClick={() => setShowActivateModal(false)} className="text-neutral-400 hover:text-neutral-600">
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <p className="text-xs text-neutral-500 dark:text-neutral-400">
                            Verify your readiness checklist before activating <strong>{activeAgent?.name}</strong> for live WhatsApp, Instagram, Messenger, and Email conversations.
                        </p>

                        <div className="space-y-2.5 text-xs">
                            <div className="flex items-center justify-between p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/50">
                                <span>Agent Instructions Configured</span>
                                {hasInstructions ? (
                                    <span className="text-emerald-600 font-bold flex items-center gap-1">✓ Ready</span>
                                ) : (
                                    <span className="text-amber-500 font-bold">Needs Setup</span>
                                )}
                            </div>

                            <div className="flex items-center justify-between p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/50">
                                <span>Knowledge Base Connected</span>
                                {hasKnowledge ? (
                                    <span className="text-emerald-600 font-bold flex items-center gap-1">✓ Ready</span>
                                ) : (
                                    <span className="text-amber-500 font-bold">Needs Knowledge</span>
                                )}
                            </div>

                            <div className="flex items-center justify-between p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/50">
                                <span>Human Handoff Configured</span>
                                {hasHandoff ? (
                                    <span className="text-emerald-600 font-bold flex items-center gap-1">✓ Ready</span>
                                ) : (
                                    <span className="text-amber-500 font-bold">Needs Handoff</span>
                                )}
                            </div>

                            <div className="flex items-center justify-between p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/50">
                                <span>Playground Simulation Tested</span>
                                {hasTested ? (
                                    <span className="text-emerald-600 font-bold flex items-center gap-1">✓ Completed</span>
                                ) : (
                                    <span className="text-neutral-400">Optional</span>
                                )}
                            </div>
                        </div>

                        <div className="flex justify-end gap-2 pt-2">
                            <button
                                type="button"
                                onClick={() => setShowActivateModal(false)}
                                className="px-3.5 py-1.5 rounded-xl text-xs font-semibold text-neutral-500 hover:bg-neutral-100"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={handleActivateAgent}
                                className="px-4 py-1.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold shadow-sm transition"
                            >
                                Confirm & Activate AI
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </ClientLayout>
    );
}
