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
                    ? 'bg-neutral-100 dark:bg-neutral-800 border-l-[3px] border-l-neutral-900 dark:border-l-white'
                    : isUnread
                        ? 'bg-neutral-50 dark:bg-neutral-800/50 border-l-[3px] border-l-transparent'
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
                                        <CheckCheck className="w-3 h-3 text-neutral-500" />
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
                                <span className="inline-flex items-center gap-0.5 px-1 py-px rounded text-[9px] font-bold bg-teal-50 text-teal-700 dark:bg-teal-950/40 dark:text-teal-400">
                                    <span className="h-1 w-1 rounded-full bg-teal-500 animate-pulse" />AI
                                </span>
                            )}
                            {isUnread && (
                                <span className="h-4.5 min-w-4.5 rounded-full bg-neutral-900 dark:bg-white dark:text-neutral-900 text-white text-[10px] font-bold flex items-center justify-center px-1">
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

    const sidebarExtra = (
        <div className="mt-2">
            <div className="mb-0.5">
                <p className="flex w-full items-center gap-1 px-2 py-1.5 mt-2 text-xs font-medium text-neutral-500 dark:text-neutral-400 select-none">Views</p>
                <div className="space-y-px">
                    {VIEWS.map(({ key, label, icon: Icon, countKey }) => {
                        const isSelected = activeFolder === key && !activeChannel;
                        const count = counts[countKey] ?? 0;

                        return (
                            <button
                                key={label}
                                type="button"
                                onClick={() => handleFilterChange({ folder: key, channel: null })}
                                className={[
                                    'group flex w-full items-center gap-2.5 rounded-full px-3 py-1.5 text-[13px] transition-colors duration-100',
                                    isSelected
                                        ? 'bg-neutral-900 text-white font-semibold dark:bg-white dark:text-neutral-900'
                                        : 'text-neutral-600 dark:text-neutral-400 font-normal hover:bg-neutral-900/5 dark:hover:bg-white/10 hover:text-neutral-900 dark:hover:text-neutral-100',
                                ].join(' ')}
                            >
                                <Icon className={[
                                    'h-4 w-4 shrink-0 transition-colors duration-100',
                                    isSelected ? 'text-white dark:text-neutral-900' : 'text-neutral-400 dark:text-neutral-500 group-hover:text-neutral-600 dark:group-hover:text-neutral-300',
                                ].join(' ')} />
                                <span className="truncate flex-1 text-left">{label}</span>
                                {count > 0 && (
                                    <span className={`text-[11px] font-medium shrink-0 ${isSelected ? 'text-neutral-300 dark:text-neutral-600' : 'text-neutral-400 dark:text-neutral-500'}`}>
                                        {count}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>
            </div>

            <div className="mb-0.5">
                <p className="flex w-full items-center gap-1 px-2 py-1.5 mt-2 text-xs font-medium text-neutral-500 dark:text-neutral-400 select-none">Channels</p>
                <div className="space-y-px">
                    {CHANNELS.map(ch => {
                        const isSelected = activeChannel === ch.key;
                        const count = counts[ch.countKey] ?? 0;

                        return (
                            <button
                                key={ch.key}
                                type="button"
                                onClick={() => handleFilterChange({ channel: ch.key, folder: null })}
                                className={[
                                    'group flex w-full items-center gap-2.5 rounded-full px-3 py-1.5 text-[13px] transition-colors duration-100',
                                    isSelected
                                        ? 'bg-neutral-900 text-white font-semibold dark:bg-white dark:text-neutral-900'
                                        : 'text-neutral-600 dark:text-neutral-400 font-normal hover:bg-neutral-900/5 dark:hover:bg-white/10 hover:text-neutral-900 dark:hover:text-neutral-100',
                                ].join(' ')}
                            >
                                <span className={[
                                    'shrink-0 transition-colors duration-100',
                                    isSelected ? 'text-white dark:text-neutral-900' : 'text-neutral-400 dark:text-neutral-500 group-hover:text-neutral-600 dark:group-hover:text-neutral-300',
                                ].join(' ')}>
                                    <ChannelBrandIcon channel={ch.icon} className="h-4 w-4" />
                                </span>
                                <span className="truncate flex-1 text-left">{ch.label}</span>
                                {count > 0 && (
                                    <span className={`text-[11px] font-medium shrink-0 ${isSelected ? 'text-neutral-300 dark:text-neutral-600' : 'text-neutral-400 dark:text-neutral-500'}`}>
                                        {count}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>
            </div>

            {labels.length > 0 && (
                <div className="mb-0.5">
                    <p className="flex w-full items-center gap-1 px-2 py-1.5 mt-2 text-xs font-medium text-neutral-500 dark:text-neutral-400 select-none">Tags</p>
                    <div className="space-y-px">
                        {labels.map(lbl => (
                            <button
                                key={lbl.id}
                                type="button"
                                onClick={() => handleFilterChange({ label: filters.label === String(lbl.id) ? null : lbl.id })}
                                className={[
                                    'group flex w-full items-center gap-2.5 rounded-full px-3 py-1.5 text-[13px] transition-colors duration-100',
                                    String(filters.label) === String(lbl.id)
                                        ? 'bg-neutral-900 text-white font-semibold dark:bg-white dark:text-neutral-900'
                                        : 'text-neutral-600 dark:text-neutral-400 font-normal hover:bg-neutral-900/5 dark:hover:bg-white/10 hover:text-neutral-900 dark:hover:text-neutral-100',
                                ].join(' ')}
                            >
                                <span className="h-1.5 w-1.5 rounded-full shrink-0 bg-neutral-400" />
                                <span className="truncate">{lbl.name}</span>
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );

    return (
        <InboxLayout sidebarExtra={sidebarExtra}>
            <Head title="Unified Omnichannel Inbox — Growbridge Connect" />

            <div className="flex h-full overflow-hidden bg-white dark:bg-neutral-950 text-neutral-900 dark:text-neutral-100">
                {/* 1. CENTER COLUMN: CONVERSATION LIST */}
                <div className="w-full md:w-80 lg:w-96 flex flex-col bg-white dark:bg-neutral-900 border-r border-neutral-200 dark:border-neutral-800 shrink-0 overflow-hidden">
                    <div className="px-3 py-2.5 border-b border-neutral-200 dark:border-neutral-800">
                        <div className="flex items-center justify-between gap-2 mb-2">
                            <h1 className="text-[13px] font-semibold text-neutral-900 dark:text-white">Conversations</h1>
                            <button
                                onClick={() => setShowNewModal(true)}
                                className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200 text-white text-[11px] font-semibold shadow-sm transition"
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
                                className="w-full text-[12px] bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg pl-8 pr-7 py-1.5 text-neutral-900 dark:text-white focus:outline-none focus:border-neutral-400 focus:ring-1 focus:ring-neutral-400 placeholder-neutral-400 dark:placeholder-neutral-500 transition"
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

                {/* 2. RIGHT COLUMN: EMPTY STATE */}
                <div className="hidden md:flex flex-1 flex-col items-center justify-center p-8 text-center bg-neutral-50 dark:bg-neutral-950">
                    <div className="h-11 w-11 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 text-neutral-500 flex items-center justify-center mb-3">
                        <MessageSquare className="w-5 h-5" />
                    </div>
                    <h2 className="text-sm font-semibold text-neutral-800 dark:text-white">Select a conversation</h2>
                    <p className="text-xs text-neutral-500 dark:text-neutral-400 max-w-xs mt-1 leading-relaxed">
                        Choose a conversation from the list to view messages and reply.
                    </p>
                    <button
                        onClick={() => setShowNewModal(true)}
                        className="mt-4 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200 text-white text-[12px] font-semibold shadow-sm transition"
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
