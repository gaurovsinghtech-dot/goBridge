import React, { useState } from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    PhoneCall, ArrowLeft, ArrowRight, CheckCircle2, Sparkles,
    Shield, Clock, Users, Bot, Sliders, Check, AlertTriangle,
    MessageSquare, Send, Calendar, CheckSquare
} from 'lucide-react';
import { Card, Button, Badge } from '@/Components/ui';
import { toast } from 'sonner';

export default function VoiceCampaignCreate({
    agents = [],
    phoneNumbers = [],
    tags = [],
    segments = [],
    totalContactsCount = 0,
}) {
    const [step, setStep] = useState(1);

    const form = useForm({
        name: '',
        type: 'lead_followup',
        description: '',
        voice_agent_id: agents[0]?.id || '',
        phone_number_id: phoneNumbers[0]?.id || '',
        caller_id_number: phoneNumbers[0]?.phone_number || '',
        audience_type: 'all',
        selected_tags: [],
        start_at: new Date().toISOString().split('T')[0],
        end_at: new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0],
        timezone: 'Asia/Kolkata',
        calling_days: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
        calling_start_time: '09:00',
        calling_end_time: '18:00',
        max_attempts: 3,
        retry_delay_hours: 24,
        call_timeout_sec: 30,
        max_duration_sec: 600,
        concurrent_limit: 2,
        daily_limit: 100,
        compliance_confirmed: false,
        ai_disclosure_enabled: true,
        whatsapp_followup_enabled: true,
        start_now: false,
    });

    const steps = [
        { id: 1, label: '1. Details' },
        { id: 2, label: '2. Audience' },
        { id: 3, label: '3. AI Agent' },
        { id: 4, label: '4. Calling Rules' },
        { id: 5, label: '5. Schedule' },
        { id: 6, label: '6. Compliance' },
        { id: 7, label: '7. Review' },
    ];

    const handleSubmit = (startNow = false) => {
        form.setData('start_now', startNow);
        form.post(route('client.voice.campaigns.store'), {
            onSuccess: () => toast.success(startNow ? 'Campaign launched successfully!' : 'Campaign draft saved!'),
            onError: () => toast.error('Please verify all required fields.'),
        });
    };

    const toggleDay = (day) => {
        const current = form.data.calling_days;
        if (current.includes(day)) {
            form.setData('calling_days', current.filter((d) => d !== day));
        } else {
            form.setData('calling_days', [...current, day]);
        }
    };

    const toggleTag = (tagId) => {
        const current = form.data.selected_tags;
        if (current.includes(tagId)) {
            form.setData('selected_tags', current.filter((id) => id !== tagId));
        } else {
            form.setData('selected_tags', [...current, tagId]);
        }
    };

    return (
        <ClientLayout>
            <Head title="Create AI Voice Campaign — Growbridge Connect" />

            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Link href={route('client.voice.campaigns.index')}>
                            <Button variant="ghost" size="sm" className="p-2">
                                <ArrowLeft className="w-4 h-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-xl font-bold text-neutral-900 dark:text-white">Create Voice Campaign</h1>
                            <p className="text-xs text-neutral-500">Automate outbound voice follow-ups with conversational AI.</p>
                        </div>
                    </div>
                </div>

                {/* Stepper Tabs */}
                <div className="flex items-center justify-between border-b border-neutral-200 dark:border-neutral-800 pb-3 overflow-x-auto gap-2">
                    {steps.map((s) => (
                        <button
                            key={s.id}
                            type="button"
                            onClick={() => setStep(s.id)}
                            className={`text-xs font-bold px-3 py-1.5 rounded-lg whitespace-nowrap transition ${
                                step === s.id
                                    ? 'bg-brand-600 text-white shadow-xs'
                                    : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
                            }`}
                        >
                            {s.label}
                        </button>
                    ))}
                </div>

                {/* Step 1: Campaign Details */}
                {step === 1 && (
                    <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                        <div>
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Campaign Details</h3>
                            <p className="text-xs text-neutral-500">Name and categorize your outbound voice campaign.</p>
                        </div>

                        <div className="space-y-4">
                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Campaign Name</label>
                                <input
                                    type="text"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder="e.g. New Lead Follow-up — Aug 2026"
                                    className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                />
                            </div>

                            <div className="space-y-2">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Campaign Type</label>
                                <div className="grid grid-cols-2 md:grid-cols-3 gap-2.5">
                                    {[
                                        { id: 'lead_followup', label: 'Lead Follow-up', desc: 'Qualify and nurture inbound leads' },
                                        { id: 'appointment_reminder', label: 'Appointment Reminder', desc: 'Confirm scheduled calls & demos' },
                                        { id: 'reengagement', label: 'Re-engagement', desc: 'Revive dormant customer accounts' },
                                        { id: 'survey', label: 'Customer Survey', desc: 'Collect feedback & satisfaction scores' },
                                        { id: 'payment_reminder', label: 'Payment Reminder', desc: 'Gentle pending invoice notices' },
                                        { id: 'custom', label: 'Custom Voice Flow', desc: 'Configurable custom campaign' },
                                    ].map((t) => (
                                        <button
                                            key={t.id}
                                            type="button"
                                            onClick={() => form.setData('type', t.id)}
                                            className={`p-3 rounded-xl border text-left transition ${
                                                form.data.type === t.id
                                                    ? 'border-brand-500 bg-brand-50/50 dark:bg-neutral-800 text-brand-700 dark:text-brand-400'
                                                    : 'border-neutral-200 dark:border-neutral-700'
                                            }`}
                                        >
                                            <span className="text-xs font-bold block text-neutral-900 dark:text-white">{t.label}</span>
                                            <span className="text-[10px] text-neutral-500 leading-tight block mt-0.5">{t.desc}</span>
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Purpose & Notes</label>
                                <textarea
                                    rows={2}
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                    placeholder="Brief summary of the campaign objective..."
                                    className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                />
                            </div>
                        </div>

                        <div className="flex justify-end">
                            <Button type="button" variant="brand" size="sm" onClick={() => setStep(2)} className="font-bold gap-1.5">
                                Next: Select Audience <ArrowRight className="w-4 h-4" />
                            </Button>
                        </div>
                    </Card>
                )}

                {/* Step 2: Audience */}
                {step === 2 && (
                    <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                        <div>
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Select Target Audience</h3>
                            <p className="text-xs text-neutral-500">Target contacts from your verified CRM database.</p>
                        </div>

                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-3">
                                {[
                                    { id: 'all', label: 'All CRM Contacts', count: totalContactsCount },
                                    { id: 'tags', label: 'Filter by CRM Tags', count: 'Custom' },
                                ].map((aud) => (
                                    <button
                                        key={aud.id}
                                        type="button"
                                        onClick={() => form.setData('audience_type', aud.id)}
                                        className={`p-3.5 rounded-xl border text-left transition ${
                                            form.data.audience_type === aud.id
                                                ? 'border-brand-500 bg-brand-50/50 dark:bg-neutral-800'
                                                : 'border-neutral-200 dark:border-neutral-700'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs font-bold text-neutral-900 dark:text-white">{aud.label}</span>
                                            <Badge variant="neutral">{aud.count}</Badge>
                                        </div>
                                    </button>
                                ))}
                            </div>

                            {form.data.audience_type === 'tags' && (
                                <div className="space-y-2 pt-2">
                                    <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Select Contact Tags</label>
                                    <div className="flex flex-wrap gap-2">
                                        {tags.map((tag) => {
                                            const selected = form.data.selected_tags.includes(tag.id);
                                            return (
                                                <button
                                                    key={tag.id}
                                                    type="button"
                                                    onClick={() => toggleTag(tag.id)}
                                                    className={`px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 ${
                                                        selected
                                                            ? 'bg-brand-600 text-white'
                                                            : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300'
                                                    }`}
                                                >
                                                    {selected && <Check className="w-3 h-3" />}
                                                    {tag.name}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="flex justify-between">
                            <Button type="button" variant="outline" size="sm" onClick={() => setStep(1)}>Back</Button>
                            <Button type="button" variant="brand" size="sm" onClick={() => setStep(3)} className="font-bold gap-1.5">
                                Next: AI Agent <ArrowRight className="w-4 h-4" />
                            </Button>
                        </div>
                    </Card>
                )}

                {/* Step 3: AI Agent & Caller ID */}
                {step === 3 && (
                    <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                        <div>
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">AI Agent & Outbound Caller ID</h3>
                            <p className="text-xs text-neutral-500">Select the voice persona and phone number that will make the calls.</p>
                        </div>

                        <div className="space-y-4">
                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Select AI Voice Agent</label>
                                <select
                                    value={form.data.voice_agent_id}
                                    onChange={(e) => form.setData('voice_agent_id', e.target.value)}
                                    className="w-full text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                >
                                    {agents.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.name} ({a.tone} tone • {a.language})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Outbound Caller ID (From Number)</label>
                                <select
                                    value={form.data.phone_number_id}
                                    onChange={(e) => {
                                        const p = phoneNumbers.find((num) => String(num.id) === e.target.value);
                                        form.setData({
                                            ...form.data,
                                            phone_number_id: e.target.value,
                                            caller_id_number: p?.phone_number || '',
                                        });
                                    }}
                                    className="w-full text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                >
                                    {phoneNumbers.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.phone_number} {p.friendly_name ? `(${p.friendly_name})` : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="flex justify-between">
                            <Button type="button" variant="outline" size="sm" onClick={() => setStep(2)}>Back</Button>
                            <Button type="button" variant="brand" size="sm" onClick={() => setStep(4)} className="font-bold gap-1.5">
                                Next: Calling Rules <ArrowRight className="w-4 h-4" />
                            </Button>
                        </div>
                    </Card>
                )}

                {/* Step 4: Calling Rules */}
                {step === 4 && (
                    <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                        <div>
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Calling Rules & Rate Limiting</h3>
                            <p className="text-xs text-neutral-500">Configure retry logic, timeouts, and concurrency safeguards.</p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Max Attempts Per Contact</label>
                                <input
                                    type="number"
                                    min="1"
                                    max="5"
                                    value={form.data.max_attempts}
                                    onChange={(e) => form.setData('max_attempts', parseInt(e.target.value) || 3)}
                                    className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                />
                                <span className="text-[10px] text-neutral-400">Default: 3 attempts</span>
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Retry After (Hours)</label>
                                <input
                                    type="number"
                                    min="1"
                                    max="72"
                                    value={form.data.retry_delay_hours}
                                    onChange={(e) => form.setData('retry_delay_hours', parseInt(e.target.value) || 24)}
                                    className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                />
                                <span className="text-[10px] text-neutral-400">Default: 24 hours</span>
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Concurrent Calls</label>
                                <input
                                    type="number"
                                    min="1"
                                    max="10"
                                    value={form.data.concurrent_limit}
                                    onChange={(e) => form.setData('concurrent_limit', parseInt(e.target.value) || 2)}
                                    className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                />
                                <span className="text-[10px] text-neutral-400">Default: 2 simultaneous calls</span>
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Daily Call Limit</label>
                                <input
                                    type="number"
                                    min="10"
                                    max="1000"
                                    value={form.data.daily_limit}
                                    onChange={(e) => form.setData('daily_limit', parseInt(e.target.value) || 100)}
                                    className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                />
                                <span className="text-[10px] text-neutral-400">Default: 100 calls/day</span>
                            </div>
                        </div>

                        <div className="flex justify-between">
                            <Button type="button" variant="outline" size="sm" onClick={() => setStep(3)}>Back</Button>
                            <Button type="button" variant="brand" size="sm" onClick={() => setStep(5)} className="font-bold gap-1.5">
                                Next: Schedule <ArrowRight className="w-4 h-4" />
                            </Button>
                        </div>
                    </Card>
                )}

                {/* Step 5: Schedule & Calling Window */}
                {step === 5 && (
                    <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                        <div>
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Calling Window & Schedule</h3>
                            <p className="text-xs text-neutral-500">Only place calls within permitted business hours.</p>
                        </div>

                        <div className="space-y-4">
                            <div className="space-y-2">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Allowed Calling Days</label>
                                <div className="flex flex-wrap gap-2">
                                    {['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].map((day) => {
                                        const active = form.data.calling_days.includes(day);
                                        return (
                                            <button
                                                key={day}
                                                type="button"
                                                onClick={() => toggleDay(day)}
                                                className={`px-3 py-1.5 rounded-lg text-xs font-bold transition ${
                                                    active
                                                        ? 'bg-brand-600 text-white'
                                                        : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400'
                                                }`}
                                            >
                                                {day}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-1">
                                    <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Allowed Start Time</label>
                                    <input
                                        type="time"
                                        value={form.data.calling_start_time}
                                        onChange={(e) => form.setData('calling_start_time', e.target.value)}
                                        className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                    />
                                </div>
                                <div className="space-y-1">
                                    <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Allowed End Time</label>
                                    <input
                                        type="time"
                                        value={form.data.calling_end_time}
                                        onChange={(e) => form.setData('calling_end_time', e.target.value)}
                                        className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3.5 py-2.5 text-neutral-900 dark:text-white"
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-between">
                            <Button type="button" variant="outline" size="sm" onClick={() => setStep(4)}>Back</Button>
                            <Button type="button" variant="brand" size="sm" onClick={() => setStep(6)} className="font-bold gap-1.5">
                                Next: Compliance <ArrowRight className="w-4 h-4" />
                            </Button>
                        </div>
                    </Card>
                )}

                {/* Step 6: Compliance & Automation */}
                {step === 6 && (
                    <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                        <div>
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Compliance & Post-Call Automation</h3>
                            <p className="text-xs text-neutral-500">Ensure compliance with telemarketing regulations & setup follow-ups.</p>
                        </div>

                        <div className="space-y-4">
                            <div className="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-xs space-y-2">
                                <div className="flex items-center gap-2 font-bold text-amber-900 dark:text-amber-300">
                                    <Shield className="w-4 h-4 text-amber-600" />
                                    Telecom & Consent Compliance
                                </div>
                                <label className="flex items-start gap-2.5 cursor-pointer text-amber-800 dark:text-amber-300">
                                    <input
                                        type="checkbox"
                                        checked={form.data.compliance_confirmed}
                                        onChange={(e) => form.setData('compliance_confirmed', e.target.checked)}
                                        className="rounded text-brand-600 focus:ring-brand-500 h-4 w-4 mt-0.5"
                                    />
                                    <span>
                                        I confirm that all targeted contacts have provided valid consent and that this campaign complies with applicable Do Not Call (DNC) lists, telemarketing rules, and jurisdiction regulations.
                                    </span>
                                </label>
                            </div>

                            <div className="space-y-3 pt-2">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <span className="text-xs font-bold text-neutral-900 dark:text-white block">AI Assistant Disclosure</span>
                                        <span className="text-[11px] text-neutral-500">Tell the customer they are speaking with an AI assistant.</span>
                                    </div>
                                    <input
                                        type="checkbox"
                                        checked={form.data.ai_disclosure_enabled}
                                        onChange={(e) => form.setData('ai_disclosure_enabled', e.target.checked)}
                                        className="rounded text-brand-600 focus:ring-brand-500 h-4 w-4"
                                    />
                                </div>

                                <div className="flex items-center justify-between">
                                    <div>
                                        <span className="text-xs font-bold text-neutral-900 dark:text-white block">WhatsApp Follow-up Automation</span>
                                        <span className="text-[11px] text-neutral-500">Send WhatsApp brochure or details when caller expresses interest.</span>
                                    </div>
                                    <input
                                        type="checkbox"
                                        checked={form.data.whatsapp_followup_enabled}
                                        onChange={(e) => form.setData('whatsapp_followup_enabled', e.target.checked)}
                                        className="rounded text-brand-600 focus:ring-brand-500 h-4 w-4"
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-between">
                            <Button type="button" variant="outline" size="sm" onClick={() => setStep(5)}>Back</Button>
                            <Button
                                type="button"
                                variant="brand"
                                size="sm"
                                onClick={() => setStep(7)}
                                disabled={!form.data.compliance_confirmed}
                                className="font-bold gap-1.5"
                            >
                                Next: Review & Launch <ArrowRight className="w-4 h-4" />
                            </Button>
                        </div>
                    </Card>
                )}

                {/* Step 7: Review & Launch */}
                {step === 7 && (
                    <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-6">
                        <div>
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Review Campaign Configuration</h3>
                            <p className="text-xs text-neutral-500">Check your settings before queueing outbound calls.</p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 space-y-2">
                                <span className="text-neutral-400 font-bold block uppercase text-[10px]">Campaign Setup</span>
                                <div className="space-y-1">
                                    <p><span className="text-neutral-500">Name:</span> <strong>{form.data.name || 'Untitled Campaign'}</strong></p>
                                    <p><span className="text-neutral-500">Type:</span> <strong>{form.data.type}</strong></p>
                                    <p><span className="text-neutral-500">Caller ID:</span> <strong>{form.data.caller_id_number || 'Default Number'}</strong></p>
                                </div>
                            </div>

                            <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 space-y-2">
                                <span className="text-neutral-400 font-bold block uppercase text-[10px]">Rules & Limits</span>
                                <div className="space-y-1">
                                    <p><span className="text-neutral-500">Max Attempts:</span> <strong>{form.data.max_attempts} attempts</strong></p>
                                    <p><span className="text-neutral-500">Calling Hours:</span> <strong>{form.data.calling_start_time} – {form.data.calling_end_time}</strong></p>
                                    <p><span className="text-neutral-500">Concurrency:</span> <strong>{form.data.concurrent_limit} simultaneous calls</strong></p>
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center justify-between pt-4 border-t border-neutral-100 dark:border-neutral-800">
                            <Button type="button" variant="outline" size="sm" onClick={() => setStep(6)}>Back</Button>
                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => handleSubmit(false)}
                                    disabled={form.processing}
                                    className="font-semibold"
                                >
                                    Save as Draft
                                </Button>
                                <Button
                                    type="button"
                                    variant="brand"
                                    size="md"
                                    onClick={() => handleSubmit(true)}
                                    disabled={form.processing || !form.data.compliance_confirmed}
                                    className="font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs gap-1.5"
                                >
                                    <Send className="w-4 h-4" /> 🚀 Launch Campaign Now
                                </Button>
                            </div>
                        </div>
                    </Card>
                )}
            </div>
        </ClientLayout>
    );
}
