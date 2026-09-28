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
    ExternalLink, ArrowRightLeft, Tag
} from 'lucide-react';
import { ChannelBrandIcon, CHANNEL_LABELS } from '@/Components/BrandIcons';
import { formatTimeTz } from '@/Utils/datetime';
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
        <div ref={ref} className="absolute bottom-full mb-2 left-0 z-50 bg-zinc-900 border border-zinc-800 rounded-2xl shadow-2xl p-2.5 w-64">
            <div className="grid grid-cols-10 gap-1">
                {EMOJI_LIST.map(e => (
                    <button
                        key={e}
                        type="button"
                        onClick={() => { onPick(e); onClose(); }}
                        className="h-7 w-7 flex items-center justify-center text-base rounded-lg hover:bg-zinc-800 transition"
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
        <div ref={ref} className="absolute bottom-full mb-2 left-0 right-0 z-50 rounded-2xl border border-zinc-800 bg-zinc-950 shadow-2xl p-4 max-h-96 flex flex-col text-white">
            <div className="flex items-center justify-between pb-3 border-b border-zinc-800">
                <span className="text-xs font-bold text-white uppercase tracking-wider">WhatsApp Approved Templates</span>
                <button type="button" onClick={onClose} className="text-zinc-400 hover:text-white"><X className="h-4 w-4" /></button>
            </div>
            {sendError && <p className="mt-2 text-xs text-red-400">{sendError}</p>}
            {!picked ? (
                <>
                    <input
                        value={query}
                        onChange={e => setQuery(e.target.value)}
                        placeholder="Search approved templates..."
                        className="my-2 rounded-xl border border-zinc-800 bg-zinc-900 px-3 py-2 text-xs text-white focus:outline-none focus:border-white transition"
                    />
                    <div className="flex-1 overflow-y-auto space-y-1.5">
                        {loading ? <p className="text-xs text-zinc-400 py-4 text-center">Loading templates...</p>
                            : filtered.length === 0 ? <p className="text-xs text-zinc-400 py-4 text-center">No approved templates found.</p>
                            : filtered.map(tpl => (
                                <button key={tpl.id} type="button" onClick={() => pickTemplate(tpl)}
                                    className="w-full text-left p-2.5 rounded-xl hover:bg-zinc-900 border border-transparent hover:border-zinc-800 transition">
                                    <p className="text-xs font-bold text-white">{tpl.name}</p>
                                    <p className="text-[11px] text-zinc-400 truncate mt-0.5">{tpl.components?.find(c => c.type === 'BODY')?.text}</p>
                                </button>
                            ))}
                    </div>
                </>
            ) : (
                <div className="flex-1 flex flex-col overflow-hidden pt-2">
                    <p className="text-xs font-bold text-white">{picked.name}</p>
                    <div className="my-2 p-3 rounded-xl bg-zinc-900 border border-zinc-800 text-xs text-zinc-300 leading-relaxed">
                        {resolveBody(picked.components?.find(c => c.type === 'BODY')?.text ?? '', vars)}
                    </div>
                    <div className="flex items-center justify-between pt-2">
                        <button type="button" onClick={() => setPicked(null)} className="text-xs text-zinc-400 hover:underline">Back</button>
                        <button type="button" onClick={handleSend} disabled={sending}
                            className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-white text-black font-extrabold text-xs hover:bg-zinc-200 disabled:opacity-50">
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
        <div className="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
            <div className="bg-black border border-zinc-800 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[85vh] text-white">
                <div className="p-4 border-b border-zinc-800 flex items-center justify-between bg-zinc-950">
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 rounded-full bg-zinc-900 border border-zinc-800 text-white flex items-center justify-center shrink-0">
                            <PhoneCall className="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <h3 className="text-sm font-bold text-white">
                                {call.direction === 'inbound' ? '☎ Incoming AI Phone Call' : '☎ Outbound AI Phone Call'}
                            </h3>
                            <p className="text-xs text-zinc-400">
                                {call.from_number || call.to_number} · Duration: {formattedDuration}
                            </p>
                        </div>
                    </div>
                    <button onClick={onClose} className="text-zinc-400 hover:text-white">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="p-5 overflow-y-auto space-y-4 text-xs">
                    <div className="grid grid-cols-2 gap-3 p-3 rounded-xl bg-zinc-950 border border-zinc-800">
                        <div>
                            <p className="text-[10px] font-bold uppercase text-zinc-400">AI Voice Agent</p>
                            <p className="font-bold text-white mt-0.5">{call.voice_agent?.name || 'Sales Assistant'}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-bold uppercase text-zinc-400">Status / Outcome</p>
                            <span className="inline-flex items-center gap-1 mt-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-zinc-900 border border-zinc-800 text-white">
                                ✓ {call.outcome || call.status || 'Completed'}
                            </span>
                        </div>
                    </div>

                    {call.recording_url && (
                        <div className="space-y-1.5">
                            <p className="text-[10px] font-bold uppercase tracking-wider text-zinc-400">Call Audio Recording</p>
                            <audio controls src={call.recording_url} className="w-full h-9 rounded-lg" />
                        </div>
                    )}

                    <div className="space-y-1.5">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-zinc-400">Call Executive Summary</p>
                        <div className="p-3 rounded-xl bg-zinc-950 border border-zinc-800 text-zinc-200 leading-relaxed">
                            {call.summary || 'Summary unavailable for this call.'}
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-zinc-400">Spoken Dialogue Transcript</p>
                        <div className="p-3 rounded-xl bg-zinc-950 border border-zinc-800 font-mono text-[11px] leading-relaxed max-h-48 overflow-y-auto whitespace-pre-wrap text-zinc-300">
                            {call.transcript || 'Transcript unavailable.'}
                        </div>
                    </div>
                </div>

                <div className="p-4 border-t border-zinc-800 bg-zinc-950 flex items-center justify-between">
                    <button onClick={onClose} className="px-4 py-2 rounded-xl text-zinc-400 text-xs font-semibold hover:bg-zinc-900">
                        Close
                    </button>
                    <button
                        onClick={() => { onClose(); onCallBack(call); }}
                        className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white text-black font-extrabold text-xs hover:bg-zinc-200 transition"
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
        <div className="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
            <div className="bg-black border border-zinc-800 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden flex flex-col text-white">
                <div className="p-4 border-b border-zinc-800 flex items-center justify-between bg-zinc-950">
                    <h3 className="text-sm font-bold flex items-center gap-2 text-white uppercase tracking-wider">
                        <ArrowRightLeft className="w-4 h-4 text-white" /> AI → Human Handoff
                    </h3>
                    <button onClick={onClose} className="text-zinc-400 hover:text-white"><X className="w-4 h-4" /></button>
                </div>

                <form onSubmit={handleSubmit} className="p-4 space-y-3 text-xs">
                    <p className="text-zinc-400 text-[11px]">
                        Switch this conversation to Human Mode immediately. AI auto-reply will pause, and the conversation will be flagged for agent takeover.
                    </p>

                    <div>
                        <label className="block font-semibold text-zinc-300 mb-1">Handoff Trigger Reason</label>
                        <select
                            value={selectedReason}
                            onChange={e => setSelectedReason(e.target.value)}
                            className="w-full rounded-xl border border-zinc-800 bg-zinc-900 px-3 py-2 text-xs text-white focus:outline-none focus:border-white transition"
                        >
                            {HANDOFF_REASONS.map(r => (
                                <option key={r} value={r}>{r}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block font-medium text-zinc-400 mb-1">Additional Note (Optional)</label>
                        <input
                            type="text"
                            placeholder="Add specific context for the agent..."
                            value={customReason}
                            onChange={e => setCustomReason(e.target.value)}
                            className="w-full rounded-xl border border-zinc-800 bg-zinc-900 px-3 py-2 text-xs text-white focus:outline-none focus:border-white transition"
                        />
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={onClose} className="px-3 py-1.5 rounded-xl text-zinc-400 hover:bg-zinc-900">Cancel</button>
                        <button type="submit" disabled={loading} className="px-4 py-2 rounded-xl bg-white text-black font-extrabold hover:bg-zinc-200 disabled:opacity-50">
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
    const [color, setColor] = useState('#ffffff');
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
        <div className="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
            <div className="bg-black border border-zinc-800 rounded-2xl shadow-2xl w-full max-w-xs overflow-hidden flex flex-col text-white">
                <div className="p-4 border-b border-zinc-800 flex items-center justify-between bg-zinc-950">
                    <h3 className="text-sm font-bold text-white flex items-center gap-2 uppercase tracking-wider">
                        <Tag className="w-4 h-4 text-white" /> Add Tag
                    </h3>
                    <button onClick={onClose} className="text-zinc-400 hover:text-white"><X className="w-4 h-4" /></button>
                </div>

                <form onSubmit={handleSubmit} className="p-4 space-y-3 text-xs">
                    <div>
                        <label className="block font-medium text-zinc-300 mb-1">Tag Name</label>
                        <input
                            type="text"
                            required
                            placeholder="e.g. Lead, Hot, VIP"
                            value={name}
                            onChange={e => setName(e.target.value)}
                            className="w-full rounded-xl border border-zinc-800 bg-zinc-900 px-3 py-2 text-xs text-white focus:outline-none focus:border-white transition"
                        />
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={onClose} className="px-3 py-1.5 rounded-xl text-zinc-400 hover:bg-zinc-900">Cancel</button>
                        <button type="submit" disabled={loading} className="px-4 py-1.5 rounded-xl bg-white text-black font-extrabold hover:bg-zinc-200 disabled:opacity-50">
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
        <div className="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
            <div className="bg-black border border-zinc-800 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden flex flex-col text-white">
                <div className="p-4 border-b border-zinc-800 flex items-center justify-between bg-zinc-950">
                    <h3 className="text-sm font-bold text-white flex items-center gap-2 uppercase tracking-wider">
                        <User className="w-4 h-4 text-white" /> Assign Team Member
                    </h3>
                    <button onClick={onClose} className="text-zinc-400 hover:text-white"><X className="w-4 h-4" /></button>
                </div>

                <div className="p-2 max-h-64 overflow-y-auto divide-y divide-zinc-900 text-xs">
                    <button
                        onClick={() => handleAssign(null)}
                        disabled={loading}
                        className="w-full text-left px-3 py-2 text-zinc-400 hover:bg-zinc-900 rounded-xl transition"
                    >
                        Unassigned (Queue)
                    </button>
                    {teamMembers.map(m => (
                        <button
                            key={m.id}
                            onClick={() => handleAssign(m.id)}
                            disabled={loading}
                            className={`w-full text-left px-3 py-2.5 hover:bg-zinc-900 rounded-xl flex items-center justify-between transition ${
                                m.id === currentAssignedId ? 'bg-zinc-900 font-bold text-white' : 'text-zinc-300'
                            }`}
                        >
                            <div>
                                <p className="font-semibold">{m.name}</p>
                                <p className="text-[10px] text-zinc-400">{m.email}</p>
                            </div>
                            {m.id === currentAssignedId && <Check className="w-4 h-4 text-white" />}
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

    // Real-time live updates
    useEffect(() => {
        if (!conversation?.uuid) return;

        const isWsConnected = () => {
            return window.Echo?.connector?.pusher?.connection?.state === 'connected';
        };

        const pollInterval = setInterval(async () => {
            if (isWsConnected() || document.hidden) return;

            try {
                const lastId = messages.length > 0 ? messages[messages.length - 1].id : 0;
                const res = await axios.get(route('client.inbox.poll', conversation.uuid), {
                    params: { after_id: lastId }
                });

                if (res.data?.messages && Array.isArray(res.data.messages) && res.data.messages.length > 0) {
                    setMessages(prev => {
                        const existingIds = new Set(prev.map(m => m.id));
                        const incoming = res.data.messages.filter(m => !existingIds.has(m.id));
                        return incoming.length > 0 ? [...prev, ...incoming] : prev;
                    });
                }

                if (res.data?.conversations) {
                    setConversations(res.data.conversations);
                }
            } catch (err) {}
        }, 3000);

        if (window.Echo && conversation?.id) {
            const channelName = `conversation.${conversation.id}`;
            const ch = window.Echo.private(channelName);
            ch.listen('.MessageReceived', (e) => {
                setMessages(prev => prev.some(m => m.id === e.id) ? prev : [...prev, e]);
            }).listen('.MessageSent', (e) => {
                setMessages(prev => prev.some(m => m.id === e.id) ? prev.map(m => m.id === e.id ? { ...m, ...e } : m) : [...prev, e]);
            }).listen('.MessageStatusUpdated', (e) => {
                setMessages(prev => prev.map(m => m.id === e.id ? { ...m, status: e.status } : m));
            });
        }

        return () => {
            clearInterval(pollInterval);
            if (window.Echo && conversation?.id) {
                window.Echo.leave(`conversation.${conversation.id}`);
            }
        };
    }, [conversation.uuid, conversation.id, messages]);

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

    const handleToggleStatus = async () => {
        const newStatus = conversation.status === 'resolved' ? 'open' : 'resolved';
        try {
            await axios.post(route('client.inbox.status', conversation.uuid), { status: newStatus });
            toast.success(`Conversation ${newStatus === 'resolved' ? 'closed' : 'reopened'}.`);
            if (newStatus === 'resolved') {
                router.visit(route('client.inbox.index'));
            } else {
                router.reload({ preserveScroll: true });
            }
        } catch (err) {
            toast.error('Failed to update status.');
        }
    };

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

    const streamItems = [
        ...messages.map(m => ({ kind: 'message', item: m, date: new Date(m.sent_at || m.created_at) })),
        ...voiceCalls.map(c => ({ kind: 'call', item: c, date: new Date(c.created_at) })),
    ].sort((a, b) => a.date - b.date);

    const contactName = conversation.contact?.first_name || conversation.contact?.last_name
        ? `${conversation.contact.first_name ?? ''} ${conversation.contact.last_name ?? ''}`.trim()
        : conversation.contact?.phone_e164 ?? conversation.contact?.email ?? 'Customer';

    const convList = Array.isArray(conversations)
        ? conversations
        : (Array.isArray(conversations?.data) ? conversations.data : []);
    const assignedUser = teamMembers.find(m => m.id === assignedUserId);

    return (
        <InboxLayout>
            <Head title={`${contactName} — Unified Inbox`} />

            <div className="flex h-full overflow-hidden bg-black text-white">
                {/* ──────────────────────────────────────────────────────────
                    COLUMN 1: CHANNELS & VIEWS SIDEBAR (LEFT)
                ─────────────────────────────────────────────────────────── */}
                <div className="w-48 shrink-0 flex flex-col h-full bg-black border-r border-zinc-800 overflow-y-auto hidden xl:flex">
                    <div className="p-3 border-b border-zinc-800 flex items-center gap-2">
                        <div className="h-7 w-7 rounded-lg bg-white text-black font-black text-xs flex items-center justify-center shadow-xs">
                            GC
                        </div>
                        <div>
                            <h2 className="text-xs font-bold text-white uppercase tracking-wider">CHANNELS</h2>
                            <p className="text-[10px] text-zinc-400">Omnichannel Hub</p>
                        </div>
                    </div>

                    {/* Views */}
                    <div className="p-2 space-y-1 border-b border-zinc-800">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-zinc-400 px-2 py-0.5">Views</p>
                        {VIEWS.map(({ key, label, icon: Icon, countKey }) => {
                            const isSelected = (filters.folder || null) === key && !filters.channel;
                            const count = counts[countKey] ?? 0;

                            return (
                                <button
                                    key={label}
                                    onClick={() => handleFilterChange({ folder: key, channel: null })}
                                    className={`w-full flex items-center justify-between px-2.5 py-1.5 rounded-xl text-xs font-semibold transition ${
                                        isSelected
                                            ? 'bg-white text-black font-extrabold shadow-md'
                                            : 'text-zinc-300 hover:bg-zinc-900 hover:text-white'
                                    }`}
                                >
                                    <div className="flex items-center gap-2 min-w-0">
                                        <Icon className="h-3.5 w-3.5 shrink-0" />
                                        <span className="truncate">{label}</span>
                                    </div>
                                    <span className={`text-[10px] font-bold px-1.5 py-0.2 rounded-full ${
                                        isSelected ? 'bg-black text-white' : 'bg-zinc-900 border border-zinc-800 text-zinc-300'
                                    }`}>
                                        {count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    {/* Channels */}
                    <div className="p-2 space-y-1 border-b border-zinc-800">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-zinc-400 px-2 py-0.5">Channels</p>
                        {CHANNELS.map(ch => {
                            const isSelected = filters.channel === ch.key;
                            const count = counts[ch.countKey] ?? 0;

                            return (
                                <button
                                    key={ch.key}
                                    onClick={() => handleFilterChange({ channel: ch.key, folder: null })}
                                    className={`w-full flex items-center justify-between px-2.5 py-1.5 rounded-xl text-xs font-semibold transition ${
                                        isSelected
                                            ? 'bg-white text-black font-extrabold shadow-md'
                                            : 'text-zinc-300 hover:bg-zinc-900 hover:text-white'
                                    }`}
                                >
                                    <div className="flex items-center gap-2 min-w-0">
                                        <ChannelBrandIcon channel={ch.icon} className="h-3.5 w-3.5 shrink-0" />
                                        <span className="truncate">{ch.label}</span>
                                    </div>
                                    <span className={`text-[10px] font-bold px-1.5 py-0.2 rounded-full ${
                                        isSelected ? 'bg-black text-white' : 'bg-zinc-900 border border-zinc-800 text-zinc-300'
                                    }`}>
                                        {count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* ──────────────────────────────────────────────────────────
                    COLUMN 2: CONVERSATION LIST (CENTER-LEFT)
                ─────────────────────────────────────────────────────────── */}
                <div className={`w-full md:w-72 lg:w-80 flex flex-col border-r border-zinc-800 bg-zinc-950 shrink-0 ${
                    mobileTab === 'list' ? 'flex' : 'hidden md:flex'
                }`}>
                    <div className="p-3 border-b border-zinc-800 bg-black">
                        <div className="relative">
                            <Search className="absolute left-3 top-2.5 h-4 w-4 text-zinc-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={e => handleSearchChange(e.target.value)}
                                placeholder="Search customer, phone, text..."
                                className="w-full text-xs bg-zinc-900 border border-zinc-800 rounded-xl pl-9 pr-8 py-2 text-white focus:outline-none focus:border-white focus:ring-1 focus:ring-white placeholder-zinc-500 transition"
                            />
                            {search && (
                                <button onClick={() => handleSearchChange('')} className="absolute right-3 top-2.5 text-zinc-400 hover:text-white">
                                    <X className="w-4 h-4" />
                                </button>
                            )}
                        </div>
                    </div>

                    <div className="flex-1 overflow-y-auto divide-y divide-zinc-900/60">
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
                                    className={`block px-3.5 py-3 transition-colors ${
                                        isSelected
                                            ? 'bg-zinc-900 border-l-4 border-l-white'
                                            : 'hover:bg-zinc-900/50'
                                    }`}
                                >
                                    <div className="flex items-start gap-3">
                                        <div className="relative shrink-0">
                                            <div className="h-9 w-9 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center text-xs font-bold text-white shadow-xs">
                                                {convName[0]?.toUpperCase()}
                                            </div>
                                            <span className="absolute -bottom-1 -right-1 h-4 w-4 rounded-full bg-black border border-zinc-700 flex items-center justify-center shadow-xs">
                                                <ChannelBrandIcon channel={conv.channel || conv.channel_account?.channel || 'whatsapp'} className="h-2.5 w-2.5" />
                                            </span>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between gap-1">
                                                <span className={`text-xs truncate ${isSelected ? 'font-bold text-white' : 'font-semibold text-zinc-200'}`}>
                                                    {convName}
                                                </span>
                                                <span className="text-[10px] text-zinc-400 shrink-0 font-medium">
                                                    {conv.last_message_at ? formatTimeTz(conv.last_message_at, userTz) : ''}
                                                </span>
                                            </div>
                                            <p className="text-[11px] text-zinc-400 truncate mt-0.5">
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
                <div className={`flex-1 flex flex-col h-full overflow-hidden bg-black min-w-0 ${
                    mobileTab === 'chat' ? 'flex' : 'hidden md:flex'
                }`}>
                    {/* Header: Horizontally Scrollable Controls to Prevent Clipping */}
                    <div className="p-3 border-b border-zinc-800 flex items-center justify-between gap-3 bg-black shrink-0">
                        {/* Left: Customer Info */}
                        <div className="flex items-center gap-2.5 shrink-0">
                            <button onClick={() => setMobileTab('list')} className="md:hidden p-1 text-zinc-400 hover:bg-zinc-900 rounded-lg">
                                <ArrowLeft className="w-4 h-4" />
                            </button>

                            <div className="h-9 w-9 rounded-full bg-white text-black flex items-center justify-center font-black text-xs shadow-xs shrink-0">
                                {contactName[0]?.toUpperCase()}
                            </div>
                            <div className="min-w-0">
                                <div className="flex items-center gap-1.5">
                                    <h1 className="text-xs font-bold text-white truncate max-w-[120px] sm:max-w-none">{contactName}</h1>
                                    <ChannelBrandIcon channel={channel} className="h-3.5 w-3.5 shrink-0" />
                                </div>
                                <p className="text-[11px] text-zinc-400 truncate max-w-[120px] sm:max-w-none">
                                    {conversation.contact?.phone_e164 || conversation.contact?.email || 'No phone/email'}
                                </p>
                            </div>
                        </div>

                        {/* Right: Horizontally Scrollable Controls */}
                        <div className="flex-1 min-w-0 flex items-center justify-end gap-1.5 overflow-x-auto py-1 scrollbar-none whitespace-nowrap">
                            {/* AI Control Header Card */}
                            <div className="flex items-center gap-1 p-0.5 rounded-xl bg-zinc-900 border border-zinc-800 shrink-0">
                                {isAiActive ? (
                                    <div className="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white text-black text-[11px] font-bold shadow-xs shrink-0">
                                        <span className="h-2 w-2 rounded-full bg-black animate-pulse" /> AI Assistant ● ACTIVE
                                    </div>
                                ) : (
                                    <div className="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-800 text-zinc-300 text-[11px] font-bold shadow-xs shrink-0">
                                        <span className="h-2 w-2 rounded-full bg-zinc-500" /> AI Assistant ○ PAUSED
                                    </div>
                                )}

                                <button
                                    onClick={() => handleSwitchMode(isAiActive ? 'human' : 'auto')}
                                    disabled={aiLoading}
                                    className="px-2 py-1 rounded-lg text-[11px] font-bold text-zinc-200 hover:bg-zinc-800 transition shrink-0 whitespace-nowrap"
                                >
                                    {isAiActive ? '[ Switch to Human ]' : '[ Enable AI ]'}
                                </button>
                            </div>

                            {/* Mode Dropdown Selector */}
                            <select
                                value={aiMode}
                                onChange={(e) => handleSwitchMode(e.target.value)}
                                className="text-[11px] rounded-xl border border-zinc-800 bg-zinc-900 py-1.5 px-2.5 font-medium text-white focus:outline-none focus:border-white transition shrink-0 cursor-pointer"
                            >
                                <option value="auto">Mode: AI Auto Reply</option>
                                <option value="suggested">Mode: AI Suggested</option>
                                <option value="human">Mode: Human Only</option>
                                <option value="paused">Mode: Paused</option>
                            </select>

                            {/* Handoff Button */}
                            <button
                                onClick={() => setShowHandoffModal(true)}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-900 text-white border border-zinc-800 text-xs font-bold hover:bg-zinc-800 transition shrink-0 whitespace-nowrap"
                                title="Execute Human Handoff"
                            >
                                <ArrowRightLeft className="w-3.5 h-3.5 text-white shrink-0" />
                                <span>Handoff</span>
                            </button>

                            {/* Assign User Button */}
                            <button
                                onClick={() => setShowAssignModal(true)}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-900 border border-zinc-800 text-xs font-semibold text-white hover:bg-zinc-800 transition shrink-0 whitespace-nowrap"
                                title="Assign Agent"
                            >
                                <User className="w-3.5 h-3.5 text-white shrink-0" />
                                <span>{assignedUser ? assignedUser.name : 'Assign'}</span>
                            </button>

                            {/* Close / Reopen */}
                            <button
                                onClick={handleToggleStatus}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold border bg-zinc-900 border-zinc-800 text-white hover:bg-zinc-800 transition shrink-0 whitespace-nowrap"
                            >
                                <CheckCircle className="w-3.5 h-3.5 shrink-0" />
                                <span>{conversation.status === 'resolved' ? 'Reopen' : 'Close'}</span>
                            </button>

                            {/* Dismiss / Close Chat Tab */}
                            <Link
                                href={route('client.inbox.index')}
                                className="p-2 rounded-xl border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-white hover:bg-zinc-800 transition shrink-0"
                                title="Close Chat Tab"
                            >
                                <X className="w-4 h-4" />
                            </Link>

                            {/* Toggle Right Customer Panel (Desktop) */}
                            <button
                                onClick={() => setShowCustomerSidebar(!showCustomerSidebar)}
                                className="hidden lg:flex p-2 rounded-xl border border-zinc-800 bg-zinc-900 text-white hover:bg-zinc-800 transition shrink-0"
                                title="Toggle Customer Info Panel"
                            >
                                <Info className="w-4 h-4" />
                            </button>

                            <button
                                onClick={() => setMobileTab('customer')}
                                className="md:hidden p-2 rounded-xl border border-zinc-800 bg-zinc-900 text-white shrink-0"
                            >
                                <Info className="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    {/* Stream Content */}
                    <div className="flex-1 overflow-y-auto p-4 space-y-3.5 bg-black">
                        {streamItems.length === 0 ? (
                            <div className="py-16 text-center">
                                <MessageSquare className="w-10 h-10 text-zinc-600 mx-auto mb-2" />
                                <p className="text-sm font-bold text-white">No conversations yet.</p>
                                <p className="text-xs text-zinc-400 mt-1">Send a message below to start communicating with {contactName}.</p>
                            </div>
                        ) : (
                            streamItems.map(({ kind, item }, idx) => {
                                if (kind === 'call') {
                                    const durationMin = Math.floor((item.duration_sec ?? 0) / 60);
                                    const durationSec = (item.duration_sec ?? 0) % 60;
                                    const formattedDuration = `${String(durationMin).padStart(2, '0')}:${String(durationSec).padStart(2, '0')}`;

                                    return (
                                        <div key={`call-${item.id || idx}`} className="my-3 max-w-lg mx-auto rounded-2xl bg-zinc-900 border border-zinc-800 p-4 shadow-md">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="flex items-center gap-3">
                                                    <div className="h-9 w-9 rounded-full bg-zinc-800 text-white border border-zinc-700 flex items-center justify-center shrink-0">
                                                        <PhoneCall className="w-4 h-4" />
                                                    </div>
                                                    <div>
                                                        <h4 className="text-xs font-bold text-white flex items-center gap-2">
                                                            {item.direction === 'inbound' ? 'Incoming Call' : 'Outbound Call'}
                                                            <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-zinc-800 border border-zinc-700 text-zinc-200">
                                                                {item.status || 'Completed'}
                                                            </span>
                                                        </h4>
                                                        <p className="text-[11px] text-zinc-400 mt-0.5">
                                                            Duration: <span className="font-semibold text-white">{formattedDuration}</span> · AI Agent: <span className="font-semibold text-white">{item.voice_agent?.name || 'Sales Assistant'}</span>
                                                        </p>
                                                    </div>
                                                </div>
                                                <span className="text-[10px] text-zinc-400 font-medium">
                                                    {formatTimeTz(item.created_at, userTz)}
                                                </span>
                                            </div>

                                            <div className="flex items-center gap-2 mt-3 pt-2.5 border-t border-zinc-800">
                                                <button
                                                    onClick={() => setSelectedCall(item)}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-white text-xs font-semibold"
                                                >
                                                    <FileText className="w-3.5 h-3.5" /> View Transcript
                                                </button>
                                                <button
                                                    onClick={() => setSelectedCall(item)}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-800 border border-zinc-700 hover:bg-zinc-700 text-white text-xs font-semibold"
                                                >
                                                    <Sparkles className="w-3.5 h-3.5 text-white" /> View Summary
                                                </button>
                                                <button
                                                    onClick={() => setSelectedCall(item)}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white text-black font-bold text-xs hover:bg-zinc-200"
                                                >
                                                    <PhoneIncoming className="w-3.5 h-3.5" /> Call Back
                                                </button>
                                            </div>
                                        </div>
                                    );
                                }

                                const msg = item;
                                const isOut = msg.direction === 'out';
                                const isBot = msg.sent_by === 'bot' || msg.sender_type === 'ai';
                                const isSystem = msg.sent_by === 'system' || msg.sender_type === 'system' || msg.type === 'system';

                                if (isSystem) {
                                    return (
                                        <div key={`msg-${msg.id || idx}`} className="flex justify-center my-2.5">
                                            <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-zinc-900 border border-zinc-800 text-zinc-300 text-[11px] font-semibold shadow-sm">
                                                <ArrowRightLeft className="w-3.5 h-3.5 text-white" />
                                                <span>{msg.body}</span>
                                                <span className="text-[10px] text-zinc-400 font-normal">· {formatTimeTz(msg.sent_at || msg.created_at, userTz)}</span>
                                            </div>
                                        </div>
                                    );
                                }

                                return (
                                    <div key={`msg-${msg.id || idx}`} className={`flex ${isOut ? 'justify-end' : 'justify-start'}`}>
                                        <div className={`max-w-lg rounded-2xl p-3.5 shadow-sm ${
                                            isOut
                                                ? isBot
                                                    ? 'bg-zinc-900 border border-white text-white rounded-br-xs'
                                                    : 'bg-white text-black font-medium border border-white rounded-br-xs'
                                                : 'bg-zinc-900 border border-zinc-800 text-white rounded-bl-xs'
                                        }`}>
                                            {isOut && isBot && (
                                                <div className="flex items-center gap-1.5 text-[10px] font-bold text-white mb-1.5 uppercase tracking-wider">
                                                    <Bot className="w-3.5 h-3.5 text-white" /> AI Agent Message
                                                </div>
                                            )}
                                            <p className="text-xs whitespace-pre-wrap leading-relaxed">{msg.body}</p>
                                            <div className={`flex items-center justify-end gap-1 mt-1.5 text-[10px] ${isOut ? (isBot ? 'text-zinc-400' : 'text-zinc-600') : 'text-zinc-400'}`}>
                                                <span>{formatTimeTz(msg.sent_at || msg.created_at, userTz)}</span>
                                                {isOut && (
                                                    <span>
                                                        {msg.status === 'read' ? <CheckCheck className="w-3.5 h-3.5 text-black" /> : <Check className="w-3.5 h-3.5" />}
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
                        <div className="p-3.5 bg-zinc-900 border-t border-zinc-800">
                            <div className="flex items-center justify-between gap-2 mb-2">
                                <span className="text-xs font-bold text-white flex items-center gap-1.5">
                                    <Sparkles className="w-3.5 h-3.5 text-white" /> AI Suggested Reply
                                </span>
                                <div className="flex items-center gap-2">
                                    <button
                                        onClick={handleGenerateAiReply}
                                        disabled={aiLoading}
                                        className="text-[11px] text-zinc-300 hover:text-white underline font-semibold"
                                    >
                                        [Regenerate]
                                    </button>
                                    <button
                                        onClick={() => setAiSuggestedReply(null)}
                                        className="text-zinc-400 hover:text-white p-0.5"
                                    >
                                        <X className="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                            <p className="text-xs text-white bg-black p-3 rounded-xl border border-zinc-800 leading-relaxed">
                                "{aiSuggestedReply}"
                            </p>
                            <div className="flex items-center justify-end gap-2 mt-2.5">
                                <button
                                    onClick={() => { setData('body', aiSuggestedReply); }}
                                    className="px-3 py-1.5 rounded-xl text-xs font-semibold text-zinc-300 hover:bg-zinc-800"
                                >
                                    [Edit in Composer]
                                </button>
                                <button
                                    onClick={() => { setData('body', aiSuggestedReply); setAiSuggestedReply(null); }}
                                    className="px-4 py-1.5 rounded-xl bg-white text-black text-xs font-bold hover:bg-zinc-200 transition"
                                >
                                    [Use Reply]
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Composer Box */}
                    <div className="border-t border-zinc-800 bg-zinc-950 p-3.5 shrink-0">
                        {sendError && (
                            <div className="mb-2.5 p-2.5 rounded-xl bg-zinc-900 border border-zinc-700 text-white text-xs flex items-center justify-between">
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

                            <form onSubmit={handleSend} className="space-y-2.5">
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
                                    className="w-full text-xs bg-zinc-900 rounded-xl p-3 text-white focus:outline-none border border-zinc-800 focus:border-white focus:ring-1 focus:ring-white resize-none placeholder-zinc-500 transition"
                                />

                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={() => setShowEmoji(!showEmoji)}
                                            className="p-2 rounded-xl text-zinc-400 hover:text-white hover:bg-zinc-900 transition"
                                            title="Add Emoji"
                                        >
                                            <Smile className="w-4 h-4" />
                                        </button>
                                        {isWhatsApp && (
                                            <button
                                                type="button"
                                                onClick={() => setShowTemplates(!showTemplates)}
                                                className="px-3 py-1.5 rounded-xl text-white hover:bg-zinc-900 text-xs font-bold flex items-center gap-1.5 transition border border-zinc-800"
                                                title="WhatsApp Template"
                                            >
                                                <LayoutTemplate className="w-4 h-4 text-white" /> Templates
                                            </button>
                                        )}
                                        <div className="relative">
                                            <button
                                                type="button"
                                                onClick={() => setShowAiMenu(!showAiMenu)}
                                                disabled={aiLoading}
                                                className="px-3 py-1.5 rounded-xl bg-zinc-900 text-white border border-zinc-800 hover:bg-zinc-800 text-xs font-bold flex items-center gap-1.5 transition"
                                                title="AI Reply Assistant"
                                            >
                                                <Sparkles className="w-4 h-4 text-white" /> AI Assistant <ChevronDown className="w-3 h-3" />
                                            </button>

                                            {showAiMenu && (
                                                <div className="absolute bottom-full mb-2 left-0 z-50 bg-zinc-900 border border-zinc-800 rounded-2xl shadow-2xl p-2 w-56 space-y-1 text-xs">
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
                                                            className="w-full text-left p-2 rounded-xl hover:bg-zinc-800 transition block"
                                                        >
                                                            <span className="font-bold text-white block">{m.label}</span>
                                                            <span className="text-[10px] text-zinc-400 block">{m.desc}</span>
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={sending || !data.body.trim()}
                                        className="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-white hover:bg-zinc-200 text-black text-xs font-extrabold shadow-md transition disabled:opacity-50"
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
                <div className={`w-72 lg:w-80 shrink-0 border-l border-zinc-800 bg-black flex flex-col h-full overflow-y-auto ${
                    showCustomerSidebar ? (mobileTab === 'customer' ? 'flex' : 'hidden lg:flex') : 'hidden'
                }`}>
                    {/* Header */}
                    <div className="p-3.5 border-b border-zinc-800 flex items-center justify-between">
                        <h3 className="text-xs font-bold text-white uppercase tracking-wider">CUSTOMER 360</h3>
                        <button onClick={() => setMobileTab('chat')} className="md:hidden text-zinc-400 hover:text-white">
                            <X className="w-4 h-4" />
                        </button>
                    </div>

                    <div className="p-4 space-y-4 text-xs">
                        {/* Profile Card */}
                        <div className="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 shadow-sm space-y-3">
                            <div className="flex items-center gap-3">
                                <div className="h-11 w-11 rounded-full bg-white text-black font-black text-sm flex items-center justify-center shadow-xs shrink-0">
                                    {contactName[0]?.toUpperCase()}
                                </div>
                                <div className="min-w-0">
                                    <h4 className="font-bold text-white truncate">{contactName}</h4>
                                    <p className="text-[11px] text-zinc-400 truncate">{conversation.contact?.phone_e164 || 'No phone'}</p>
                                    <p className="text-[11px] text-zinc-400 truncate">{conversation.contact?.email || 'No email'}</p>
                                </div>
                            </div>

                            {/* Connected Channel Badges */}
                            <div className="flex flex-wrap gap-1.5 pt-2 border-t border-zinc-900 text-[10px]">
                                {journey.channels && journey.channels.length > 0 ? (
                                    journey.channels.map((ch) => (
                                        <span key={ch} className="px-2.5 py-1 rounded-full bg-zinc-900 border border-zinc-800 font-bold text-zinc-300 capitalize flex items-center gap-1">
                                            <ChannelBrandIcon channel={ch} className="w-3 h-3" />
                                            {ch}
                                        </span>
                                    ))
                                ) : (
                                    <span className="text-zinc-500 italic">No channels linked</span>
                                )}
                            </div>
                        </div>

                        {/* Next Action Card */}
                        {journey.next_action && (
                            <div className="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 space-y-2">
                                <div className="flex items-center gap-1.5 text-white font-bold text-[11px]">
                                    <Clock className="w-3.5 h-3.5 text-white" /> Next Action
                                </div>
                                <p className="font-bold text-xs text-white">{journey.next_action.title}</p>
                                <div className="text-[11px] text-zinc-400 font-mono font-semibold">
                                    ⏱ Due: {journey.next_action.due_at}
                                </div>
                                <Link href={route('client.voice.follow-ups.show', journey.next_action.uuid)} className="block pt-1">
                                    <button className="w-full py-1.5 rounded-xl bg-white text-black font-bold text-[11px] hover:bg-zinc-200 transition">
                                        Execute Action
                                    </button>
                                </Link>
                            </div>
                        )}

                        {/* AI Customer Summary Card */}
                        {aiCustomerSummary?.summary && (
                            <div className="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 space-y-2.5">
                                <div className="flex items-center gap-1.5 text-white font-bold text-[11px]">
                                    <Sparkles className="w-3.5 h-3.5 text-white" /> AI Customer Summary
                                </div>
                                <p className="text-zinc-300 text-[11px] leading-relaxed">
                                    {aiCustomerSummary.summary}
                                </p>
                                <div className="flex justify-between text-[11px] pt-2 border-t border-zinc-900">
                                    <span className="text-zinc-400">Lead Interest:</span>
                                    <span className="font-bold text-white">🔥 {aiCustomerSummary.lead_interest || 'Medium'}</span>
                                </div>
                            </div>
                        )}

                        {/* Quick Actions */}
                        <div className="space-y-2">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-zinc-400">Quick Actions</span>
                            <div className="grid grid-cols-2 gap-2">
                                <Link href={route('client.voice.follow-ups.create')} className="w-full">
                                    <button className="w-full p-2.5 rounded-xl bg-zinc-900 border border-zinc-800 font-bold text-white hover:bg-zinc-800 flex items-center justify-center gap-1.5 text-[11px] transition">
                                        <Plus className="w-3.5 h-3.5 text-white" /> + Task
                                    </button>
                                </Link>
                                <button
                                    onClick={() => {
                                        if (conversation.contact?.uuid) {
                                            router.visit(route('client.contacts.show', conversation.contact.uuid));
                                        }
                                    }}
                                    className="p-2.5 rounded-xl bg-zinc-900 border border-zinc-800 font-bold text-white hover:bg-zinc-800 flex items-center justify-center gap-1.5 text-[11px] transition"
                                >
                                    <ExternalLink className="w-3.5 h-3.5 text-white" /> Customer 360
                                </button>
                            </div>
                        </div>

                        {/* Tags Section */}
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-[10px] font-bold uppercase tracking-wider text-zinc-400">Tags</span>
                                <button onClick={() => setShowTagModal(true)} className="text-[11px] font-bold text-white hover:underline">
                                    + Add Tag
                                </button>
                            </div>
                            <div className="flex flex-wrap gap-1.5">
                                {contactTags.length === 0 ? (
                                    <span className="text-[11px] text-zinc-500 italic">No tags attached</span>
                                ) : (
                                    contactTags.map(t => (
                                        <span key={t.id} className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-zinc-800 border border-zinc-700 text-white shadow-xs">
                                            {t.name}
                                        </span>
                                    ))
                                )}
                            </div>
                        </div>

                        {/* Internal Notes */}
                        <div className="space-y-2">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-zinc-400">Internal Notes (Team Only)</span>
                            <form onSubmit={handleAddNote} className="space-y-2">
                                <textarea
                                    value={newNote}
                                    onChange={e => setNewNote(e.target.value)}
                                    rows={2}
                                    placeholder="Add a private note..."
                                    className="w-full text-xs bg-zinc-900 rounded-xl p-3 text-white focus:outline-none border border-zinc-800 focus:border-white resize-none placeholder-zinc-500 transition"
                                />
                                <div className="flex justify-end">
                                    <button
                                        type="submit"
                                        disabled={notePosting || !newNote.trim()}
                                        className="px-3.5 py-1.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-extrabold text-[11px] disabled:opacity-50 transition"
                                    >
                                        {notePosting ? 'Saving...' : 'Add Note'}
                                    </button>
                                </div>
                            </form>

                            <div className="space-y-2 max-h-48 overflow-y-auto">
                                {notes.length === 0 ? (
                                    <p className="text-[11px] text-zinc-500 italic">No notes yet.</p>
                                ) : (
                                    notes.map(note => (
                                        <div key={note.id} className="p-3 rounded-xl bg-zinc-950 border border-zinc-800 text-[11px]">
                                            <div className="flex justify-between text-[10px] text-zinc-300 font-bold mb-1">
                                                <span>{note.user?.name || 'Agent'}</span>
                                                <span className="text-zinc-500 font-normal">{formatTimeTz(note.created_at, userTz)}</span>
                                            </div>
                                            <p className="text-zinc-300 whitespace-pre-wrap">{note.body}</p>
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
