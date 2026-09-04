import React, { useState } from 'react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    PhoneCall, Settings, Shield, CheckCircle2, AlertCircle, Key,
    PhoneForwarded, Sparkles, MessageSquare, Volume2, Save,
    ExternalLink, RefreshCw, Lock, Eye, EyeOff, Copy, Check
} from 'lucide-react';
import { Card, Button, Badge } from '@/Components/ui';
import { toast } from 'sonner';

export default function VoiceSettings({
    twilioConfig = {},
    phoneNumbers = [],
    agents = [],
}) {
    const { t } = useTranslation();
    const [showAuthToken, setShowAuthToken] = useState(false);
    const [isTesting, setIsTesting] = useState(false);
    const [copiedWebhook, setCopiedWebhook] = useState(false);

    const form = useForm({
        account_sid: twilioConfig.account_sid || '',
        auth_token: '',
        default_from_number: twilioConfig.default_from_number || '',
        human_transfer_number: twilioConfig.human_transfer_number || '',
        fallback_action: twilioConfig.fallback_action || 'whatsapp_callback',
        call_recording: twilioConfig.call_recording ?? true,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        form.post(route('client.voice.settings.update'), {
            onSuccess: () => toast.success('Voice and Twilio settings updated successfully.'),
            onError: () => toast.error('Please check the form for errors.'),
        });
    };

    const handleTestConnection = () => {
        setIsTesting(true);
        window.axios.post(route('client.voice.settings.test'))
            .then(res => {
                if (res.data.success) {
                    toast.success(res.data.message || 'Connected to Twilio successfully.');
                } else {
                    toast.error(res.data.message || 'Twilio connection failed. Please check credentials.');
                }
            })
            .catch(err => {
                toast.error(err.response?.data?.message || 'Error testing Twilio connection.');
            })
            .finally(() => setIsTesting(false));
    };

    const webhookUrl = `${window.location.origin}/webhooks/voice/twilio/incoming`;

    const copyWebhook = () => {
        navigator.clipboard.writeText(webhookUrl);
        setCopiedWebhook(true);
        toast.success('Webhook URL copied to clipboard.');
        setTimeout(() => setCopiedWebhook(false), 2000);
    };

    return (
        <ClientLayout>
            <Head title="Voice & Phone Settings — Growbridge Connect" />

            <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* ─── Header ──────────────────────────────────────────────── */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400 flex items-center justify-center">
                            <Settings className="w-5 h-5" />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold text-neutral-900 dark:text-white">AI Voice & Twilio Settings</h1>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                Configure provider credentials, human handoff destinations, and fallback routing for your business calls.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Badge variant={twilioConfig.status === 'connected' ? 'success' : 'neutral'}>
                            {twilioConfig.status === 'connected' ? 'Twilio Connected' : 'Twilio Not Configured'}
                        </Badge>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* ─── Twilio Credentials Card ─────────────────────────────── */}
                    <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Key className="w-4 h-4 text-brand-600 dark:text-brand-400" />
                                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Twilio Telephony Credentials</h3>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleTestConnection}
                                disabled={isTesting}
                                className="text-xs font-semibold"
                            >
                                <RefreshCw className={`w-3.5 h-3.5 mr-1.5 ${isTesting ? 'animate-spin' : ''}`} />
                                Test Twilio Connection
                            </Button>
                        </div>

                        <p className="text-xs text-neutral-500">
                            Stored securely on the backend. Your Auth Token is never exposed in browser JavaScript.
                        </p>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                    Twilio Account SID
                                </label>
                                <input
                                    type="text"
                                    value={form.data.account_sid}
                                    onChange={(e) => form.setData('account_sid', e.target.value)}
                                    placeholder="ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
                                    className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3 py-2 text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500"
                                />
                                {form.errors.account_sid && <p className="text-[11px] text-red-500">{form.errors.account_sid}</p>}
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300 flex items-center justify-between">
                                    <span>Twilio Auth Token</span>
                                    {twilioConfig.has_auth_token && (
                                        <span className="text-[10px] text-emerald-600 font-bold">● Token Configured</span>
                                    )}
                                </label>
                                <div className="relative">
                                    <input
                                        type={showAuthToken ? 'text' : 'password'}
                                        value={form.data.auth_token}
                                        onChange={(e) => form.setData('auth_token', e.target.value)}
                                        placeholder={twilioConfig.has_auth_token ? '••••••••••••••••••••••••••••••••' : 'Enter Twilio Auth Token'}
                                        className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3 py-2 pr-9 text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowAuthToken(!showAuthToken)}
                                        className="absolute right-2.5 top-2.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                                    >
                                        {showAuthToken ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                    </button>
                                </div>
                                {form.errors.auth_token && <p className="text-[11px] text-red-500">{form.errors.auth_token}</p>}
                            </div>
                        </div>

                        {/* Webhook URL Guide */}
                        <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700/80 space-y-2 text-xs">
                            <span className="font-bold text-neutral-800 dark:text-neutral-200">
                                Inbound Twilio Voice Webhook URL:
                            </span>
                            <div className="flex items-center gap-2">
                                <code className="flex-1 p-2 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 font-mono text-[11px] text-neutral-800 dark:text-neutral-200 select-all">
                                    {webhookUrl}
                                </code>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={copyWebhook}
                                    className="px-3 py-2 text-xs font-semibold"
                                >
                                    {copiedWebhook ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                                </Button>
                            </div>
                            <p className="text-[11px] text-neutral-500">
                                Paste this Webhook URL into your Twilio Console under Phone Numbers → Voice Configuration (A CALL COMES IN → Webhook POST).
                            </p>
                        </div>
                    </Card>

                    {/* ─── Human Handoff & Fallback Settings ───────────────────── */}
                    <Card className="p-6 border-neutral-200 dark:border-neutral-800 space-y-5">
                        <div className="flex items-center gap-2">
                            <PhoneForwarded className="w-4 h-4 text-amber-500" />
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Human Handoff & Transfer Routing</h3>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                    Default Human Transfer Number
                                </label>
                                <input
                                    type="text"
                                    value={form.data.human_transfer_number}
                                    onChange={(e) => form.setData('human_transfer_number', e.target.value)}
                                    placeholder="+91 98765 43210"
                                    className="w-full text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3 py-2 text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500"
                                />
                                <p className="text-[11px] text-neutral-400">
                                    When a caller requests a human or presses 0, Twilio dials this number.
                                </p>
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                    No Agent Available Fallback
                                </label>
                                <select
                                    value={form.data.fallback_action}
                                    onChange={(e) => form.setData('fallback_action', e.target.value)}
                                    className="w-full text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 px-3 py-2 text-neutral-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500"
                                >
                                    <option value="whatsapp_callback">Send WhatsApp Follow-up Message (Recommended)</option>
                                    <option value="take_message">Take Message & Notify Team</option>
                                    <option value="schedule_callback">Schedule Priority Callback</option>
                                    <option value="send_sms">Send SMS Follow-up</option>
                                    <option value="end_call">Friendly Closing & Hangup</option>
                                </select>
                                <p className="text-[11px] text-neutral-400">
                                    What happens when human agents are busy or unavailable.
                                </p>
                            </div>
                        </div>

                        {/* Call Recording Toggle */}
                        <div className="flex items-center justify-between pt-3 border-t border-neutral-100 dark:border-neutral-800">
                            <div>
                                <span className="text-xs font-bold text-neutral-900 dark:text-white block">
                                    Call Recording & AI Transcripts
                                </span>
                                <span className="text-[11px] text-neutral-500">
                                    Record calls and generate automatic AI transcripts & summaries.
                                </span>
                            </div>
                            <label className="relative inline-flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={form.data.call_recording}
                                    onChange={(e) => form.setData('call_recording', e.target.checked)}
                                    className="sr-only peer"
                                />
                                <div className="w-10 h-5 bg-neutral-200 peer-focus:outline-none rounded-full peer dark:bg-neutral-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-neutral-600 peer-checked:bg-brand-600"></div>
                            </label>
                        </div>
                    </Card>

                    {/* ─── Save Button ─────────────────────────────────────────── */}
                    <div className="flex justify-end gap-3">
                        <Button
                            type="submit"
                            variant="brand"
                            size="md"
                            disabled={form.processing}
                            className="px-6 py-2.5 font-bold shadow-xs"
                        >
                            <Save className="w-4 h-4 mr-2" />
                            {form.processing ? 'Saving...' : 'Save Voice Settings'}
                        </Button>
                    </div>
                </form>
            </div>
        </ClientLayout>
    );
}
