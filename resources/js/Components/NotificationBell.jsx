import { useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';
import { Bell, CheckCheck, Flame, Bot, PhoneCall, AlertTriangle, MessageSquare, ExternalLink } from 'lucide-react';
import { Button, Badge } from '@/Components/ui';
import { toast } from 'sonner';

export default function NotificationBell() {
    const [isOpen, setIsOpen] = useState(false);
    const [unreadCount, setUnreadCount] = useState(0);
    const [notifications, setNotifications] = useState([]);
    const [filter, setFilter] = useState('unread'); // 'unread' | 'all'
    const [loading, setLoading] = useState(false);

    const fetchUnreadCount = () => {
        if (document.hidden) return; // Skip polling if tab is in background

        window.axios?.get(route('client.notifications.unread-count'))
            .then((res) => {
                setUnreadCount(res.data.count || 0);
            })
            .catch(() => {});
    };

    const fetchRecent = () => {
        setLoading(true);
        window.axios?.get(route('client.notifications.recent'))
            .then((res) => {
                setNotifications(res.data || []);
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        fetchUnreadCount();
        const interval = setInterval(fetchUnreadCount, 30000); // 30s smart poll
        return () => clearInterval(interval);
    }, []);

    const toggleOpen = () => {
        const nextState = !isOpen;
        setIsOpen(nextState);
        if (nextState) {
            fetchRecent();
        }
    };

    const handleMarkAllRead = () => {
        window.axios?.post(route('client.notifications.mark-all-read'))
            .then(() => {
                setUnreadCount(0);
                setNotifications((prev) => prev.map((n) => ({ ...n, read_at: new Date().toISOString() })));
                toast.success('All notifications marked as read.');
            });
    };

    const handleMarkRead = (id) => {
        window.axios?.post(route('client.notifications.mark-read', id))
            .then(() => {
                setUnreadCount((c) => Math.max(0, c - 1));
                setNotifications((prev) => prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n)));
            });
    };

    const getIcon = (type) => {
        switch (type) {
            case 'hot_lead':
                return <Flame className="w-4 h-4 text-amber-500" />;
            case 'ai_human_handoff':
                return <Bot className="w-4 h-4 text-purple-500" />;
            case 'voice_call_completed':
                return <PhoneCall className="w-4 h-4 text-emerald-500" />;
            case 'quota_warning':
                return <AlertTriangle className="w-4 h-4 text-rose-500" />;
            default:
                return <MessageSquare className="w-4 h-4 text-blue-500" />;
        }
    };

    const filteredList = filter === 'unread'
        ? notifications.filter((n) => !n.read_at)
        : notifications;

    return (
        <div className="relative">
            <button
                type="button"
                onClick={toggleOpen}
                className="relative p-2 rounded-xl text-slate-500 hover:text-slate-900 dark:text-neutral-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-neutral-800 transition-colors"
            >
                <Bell className="w-5 h-5" />
                {unreadCount > 0 && (
                    <span className="absolute top-1 right-1 px-1.5 py-0.2 rounded-full bg-rose-600 text-white text-[10px] font-bold ring-2 ring-white dark:ring-neutral-900">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
            </button>

            {isOpen && (
                <div className="absolute right-0 mt-2 w-80 sm:w-96 rounded-2xl bg-white dark:bg-neutral-900 border border-slate-200 dark:border-neutral-800 shadow-2xl z-50 overflow-hidden">
                    {/* Header */}
                    <div className="p-4 border-b border-slate-100 dark:border-neutral-800 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <span className="font-bold text-sm text-slate-900 dark:text-white">Notifications</span>
                            {unreadCount > 0 && (
                                <Badge variant="neutral" className="text-[10px]">{unreadCount} unread</Badge>
                            )}
                        </div>

                        {unreadCount > 0 && (
                            <button
                                type="button"
                                onClick={handleMarkAllRead}
                                className="text-[11px] font-medium text-brand-600 dark:text-brand-400 hover:underline flex items-center gap-1"
                            >
                                <CheckCheck className="w-3.5 h-3.5" /> Mark all read
                            </button>
                        )}
                    </div>

                    {/* Filter Tabs */}
                    <div className="flex border-b border-slate-100 dark:border-neutral-800 px-4 pt-2 gap-4 text-xs font-medium">
                        <button
                            type="button"
                            onClick={() => setFilter('unread')}
                            className={`pb-2 border-b-2 transition-colors ${
                                filter === 'unread'
                                    ? 'border-brand-900 text-brand-900 dark:text-white font-bold'
                                    : 'border-transparent text-slate-400 hover:text-slate-600'
                            }`}
                        >
                            Unread
                        </button>
                        <button
                            type="button"
                            onClick={() => setFilter('all')}
                            className={`pb-2 border-b-2 transition-colors ${
                                filter === 'all'
                                    ? 'border-brand-900 text-brand-900 dark:text-white font-bold'
                                    : 'border-transparent text-slate-400 hover:text-slate-600'
                            }`}
                        >
                            All
                        </button>
                    </div>

                    {/* List */}
                    <div className="max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-neutral-800">
                        {loading ? (
                            <div className="p-8 text-center text-xs text-slate-400">Loading notifications...</div>
                        ) : filteredList.length === 0 ? (
                            <div className="p-8 text-center text-xs text-slate-400">
                                No {filter === 'unread' ? 'unread ' : ''}notifications.
                            </div>
                        ) : (
                            filteredList.map((n) => {
                                const data = n.data || {};
                                return (
                                    <div
                                        key={n.id}
                                        className={`p-3.5 hover:bg-slate-50 dark:hover:bg-neutral-800/40 transition-colors flex items-start gap-3 ${
                                            !n.read_at ? 'bg-brand-50/20 dark:bg-neutral-800/20' : ''
                                        }`}
                                    >
                                        <div className="p-2 rounded-xl bg-slate-100 dark:bg-neutral-800 shrink-0 mt-0.5">
                                            {getIcon(data.type)}
                                        </div>

                                        <div className="space-y-1 flex-1 min-w-0">
                                            <div className="flex items-center justify-between gap-1">
                                                <span className="font-bold text-xs text-slate-900 dark:text-white truncate">
                                                    {data.title || 'System Notification'}
                                                </span>
                                                <span className="text-[10px] text-slate-400 shrink-0">
                                                    {new Date(n.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                                </span>
                                            </div>

                                            <p className="text-[11px] text-slate-600 dark:text-neutral-300 leading-normal line-clamp-2">
                                                {data.message || ''}
                                            </p>

                                            {data.url && (
                                                <div className="pt-1 flex items-center justify-between">
                                                    <Link
                                                        href={data.url}
                                                        onClick={() => {
                                                            handleMarkRead(n.id);
                                                            setIsOpen(false);
                                                        }}
                                                        className="inline-flex items-center gap-1 text-[11px] font-semibold text-brand-600 dark:text-brand-400 hover:underline"
                                                    >
                                                        View details <ExternalLink className="w-3 h-3" />
                                                    </Link>

                                                    {!n.read_at && (
                                                        <button
                                                            type="button"
                                                            onClick={() => handleMarkRead(n.id)}
                                                            className="text-[10px] text-slate-400 hover:text-slate-600"
                                                        >
                                                            Mark read
                                                        </button>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
