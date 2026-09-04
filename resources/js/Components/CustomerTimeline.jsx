import { useState, useEffect } from 'react';
import {
    MessageSquare, PhoneCall, Sparkles, UserCheck, ShieldAlert,
    Clock, Mail, ArrowRight, CheckCircle2, Bot, Layers,
} from 'lucide-react';
import { Card, Badge, Button } from '@/Components/ui';

export default function CustomerTimeline({ contactUuid }) {
    const [timeline, setTimeline] = useState([]);
    const [contact, setContact] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (!contactUuid) return;
        setLoading(true);
        window.axios.get(route('client.contacts.timeline', contactUuid))
            .then((res) => {
                if (res.data.success) {
                    setTimeline(res.data.timeline || []);
                    setContact(res.data.contact);
                }
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, [contactUuid]);

    if (loading) {
        return (
            <div className="p-6 text-center text-xs text-slate-400">
                <Clock className="w-5 h-5 animate-spin mx-auto mb-2 text-brand-600" />
                Loading unified customer timeline...
            </div>
        );
    }

    const getChannelIcon = (channel, type) => {
        if (type === 'voice_call' || channel === 'phone') {
            return <PhoneCall className="w-4 h-4 text-emerald-600" />;
        }
        if (channel === 'email') {
            return <Mail className="w-4 h-4 text-blue-600" />;
        }
        if (type === 'human_handoff') {
            return <UserCheck className="w-4 h-4 text-amber-600" />;
        }
        if (type === 'opt_out') {
            return <ShieldAlert className="w-4 h-4 text-rose-600" />;
        }
        if (type === 'ai_reply') {
            return <Bot className="w-4 h-4 text-purple-600" />;
        }
        return <MessageSquare className="w-4 h-4 text-brand-600" />;
    };

    return (
        <div className="space-y-4">
            {/* Customer Summary Bar */}
            {contact && (
                <div className="flex items-center justify-between p-3.5 rounded-xl bg-slate-50 dark:bg-neutral-800/60 border border-slate-200 dark:border-neutral-800 text-xs">
                    <div>
                        <span className="text-slate-400 block text-[11px]">Lead Score</span>
                        <div className="flex items-center gap-1.5 mt-0.5">
                            <span className="font-bold text-slate-900 dark:text-white text-sm">{contact.lead_score ?? 10}/100</span>
                            <Badge variant={contact.lead_score_band === 'very_hot' || contact.lead_score_band === 'hot' ? 'success' : 'neutral'} className="capitalize text-[10px]">
                                {contact.lead_score_band || 'Cold'}
                            </Badge>
                        </div>
                    </div>

                    <div>
                        <span className="text-slate-400 block text-[11px]">Opt-in Status</span>
                        <Badge variant={contact.marketing_opt_out ? 'danger' : 'success'} className="mt-0.5 text-[10px]">
                            {contact.marketing_opt_out ? 'Opted Out' : 'Active'}
                        </Badge>
                    </div>
                </div>
            )}

            {/* Timeline Stream */}
            {timeline.length === 0 ? (
                <div className="p-8 text-center text-xs text-slate-400">
                    No customer events recorded yet.
                </div>
            ) : (
                <div className="relative pl-6 space-y-4 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200 dark:before:bg-neutral-800">
                    {timeline.map((evt) => (
                        <div key={evt.id} className="relative group">
                            {/* Dot / Icon */}
                            <div className="absolute -left-6 top-1.5 w-5 h-5 rounded-full bg-white dark:bg-neutral-900 border-2 border-slate-300 dark:border-neutral-700 flex items-center justify-center">
                                <div className="scale-75">{getChannelIcon(evt.channel, evt.type)}</div>
                            </div>

                            {/* Event Card */}
                            <div className="p-3 rounded-xl bg-white dark:bg-neutral-800 border border-slate-200 dark:border-neutral-700/80 shadow-xs text-xs space-y-1.5">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <span className="font-semibold text-slate-900 dark:text-white capitalize">
                                            {evt.title}
                                        </span>
                                        <span className="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-neutral-700 text-slate-600 dark:text-neutral-300 uppercase font-mono">
                                            {evt.channel}
                                        </span>
                                    </div>
                                    <span className="text-[11px] text-slate-400">
                                        {new Date(evt.occurred_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                    </span>
                                </div>

                                {evt.description && (
                                    <p className="text-slate-600 dark:text-neutral-300 leading-relaxed text-[11px]">
                                        {evt.description}
                                    </p>
                                )}

                                {evt.metadata?.recording_url && (
                                    <div className="pt-1">
                                        <audio controls src={evt.metadata.recording_url} className="h-7 w-full max-w-[240px]" />
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
