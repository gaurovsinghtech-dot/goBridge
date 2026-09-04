import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import InboxLayout from '@/Layouts/InboxLayout';
import NewConversationModal from '@/Components/Inbox/NewConversationModal';
import {
    Send, AlertTriangle, StickyNote, MessageSquare, Phone, Globe,
    RefreshCw, Search, Inbox, User, CheckCircle, Clock, X, Smile,
    Paperclip, Image as ImageIcon, ChevronDown, UserCheck,
    LayoutTemplate, Plus, Loader2, Bot, Calendar, BarChart2,
    Volume2, Sparkles, PhoneCall, PhoneIncoming, PhoneOutgoing,
    MoreVertical, Zap, Check, CheckCheck, FileText, UserPlus,
    Play, Info, ArrowLeft, ChevronRight, Edit2, ShieldAlert,
    ExternalLink, ArrowRightLeft
} from 'lucide-react';
import { ChannelBrandIcon, CHANNEL_LABELS } from '@/Components/BrandIcons';
import { formatTimeTz, formatInTz } from '@/Utils/datetime';
import { useState, useEffect, useRef, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';
import { toast } from 'sonner';

/* ─── Constants & Filter Definitions ─────────────────── */

const VIEWS = [
    { key: null,     label: 'All Conversations', icon: Inbox,         countKey: 'all' },
    { key: 'unread', label: 'Unread',            icon: MessageSquare, countKey: 'unread' },
    { key: 'mine',   label: 'Assigned to Me',    icon: User,          countKey: 'mine' },
    { key: 'ai',     label: 'AI Conversations',  icon: Bot,           countKey: 'ai' },
    { key: 'open',   label: 'Open',              icon: Clock,         countKey: 'open' },
    { key: 'closed', label: 'Closed',            icon: CheckCircle,   countKey: 'closed' },
];

const CHANNELS = [
    { key: 'whatsapp',  label: 'WhatsApp',     icon: 'whatsapp',  countKey: 'whatsapp' },
    { key: 'instagram', label: 'Instagram',    icon: 'instagram', countKey: 'instagram' },
    { key: 'messenger', label: 'Messenger',    icon: 'messenger', countKey: 'messenger' },
    { key: 'email',     label: 'Email',        icon: 'email',     countKey: 'email' },
    { key: 'phone',     label: 'Phone / Calls', icon: 'voice',     countKey: 'calls' },
];

const EMOJI_LIST = [
    '😀','😂','😊','😍','🤔','👍','👎','❤️','🎉','✅',
    '❌','🔥','⭐','💡','📌','🚀','💬','📞','✉️','📎',
    '🖼️','📁','🕐','💰','🎯','👋','🙏','💪','🤝','✨',
];

const HANDOFF_REASONS = [
    'Customer requested human',
    'Complaint / angry customer',
    'Low AI confidence',
    'Payment or billing dispute',
    'Technical support escalation',
    'High-value sales opportunity',
    'Unsupported custom inquiry',
];

function extractVars(components) {
    const body = components?.find(c => c.type === 'BODY');
    const text = body?.text ?? '';
    const matches = [...text.matchAll(/\{\{(\d+)\}\}/g)];
    return [...new Set(matches.map(m => m[1]))].sort((a, b) => Number(a) - Number(b));
}

function resolveBody(text, vars) {
    return text.replace(/\{\{(\d+)\}\}/g, (_, i) => vars[i] ?? `{{${i}}}`);
}

/* ─── Emoji Picker Component ─────────────────────────── */
function EmojiPicker({ onPick, onClose }) {
    const ref = useRef(null);
    useEffect(() => {
        const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) onClose(); };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [onClose]);

    return (
        <div ref={ref} className="absolute bottom-full mb-2 left-0 z-50 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl shadow-xl p-2 w-64">
            <div className="grid grid-cols-10 gap-0.5">
                {EMOJI_LIST.map(e => (
                    <button
                        key={e}
                        type="button"
                        onClick={() => { onPick(e); onClose(); }}
                        className="h-7 w-7 flex items-center justify-center text-base rounded hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                    >
                        {e}
                    </button>
                ))}
            </div>
        </div>
    );
}

/* ─── WhatsApp Template Picker ───────────────────────── */
function TemplatePicker({ conversationId, onSent, onClose }) {
    const ref = useRef(null);
    const [query, setQuery]                 = useState('');
    const [templates, setTemplates]         = useState([]);
    const [loading, setLoading]             = useState(true);
    const [picked, setPicked]               = useState(null);
    const [vars, setVars]                   = useState({});
    const [sending, setSending]             = useState(false);
    const [sendError, setSendError]         = useState('');

    useEffect(() => {
        const h = (e) => { if (ref.current && !ref.current.contains(e.target)) onClose(); };
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, [onClose]);

    useEffect(() => {
        axios.get(route('client.inbox.templates'))
            .then(r => setTemplates(r.data ?? []))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    const filtered = templates.filter(t => t.name.toLowerCase().includes(query.toLowerCase()));

    const pickTemplate = (tpl) => {
        const varIds = extractVars(tpl.components);
        setPicked(tpl);
        setVars(Object.fromEntries(varIds.map(id => [id, ''])));
        setSendError('');
    };

    const handleSend = async () => {
        setSendError('');
        setSending(true);
        const bodyComp = picked.components?.find(c => c.type === 'BODY');
        const resolvedText = bodyComp ? resolveBody(bodyComp.text ?? '', vars) : picked.name;

        try {
            const r = await axios.post(
                route('client.inbox.reply', conversationId),
                {
                    type: 'template',
                    body: resolvedText,
                    payload: {
                        name: picked.name,
                        language: picked.language,
                        components: [{ type: 'body', parameters: Object.entries(vars).map(([_, text]) => ({ type: 'text', text })) }],
                    },
                },
                { headers: { Accept: 'application/json' } }
            );
            onSent(r.data?.message);
        } catch (err) {
            setSendError(err.response?.data?.error ?? err.response?.data?.message ?? 'Failed to send template.');
        } finally {
            setSending(false);
        }
    };

    return (
        <div ref={ref} className="absolute bottom-full mb-2 left-0 right-0 z-50 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-2xl p-4 max-h-96 flex flex-col">
            <div className="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800">
                <span className="text-xs font-bold text-neutral-800 dark:text-neutral-200">WhatsApp Approved Templates</span>
                <button type="button" onClick={onClose} className="text-neutral-400 hover:text-neutral-600"><X className="h-4 w-4" /></button>
            </div>
            {sendError && <p className="mt-2 text-xs text-red-500">{sendError}</p>}
            {!picked ? (
                <>
                    <input
                        value={query}
                        onChange={e => setQuery(e.target.value)}
                        placeholder="Search approved templates..."
                        className="my-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-1.5 text-xs text-neutral-800 dark:text-neutral-200 focus:outline-none"
                    />
                    <div className="flex-1 overflow-y-auto space-y-1">
                        {loading ? <p className="text-xs text-neutral-400 py-4 text-center">Loading templates...</p>
                            : filtered.length === 0 ? <p className="text-xs text-neutral-400 py-4 text-center">No approved templates found.</p>
                            : filtered.map(tpl => (
                                <button key={tpl.id} type="button" onClick={() => pickTemplate(tpl)}
                                    className="w-full text-left p-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition">
                                    <p className="text-xs font-semibold text-neutral-800 dark:text-neutral-100">{tpl.name}</p>
                                    <p className="text-[11px] text-neutral-500 truncate">{tpl.components?.find(c => c.type === 'BODY')?.text}</p>
                                </button>
                            ))}
                    </div>
                </>
            ) : (
                <div className="flex-1 flex flex-col overflow-hidden pt-2">
                    <p className="text-xs font-bold text-neutral-800 dark:text-neutral-100">{picked.name}</p>
                    <div className="my-2 p-2 rounded-lg bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-xs text-neutral-700 dark:text-neutral-300">
                        {resolveBody(picked.components?.find(c => c.type === 'BODY')?.text ?? '', vars)}
                    </div>
                    <div className="flex items-center justify-between pt-2">
                        <button type="button" onClick={() => setPicked(null)} className="text-xs text-neutral-500 hover:underline">Back</button>
                        <button type="button" onClick={handleSend} disabled={sending}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-600 text-white text-xs font-bold hover:bg-brand-700 disabled:opacity-50">
                            {sending ? 'Sending...' : 'Send Template'}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

/* ─── Phone Call Details Modal ───────────────────────── */
function CallDetailsModal({ call, onClose, onCallBack }) {
    if (!call) return null;
    const durationMin = Math.floor((call.duration_sec ?? 0) / 60);
    const durationSec = (call.duration_sec ?? 0) % 60;
    const formattedDuration = `${String(durationMin).padStart(2, '0')}:${String(durationSec).padStart(2, '0')}`;

    return (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[85vh]">
                <div className="p-4 border-b border-neutral-100 dark:border-neutral-800 flex items-center justify-between bg-neutral-50/50 dark:bg-neutral-800/50">
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                            <PhoneCall className="w-5 h-5" />
                        </div>
                        <div>
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                                {call.direction === 'inbound' ? '☎ Incoming AI Phone Call' : '☎ Outbound AI Phone Call'}
                            </h3>
                            <p className="text-xs text-neutral-500">
                                {call.from_number || call.to_number} · Duration: {formattedDuration}
                            </p>
                        </div>
                    </div>
                    <button onClick={onClose} className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="p-5 overflow-y-auto space-y-4 text-xs">
                    <div className="grid grid-cols-2 gap-3 p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700">
                        <div>
                            <p className="text-[10px] font-semibold uppercase text-neutral-400">AI Voice Agent</p>
                            <p className="font-bold text-neutral-800 dark:text-neutral-200 mt-0.5">{call.voice_agent?.name || 'Sales Assistant'}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-semibold uppercase text-neutral-400">Status / Outcome</p>
                            <span className="inline-flex items-center gap-1 mt-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                ✓ {call.outcome || call.status || 'Completed'}
                            </span>
                        </div>
                    </div>

                    {call.recording_url && (
                        <div className="space-y-1.5">
                            <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400">Call Audio Recording</p>
                            <audio controls src={call.recording_url} className="w-full h-9 rounded-lg" />
                        </div>
                    )}

                    <div className="space-y-1.5">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400">Call Executive Summary</p>
                        <div className="p-3 rounded-xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/40 text-neutral-800 dark:text-neutral-200 leading-relaxed">
                            {call.summary || 'Summary unavailable for this call.'}
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400">Spoken Dialogue Transcript</p>
                        <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 font-mono text-[11px] leading-relaxed max-h-48 overflow-y-auto whitespace-pre-wrap text-neutral-700 dark:text-neutral-300">
                            {call.transcript || 'Transcript unavailable.'}
                        </div>
                    </div>
                </div>

                <div className="p-4 border-t border-neutral-100 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/50 flex items-center justify-between">
                    <button onClick={onClose} className="px-4 py-2 rounded-xl text-neutral-600 dark:text-neutral-400 text-xs font-semibold hover:bg-neutral-200/50">
                        Close
                    </button>
                    <button
                        onClick={() => { onClose(); onCallBack(call); }}
                        className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-md transition"
                    >
                        <PhoneCall className="w-3.5 h-3.5" /> Call Back Customer
                    </button>
                </div>
            </div>
        </div>
    );
}

/* ─── Manual Human Handoff Modal ─────────────────────── */
function HandoffModal({ conversationId, onClose, onHandoffSuccess }) {
    const [selectedReason, setSelectedReason] = useState(HANDOFF_REASONS[0]);
    const [customReason, setCustomReason] = useState('');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        const reason = customReason.trim() || selectedReason;
        setLoading(true);
        try {
            await axios.post(route('client.inbox.handover', conversationId), { reason });
            toast.success(`Handoff executed: ${reason}`);
            onHandoffSuccess(reason);
            onClose();
        } catch (err) {
            toast.error('Failed to trigger handoff.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden flex flex-col">
                <div className="p-4 border-b border-neutral-100 dark:border-neutral-800 flex items-center justify-between bg-amber-500/10 text-amber-900 dark:text-amber-300">
                    <h3 className="text-sm font-bold flex items-center gap-2">
                        <ArrowRightLeft className="w-4 h-4 text-amber-600" /> AI → Human Handoff
                    </h3>
                    <button onClick={onClose} className="text-neutral-400 hover:text-neutral-600"><X className="w-4 h-4" /></button>
                </div>

                <form onSubmit={handleSubmit} className="p-4 space-y-3 text-xs">
                    <p className="text-neutral-600 dark:text-neutral-400 text-[11px]">
                        Switch this conversation to Human Mode immediately. AI auto-reply will pause, and the conversation will be flagged for agent takeover.
                    </p>

                    <div>
                        <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Handoff Trigger Reason</label>
                        <select
                            value={selectedReason}
                            onChange={e => setSelectedReason(e.target.value)}
                            className="w-full rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-2 text-xs text-neutral-800 dark:text-neutral-200 focus:outline-none"
                        >
                            {HANDOFF_REASONS.map(r => (
                                <option key={r} value={r}>{r}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block font-medium text-neutral-600 dark:text-neutral-400 mb-1">Additional Note (Optional)</label>
                        <input
                            type="text"
                            placeholder="Add specific context for the agent..."
                            value={customReason}
                            onChange={e => setCustomReason(e.target.value)}
                            className="w-full rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-2 text-xs text-neutral-800 dark:text-neutral-200 focus:outline-none"
                        />
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={onClose} className="px-3 py-1.5 rounded-lg text-neutral-500 hover:bg-neutral-100">Cancel</button>
                        <button type="submit" disabled={loading} className="px-4 py-2 rounded-xl bg-amber-600 text-white font-bold hover:bg-amber-700 disabled:opacity-50">
                            {loading ? 'Transferring...' : 'Execute Handoff'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

/* ─── Add Tag Modal ──────────────────────────────────── */
function AddTagModal({ conversationId, onClose, onSuccess }) {
    const [name, setName] = useState('');
    const [color, setColor] = useState('#3b82f6');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!name.trim()) return;
        setLoading(true);
        try {
            const res = await axios.post(route('client.inbox.add-tag', conversationId), { name, color });
            toast.success('Tag added.');
            onSuccess(res.data?.tag);
            onClose();
        } catch (err) {
            toast.error('Failed to add tag.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl shadow-2xl w-full max-w-xs overflow-hidden flex flex-col">
                <div className="p-4 border-b border-neutral-100 dark:border-neutral-800 flex items-center justify-between">
                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                        <Tag className="w-4 h-4 text-brand-600" /> Add Contact Tag
                    </h3>
                    <button onClick={onClose} className="text-neutral-400 hover:text-neutral-600"><X className="w-4 h-4" /></button>
                </div>

                <form onSubmit={handleSubmit} className="p-4 space-y-3 text-xs">
                    <div>
                        <label className="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Tag Name</label>
                        <input
                            type="text"
                            required
                            placeholder="e.g. Lead, Hot, VIP"
                            value={name}
                            onChange={e => setName(e.target.value)}
                            className="w-full rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-2 text-xs text-neutral-800 dark:text-neutral-200 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label className="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Color</label>
                        <div className="flex items-center gap-2">
                            {['#ef4444', '#f97316', '#f59e0b', '#10b981', '#06b6d4', '#3b82f6', '#8b5cf6', '#ec4899'].map(c => (
                                <button
                                    key={c}
                                    type="button"
                                    onClick={() => setColor(c)}
                                    className={`h-6 w-6 rounded-full transition ${color === c ? 'ring-2 ring-offset-2 ring-brand-500 scale-110' : 'opacity-80'}`}
                                    style={{ backgroundColor: c }}
                                />
                            ))}
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={onClose} className="px-3 py-1.5 rounded-lg text-neutral-500 hover:bg-neutral-100">Cancel</button>
                        <button type="submit" disabled={loading} className="px-4 py-1.5 rounded-xl bg-brand-600 text-white font-bold hover:bg-brand-700 disabled:opacity-50">
                            {loading ? 'Adding...' : 'Add Tag'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

/* ─── Assign Agent Dropdown Modal ────────────────────── */
function AssignAgentModal({ teamMembers = [], currentAssignedId, conversationId, onClose, onAssigned }) {
    const [loading, setLoading] = useState(false);

    const handleAssign = async (userId) => {
        setLoading(true);
        try {
            await axios.post(route('client.inbox.assign', conversationId), { user_id: userId });
            toast.success('Agent assigned.');
            onAssigned(userId);
            onClose();
        } catch (err) {
            toast.error('Failed to assign agent.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden flex flex-col">
                <div className="p-4 border-b border-neutral-100 dark:border-neutral-800 flex items-center justify-between">
                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                        <User className="w-4 h-4 text-brand-600" /> Assign Team Member
                    </h3>
                    <button onClick={onClose} className="text-neutral-400 hover:text-neutral-600"><X className="w-4 h-4" /></button>
                </div>

                <div className="p-2 max-h-64 overflow-y-auto divide-y divide-neutral-100 dark:divide-neutral-800 text-xs">
                    <button
                        onClick={() => handleAssign(null)}
                        disabled={loading}
                        className="w-full text-left px-3 py-2 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg"
                    >
                        Unassigned (Queue)
                    </button>
                    {teamMembers.map(m => (
                        <button
                            key={m.id}
                            onClick={() => handleAssign(m.id)}
                            disabled={loading}
                            className={`w-full text-left px-3 py-2.5 hover:bg-brand-50 dark:hover:bg-brand-950/40 rounded-lg flex items-center justify-between transition ${
                                m.id === currentAssignedId ? 'bg-brand-50 dark:bg-brand-950/40 font-bold text-brand-700 dark:text-brand-300' : 'text-neutral-800 dark:text-neutral-200'
                            }`}
                        >
                            <div>
                                <p className="font-semibold">{m.name}</p>
                                <p className="text-[10px] text-neutral-400">{m.email}</p>
                            </div>
                            {m.id === currentAssignedId && <Check className="w-4 h-4 text-brand-600" />}
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
}

/* ─── Main Inbox Show Component ──────────────────────── */
export default function InboxShow({
    conversation,
    messages: initialMessages = [],
    voiceCalls: initialVoiceCalls = [],
    notes: initialNotes = [],
    journey = {},
    aiCustomerSummary = {},
    allTags = [],
    allLabels = [],
    conversations: initialConversations,
    filters = {},
    counts = {},
    teamMembers = [],
    whatsappTemplates = [],
    channelAccounts = [],
}) {
    const { t } = useTranslation();
    const { props } = usePage();
    const userTz = props.timezone || 'Asia/Dhaka';
    const channel = conversation.channel || conversation.channel_account?.channel || 'whatsapp';
    const isWhatsApp = channel === 'whatsapp';

    const [messages, setMessages]           = useState(initialMessages);
    const [voiceCalls, setVoiceCalls]       = useState(initialVoiceCalls);
    const [notes, setNotes]                 = useState(initialNotes);
    const [newNote, setNewNote]             = useState('');
    const [notePosting, setNotePosting]     = useState(false);
    const [contactTags, setContactTags]     = useState(conversation.contact?.tags ?? []);
    
    // AI Mode: 'auto' | 'suggested' | 'human' | 'paused'
    const [aiMode, setAiMode]               = useState(conversation.ai_mode ?? (conversation.assigned_to === 'bot' ? 'auto' : 'human'));
    const [assignedTo, setAssignedTo]       = useState(conversation.assigned_to ?? 'bot');
    const [assignedUserId, setAssignedUserId] = useState(conversation.assigned_user_id ?? null);
    const [conversations, setConversations] = useState(initialConversations);
    const [search, setSearch]               = useState(filters.search || filters.q || '');
    const [mobileTab, setMobileTab]         = useState('chat'); // 'chat' | 'list' | 'customer'
    const [showCustomerSidebar, setShowCustomerSidebar] = useState(true);

    // AI Suggestion State
    const [aiSuggestedReply, setAiSuggestedReply] = useState(null);
    const [aiLoading, setAiLoading]         = useState(false);
    const [showAiMenu, setShowAiMenu]       = useState(false);

    // Modals state
    const [selectedCall, setSelectedCall]   = useState(null);
    const [showTagModal, setShowTagModal]   = useState(false);
    const [showAssignModal, setShowAssignModal] = useState(false);
    const [showHandoffModal, setShowHandoffModal] = useState(false);

    // Composer Toolbar state
    const [showEmoji, setShowEmoji]         = useState(false);
    const [showTemplates, setShowTemplates] = useState(false);
    const [sending, setSending]             = useState(false);
    const [sendError, setSendError]         = useState(null);

    const { data, setData, reset } = useForm({ body: '', type: 'text', payload: null });
    const bottomRef = useRef(null);
    const searchTimer = useRef(null);

    const isAiActive = (aiMode === 'auto' || assignedTo === 'bot') && aiMode !== 'human' && aiMode !== 'paused';

    const scrollToBottom = useCallback(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, []);

    useEffect(() => {
        setMessages(initialMessages);
        setVoiceCalls(initialVoiceCalls);
        setNotes(initialNotes);
        setContactTags(conversation.contact?.tags ?? []);
        setAiMode(conversation.ai_mode ?? (conversation.assigned_to === 'bot' ? 'auto' : 'human'));
        setAssignedTo(conversation.assigned_to ?? 'bot');
        setAssignedUserId(conversation.assigned_user_id ?? null);
        setAiSuggestedReply(null);
    }, [conversation.id]);

    useEffect(() => {
        scrollToBottom();
    }, [messages, voiceCalls]);

    // WebSocket real-time events
    useEffect(() => {
        if (!window.Echo) return;
        const ch = window.Echo.private(`conversation.${conversation.id}`);
        ch.listen('.MessageReceived', (e) => {
            setMessages(prev => prev.some(m => m.id === e.id) ? prev : [...prev, e]);
        }).listen('.MessageSent', (e) => {
            setMessages(prev => prev.some(m => m.id === e.id) ? prev.map(m => m.id === e.id ? { ...m, ...e } : m) : [...prev, e]);
        }).listen('.MessageStatusUpdated', (e) => {
            setMessages(prev => prev.map(m => m.id === e.id ? { ...m, status: e.status } : m));
        });

        return () => { window.Echo.leave(`conversation.${conversation.id}`); };
    }, [conversation.id]);

    const handleFilterChange = (newFilters) => {
        router.get(
            route('client.inbox.show', conversation.uuid),
            { ...filters, ...newFilters, page: 1 },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleSearchChange = (val) => {
        setSearch(val);
        if (searchTimer.current) clearTimeout(searchTimer.current);
        searchTimer.current = setTimeout(() => {
            handleFilterChange({ search: val });
        }, 300);
    };

    // AI Mode Switcher (auto, suggested, human, paused)
    const handleSwitchMode = async (newMode) => {
        setAiLoading(true);
        try {
            const res = await axios.post(route('client.inbox.ai-mode', conversation.uuid), { mode: newMode });
            setAiMode(res.data.ai_mode || newMode);
            setAssignedTo(res.data.mode);
            toast.success(res.data.message);
        } catch (e) {
            toast.error('Failed to change AI mode.');
        } finally {
            setAiLoading(false);
        }
    };

    // AI Generate Suggested Reply (with action / tone)
    const handleGenerateAiReply = async (action = 'generate') => {
        setAiLoading(true);
        setShowAiMenu(false);
        try {
            const res = await axios.post(route('client.inbox.ai-generate-reply', conversation.uuid), {
                action,
                draft: data.body || '',
            });
            if (res.data?.reply) {
                setAiSuggestedReply(res.data.reply);
            }
        } catch (e) {
            toast.error('Failed to generate AI suggestion.');
        } finally {
            setAiLoading(false);
        }
    };

    // Add Internal Note
    const handleAddNote = async (e) => {
        e.preventDefault();
        if (!newNote.trim()) return;
        setNotePosting(true);
        try {
            const res = await axios.post(route('client.inbox.notes.store', conversation.uuid), { body: newNote });
            setNotes(prev => [res.data?.note || { id: Date.now(), body: newNote, user: props.auth?.user, created_at: new Date() }, ...prev]);
            setNewNote('');
            toast.success('Note added.');
        } catch (err) {
            toast.error('Failed to save note.');
        } finally {
            setNotePosting(false);
        }
    };

    // Close / Reopen Conversation
    const handleToggleStatus = async () => {
        const newStatus = conversation.status === 'resolved' ? 'open' : 'resolved';
        try {
            await axios.post(route('client.inbox.status', conversation.uuid), { status: newStatus });
            toast.success(`Conversation ${newStatus === 'resolved' ? 'closed' : 'reopened'}.`);
            router.reload({ preserveScroll: true });
        } catch (err) {
            toast.error('Failed to update status.');
        }
    };

    // Outbound Message Send
    const handleSend = (e) => {
        e?.preventDefault();
        if (!data.body.trim()) return;
        setSending(true);
        setSendError(null);

        axios.post(route('client.inbox.reply', conversation.uuid), data, { headers: { Accept: 'application/json' } })
            .then(res => {
                if (res.data?.message) {
                    setMessages(prev => prev.some(m => m.id === res.data.message.id) ? prev : [...prev, res.data.message]);
                }
                reset('body');
                setAiSuggestedReply(null);
            })
            .catch(err => {
                setSendError(err.response?.data?.error ?? err.response?.data?.message ?? 'Message could not be sent.');
            })
            .finally(() => setSending(false));
    };

    // Chronological Interleaving of Messages & Phone Calls
    const streamItems = [
        ...messages.map(m => ({ kind: 'message', item: m, date: new Date(m.sent_at || m.created_at) })),
        ...voiceCalls.map(c => ({ kind: 'call', item: c, date: new Date(c.created_at) })),
    ].sort((a, b) => a.date - b.date);

    const contactName = conversation.contact?.first_name || conversation.contact?.last_name
        ? `${conversation.contact.first_name ?? ''} ${conversation.contact.last_name ?? ''}`.trim()
        : conversation.contact?.phone_e164 ?? conversation.contact?.email ?? 'Customer';

    const convList = conversations?.data ?? [];
    const assignedUser = teamMembers.find(m => m.id === assignedUserId);

    return (
        <InboxLayout>
            <Head title={`${contactName} — Unified Inbox`} />

            <div className="flex h-full overflow-hidden bg-white dark:bg-neutral-950">
                {/* ──────────────────────────────────────────────────────────
                    COLUMN 1: CHANNELS & VIEWS SIDEBAR (LEFT)
                ─────────────────────────────────────────────────────────── */}
                <div className="w-52 shrink-0 flex-col h-full bg-neutral-50/80 dark:bg-neutral-900/60 border-r border-neutral-200 dark:border-neutral-800 overflow-y-auto hidden xl:flex">
                    <div className="p-3 border-b border-neutral-200 dark:border-neutral-800 flex items-center gap-2">
                        <div className="h-7 w-7 rounded-lg bg-brand-600 flex items-center justify-center text-white font-bold text-xs shadow-xs">
                            GC
                        </div>
                        <div>
                            <h2 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">Channels</h2>
                            <p className="text-[10px] text-neutral-400">Omnichannel Hub</p>
                        </div>
                    </div>

                    {/* Views */}
                    <div className="p-2 space-y-0.5 border-b border-neutral-200 dark:border-neutral-800">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 px-2 py-1">Views</p>
                        {VIEWS.map(({ key, label, icon: Icon, countKey }) => {
                            const isSelected = (filters.folder || null) === key && !filters.channel;
                            const count = counts[countKey] ?? 0;

                            return (
                                <button
                                    key={label}
                                    onClick={() => handleFilterChange({ folder: key, channel: null })}
                                    className={`w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs font-medium transition ${
                                        isSelected
                                            ? 'bg-brand-600 text-white shadow-xs'
                                            : 'text-neutral-700 dark:text-neutral-300 hover:bg-neutral-200/60 dark:hover:bg-neutral-800/60'
                                    }`}
                                >
                                    <div className="flex items-center gap-2 min-w-0">
                                        <Icon className="h-3.5 w-3.5 shrink-0" />
                                        <span className="truncate">{label}</span>
                                    </div>
                                    <span className={`text-[10px] font-bold px-1.5 py-0.2 rounded-full ${
                                        isSelected ? 'bg-white/20 text-white' : 'bg-neutral-200/80 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400'
                                    }`}>
                                        {count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    {/* Channels */}
                    <div className="p-2 space-y-0.5 border-b border-neutral-200 dark:border-neutral-800">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 px-2 py-1">Channels</p>
                        {CHANNELS.map(ch => {
                            const isSelected = filters.channel === ch.key;
                            const count = counts[ch.countKey] ?? 0;

                            return (
                                <button
                                    key={ch.key}
                                    onClick={() => handleFilterChange({ channel: ch.key, folder: null })}
                                    className={`w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs font-medium transition ${
                                        isSelected
                                            ? 'bg-brand-600 text-white shadow-xs'
                                            : 'text-neutral-700 dark:text-neutral-300 hover:bg-neutral-200/60 dark:hover:bg-neutral-800/60'
                                    }`}
                                >
                                    <div className="flex items-center gap-2 min-w-0">
                                        <ChannelBrandIcon channel={ch.icon} className="h-3.5 w-3.5 shrink-0" />
                                        <span className="truncate">{ch.label}</span>
                                    </div>
                                    <span className={`text-[10px] font-bold px-1.5 py-0.2 rounded-full ${
                                        isSelected ? 'bg-white/20 text-white' : 'bg-neutral-200/80 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400'
                                    }`}>
                                        {count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* ──────────────────────────────────────────────────────────
                    COLUMN 2: CONVERSATION LIST (CENTER)
                ─────────────────────────────────────────────────────────── */}
                <div className={`w-full md:w-80 lg:w-88 flex flex-col border-r border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shrink-0 ${
                    mobileTab === 'list' ? 'flex' : 'hidden md:flex'
                }`}>
                    <div className="p-3 border-b border-neutral-200 dark:border-neutral-800">
                        <div className="relative">
                            <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-neutral-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={e => handleSearchChange(e.target.value)}
                                placeholder="Search customer, phone, text..."
                                className="w-full text-xs bg-neutral-100 dark:bg-neutral-800 rounded-lg pl-8 pr-7 py-2 text-neutral-800 dark:text-neutral-200 focus:outline-none placeholder-neutral-400"
                            />
                            {search && (
                                <button onClick={() => handleSearchChange('')} className="absolute right-2.5 top-2.5 text-neutral-400 hover:text-neutral-600">
                                    <X className="w-3.5 h-3.5" />
                                </button>
                            )}
                        </div>
                    </div>

                    <div className="flex-1 overflow-y-auto divide-y divide-neutral-100 dark:divide-neutral-800">
                        {convList.map(conv => {
                            const isSelected = conv.id === conversation.id;
                            const convName = conv.contact?.first_name || conv.contact?.last_name
                                ? `${conv.contact.first_name ?? ''} ${conv.contact.last_name ?? ''}`.trim()
                                : conv.contact?.phone_e164 ?? conv.contact?.email ?? 'Customer';

                            return (
                                <Link
                                    key={conv.id}
                                    href={route('client.inbox.show', conv.uuid)}
                                    onClick={() => setMobileTab('chat')}
                                    className={`block px-3 py-2.5 transition-colors ${
                                        isSelected
                                            ? 'bg-brand-50/80 dark:bg-brand-950/40 border-l-4 border-l-brand-600'
                                            : 'hover:bg-neutral-50 dark:hover:bg-neutral-800/50'
                                    }`}
                                >
                                    <div className="flex items-start gap-2.5">
                                        <div className="relative shrink-0">
                                            <div className="h-9 w-9 rounded-full bg-gradient-to-br from-brand-500 to-indigo-600 flex items-center justify-center text-xs font-bold text-white shadow-xs">
                                                {convName[0]?.toUpperCase()}
                                            </div>
                                            <span className="absolute -bottom-1 -right-1 h-4 w-4 rounded-full bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 flex items-center justify-center shadow-xs">
                                                <ChannelBrandIcon channel={conv.channel || conv.channel_account?.channel || 'whatsapp'} className="h-3 w-3" />
                                            </span>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between gap-1">
                                                <span className={`text-xs truncate ${isSelected ? 'font-bold text-brand-700 dark:text-brand-300' : 'font-semibold text-neutral-800 dark:text-neutral-200'}`}>
                                                    {convName}
                                                </span>
                                                <span className="text-[10px] text-neutral-400 shrink-0">
                                                    {conv.last_message_at ? formatTimeTz(conv.last_message_at, userTz) : ''}
                                                </span>
                                            </div>
                                            <p className="text-[11px] text-neutral-500 dark:text-neutral-400 truncate mt-0.5">
                                                {conv.last_message?.body || '(media message)'}
                                            </p>
                                        </div>
                                    </div>
                                </Link>
                            );
                        })}
                    </div>
                </div>

                {/* ──────────────────────────────────────────────────────────
                    COLUMN 3: CONVERSATION STREAM & ACTIONS (CENTER-RIGHT)
                ─────────────────────────────────────────────────────────── */}
                <div className={`flex-1 flex flex-col h-full overflow-hidden bg-white dark:bg-neutral-950 ${
                    mobileTab === 'chat' ? 'flex' : 'hidden md:flex'
                }`}>
                    {/* Header */}
                    <div className="p-3 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between gap-3 bg-white dark:bg-neutral-900 shrink-0">
                        <div className="flex items-center gap-2.5 min-w-0">
                            {/* Mobile back button */}
                            <button onClick={() => setMobileTab('list')} className="md:hidden p-1 text-neutral-500 hover:bg-neutral-100 rounded-lg">
                                <ArrowLeft className="w-4 h-4" />
                            </button>

                            <div className="h-9 w-9 rounded-full bg-brand-600 text-white flex items-center justify-center font-bold text-xs shadow-xs shrink-0">
                                {contactName[0]?.toUpperCase()}
                            </div>
                            <div className="min-w-0">
                                <div className="flex items-center gap-1.5">
                                    <h1 className="text-xs font-bold text-neutral-900 dark:text-white truncate">{contactName}</h1>
                                    <ChannelBrandIcon channel={channel} className="h-3.5 w-3.5 shrink-0" />
                                </div>
                                <p className="text-[11px] text-neutral-400 truncate">
                                    {conversation.contact?.phone_e164 || conversation.contact?.email || 'No phone/email'}
                                </p>
                            </div>
                        </div>

                        {/* Top Controls: AI / Human Control Pill & Actions */}
                        <div className="flex items-center gap-2 shrink-0">
                            {/* AI Control Header Card */}
                            <div className="flex items-center gap-1.5 p-1 rounded-xl bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                                {isAiActive ? (
                                    <div className="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-purple-600 text-white text-[11px] font-bold shadow-xs">
                                        <span className="h-2 w-2 rounded-full bg-white animate-pulse" /> AI Assistant ● ACTIVE
                                    </div>
                                ) : (
                                    <div className="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-neutral-600 text-white text-[11px] font-bold shadow-xs">
                                        <span className="h-2 w-2 rounded-full bg-neutral-300" /> AI Assistant ○ PAUSED
                                    </div>
                                )}

                                <button
                                    onClick={() => handleSwitchMode(isAiActive ? 'human' : 'auto')}
                                    disabled={aiLoading}
                                    className="px-2.5 py-1 rounded-lg text-[11px] font-bold text-neutral-700 dark:text-neutral-200 hover:bg-neutral-200 dark:hover:bg-neutral-700 transition"
                                >
                                    {isAiActive ? '[ Switch to Human ]' : '[ Enable AI ]'}
                                </button>
                            </div>

                            {/* Mode Dropdown Selector */}
                            <select
                                value={aiMode}
                                onChange={(e) => handleSwitchMode(e.target.value)}
                                className="hidden sm:inline-block text-[11px] rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 py-1.5 px-2 font-medium text-neutral-700 dark:text-neutral-300 focus:outline-none"
                            >
                                <option value="auto">Mode: AI Auto Reply</option>
                                <option value="suggested">Mode: AI Suggested</option>
                                <option value="human">Mode: Human Only</option>
                                <option value="paused">Mode: Paused</option>
                            </select>

                            {/* Handoff Button */}
                            <button
                                onClick={() => setShowHandoffModal(true)}
                                className="hidden sm:inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-800 text-xs font-bold hover:bg-amber-500/20"
                                title="Execute Human Handoff"
                            >
                                <ArrowRightLeft className="w-3.5 h-3.5 text-amber-600" />
                                <span>Handoff</span>
                            </button>

                            {/* Assign User Button */}
                            <button
                                onClick={() => setShowAssignModal(true)}
                                className="hidden sm:inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                title="Assign Agent"
                            >
                                <User className="w-3.5 h-3.5 text-brand-600" />
                                <span>{assignedUser ? assignedUser.name : 'Assign'}</span>
                            </button>

                            {/* Close / Reopen */}
                            <button
                                onClick={handleToggleStatus}
                                className={`hidden sm:inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold border ${
                                    conversation.status === 'resolved'
                                        ? 'bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700'
                                        : 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
                                }`}
                            >
                                <CheckCircle className="w-3.5 h-3.5" />
                                <span>{conversation.status === 'resolved' ? 'Reopen' : 'Close'}</span>
                            </button>

                            {/* Toggle Right Customer Panel (Desktop) */}
                            <button
                                onClick={() => setShowCustomerSidebar(!showCustomerSidebar)}
                                className="hidden lg:flex p-2 rounded-lg border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                title="Toggle Customer Info Panel"
                            >
                                <Info className="w-4 h-4" />
                            </button>

                            {/* Mobile View Customer Button */}
                            <button
                                onClick={() => setMobileTab('customer')}
                                className="md:hidden p-2 rounded-lg border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-400"
                            >
                                <Info className="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    {/* Stream Content (Messages, Timeline System Events & Phone Calls) */}
                    <div className="flex-1 overflow-y-auto p-4 space-y-3 bg-neutral-50/30 dark:bg-neutral-950">
                        {streamItems.length === 0 ? (
                            <div className="py-12 text-center">
                                <MessageSquare className="w-10 h-10 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
                                <p className="text-sm font-medium text-neutral-600 dark:text-neutral-300">No conversations yet.</p>
                                <p className="text-xs text-neutral-400 mt-1">Send a message below to start communicating with {contactName}.</p>
                            </div>
                        ) : (
                            streamItems.map(({ kind, item }, idx) => {
                                if (kind === 'call') {
                                    const durationMin = Math.floor((item.duration_sec ?? 0) / 60);
                                    const durationSec = (item.duration_sec ?? 0) % 60;
                                    const formattedDuration = `${String(durationMin).padStart(2, '0')}:${String(durationSec).padStart(2, '0')}`;

                                    return (
                                        <div key={`call-${item.id || idx}`} className="my-3 max-w-lg mx-auto rounded-2xl bg-white dark:bg-neutral-900 border border-amber-200 dark:border-amber-900/40 p-3.5 shadow-xs">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="flex items-center gap-2.5">
                                                    <div className="h-8 w-8 rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400 flex items-center justify-center shrink-0">
                                                        <PhoneCall className="w-4 h-4" />
                                                    </div>
                                                    <div>
                                                        <h4 className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                                                            {item.direction === 'inbound' ? 'Incoming Call' : 'Outbound Call'}
                                                            <span className="text-[10px] font-medium px-1.5 py-0.2 rounded bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300">
                                                                {item.status || 'Completed'}
                                                            </span>
                                                        </h4>
                                                        <p className="text-[11px] text-neutral-500 mt-0.5">
                                                            Duration: <span className="font-semibold text-neutral-700 dark:text-neutral-300">{formattedDuration}</span> · AI Agent: <span className="font-semibold text-neutral-700 dark:text-neutral-300">{item.voice_agent?.name || 'Sales Assistant'}</span>
                                                        </p>
                                                    </div>
                                                </div>
                                                <span className="text-[10px] text-neutral-400 font-medium">
                                                    {formatTimeTz(item.created_at, userTz)}
                                                </span>
                                            </div>

                                            <div className="flex items-center gap-2 mt-3 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                                                <button
                                                    onClick={() => setSelectedCall(item)}
                                                    className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 text-neutral-700 dark:text-neutral-300 text-xs font-semibold"
                                                >
                                                    <FileText className="w-3 h-3" /> View Transcript
                                                </button>
                                                <button
                                                    onClick={() => setSelectedCall(item)}
                                                    className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 text-neutral-700 dark:text-neutral-300 text-xs font-semibold"
                                                >
                                                    <Sparkles className="w-3 h-3 text-indigo-500" /> View Summary
                                                </button>
                                                <button
                                                    onClick={() => setSelectedCall(item)}
                                                    className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 hover:bg-amber-100 text-xs font-semibold"
                                                >
                                                    <PhoneIncoming className="w-3 h-3" /> Call Back
                                                </button>
                                            </div>
                                        </div>
                                    );
                                }

                                const msg = item;
                                const isOut = msg.direction === 'out';
                                const isBot = msg.sent_by === 'bot' || msg.sender_type === 'ai';
                                const isSystem = msg.sent_by === 'system' || msg.sender_type === 'system' || msg.type === 'system';

                                // Timeline System Event (e.g. AI -> Human handoff)
                                if (isSystem) {
                                    return (
                                        <div key={`msg-${msg.id || idx}`} className="flex justify-center my-2">
                                            <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-50 dark:bg-amber-950/40 border border-amber-200/70 dark:border-amber-900/50 text-amber-800 dark:text-amber-300 text-[11px] font-semibold shadow-2xs">
                                                <ArrowRightLeft className="w-3.5 h-3.5 text-amber-600" />
                                                <span>{msg.body}</span>
                                                <span className="text-[10px] text-amber-600/70 font-normal">· {formatTimeTz(msg.sent_at || msg.created_at, userTz)}</span>
                                            </div>
                                        </div>
                                    );
                                }

                                return (
                                    <div key={`msg-${msg.id || idx}`} className={`flex ${isOut ? 'justify-end' : 'justify-start'}`}>
                                        <div className={`max-w-lg rounded-2xl p-3 shadow-2xs ${
                                            isOut
                                                ? isBot
                                                    ? 'bg-purple-600 text-white rounded-br-xs'
                                                    : 'bg-brand-600 text-white rounded-br-xs'
                                                : 'bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 text-neutral-800 dark:text-neutral-200 rounded-bl-xs'
                                        }`}>
                                            {isOut && isBot && (
                                                <div className="flex items-center gap-1 text-[10px] font-bold text-purple-200 mb-1">
                                                    <Bot className="w-3 h-3" /> AI Agent Message
                                                </div>
                                            )}
                                            <p className="text-xs whitespace-pre-wrap leading-relaxed">{msg.body}</p>
                                            <div className={`flex items-center justify-end gap-1 mt-1 text-[10px] ${isOut ? 'text-white/70' : 'text-neutral-400'}`}>
                                                <span>{formatTimeTz(msg.sent_at || msg.created_at, userTz)}</span>
                                                {isOut && (
                                                    <span>
                                                        {msg.status === 'read' ? <CheckCheck className="w-3.5 h-3.5 text-blue-200" /> : <Check className="w-3.5 h-3.5" />}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })
                        )}
                        <div ref={bottomRef} />
                    </div>

                    {/* AI Suggested Reply Banner */}
                    {aiSuggestedReply && (
                        <div className="p-3 bg-purple-50 dark:bg-purple-950/30 border-t border-purple-200 dark:border-purple-800">
                            <div className="flex items-center justify-between gap-2 mb-1.5">
                                <span className="text-xs font-bold text-purple-800 dark:text-purple-300 flex items-center gap-1.5">
                                    <Sparkles className="w-3.5 h-3.5" /> AI Suggested Reply
                                </span>
                                <div className="flex items-center gap-1">
                                    <button
                                        onClick={handleGenerateAiReply}
                                        disabled={aiLoading}
                                        className="text-[11px] text-purple-600 dark:text-purple-400 hover:underline px-1.5 py-0.5 font-semibold"
                                    >
                                        [Regenerate]
                                    </button>
                                    <button
                                        onClick={() => setAiSuggestedReply(null)}
                                        className="text-neutral-400 hover:text-neutral-600 p-0.5"
                                    >
                                        <X className="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                            <p className="text-xs text-neutral-700 dark:text-neutral-200 bg-white dark:bg-neutral-900 p-2.5 rounded-lg border border-purple-100 dark:border-purple-900/40 leading-relaxed">
                                "{aiSuggestedReply}"
                            </p>
                            <div className="flex items-center justify-end gap-2 mt-2">
                                <button
                                    onClick={() => { setData('body', aiSuggestedReply); }}
                                    className="px-3 py-1 rounded-lg text-xs font-semibold text-neutral-600 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-800"
                                >
                                    [Edit in Composer]
                                </button>
                                <button
                                    onClick={() => { setData('body', aiSuggestedReply); setAiSuggestedReply(null); }}
                                    className="px-3.5 py-1 rounded-lg bg-purple-600 text-white text-xs font-bold hover:bg-purple-700"
                                >
                                    [Use Reply]
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Composer Box */}
                    <div className="border-t border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-3 shrink-0">
                        {sendError && (
                            <div className="mb-2 p-2 rounded-lg bg-red-50 dark:bg-red-950/30 border border-red-200 text-red-600 text-xs flex items-center justify-between">
                                <span>{sendError}</span>
                                <button onClick={() => setSendError(null)}><X className="w-3.5 h-3.5" /></button>
                            </div>
                        )}

                        <div className="relative">
                            {showEmoji && <EmojiPicker onPick={e => setData('body', (data.body ?? '') + e)} onClose={() => setShowEmoji(false)} />}
                            {showTemplates && (
                                <TemplatePicker
                                    conversationId={conversation.uuid}
                                    onSent={(msg) => {
                                        if (msg) setMessages(prev => [...prev, msg]);
                                        setShowTemplates(false);
                                    }}
                                    onClose={() => setShowTemplates(false)}
                                />
                            )}

                            <form onSubmit={handleSend} className="space-y-2">
                                <textarea
                                    value={data.body}
                                    onChange={e => setData('body', e.target.value)}
                                    onKeyDown={e => {
                                        if (e.key === 'Enter' && !e.shiftKey) {
                                            e.preventDefault();
                                            handleSend();
                                        }
                                    }}
                                    rows={2}
                                    placeholder={`Reply via ${CHANNEL_LABELS[channel] ?? channel}... (Press Enter to send)`}
                                    className="w-full text-xs bg-neutral-50 dark:bg-neutral-800 rounded-xl p-3 text-neutral-800 dark:text-neutral-200 focus:outline-none border border-neutral-200 dark:border-neutral-700 resize-none placeholder-neutral-400"
                                />

                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            onClick={() => setShowEmoji(!showEmoji)}
                                            className="p-1.5 rounded-lg text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                            title="Add Emoji"
                                        >
                                            <Smile className="w-4 h-4" />
                                        </button>
                                        {isWhatsApp && (
                                            <button
                                                type="button"
                                                onClick={() => setShowTemplates(!showTemplates)}
                                                className="p-1.5 rounded-lg text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-xs font-semibold flex items-center gap-1"
                                                title="WhatsApp Template"
                                            >
                                                <LayoutTemplate className="w-4 h-4 text-emerald-600" /> Templates
                                            </button>
                                        )}
                                        <div className="relative">
                                            <button
                                                type="button"
                                                onClick={() => setShowAiMenu(!showAiMenu)}
                                                disabled={aiLoading}
                                                className="p-1.5 rounded-lg text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/30 text-xs font-semibold flex items-center gap-1"
                                                title="AI Reply Assistant"
                                            >
                                                <Sparkles className="w-4 h-4" /> AI Assistant <ChevronDown className="w-3 h-3" />
                                            </button>

                                            {showAiMenu && (
                                                <div className="absolute bottom-full mb-2 left-0 z-50 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl shadow-2xl p-1.5 w-52 space-y-1 text-xs">
                                                    {[
                                                        { id: 'generate', label: '🤖 Generate AI Reply', desc: 'Contextual draft' },
                                                        { id: 'shorter', label: '✂ Make Shorter', desc: 'Concise & punchy' },
                                                        { id: 'professional', label: '💼 Make Professional', desc: 'Polite & corporate' },
                                                        { id: 'friendly', label: '😊 Make Friendly', desc: 'Warm & engaging' },
                                                        { id: 'translate', label: '🌐 Translate Draft', desc: 'Auto translation' },
                                                        { id: 'summarize', label: '📋 Summarize Conversation', desc: 'Key points' },
                                                    ].map((m) => (
                                                        <button
                                                            key={m.id}
                                                            type="button"
                                                            onClick={() => handleGenerateAiReply(m.id)}
                                                            className="w-full text-left p-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition block"
                                                        >
                                                            <span className="font-bold text-neutral-900 dark:text-white block">{m.label}</span>
                                                            <span className="text-[10px] text-neutral-400 block">{m.desc}</span>
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={sending || !data.body.trim()}
                                        className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold shadow-sm transition disabled:opacity-50"
                                    >
                                        {sending ? 'Sending...' : 'Send'} <Send className="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {/* ──────────────────────────────────────────────────────────
                    COLUMN 4: CUSTOMER 360 & JOURNEY PANEL (RIGHT)
                ─────────────────────────────────────────────────────────── */}
                <div className={`w-80 shrink-0 border-l border-neutral-200 dark:border-neutral-800 bg-neutral-50/80 dark:bg-neutral-900/50 flex flex-col h-full overflow-y-auto ${
                    showCustomerSidebar ? (mobileTab === 'customer' ? 'flex' : 'hidden lg:flex') : 'hidden'
                }`}>
                    {/* Header */}
                    <div className="p-3.5 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                        <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">Customer 360</h3>
                        <button onClick={() => setMobileTab('chat')} className="md:hidden text-neutral-400 hover:text-neutral-600">
                            <X className="w-4 h-4" />
                        </button>
                    </div>

                    <div className="p-4 space-y-4 text-xs">
                        {/* Profile Card */}
                        <div className="p-3.5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-2.5">
                            <div className="flex items-center gap-3">
                                <div className="h-11 w-11 rounded-full bg-brand-600 text-white font-bold text-sm flex items-center justify-center shadow-xs shrink-0">
                                    {contactName[0]?.toUpperCase()}
                                </div>
                                <div className="min-w-0">
                                    <h4 className="font-bold text-neutral-900 dark:text-white truncate">{contactName}</h4>
                                    <p className="text-[11px] text-neutral-500 truncate">{conversation.contact?.phone_e164 || 'No phone'}</p>
                                    <p className="text-[11px] text-neutral-500 truncate">{conversation.contact?.email || 'No email'}</p>
                                </div>
                            </div>

                            {/* Connected Channel Badges */}
                            <div className="flex flex-wrap gap-1 pt-1 border-t border-neutral-100 dark:border-neutral-800 text-[10px]">
                                {journey.channels && journey.channels.length > 0 ? (
                                    journey.channels.map((ch) => (
                                        <span key={ch} className="px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 font-semibold text-neutral-700 dark:text-neutral-300 capitalize">
                                            {ch === 'whatsapp' && '💬 WhatsApp'}
                                            {ch === 'messenger' && '🔵 Messenger'}
                                            {ch === 'instagram' && '📷 Instagram'}
                                            {ch === 'email' && '📧 Email'}
                                            {ch === 'voice' && '📞 Voice'}
                                        </span>
                                    ))
                                ) : (
                                    <span className="text-neutral-400 italic">No channels linked</span>
                                )}
                            </div>
                        </div>

                        {/* Next Action Card */}
                        {journey.next_action && (
                            <div className="p-3.5 rounded-2xl bg-brand-50/40 dark:bg-brand-950/20 border border-brand-200/80 dark:border-brand-900/40 space-y-2">
                                <div className="flex items-center gap-1.5 text-brand-900 dark:text-brand-100 font-bold text-[11px]">
                                    <Clock className="w-3.5 h-3.5 text-brand-600" /> Next Action
                                </div>
                                <p className="font-bold text-xs text-neutral-900 dark:text-white">{journey.next_action.title}</p>
                                <div className="text-[11px] text-brand-600 font-mono font-semibold">
                                    ⏱ Due: {journey.next_action.due_at}
                                </div>
                                <Link href={route('client.voice.follow-ups.show', journey.next_action.uuid)} className="block pt-1">
                                    <button className="w-full py-1.5 rounded-lg bg-brand-600 text-white font-bold text-[11px] hover:bg-brand-700">
                                        Execute Action
                                    </button>
                                </Link>
                            </div>
                        )}

                        {/* AI Customer Summary Card */}
                        {aiCustomerSummary?.summary && (
                            <div className="p-3.5 rounded-2xl bg-purple-50/40 dark:bg-purple-950/20 border border-purple-200/80 dark:border-purple-900/40 space-y-2.5">
                                <div className="flex items-center gap-1.5 text-purple-900 dark:text-purple-100 font-bold text-[11px]">
                                    <Sparkles className="w-3.5 h-3.5 text-purple-600" /> AI Customer Summary
                                </div>
                                <p className="text-neutral-700 dark:text-neutral-300 text-[11px] leading-relaxed">
                                    {aiCustomerSummary.summary}
                                </p>
                                <div className="flex justify-between text-[11px] pt-1 border-t border-purple-100 dark:border-purple-900/30">
                                    <span className="text-neutral-500">Lead Interest:</span>
                                    <span className="font-bold text-emerald-600">🔥 {aiCustomerSummary.lead_interest || 'High'}</span>
                                </div>
                            </div>
                        )}

                        {/* Quick Actions */}
                        <div className="space-y-1.5">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-neutral-400">Quick Actions</span>
                            <div className="grid grid-cols-2 gap-2">
                                <Link href={route('client.voice.follow-ups.create')} className="w-full">
                                    <button className="w-full p-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 flex items-center justify-center gap-1.5 text-[11px]">
                                        <Plus className="w-3.5 h-3.5 text-brand-600" /> + Task
                                    </button>
                                </Link>
                                <button
                                    onClick={() => {
                                        if (conversation.contact?.uuid) {
                                            router.visit(route('client.contacts.show', conversation.contact.uuid));
                                        }
                                    }}
                                    className="p-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 flex items-center justify-center gap-1.5 text-[11px]"
                                >
                                    <ExternalLink className="w-3.5 h-3.5 text-indigo-500" /> Customer 360
                                </button>
                            </div>
                        </div>

                        {/* Tags Section */}
                        <div className="space-y-1.5">
                            <div className="flex items-center justify-between">
                                <span className="text-[10px] font-bold uppercase tracking-wider text-neutral-400">Tags</span>
                                <button onClick={() => setShowTagModal(true)} className="text-[11px] font-bold text-brand-600 dark:text-brand-400 hover:underline">
                                    + Add Tag
                                </button>
                            </div>
                            <div className="flex flex-wrap gap-1.5">
                                {contactTags.length === 0 ? (
                                    <span className="text-[11px] text-neutral-400 italic">No tags attached</span>
                                ) : (
                                    contactTags.map(t => (
                                        <span key={t.id} className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold text-white shadow-2xs" style={{ backgroundColor: t.color || '#3b82f6' }}>
                                            {t.name}
                                        </span>
                                    ))
                                )}
                            </div>
                        </div>

                        {/* Internal Notes */}
                        <div className="space-y-2">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-neutral-400">Internal Notes (Team Only)</span>
                            <form onSubmit={handleAddNote} className="space-y-1.5">
                                <textarea
                                    value={newNote}
                                    onChange={e => setNewNote(e.target.value)}
                                    rows={2}
                                    placeholder="Add a private note..."
                                    className="w-full text-xs bg-white dark:bg-neutral-800 rounded-xl p-2.5 text-neutral-800 dark:text-neutral-200 focus:outline-none border border-neutral-200 dark:border-neutral-700 resize-none"
                                />
                                <div className="flex justify-end">
                                    <button
                                        type="submit"
                                        disabled={notePosting || !newNote.trim()}
                                        className="px-3 py-1 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-bold text-[11px] disabled:opacity-50"
                                    >
                                        {notePosting ? 'Saving...' : 'Add Note'}
                                    </button>
                                </div>
                            </form>

                            <div className="space-y-2 max-h-48 overflow-y-auto">
                                {notes.length === 0 ? (
                                    <p className="text-[11px] text-neutral-400 italic">No notes yet.</p>
                                ) : (
                                    notes.map(note => (
                                        <div key={note.id} className="p-2.5 rounded-xl bg-amber-50/70 dark:bg-amber-950/20 border border-amber-200/80 dark:border-amber-900/40 text-[11px]">
                                            <div className="flex justify-between text-[10px] text-amber-800 dark:text-amber-300 font-bold mb-1">
                                                <span>{note.user?.name || 'Agent'}</span>
                                                <span className="text-neutral-400 font-normal">{formatTimeTz(note.created_at, userTz)}</span>
                                            </div>
                                            <p className="text-neutral-700 dark:text-neutral-200 whitespace-pre-wrap">{note.body}</p>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modals */}
            {selectedCall && (
                <CallDetailsModal
                    call={selectedCall}
                    onClose={() => setSelectedCall(null)}
                    onCallBack={(c) => {
                        toast.success(`Calling ${c.from_number || c.to_number}...`);
                    }}
                />
            )}

            {showHandoffModal && (
                <HandoffModal
                    conversationId={conversation.uuid}
                    onClose={() => setShowHandoffModal(false)}
                    onHandoffSuccess={(reason) => {
                        setAiMode('human');
                        setAssignedTo('human');
                    }}
                />
            )}

            {showTagModal && (
                <AddTagModal
                    conversationId={conversation.uuid}
                    onClose={() => setShowTagModal(false)}
                    onSuccess={(tag) => {
                        if (tag) setContactTags(prev => [...prev, tag]);
                    }}
                />
            )}

            {showAssignModal && (
                <AssignAgentModal
                    teamMembers={teamMembers}
                    currentAssignedId={assignedUserId}
                    conversationId={conversation.uuid}
                    onClose={() => setShowAssignModal(false)}
                    onAssigned={(userId) => setAssignedUserId(userId)}
                />
            )}
        </InboxLayout>
    );
}
