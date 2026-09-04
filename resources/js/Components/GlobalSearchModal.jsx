import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Search, User, MessageSquare, PhoneCall, Megaphone, Zap, ArrowRight, X } from 'lucide-react';
import { Badge } from '@/Components/ui';

export default function GlobalSearchModal({ isOpen, onClose }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState({
        contacts: [],
        conversations: [],
        calls: [],
        campaigns: [],
        automations: [],
    });
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const handleKeyDown = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                if (isOpen) {
                    onClose();
                } else {
                    // Trigger open if parent handles it
                }
            }
            if (e.key === 'Escape' && isOpen) {
                onClose();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, onClose]);

    useEffect(() => {
        if (!query.trim() || query.length < 2) {
            setResults({ contacts: [], conversations: [], calls: [], campaigns: [], automations: [] });
            return;
        }

        const timer = setTimeout(() => {
            setLoading(true);
            window.axios?.get(route('client.search'), { params: { q: query } })
                .then((res) => {
                    setResults(res.data || {});
                })
                .catch(() => {})
                .finally(() => setLoading(false));
        }, 250);

        return () => clearTimeout(timer);
    }, [query]);

    if (!isOpen) return null;

    const navigateTo = (url) => {
        onClose();
        router.visit(url);
    };

    const hasResults = Object.values(results).some((arr) => Array.isArray(arr) && arr.length > 0);

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center pt-20 p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-150">
            <div className="w-full max-w-2xl bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-neutral-800 overflow-hidden">
                {/* Search Bar */}
                <div className="p-4 border-b border-slate-100 dark:border-neutral-800 flex items-center gap-3">
                    <Search className="w-5 h-5 text-slate-400 shrink-0" />
                    <input
                        type="text"
                        autoFocus
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search contacts, conversations, calls, campaigns, automations... (Ctrl+K)"
                        className="w-full bg-transparent border-0 focus:outline-none focus:ring-0 text-slate-900 dark:text-white placeholder-slate-400 text-sm"
                    />
                    {query && (
                        <button
                            type="button"
                            onClick={() => setQuery('')}
                            className="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-white"
                        >
                            <X className="w-4 h-4" />
                        </button>
                    )}
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-2 py-1 text-[11px] font-semibold bg-slate-100 dark:bg-neutral-800 text-slate-500 rounded-lg"
                    >
                        ESC
                    </button>
                </div>

                {/* Results List */}
                <div className="max-h-[60vh] overflow-y-auto p-4 space-y-4">
                    {loading && (
                        <div className="py-8 text-center text-xs text-slate-400">Searching workspace...</div>
                    )}

                    {!loading && query.length >= 2 && !hasResults && (
                        <div className="py-8 text-center text-xs text-slate-400">
                            No results found for &ldquo;{query}&rdquo;.
                        </div>
                    )}

                    {/* Contacts */}
                    {results.contacts?.length > 0 && (
                        <div>
                            <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-2">Contacts</span>
                            <div className="mt-1 space-y-1">
                                {results.contacts.map((c) => (
                                    <div
                                        key={c.id}
                                        onClick={() => navigateTo(c.url)}
                                        className="p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-neutral-800 cursor-pointer flex items-center justify-between transition-colors group"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="p-2 rounded-lg bg-blue-50 dark:bg-blue-950/40 text-blue-600">
                                                <User className="w-4 h-4" />
                                            </div>
                                            <div>
                                                <span className="font-semibold text-xs text-slate-900 dark:text-white block">{c.title}</span>
                                                <span className="text-[11px] text-slate-400">{c.subtitle}</span>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {c.lead_score > 0 && (
                                                <Badge variant="brand" className="text-[10px]">{c.lead_score} pts</Badge>
                                            )}
                                            <ArrowRight className="w-4 h-4 text-slate-400 group-hover:translate-x-0.5 transition-transform" />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Conversations */}
                    {results.conversations?.length > 0 && (
                        <div>
                            <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-2">Conversations</span>
                            <div className="mt-1 space-y-1">
                                {results.conversations.map((conv) => (
                                    <div
                                        key={conv.id}
                                        onClick={() => navigateTo(conv.url)}
                                        className="p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-neutral-800 cursor-pointer flex items-center justify-between transition-colors group"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="p-2 rounded-lg bg-purple-50 dark:bg-purple-950/40 text-purple-600">
                                                <MessageSquare className="w-4 h-4" />
                                            </div>
                                            <div>
                                                <span className="font-semibold text-xs text-slate-900 dark:text-white block">{conv.title}</span>
                                                <span className="text-[11px] text-slate-400">{conv.subtitle}</span>
                                            </div>
                                        </div>
                                        <ArrowRight className="w-4 h-4 text-slate-400 group-hover:translate-x-0.5 transition-transform" />
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* AI Voice Calls */}
                    {results.calls?.length > 0 && (
                        <div>
                            <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-2">AI Voice Calls</span>
                            <div className="mt-1 space-y-1">
                                {results.calls.map((call) => (
                                    <div
                                        key={call.id}
                                        onClick={() => navigateTo(call.url)}
                                        className="p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-neutral-800 cursor-pointer flex items-center justify-between transition-colors group"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600">
                                                <PhoneCall className="w-4 h-4" />
                                            </div>
                                            <div>
                                                <span className="font-semibold text-xs text-slate-900 dark:text-white block">{call.title}</span>
                                                <span className="text-[11px] text-slate-400">{call.subtitle}</span>
                                            </div>
                                        </div>
                                        <ArrowRight className="w-4 h-4 text-slate-400 group-hover:translate-x-0.5 transition-transform" />
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
