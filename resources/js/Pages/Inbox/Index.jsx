import { Head, Link, router, usePage } from '@inertiajs/react';
import InboxLayout from '@/Layouts/InboxLayout';
import NewConversationModal from '@/Components/Inbox/NewConversationModal';
import {
    MessageSquare, Inbox, CheckCircle, Clock, User,
    Search, Plus, Bot, PhoneCall, Mail,
    Sparkles, Check, CheckCheck, Tag, Filter, X
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

function ConversationListItem({ conv, isActive, userTz }) {
    const channel = conv.channel || conv.channel_account?.channel || 'whatsapp';
    const lastMsg = conv.last_message ?? {};
    const name = conv.contact?.first_name || conv.contact?.last_name
        ? `${conv.contact.first_name ?? ''} ${conv.contact.last_name ?? ''}`.trim()
        : conv.contact?.phone_e164 ?? conv.contact?.email ?? 'Customer';

    const isBot = conv.assigned_to === 'bot' || conv.assigned_to === 'ai';
    const isUnread = (conv.unread_count ?? 0) > 0;

    return (
        <Link
            href={route('client.inbox.show', conv.uuid)}
            className={`block px-3.5 py-3 border-b border-neutral-100 dark:border-neutral-800 transition-colors ${
                isActive
                    ? 'bg-brand-50 dark:bg-brand-900/20 border-l-4 border-l-brand-600'
                    : isUnread
                        ? 'bg-neutral-50/80 dark:bg-neutral-800/40'
                        : 'hover:bg-neutral-50 dark:hover:bg-neutral-800/50'
            }`}
        >
            <div className="flex items-start gap-3">
                {/* Profile Avatar with Channel Icon badge */}
                <div className="relative shrink-0">
                    <div className="h-10 w-10 rounded-full bg-gradient-to-br from-brand-500 to-indigo-600 flex items-center justify-center text-xs font-bold text-white shadow-xs">
                        {name[0]?.toUpperCase() ?? '?'}
                    </div>
                    <span className="absolute -bottom-1 -right-1 h-5 w-5 rounded-full bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 flex items-center justify-center shadow-2xs">
                        <ChannelBrandIcon channel={channel} className="h-3 w-3" />
                    </span>
                </div>

                {/* Main Information */}
                <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between gap-1">
                        <div className="flex items-center gap-1.5 min-w-0">
                            <span className={`text-xs truncate ${isUnread ? 'font-bold text-neutral-900 dark:text-neutral-100' : 'font-semibold text-neutral-800 dark:text-neutral-200'}`}>
                                {name}
                            </span>
                            {/* AI / Human status indicator */}
                            {isBot ? (
                                <span className="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded text-[10px] font-semibold bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">
                                    <span className="h-1.5 w-1.5 rounded-full bg-purple-500 animate-pulse" /> AI
                                </span>
                            ) : (
                                <span className="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded text-[10px] font-medium bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                                    <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" /> Human
                                </span>
                            )}
                        </div>
                        <span className="text-[10px] text-neutral-400 shrink-0 font-medium">
                            {conv.last_message_at ? formatTimeTz(conv.last_message_at, userTz) : ''}
                        </span>
                    </div>

                    <div className="flex items-center gap-1.5 mt-1">
                        {lastMsg.direction === 'out' && (
                            <span className="shrink-0 text-neutral-400">
                                {lastMsg.status === 'read' ? (
                                    <CheckCheck className="w-3.5 h-3.5 text-blue-500" />
                                ) : (
                                    <Check className="w-3.5 h-3.5" />
                                )}
                            </span>
                        )}
                        <p className={`text-xs truncate flex-1 ${isUnread ? 'font-semibold text-neutral-900 dark:text-neutral-200' : 'text-neutral-500 dark:text-neutral-400'}`}>
                            {lastMsg.body || '(media message)'}
                        </p>
                        {isUnread && (
                            <span className="shrink-0 h-4 min-w-4 rounded-full bg-brand-600 text-white text-[10px] font-bold flex items-center justify-center px-1">
                                {conv.unread_count > 99 ? '99+' : conv.unread_count}
                            </span>
                        )}
                    </div>

                    {/* Contact Tags */}
                    {conv.contact?.tags?.length > 0 && (
                        <div className="flex flex-wrap gap-1 mt-1.5">
                            {conv.contact.tags.slice(0, 3).map(tag => (
                                <span
                                    key={tag.id}
                                    className="inline-flex items-center px-1.5 py-0.2 rounded-full text-[9px] font-medium text-white shadow-2xs"
                                    style={{ backgroundColor: tag.color || '#3b82f6' }}
                                >
                                    {tag.name}
                                </span>
                            ))}
                        </div>
                    )}
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
    const convList = conversations?.data ?? [];

    return (
        <InboxLayout>
            <Head title="Unified Omnichannel Inbox — Growbridge Connect" />

            <div className="flex h-full overflow-hidden bg-white dark:bg-neutral-950">
                {/* 1. LEFT COLUMN: CHANNELS & FILTERS SIDEBAR */}
                <div className="w-56 shrink-0 flex flex-col h-full bg-neutral-50/80 dark:bg-neutral-900/60 border-r border-neutral-200 dark:border-neutral-800 overflow-y-auto hidden md:flex">
                    {/* Brand / Header */}
                    <div className="p-3.5 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="h-7 w-7 rounded-lg bg-brand-600 flex items-center justify-center text-white font-bold text-xs shadow-xs">
                                GC
                            </div>
                            <div>
                                <h2 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">Inbox</h2>
                                <p className="text-[10px] text-neutral-400">Unified Hub</p>
                            </div>
                        </div>
                    </div>

                    {/* Views Section */}
                    <div className="p-2 space-y-0.5 border-b border-neutral-200 dark:border-neutral-800">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 px-2 py-1">Views</p>
                        {VIEWS.map(({ key, label, icon: Icon, countKey }) => {
                            const isSelected = activeFolder === key && !activeChannel;
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

                    {/* Channels Section */}
                    <div className="p-2 space-y-0.5 border-b border-neutral-200 dark:border-neutral-800">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 px-2 py-1">Channels</p>
                        {CHANNELS.map(ch => {
                            const isSelected = activeChannel === ch.key;
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

                    {/* Tags / Labels */}
                    {labels.length > 0 && (
                        <div className="p-2 space-y-0.5">
                            <p className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 px-2 py-1">Tags</p>
                            {labels.map(lbl => (
                                <button
                                    key={lbl.id}
                                    onClick={() => handleFilterChange({ label: filters.label === String(lbl.id) ? null : lbl.id })}
                                    className={`w-full flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-xs font-medium transition ${
                                        String(filters.label) === String(lbl.id)
                                            ? 'bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-300 font-semibold'
                                            : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200/50 dark:hover:bg-neutral-800/50'
                                    }`}
                                >
                                    <span className="h-2 w-2 rounded-full shrink-0" style={{ backgroundColor: lbl.color }} />
                                    <span className="truncate">{lbl.name}</span>
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* 2. CENTER COLUMN: CONVERSATION LIST */}
                <div className="w-full md:w-80 lg:w-96 flex flex-col border-r border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shrink-0">
                    <div className="p-3 border-b border-neutral-200 dark:border-neutral-800">
                        <div className="flex items-center justify-between gap-2 mb-2">
                            <h1 className="text-sm font-bold text-neutral-900 dark:text-white">Conversations</h1>
                            <button
                                onClick={() => setShowNewModal(true)}
                                className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold shadow-xs transition"
                            >
                                <Plus className="w-3.5 h-3.5" /> New
                            </button>
                        </div>

                        {/* Debounced Search */}
                        <div className="relative">
                            <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-neutral-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={e => handleSearchChange(e.target.value)}
                                placeholder="Search customer, phone, email, text..."
                                className="w-full text-xs bg-neutral-100 dark:bg-neutral-800 rounded-lg pl-8 pr-7 py-2 text-neutral-800 dark:text-neutral-200 focus:outline-none focus:ring-1 focus:ring-brand-500 placeholder-neutral-400"
                            />
                            {search && (
                                <button
                                    onClick={() => handleSearchChange('')}
                                    className="absolute right-2.5 top-2.5 text-neutral-400 hover:text-neutral-600"
                                >
                                    <X className="w-3.5 h-3.5" />
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Conversation List Stream */}
                    <div className="flex-1 overflow-y-auto divide-y divide-neutral-100 dark:divide-neutral-800">
                        {convList.length === 0 ? (
                            <div className="p-8 text-center">
                                <MessageSquare className="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
                                <p className="text-xs font-medium text-neutral-700 dark:text-neutral-300">No conversations found</p>
                                <p className="text-[11px] text-neutral-400 mt-0.5">Try selecting another channel or starting a new thread.</p>
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

                {/* 3. RIGHT COLUMN: EMPTY / PROMPT STATE */}
                <div className="hidden md:flex flex-1 flex-col items-center justify-center p-8 text-center bg-neutral-50/50 dark:bg-neutral-950">
                    <div className="h-14 w-14 rounded-2xl bg-brand-100 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 flex items-center justify-center mb-4 shadow-xs">
                        <Sparkles className="w-7 h-7" />
                    </div>
                    <h2 className="text-base font-bold text-neutral-900 dark:text-white">Unified Omnichannel Inbox</h2>
                    <p className="text-xs text-neutral-500 dark:text-neutral-400 max-w-sm mt-1">
                        Select any conversation from the list to view customer details, message stream, phone call audio transcripts, AI/Human toggles, and send responses.
                    </p>
                    <button
                        onClick={() => setShowNewModal(true)}
                        className="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold shadow-sm transition"
                    >
                        <Plus className="w-4 h-4" /> Start New Conversation
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
