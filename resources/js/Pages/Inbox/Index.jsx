import { Head, Link, router, usePage } from '@inertiajs/react';
import InboxLayout from '@/Layouts/InboxLayout';
import NewConversationModal from '@/Components/Inbox/NewConversationModal';
import {
    MessageSquare, Inbox, CheckCircle, Clock, User,
    Search, Plus, Bot, Check, CheckCheck, X, Settings
} from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { ChannelBrandIcon, CHANNEL_LABELS } from '@/Components/BrandIcons';
import { formatTimeTz } from '@/Utils/datetime';

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

const AVATAR_COLORS = [
    'bg-blue-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500',
    'bg-violet-500', 'bg-cyan-500', 'bg-orange-500', 'bg-pink-500',
];

function getAvatarColor(name) {
    let hash = 0;
    for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
    return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
}

function ConversationListItem({ conv, isActive, userTz }) {
    const channel = conv.channel || conv.channel_account?.channel || 'whatsapp';
    const lastMsg = conv.last_message ?? {};
    const name = conv.contact?.first_name || conv.contact?.last_name
        ? `${conv.contact.first_name ?? ''} ${conv.contact.last_name ?? ''}`.trim()
        : conv.contact?.phone_e164 ?? conv.contact?.email ?? 'Customer';

    const isBot = conv.assigned_to === 'bot' || conv.assigned_to === 'ai';
    const isUnread = (conv.unread_count ?? 0) > 0;
    const initials = name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase() || '?';

    return (
        <Link
            href={route('client.inbox.show', conv.uuid)}
            className={`group block px-3 py-2.5 transition-colors ${
                isActive
                    ? 'bg-blue-50 dark:bg-blue-950/30 border-l-[3px] border-l-blue-600'
                    : isUnread
                        ? 'bg-blue-50/40 dark:bg-neutral-800/50 border-l-[3px] border-l-transparent'
                        : 'border-l-[3px] border-l-transparent hover:bg-neutral-50 dark:hover:bg-neutral-800/40'
            }`}
        >
            <div className="flex items-center gap-2.5">
                <div className="relative shrink-0">
                    <div className={`h-9 w-9 rounded-full ${getAvatarColor(name)} text-white flex items-center justify-center text-[11px] font-bold`}>
                        {initials}
                    </div>
                    <span className="absolute -bottom-0.5 -right-0.5 h-4 w-4 rounded-full bg-white dark:bg-neutral-900 flex items-center justify-center">
                        <ChannelBrandIcon channel={channel} className="h-2.5 w-2.5" />
                    </span>
                </div>

                <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between gap-1">
                        <span className={`text-[13px] truncate ${isUnread ? 'font-bold text-neutral-900 dark:text-white' : 'font-medium text-neutral-700 dark:text-neutral-200'}`}>
                            {name}
                        </span>
                        <span className="text-[10px] text-neutral-400 dark:text-neutral-500 shrink-0 tabular-nums">
                            {conv.last_message_at ? formatTimeTz(conv.last_message_at, userTz) : ''}
                        </span>
                    </div>

                    <div className="flex items-center justify-between gap-2 mt-0.5">
                        <div className="flex items-center gap-1 min-w-0 flex-1">
                            {lastMsg.direction === 'out' && (
                                <span className="shrink-0 text-neutral-400">
                                    {lastMsg.status === 'read' ? (
                                        <CheckCheck className="w-3 h-3 text-blue-500" />
                                    ) : (
                                        <Check className="w-3 h-3" />
                                    )}
                                </span>
                            )}
                            <p className={`text-xs truncate ${isUnread ? 'font-medium text-neutral-800 dark:text-neutral-200' : 'text-neutral-500 dark:text-neutral-400'}`}>
                                {lastMsg.body || '(media)'}
                            </p>
                        </div>
                        <div className="flex items-center gap-1.5 shrink-0">
                            {isBot && (
                                <span className="inline-flex items-center gap-0.5 px-1 py-px rounded text-[9px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                    <span className="h-1 w-1 rounded-full bg-blue-500 animate-pulse" />AI
                                </span>
                            )}
                            {isUnread && (
                                <span className="h-4.5 min-w-4.5 rounded-full bg-blue-600 text-white text-[10px] font-bold flex items-center justify-center px-1">
                                    {conv.unread_count > 99 ? '99+' : conv.unread_count}
                                </span>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </Link>
    );
}

export default function InboxIndex({
    conversations: initialConversations,
    filters = {},
    labels = [],
    channelAccounts = [],
    counts = {},
}) {
    const { t } = useTranslation();
    const { props } = usePage();
    const userTz = props.timezone || 'Asia/Dhaka';

    const [conversations, setConversations] = useState(initialConversations);
    const [search, setSearch]               = useState(filters.search || filters.q || '');
    const [showNewModal, setShowNewModal]   = useState(false);
    const searchTimer = useRef(null);

    useEffect(() => {
        setConversations(initialConversations);
    }, [initialConversations]);

    const handleFilterChange = (newFilters) => {
        router.get(
            route('client.inbox.index'),
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

    const activeFolder = filters.folder || null;
    const activeChannel = filters.channel || null;
    const convList = Array.isArray(conversations)
        ? conversations
        : (Array.isArray(conversations?.data) ? conversations.data : []);

    return (
        <InboxLayout>
            <Head title="Unified Omnichannel Inbox — Growbridge Connect" />

            <div className="flex h-full overflow-hidden bg-[#FAFAF9] dark:bg-neutral-950 text-neutral-900 dark:text-neutral-100">
                {/* 1. LEFT COLUMN: CHANNELS & FILTERS SIDEBAR */}
                <div className="w-52 shrink-0 flex flex-col h-full bg-white dark:bg-neutral-900 border-r border-neutral-200 dark:border-neutral-800 overflow-y-auto hidden md:flex">
                    {/* Header */}
                    <div className="px-3 py-3 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                        <h2 className="text-[11px] font-bold text-neutral-900 dark:text-white uppercase tracking-widest">Inbox</h2>
                        <Link
                            href={route('client.inbox.setup')}
                            title="Channel Setup"
                            className="p-1 rounded-md text-neutral-400 hover:text-neutral-700 hover:bg-neutral-100 dark:hover:text-white dark:hover:bg-neutral-800 transition"
                        >
                            <Settings className="w-3.5 h-3.5" />
                        </Link>
                    </div>

                    {/* Views */}
                    <div className="px-2 pt-3 pb-2 space-y-0.5">
                        <p className="text-[9px] font-bold uppercase tracking-widest text-neutral-400 dark:text-neutral-500 px-2 mb-1.5">Views</p>
                        {VIEWS.map(({ key, label, icon: Icon, countKey }) => {
                            const isSelected = activeFolder === key && !activeChannel;
                            const count = counts[countKey] ?? 0;

                            return (
                                <button
                                    key={label}
                                    onClick={() => handleFilterChange({ folder: key, channel: null })}
                                    className={`w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-[12px] transition ${
                                        isSelected
                                            ? 'bg-blue-600 text-white font-semibold shadow-sm'
                                            : 'text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 font-medium'
                                    }`}
                                >
                                    <div className="flex items-center gap-2 min-w-0">
                                        <Icon className="h-3.5 w-3.5 shrink-0" />
                                        <span className="truncate">{label}</span>
                                    </div>
                                    {count > 0 && (
                                        <span className={`text-[10px] font-semibold tabular-nums ${
                                            isSelected ? 'text-blue-100' : 'text-neutral-400 dark:text-neutral-500'
                                        }`}>
                                            {count}
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>

                    <hr className="border-neutral-200 dark:border-neutral-800 mx-3" />

                    {/* Channels */}
                    <div className="px-2 pt-3 pb-2 space-y-0.5">
                        <p className="text-[9px] font-bold uppercase tracking-widest text-neutral-400 dark:text-neutral-500 px-2 mb-1.5">Channels</p>
                        {CHANNELS.map(ch => {
                            const isSelected = activeChannel === ch.key;
                            const count = counts[ch.countKey] ?? 0;

                            return (
                                <button
                                    key={ch.key}
                                    onClick={() => handleFilterChange({ channel: ch.key, folder: null })}
                                    className={`w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-[12px] transition ${
                                        isSelected
                                            ? 'bg-blue-600 text-white font-semibold shadow-sm'
                                            : 'text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 font-medium'
                                    }`}
                                >
                                    <div className="flex items-center gap-2 min-w-0">
                                        <ChannelBrandIcon channel={ch.icon} className="h-3.5 w-3.5 shrink-0" />
                                        <span className="truncate">{ch.label}</span>
                                    </div>
                                    {count > 0 && (
                                        <span className={`text-[10px] font-semibold tabular-nums ${
                                            isSelected ? 'text-blue-100' : 'text-neutral-400 dark:text-neutral-500'
                                        }`}>
                                            {count}
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>

                    {/* Tags / Labels */}
                    {labels.length > 0 && (
                        <>
                            <hr className="border-neutral-200 dark:border-neutral-800 mx-3" />
                            <div className="px-2 pt-3 pb-2 space-y-0.5">
                                <p className="text-[9px] font-bold uppercase tracking-widest text-neutral-400 dark:text-neutral-500 px-2 mb-1.5">Tags</p>
                                {labels.map(lbl => (
                                    <button
                                        key={lbl.id}
                                        onClick={() => handleFilterChange({ label: filters.label === String(lbl.id) ? null : lbl.id })}
                                        className={`w-full flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-[12px] transition ${
                                            String(filters.label) === String(lbl.id)
                                                ? 'bg-blue-50 dark:bg-neutral-800 text-blue-700 dark:text-blue-300 font-semibold'
                                                : 'text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 font-medium'
                                        }`}
                                    >
                                        <span className="h-1.5 w-1.5 rounded-full shrink-0 bg-blue-500" />
                                        <span className="truncate">{lbl.name}</span>
                                    </button>
                                ))}
                            </div>
                        </>
                    )}
                </div>

                {/* 2. CENTER COLUMN: CONVERSATION LIST */}
                <div className="w-full md:w-80 lg:w-96 flex flex-col border-r border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shrink-0">
                    <div className="px-3 py-2.5 border-b border-neutral-200 dark:border-neutral-800">
                        <div className="flex items-center justify-between gap-2 mb-2">
                            <h1 className="text-[13px] font-semibold text-neutral-900 dark:text-white">Conversations</h1>
                            <button
                                onClick={() => setShowNewModal(true)}
                                className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-semibold shadow-sm transition"
                            >
                                <Plus className="w-3 h-3" /> New
                            </button>
                        </div>

                        <div className="relative">
                            <Search className="absolute left-2.5 top-2 h-3.5 w-3.5 text-neutral-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={e => handleSearchChange(e.target.value)}
                                placeholder="Search conversations..."
                                className="w-full text-[12px] bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg pl-8 pr-7 py-1.5 text-neutral-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder-neutral-400 dark:placeholder-neutral-500 transition"
                            />
                            {search && (
                                <button
                                    onClick={() => handleSearchChange('')}
                                    className="absolute right-2.5 top-2 text-neutral-400 hover:text-neutral-700 dark:hover:text-white"
                                >
                                    <X className="w-3.5 h-3.5" />
                                </button>
                            )}
                        </div>
                    </div>

                    <div className="flex-1 overflow-y-auto">
                        {convList.length === 0 ? (
                            <div className="p-8 text-center">
                                <MessageSquare className="w-7 h-7 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
                                <p className="text-[13px] font-medium text-neutral-700 dark:text-neutral-200">No conversations</p>
                                <p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5 mb-3">Try a different filter or start a new thread.</p>
                                <Link
                                    href={route('client.inbox.setup')}
                                    className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-200 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-[11px] font-medium transition"
                                >
                                    <Settings className="w-3 h-3" /> Connect Channels
                                </Link>
                            </div>
                        ) : (
                            convList.map(conv => (
                                <ConversationListItem
                                    key={conv.id}
                                    conv={conv}
                                    isActive={false}
                                    userTz={userTz}
                                />
                            ))
                        )}
                    </div>
                </div>

                {/* 3. RIGHT COLUMN: EMPTY STATE */}
                <div className="hidden md:flex flex-1 flex-col items-center justify-center p-8 text-center bg-neutral-50 dark:bg-neutral-950">
                    <div className="h-11 w-11 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 text-blue-500 flex items-center justify-center mb-3">
                        <MessageSquare className="w-5 h-5" />
                    </div>
                    <h2 className="text-sm font-semibold text-neutral-800 dark:text-white">Select a conversation</h2>
                    <p className="text-xs text-neutral-500 dark:text-neutral-400 max-w-xs mt-1 leading-relaxed">
                        Choose a conversation from the list to view messages and reply.
                    </p>
                    <button
                        onClick={() => setShowNewModal(true)}
                        className="mt-4 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-[12px] font-semibold shadow-sm transition"
                    >
                        <Plus className="w-3.5 h-3.5" /> New Conversation
                    </button>
                </div>
            </div>

            {showNewModal && (
                <NewConversationModal
                    onClose={() => setShowNewModal(false)}
                />
            )}
        </InboxLayout>
    );
}
