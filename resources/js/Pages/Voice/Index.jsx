import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Button, Card, Badge, Modal, Input } from '@/Components/ui';
import {
    PhoneCall, Plus, PhoneForwarded, Settings, Play, PhoneIncoming,
    CheckCircle2, Languages, Sparkles, Sliders, ShieldCheck, Activity,
} from 'lucide-react';
import { toast } from 'sonner';

export default function VoiceIndex({ agents = [], stats = {} }) {
    const [testCallModal, setTestCallModal] = useState(false);
    const [selectedAgent, setSelectedAgent] = useState(null);
    const [testPhone, setTestPhone] = useState('');
    const [calling, setCalling] = useState(false);

    const handleOpenTestCall = (agent) => {
        setSelectedAgent(agent);
        setTestPhone('');
        setTestCallModal(true);
    };

    const handleTriggerTestCall = (e) => {
        e.preventDefault();
        if (!testPhone || !selectedAgent) return;

        setCalling(true);
        window.axios.post(route('client.voice.test-call', selectedAgent.uuid), {
            phone: testPhone,
        }).then((res) => {
            toast.success(res.data.message || 'Test call initiated!');
            setTestCallModal(false);
        }).catch((err) => {
            toast.error(err.response?.data?.message || 'Failed to initiate test call.');
        }).finally(() => {
            setCalling(false);
        });
    };

    const handleToggleStatus = (agent) => {
        router.post(route('client.voice.toggle', agent.uuid), {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Agent status updated.'),
        });
    };

    return (
        <ClientLayout>
            <Head title="AI Voice Agents — Growbridge Connect" />

            <div className="space-y-6">
                {/* Header Section */}
                <div className="bg-gradient-to-r from-brand-900 via-brand-800 to-brand-950 text-white rounded-2xl p-6 sm:p-8 shadow-lg relative overflow-hidden">
                    <div className="relative z-10 max-w-2xl">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-accent-500/20 text-accent-400 text-xs font-semibold uppercase tracking-wider mb-3">
                            <Sparkles className="w-3.5 h-3.5" />
                            Next-Gen Telephony AI
                        </div>
                        <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-white mb-2">
                            AI Voice Agents Built To Resolve Customer Queries
                        </h1>
                        <p className="text-slate-300 text-sm sm:text-base leading-relaxed">
                            Deploy natural-sounding conversational voice agents for Inbound support & Outbound qualification in English, Hindi, and Hinglish.
                        </p>
                        <div className="flex flex-wrap items-center gap-3 mt-5">
                            <Link href={route('client.voice.campaigns.index')}>
                                <Button className="bg-accent-500 hover:bg-accent-600 text-slate-950 font-semibold gap-2 border-0 shadow-md">
                                    <PhoneCall className="w-4 h-4" /> Voice Campaigns
                                </Button>
                            </Link>
                            <Link href={route('client.ai.voice-studio.index')}>
                                <Button variant="outline" className="bg-white/10 hover:bg-white/20 text-white border-white/20 gap-2">
                                    <Sparkles className="w-4 h-4" /> Voice Studio
                                </Button>
                            </Link>
                            <Link href={route('client.voice.numbers.index')}>
                                <Button variant="outline" className="bg-white/10 hover:bg-white/20 text-white border-white/20 gap-2">
                                    <PhoneCall className="w-4 h-4" /> Numbers
                                </Button>
                            </Link>
                            <Link href={route('client.voice.calls.index')}>
                                <Button variant="outline" className="bg-white/10 hover:bg-white/20 text-white border-white/20 gap-2">
                                    <PhoneIncoming className="w-4 h-4" /> Call Logs
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>

                {/* KPI Metrics */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card className="p-5 border-slate-200 dark:border-neutral-800">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-slate-500 dark:text-neutral-400">Total Voice Agents</p>
                                <p className="text-2xl font-bold text-slate-900 dark:text-white mt-1">{stats.total_agents ?? 0}</p>
                            </div>
                            <div className="p-3 bg-brand-50 dark:bg-neutral-800 text-brand-600 dark:text-brand-400 rounded-xl">
                                <PhoneCall className="w-5 h-5" />
                            </div>
                        </div>
                    </Card>

                    <Card className="p-5 border-slate-200 dark:border-neutral-800">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-slate-500 dark:text-neutral-400">Active Agents</p>
                                <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{stats.active_agents ?? 0}</p>
                            </div>
                            <div className="p-3 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 rounded-xl">
                                <Activity className="w-5 h-5" />
                            </div>
                        </div>
                    </Card>

                    <Card className="p-5 border-slate-200 dark:border-neutral-800">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-slate-500 dark:text-neutral-400">Total Calls Processed</p>
                                <p className="text-2xl font-bold text-slate-900 dark:text-white mt-1">{stats.total_calls ?? 0}</p>
                            </div>
                            <div className="p-3 bg-blue-50 dark:bg-neutral-800 text-blue-600 dark:text-blue-400 rounded-xl">
                                <PhoneForwarded className="w-5 h-5" />
                            </div>
                        </div>
                    </Card>

                    <Card className="p-5 border-slate-200 dark:border-neutral-800">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-slate-500 dark:text-neutral-400">Completed Calls</p>
                                <p className="text-2xl font-bold text-slate-900 dark:text-white mt-1">{stats.completed_calls ?? 0}</p>
                            </div>
                            <div className="p-3 bg-accent-50 dark:bg-neutral-800 text-accent-600 dark:text-accent-400 rounded-xl">
                                <CheckCircle2 className="w-5 h-5" />
                            </div>
                        </div>
                    </Card>
                </div>

                {/* Agents List / Grid */}
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Configured Voice Agents</h2>
                    </div>

                    {agents.length === 0 ? (
                        <Card className="p-12 text-center border-dashed border-slate-300 dark:border-neutral-800">
                            <div className="mx-auto w-12 h-12 rounded-full bg-brand-50 dark:bg-neutral-800 text-brand-600 dark:text-brand-400 flex items-center justify-center mb-3">
                                <PhoneCall className="w-6 h-6" />
                            </div>
                            <h3 className="text-base font-semibold text-slate-900 dark:text-white">No Voice Agents yet</h3>
                            <p className="text-sm text-slate-500 dark:text-neutral-400 max-w-md mx-auto mt-1 mb-5">
                                Create your first AI Voice Agent to start taking inbound calls or running outbound campaigns via Exotel, Twilio, or Plivo.
                            </p>
                            <Link href={route('client.voice.create')}>
                                <Button className="gap-2 bg-brand-600 hover:bg-brand-700 text-white">
                                    <Plus className="w-4 h-4" /> Create First Agent
                                </Button>
                            </Link>
                        </Card>
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                            {agents.map((agent) => (
                                <Card key={agent.id} className="p-5 border-slate-200 dark:border-neutral-800 flex flex-col justify-between hover:shadow-md transition-shadow">
                                    <div>
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <h3 className="font-semibold text-slate-900 dark:text-white text-base">{agent.name}</h3>
                                                <p className="text-xs text-slate-500 dark:text-neutral-400 line-clamp-2 mt-1">
                                                    {agent.description || 'General AI Voice Assistant'}
                                                </p>
                                            </div>
                                            <Badge variant={agent.status === 'active' ? 'success' : 'neutral'} className="capitalize">
                                                {agent.status}
                                            </Badge>
                                        </div>

                                        <div className="grid grid-cols-2 gap-2 my-4 pt-3 border-t border-slate-100 dark:border-neutral-800 text-xs">
                                            <div>
                                                <span className="text-slate-400 block">Language</span>
                                                <span className="font-medium text-slate-700 dark:text-neutral-200 uppercase">{agent.language}</span>
                                            </div>
                                            <div>
                                                <span className="text-slate-400 block">Provider</span>
                                                <span className="font-medium text-slate-700 dark:text-neutral-200 capitalize">{agent.provider}</span>
                                            </div>
                                            <div>
                                                <span className="text-slate-400 block">Total Calls</span>
                                                <span className="font-medium text-slate-700 dark:text-neutral-200">{agent.calls_count ?? agent.total_calls ?? 0}</span>
                                            </div>
                                            <div>
                                                <span className="text-slate-400 block">Tone</span>
                                                <span className="font-medium text-slate-700 dark:text-neutral-200 capitalize">{agent.tone}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center justify-between gap-2 pt-3 border-t border-slate-100 dark:border-neutral-800">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="text-xs gap-1"
                                            onClick={() => handleOpenTestCall(agent)}
                                        >
                                            <Play className="w-3.5 h-3.5 text-emerald-600" /> Test Call
                                        </Button>

                                        <div className="flex items-center gap-1.5">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="text-xs"
                                                onClick={() => handleToggleStatus(agent)}
                                            >
                                                {agent.status === 'active' ? 'Disable' : 'Enable'}
                                            </Button>
                                            <Link href={route('client.ai.voice-studio.show', agent.uuid)}>
                                                <Button size="sm" variant="outline" className="text-xs gap-1">
                                                    <Settings className="w-3.5 h-3.5" /> Studio
                                                </Button>
                                            </Link>
                                        </div>
                                    </div>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* Test Call Modal */}
            <Modal
                show={testCallModal}
                onClose={() => setTestCallModal(false)}
                title={`Test Call — ${selectedAgent?.name}`}
            >
                <form onSubmit={handleTriggerTestCall} className="space-y-4">
                    <p className="text-sm text-slate-600 dark:text-neutral-300">
                        Enter your phone number in international E.164 format (e.g. <code>+919876543210</code>). Growbridge Connect will trigger an outbound AI call to test this agent.
                    </p>

                    <div>
                        <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                            Destination Phone Number
                        </label>
                        <Input
                            type="text"
                            placeholder="+91..."
                            value={testPhone}
                            onChange={(e) => setTestPhone(e.target.value)}
                            required
                            autoFocus
                        />
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-4">
                        <Button type="button" variant="ghost" onClick={() => setTestCallModal(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={calling} className="bg-brand-600 hover:bg-brand-700 text-white gap-2">
                            <PhoneCall className="w-4 h-4" /> {calling ? 'Dialing...' : 'Initiate Call'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </ClientLayout>
    );
}
