import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Button, Card, Input, Select, Badge } from '@/Components/ui';
import {
    PhoneCall, ArrowLeft, Bot, Sparkles, PhoneForwarded,
    Clock, Database, Shield, Sliders, CheckCircle2,
} from 'lucide-react';
import { toast } from 'sonner';

export default function VoiceBuilder({ agent, knowledgeBases = [] }) {
    const isEdit = !!agent;
    const [activeTab, setActiveTab] = useState('general');

    const { data, setData, post, put, processing, errors } = useForm({
        name: agent?.name ?? '',
        description: agent?.description ?? '',
        status: agent?.status ?? 'active',
        language: agent?.language ?? 'en-US',
        tone: agent?.tone ?? 'professional',
        voice_id: agent?.voice_id ?? 'Polly.Aditi',
        provider: agent?.provider ?? 'twilio',
        phone_number: agent?.phone_number ?? '',
        system_prompt: agent?.system_prompt ?? 'You are a professional customer support and sales voice assistant for Growbridge Connect. Answer queries accurately, politely, and concisely.',
        greeting_message: agent?.greeting_message ?? 'Hello! Thank you for calling Growbridge Connect. How can I assist you today?',
        ai_kb_id: agent?.ai_kb_id ?? '',
        human_transfer_number: agent?.human_transfer_number ?? '',
        max_duration_sec: agent?.max_duration_sec ?? 300,
        ai_model: agent?.ai_model ?? 'gpt-4o-mini',
        tools_config: agent?.tools_config ?? {
            qualify_leads: true,
            book_appointments: false,
            update_crm: true,
        },
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('client.voice.update', agent.uuid), {
                onSuccess: () => toast.success('Voice Agent updated successfully.'),
            });
        } else {
            post(route('client.voice.store'), {
                onSuccess: () => toast.success('Voice Agent created successfully.'),
            });
        }
    };

    const tabs = [
        { id: 'general', label: 'General', icon: Sliders },
        { id: 'voice', label: 'Voice & Language', icon: Bot },
        { id: 'instructions', label: 'AI Instructions', icon: Sparkles },
        { id: 'knowledge', label: 'Knowledge Base', icon: Database },
        { id: 'transfer', label: 'Working Hours & Transfer', icon: PhoneForwarded },
    ];

    return (
        <ClientLayout>
            <Head title={`${isEdit ? 'Edit' : 'Create'} Voice Agent — Growbridge Connect`} />

            <form onSubmit={handleSubmit} className="space-y-6 max-w-5xl mx-auto">
                {/* Top bar */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link href={route('client.voice.index')}>
                            <Button type="button" variant="ghost" size="sm" className="p-2">
                                <ArrowLeft className="w-4 h-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-xl font-bold text-slate-900 dark:text-white">
                                {isEdit ? `Edit Agent: ${agent.name}` : 'Create New AI Voice Agent'}
                            </h1>
                            <p className="text-xs text-slate-500 dark:text-neutral-400">
                                Configure natural conversational voice flow and telephony routing.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link href={route('client.voice.index')}>
                            <Button type="button" variant="ghost">Cancel</Button>
                        </Link>
                        <Button type="submit" disabled={processing} className="bg-brand-600 hover:bg-brand-700 text-white gap-2">
                            <CheckCircle2 className="w-4 h-4" /> {isEdit ? 'Save Changes' : 'Create Agent'}
                        </Button>
                    </div>
                </div>

                {/* Tab Navigation */}
                <div className="flex border-b border-slate-200 dark:border-neutral-800 overflow-x-auto gap-2">
                    {tabs.map((tab) => {
                        const Icon = tab.icon;
                        const active = activeTab === tab.id;
                        return (
                            <button
                                key={tab.id}
                                type="button"
                                onClick={() => setActiveTab(tab.id)}
                                className={`flex items-center gap-2 px-4 py-2.5 text-xs font-medium border-b-2 transition-colors whitespace-nowrap ${
                                    active
                                        ? 'border-brand-600 text-brand-600 dark:text-brand-400 font-semibold'
                                        : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-neutral-400'
                                }`}
                            >
                                <Icon className="w-3.5 h-3.5" />
                                {tab.label}
                            </button>
                        );
                    })}
                </div>

                {/* Tab Contents */}
                {activeTab === 'general' && (
                    <Card className="p-6 space-y-4 border-slate-200 dark:border-neutral-800">
                        <h3 className="font-semibold text-sm text-slate-900 dark:text-white">Basic Information</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                    Agent Name *
                                </label>
                                <Input
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. Sales Qualification Agent"
                                    required
                                />
                                {errors.name && <p className="text-xs text-rose-500 mt-1">{errors.name}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                    Status
                                </label>
                                <Select
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                >
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="draft">Draft</option>
                                </Select>
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                    Description
                                </label>
                                <Input
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Purpose of this voice agent"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                    Telephony Provider *
                                </label>
                                <Select
                                    value={data.provider}
                                    onChange={(e) => setData('provider', e.target.value)}
                                >
                                    <option value="twilio">Twilio Voice (Global & Multi-Region)</option>
                                    <option value="exotel">Exotel (India & APAC)</option>
                                    <option value="plivo">Plivo (Global & India)</option>
                                </Select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                    Caller ID / Virtual Number
                                </label>
                                <Input
                                    value={data.phone_number}
                                    onChange={(e) => setData('phone_number', e.target.value)}
                                    placeholder="+91... or +1..."
                                />
                            </div>
                        </div>
                    </Card>
                )}

                {activeTab === 'voice' && (
                    <Card className="p-6 space-y-4 border-slate-200 dark:border-neutral-800">
                        <h3 className="font-semibold text-sm text-slate-900 dark:text-white">Voice & Tone Settings</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                    Language
                                </label>
                                <Select
                                    value={data.language}
                                    onChange={(e) => setData('language', e.target.value)}
                                >
                                    <option value="en-US">English (US / Global)</option>
                                    <option value="hi-IN">Hindi (India)</option>
                                    <option value="hinglish">Hinglish (Natural Mix)</option>
                                </Select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                    Conversation Tone
                                </label>
                                <Select
                                    value={data.tone}
                                    onChange={(e) => setData('tone', e.target.value)}
                                >
                                    <option value="professional">Professional & Crisp</option>
                                    <option value="friendly">Friendly & Warm</option>
                                    <option value="empathetic">Empathetic (Customer Support)</option>
                                    <option value="energetic">Energetic (Sales & Outreach)</option>
                                </Select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                    Voice Persona
                                </label>
                                <Select
                                    value={data.voice_id}
                                    onChange={(e) => setData('voice_id', e.target.value)}
                                >
                                    <option value="Polly.Aditi">Aditi (Bilingual Indian English/Hindi)</option>
                                    <option value="Polly.Raveena">Raveena (Indian English)</option>
                                    <option value="Polly.Kajal">Kajal (Hindi Female)</option>
                                    <option value="Polly.Joanna">Joanna (US English Female)</option>
                                    <option value="Polly.Matthew">Matthew (US English Male)</option>
                                </Select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                    Max Call Duration (Seconds)
                                </label>
                                <Input
                                    type="number"
                                    min="30"
                                    max="1800"
                                    value={data.max_duration_sec}
                                    onChange={(e) => setData('max_duration_sec', parseInt(e.target.value) || 300)}
                                />
                            </div>
                        </div>
                    </Card>
                )}

                {activeTab === 'instructions' && (
                    <Card className="p-6 space-y-4 border-slate-200 dark:border-neutral-800">
                        <h3 className="font-semibold text-sm text-slate-900 dark:text-white">AI Instructions & Dialogue Flow</h3>
                        
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                Greeting Message (Spoken first when call connects)
                            </label>
                            <Input
                                value={data.greeting_message}
                                onChange={(e) => setData('greeting_message', e.target.value)}
                                placeholder="Hello! I am your AI assistant..."
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                System Prompt & AI Behavior Instructions
                            </label>
                            <textarea
                                rows={6}
                                className="w-full rounded-lg border-slate-300 dark:border-neutral-700 dark:bg-neutral-800 text-sm focus:ring-brand-500 focus:border-brand-500"
                                value={data.system_prompt}
                                onChange={(e) => setData('system_prompt', e.target.value)}
                                placeholder="Explain business rules, how to handle pricing questions, FAQs, and when to transfer."
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                AI Model
                            </label>
                            <Select
                                value={data.ai_model}
                                onChange={(e) => setData('ai_model', e.target.value)}
                            >
                                <option value="gpt-4o-mini">GPT-4o Mini (Fast & Low Latency)</option>
                                <option value="gemini-1.5-flash">Gemini 1.5 Flash (Ultra Fast)</option>
                                <option value="claude-3-haiku">Claude 3 Haiku</option>
                            </Select>
                        </div>
                    </Card>
                )}

                {activeTab === 'knowledge' && (
                    <Card className="p-6 space-y-4 border-slate-200 dark:border-neutral-800">
                        <h3 className="font-semibold text-sm text-slate-900 dark:text-white">Knowledge Base (RAG)</h3>
                        <p className="text-xs text-slate-500 dark:text-neutral-400">
                            Connect a knowledge base containing your business FAQs, product catalog, or policies so the AI voice agent can answer customer questions directly.
                        </p>

                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                Linked Knowledge Base
                            </label>
                            <Select
                                value={data.ai_kb_id}
                                onChange={(e) => setData('ai_kb_id', e.target.value)}
                            >
                                <option value="">No Knowledge Base (Use System Prompt Only)</option>
                                {knowledgeBases.map((kb) => (
                                    <option key={kb.id} value={kb.id}>{kb.name}</option>
                                ))}
                            </Select>
                        </div>
                    </Card>
                )}

                {activeTab === 'transfer' && (
                    <Card className="p-6 space-y-4 border-slate-200 dark:border-neutral-800">
                        <h3 className="font-semibold text-sm text-slate-900 dark:text-white">Human Escalation & Transfers</h3>
                        <p className="text-xs text-slate-500 dark:text-neutral-400">
                            When a caller asks to speak with a human or has a complex issue, the agent will transfer the live call to this number.
                        </p>

                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                Human Transfer Number (E.164 format)
                            </label>
                            <Input
                                value={data.human_transfer_number}
                                onChange={(e) => setData('human_transfer_number', e.target.value)}
                                placeholder="+919876543210"
                            />
                        </div>
                    </Card>
                )}
            </form>
        </ClientLayout>
    );
}
