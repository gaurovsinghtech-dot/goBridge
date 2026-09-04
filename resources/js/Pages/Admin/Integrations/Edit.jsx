import { Head, useForm, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';
import {
    Eye, EyeOff, FlaskConical, RotateCcw, ArrowLeft, CheckCircle, XCircle,
    HardDrive, Info, BookOpen, ChevronDown, ChevronUp, Copy, Check, Link2,
    PhoneCall, MessageSquare, Sparkles, MapPin, Database, Cpu, ShieldCheck,
    Layers, ExternalLink, Save, Zap, Share2, ArrowLeftRight, Workflow, CheckCircle2
} from 'lucide-react';
import axios from 'axios';
import { formatInTz } from '@/Utils/datetime';
import { useTranslation, Trans } from 'react-i18next';

const SETUP_GUIDES = {
    twilio: {
        title: 'Twilio Telephony & SMS Setup Guide',
        subtitle: 'Powers Virtual Phone Numbers · SMS Messaging · Voice Inbound/Outbound · AI Voice Agents · Call Status Webhooks',
        steps: [
            'Log in to your Twilio Console at console.twilio.com.',
            'On your dashboard, copy your Account SID and primary Auth Token into the fields below.',
            'Under Phone Numbers → Manage → Buy a Number, purchase numbers with Voice & SMS capabilities.',
            '(Optional) Create a TwiML App under Voice → TwiML Apps and paste the SID into Voice Application SID.',
            'Twilio automatically handles subaccounts per customer workspace for clean tenant separation.',
            'Save and click "Test Connection" to verify master credentials.',
        ],
        link: 'https://console.twilio.com',
        linkLabel: 'Open Twilio Console',
    },
    meta_app: {
        title: 'Meta WhatsApp Business API — Complete Setup Guide',
        subtitle: 'WhatsApp Cloud API · WABA · Embedded Signup · Webhooks · Message Templates',
        steps: [
            'Go to developers.facebook.com → "My Apps" → "Create App" (Type: Business).',
            'Under App Settings → Basic, copy your App ID and App Secret.',
            'In the App Dashboard, add the "WhatsApp" product to your app.',
            'In Meta Business Suite → Settings → System Users, create an Admin System User with whatsapp_business_messaging & whatsapp_business_management permissions, then copy the System User Token.',
            'Under Facebook Login for Business → Configurations, create a configuration for Embedded Signup and copy its Configuration ID.',
            'Set your Webhook callback URL to the URL shown below with your Verify Token.',
        ],
        link: 'https://developers.facebook.com/apps',
        linkLabel: 'Open Meta App Dashboard',
    },
    ai_providers: {
        title: 'AI Providers Setup Guide',
        subtitle: 'OpenAI · Google Gemini · Anthropic Claude for Chatbots, Agents, and Auto-Replies',
        steps: [
            'OpenAI: Get your secret key from platform.openai.com/api-keys (starts with sk-...).',
            'Google Gemini: Get your API key from aistudio.google.com/app/apikey (starts with AIzaSy...).',
            'Anthropic Claude: Get your API key from console.anthropic.com/keys (starts with sk-ant-...).',
            'Select your Default AI Provider (e.g. OpenAI) and enter the corresponding API key.',
            'You can configure multiple AI engines simultaneously for dynamic fallbacks and model switching.',
        ],
        link: 'https://platform.openai.com/api-keys',
        linkLabel: 'OpenAI Platform',
    },
    storage_local: {
        title: 'Local Storage Setup',
        subtitle: 'Default server disk storage for media, files, and voice recordings',
        steps: [
            'Local storage stores all uploaded files directly in storage/app/public.',
            'Ensure the web server user has write permissions to storage/app/public.',
            'Run: php artisan storage:link if public assets are not accessible.',
            'Local Storage is enabled and set as the default active storage by default.',
        ],
    },
    storage_s3: {
        title: 'AWS S3 Object Storage Setup Guide',
        subtitle: 'Enterprise Cloud Object Storage · Workspace Scoped Paths · Private Objects & Signed URLs',
        steps: [
            'Log in to AWS Console at console.aws.amazon.com and open the Amazon S3 service.',
            'Create an S3 Bucket with default settings (Keep "Block all public access" ON — Growbridge uses private signed URLs).',
            'In IAM, create an IAM User with Programmatic Access and attach AmazonS3FullAccess policy (or scoped bucket policy).',
            'Copy the Access Key ID and Secret Access Key into the credentials fields below.',
            'Enter your AWS Region (e.g. us-east-1, ap-south-1) and Bucket Name.',
            'Click "Test Connection" to perform real put-get-delete verification with AWS S3.',
            'Optionally configure AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY in your server environment.',
        ],
        link: 'https://s3.console.aws.amazon.com',
        linkLabel: 'Open AWS S3 Console',
    },
    crm_hubspot: {
        title: 'HubSpot CRM Setup Guide',
        subtitle: 'Contacts · Deals · Two-Way Sync · Conversation Activities · Call Logs',
        steps: [
            'Log in to HubSpot and go to Settings → Integrations → Private Apps.',
            'Click "Create a private app", give it a name (e.g. Growbridge Connect).',
            'Under Scopes, enable: crm.objects.contacts.read, crm.objects.contacts.write, crm.objects.deals.read, crm.objects.deals.write, crm.objects.custom.read.',
            'Click "Create app" and copy the Access Token (starts with pat-...).',
            'Paste the Access Token below and click "Test Connection".',
        ],
        link: 'https://app.hubspot.com',
        linkLabel: 'Open HubSpot App',
    },
    crm_salesforce: {
        title: 'Salesforce CRM Setup Guide',
        subtitle: 'Enterprise Contacts · Leads · Call Task Logs · Real-time Events',
        steps: [
            'Log in to Salesforce Setup → App Manager → New Connected App.',
            'Enable OAuth Settings, select scopes: api, refresh_token, offline_access.',
            'Copy the Consumer Key (Client ID) and Consumer Secret.',
            'Paste your Salesforce My Domain Instance URL (e.g. https://yourcompany.my.salesforce.com) and credentials.',
        ],
        link: 'https://login.salesforce.com',
        linkLabel: 'Open Salesforce',
    },
    crm_zoho: {
        title: 'Zoho CRM Setup Guide',
        subtitle: 'Contacts · Leads · Notes · Activity Synchronization',
        steps: [
            'Go to api-console.zoho.com and create a Server-based Application.',
            'Copy the Client ID and Client Secret.',
            'Select your Zoho Data Center region (US, India, Europe, Australia).',
            'Generate a permanent token or OAuth authorization code with ZohoCRM.modules.ALL scope.',
        ],
        link: 'https://api-console.zoho.com',
        linkLabel: 'Zoho API Console',
    },
    crm_pipedrive: {
        title: 'Pipedrive CRM Setup Guide',
        subtitle: 'Persons · Deals · Activity Tracking · Notes',
        steps: [
            'Log in to Pipedrive → Settings → Personal Preferences → API.',
            'Copy your Personal API Token.',
            'Enter your company subdomain and token into the form below.',
        ],
        link: 'https://app.pipedrive.com',
        linkLabel: 'Open Pipedrive',
    },
    crm_freshsales: {
        title: 'Freshsales CRM Setup Guide',
        subtitle: 'Contacts · Accounts · Notes · Voice Activity',
        steps: [
            'Log in to your Freshsales Suite account.',
            'Click your profile icon → Settings → API Settings.',
            'Copy your API Key and enter your Freshsales domain (e.g. yourcompany.freshsales.io).',
        ],
        link: 'https://www.freshworks.com/crm',
        linkLabel: 'Open Freshsales',
    },
    crm_dynamics: {
        title: 'Microsoft Dynamics 365 Setup Guide',
        subtitle: 'Dataverse · Contacts · Accounts · Automated Task Logs',
        steps: [
            'In Microsoft Entra ID (Azure Portal) → App Registrations, register a new app.',
            'Add API Permissions for Dynamics CRM: user_impersonation.',
            'Generate a Client Secret and copy your Dynamics Org URL (e.g. https://orgXXXXX.crm.dynamics.com).',
        ],
        link: 'https://portal.azure.com',
        linkLabel: 'Azure Portal',
    },
    crm_gohighlevel: {
        title: 'GoHighLevel Setup Guide',
        subtitle: 'Sub-Account Contacts · Conversations · Notes · Inbound Webhooks',
        steps: [
            'Log in to GoHighLevel → Settings → Business Info / Integrations.',
            'Copy your API v2 Key or OAuth token.',
            'Enter your Location ID to restrict sync to a specific sub-account.',
        ],
        link: 'https://app.gohighlevel.com',
        linkLabel: 'Open GoHighLevel',
    },
    crm_custom: {
        title: 'Custom CRM via REST API & Webhooks',
        subtitle: 'Connect Any Proprietary, In-House, or Bespoke CRM System',
        steps: [
            'Provide your CRM REST API Base URL (e.g. https://api.yourcrm.com/v1).',
            'Select authentication method (Bearer Token, Custom Header, or Basic Auth).',
            'Specify endpoints for /contacts, /leads, and /activities.',
            'Configure your CRM to send webhooks to the Inbound Webhook endpoint provided.',
        ],
    },
    crm_webhook: {
        title: 'Generic CRM Webhook Setup',
        subtitle: 'Lightweight HTTP POST payloads for real-time contact and event distribution',
        steps: [
            'Enter the destination URL where Growbridge will POST contact & conversation updates.',
            '(Optional) Enter a secret signature key for HMAC-SHA256 payload verification.',
        ],
    },
    google_places: {
        title: 'Google Places API Setup',
        subtitle: 'Business locations & address lookup for CRM contacts and lead scrapers',
        steps: [
            'Go to console.cloud.google.com and create or select a project.',
            'Under APIs & Services → Library, search for "Places API (New)" and enable it.',
            'Under Credentials → Create Credentials, generate an API Key.',
            'Paste your Google API key into the field below and test connection.',
        ],
        link: 'https://console.cloud.google.com/apis/library/places-backend.googleapis.com',
        linkLabel: 'Google Cloud Console',
    },
    storage_do: {
        title: 'DigitalOcean Spaces Setup',
        steps: [
            'Log in to cloud.digitalocean.com → Spaces and create a Space.',
            'In API → Spaces Access Keys, generate a new Key and Secret.',
            'Enter the Space Name, Region Endpoint, and credentials below.',
        ],
        link: 'https://cloud.digitalocean.com/spaces',
        linkLabel: 'DigitalOcean Spaces',
    },
    storage_wasabi: {
        title: 'Wasabi Cloud Storage Setup',
        steps: [
            'Log in to console.wasabisys.com and create a bucket.',
            'In Access Keys, generate an Access Key ID and Secret.',
            'Paste the bucket name, region, and credentials below.',
        ],
        link: 'https://console.wasabisys.com',
        linkLabel: 'Wasabi Console',
    },
};

const BRAND = {
    twilio: {
        bg: 'bg-rose-500',
        color: '#F22F46',
        icon: <PhoneCall className="h-5 w-5 text-white" />,
        subtitle: 'Telephony, SMS & Voice Engine',
    },
    meta_app: {
        bg: 'bg-[#0866FF]',
        color: '#0866FF',
        icon: <MessageSquare className="h-5 w-5 text-white" />,
        subtitle: 'Official WhatsApp Business API',
    },
    ai_providers: {
        bg: 'bg-emerald-600',
        color: '#10B981',
        icon: <Sparkles className="h-5 w-5 text-white" />,
        subtitle: 'LLM & AI Assistant Engine',
    },
    storage_local: {
        bg: 'bg-sky-600',
        color: '#0284c7',
        icon: <HardDrive className="h-5 w-5 text-white" />,
        subtitle: 'Default Server File System',
    },
    crm_hubspot: {
        bg: 'bg-[#FF7A59]',
        color: '#FF7A59',
        icon: <Share2 className="h-5 w-5 text-white" />,
        subtitle: 'Contacts • Deals • Two-Way Sync • Notes & Activities',
    },
    crm_salesforce: {
        bg: 'bg-[#00A1E0]',
        color: '#00A1E0',
        icon: <Share2 className="h-5 w-5 text-white" />,
        subtitle: 'Enterprise Contacts • Leads • Tasks • Opportunities',
    },
    crm_zoho: {
        bg: 'bg-[#E42528]',
        color: '#E42528',
        icon: <Share2 className="h-5 w-5 text-white" />,
        subtitle: 'Contacts • Leads • Activities • Notes',
    },
    crm_pipedrive: {
        bg: 'bg-[#029b35]',
        color: '#029b35',
        icon: <Share2 className="h-5 w-5 text-white" />,
        subtitle: 'Persons • Leads • Deals • Activity Sync',
    },
    crm_freshsales: {
        bg: 'bg-[#0081FE]',
        color: '#0081FE',
        icon: <Share2 className="h-5 w-5 text-white" />,
        subtitle: 'Contacts • Accounts • Notes • Voice Activity',
    },
    crm_dynamics: {
        bg: 'bg-[#0078D4]',
        color: '#0078D4',
        icon: <Share2 className="h-5 w-5 text-white" />,
        subtitle: 'Microsoft Dataverse • Contacts • Accounts • Tasks',
    },
    crm_gohighlevel: {
        bg: 'bg-[#1A56DB]',
        color: '#1A56DB',
        icon: <Share2 className="h-5 w-5 text-white" />,
        subtitle: 'Sub-Account Contacts • Conversations • Notes',
    },
    crm_custom: {
        bg: 'bg-indigo-600',
        color: '#6366F1',
        icon: <Workflow className="h-5 w-5 text-white" />,
        subtitle: 'Connect In-House & Custom CRMs via REST API',
    },
    crm_webhook: {
        bg: 'bg-purple-600',
        color: '#8B5CF6',
        icon: <ArrowLeftRight className="h-5 w-5 text-white" />,
        subtitle: 'Real-time Inbound & Outbound Webhook Distribution',
    },
    google_places: {
        bg: 'bg-amber-500',
        color: '#F59E0B',
        icon: <MapPin className="h-5 w-5 text-white" />,
        subtitle: 'Google Maps Places Service',
    },
    storage_s3: {
        bg: 'bg-orange-500',
        color: '#FF9900',
        icon: <Database className="h-5 w-5 text-white" />,
        subtitle: 'Amazon S3 Object Storage',
    },
    storage_do: {
        bg: 'bg-blue-600',
        color: '#0080FF',
        icon: <Database className="h-5 w-5 text-white" />,
        subtitle: 'DigitalOcean Spaces Storage',
    },
    storage_wasabi: {
        bg: 'bg-green-600',
        color: '#3CBA54',
        icon: <Database className="h-5 w-5 text-white" />,
        subtitle: 'Wasabi Hot Cloud Storage',
    },
};

const DEFAULT_BRAND = {
    bg: 'bg-neutral-600',
    color: '#6b7280',
    icon: <Layers className="h-5 w-5 text-white" />,
    subtitle: 'Integration service',
};

function SecretField({ label, fieldKey, value, onChange, required, hint }) {
    const [visible, setVisible] = useState(false);
    const isMasked = value && /^•+/.test(value);

    return (
        <div>
            <label className="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1.5">
                {label} {required && <span className="text-rose-500 font-bold">*</span>}
            </label>
            <div className="relative">
                <input
                    type={visible ? 'text' : 'password'}
                    value={value}
                    onChange={e => onChange(fieldKey, e.target.value)}
                    placeholder={isMasked ? '•••••••••••• (Leave unchanged)' : 'Enter value...'}
                    className="w-full rounded-xl border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3.5 py-2.5 pr-10 text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-brand-500"
                />
                <button
                    type="button"
                    onClick={() => setVisible(v => !v)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                >
                    {visible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
            </div>
            {hint && <p className="mt-1.5 text-xs text-neutral-500 dark:text-neutral-400">{hint}</p>}
        </div>
    );
}

function PlainField({ label, fieldKey, value, onChange, required, hint, type = 'text', options = [] }) {
    if (type === 'select') {
        return (
            <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1.5">
                    {label} {required && <span className="text-rose-500 font-bold">*</span>}
                </label>
                <select
                    value={value || ''}
                    onChange={e => onChange(fieldKey, e.target.value)}
                    className="w-full rounded-xl border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3.5 py-2.5 text-sm text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2 focus:ring-brand-500 font-medium"
                >
                    {Object.entries(options).map(([optVal, optLabel]) => (
                        <option key={optVal} value={optVal}>
                            {optLabel}
                        </option>
                    ))}
                </select>
                {hint && <p className="mt-1.5 text-xs text-neutral-500 dark:text-neutral-400">{hint}</p>}
            </div>
        );
    }

    return (
        <div>
            <label className="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1.5">
                {label} {required && <span className="text-rose-500 font-bold">*</span>}
            </label>
            <input
                type="text"
                value={value || ''}
                onChange={e => onChange(fieldKey, e.target.value)}
                placeholder="Enter value..."
                className="w-full rounded-xl border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 px-3.5 py-2.5 text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-brand-500"
            />
            {hint && <p className="mt-1.5 text-xs text-neutral-500 dark:text-neutral-400">{hint}</p>}
        </div>
    );
}

function WebhookUrlCard({ urls }) {
    const [copiedKey, setCopiedKey] = useState(null);
    if (!urls) return null;

    const copy = (key, val) => {
        navigator.clipboard?.writeText(val);
        setCopiedKey(key);
        setTimeout(() => setCopiedKey(null), 1500);
    };

    return (
        <div className="rounded-2xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-5 space-y-3 shadow-sm">
            <div className="flex items-center gap-2">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-100 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 shrink-0">
                    <Link2 className="h-4 w-4" />
                </div>
                <div>
                    <h4 className="text-sm font-bold text-neutral-900 dark:text-neutral-100">Webhook & Callback Endpoints</h4>
                    <p className="text-xs text-neutral-500 dark:text-neutral-400">Configure these URLs in your provider console.</p>
                </div>
            </div>

            <div className="space-y-2 pt-1">
                {Object.entries(urls).map(([key, url]) => (
                    <div key={key} className="space-y-1">
                        <span className="text-[10px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {key.replace(/_/g, ' ')}
                        </span>
                        <div className="flex items-center gap-2 rounded-xl bg-neutral-50 dark:bg-neutral-800 px-3.5 py-2 border border-neutral-200/80 dark:border-neutral-700">
                            <code className="flex-1 truncate text-xs text-neutral-700 dark:text-neutral-300 font-mono select-all">
                                {url}
                            </code>
                            <button
                                type="button"
                                onClick={() => copy(key, url)}
                                className="flex items-center gap-1 rounded-lg border border-neutral-200 dark:border-neutral-700 px-2 py-1 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-white dark:hover:bg-neutral-700 shrink-0 transition shadow-xs"
                            >
                                {copiedKey === key ? (
                                    <><Check className="h-3.5 w-3.5 text-emerald-500" /> Copied</>
                                ) : (
                                    <><Copy className="h-3.5 w-3.5" /> Copy</>
                                )}
                            </button>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function SetupGuideCard({ provider }) {
    const [open, setOpen] = useState(false);
    const guide = SETUP_GUIDES[provider];
    if (!guide) return null;

    return (
        <div className="rounded-2xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-5 shadow-sm">
            <button
                type="button"
                onClick={() => setOpen(v => !v)}
                className="flex items-center gap-2 w-full text-left"
            >
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 shrink-0">
                    <BookOpen className="h-4 w-4" />
                </div>
                <div className="flex-1 min-w-0">
                    <span className="text-sm font-bold text-neutral-900 dark:text-neutral-100">Setup Guide & Documentation</span>
                    <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 truncate">{guide.title}</p>
                </div>
                {open ? <ChevronUp className="h-4 w-4 text-neutral-400 shrink-0" /> : <ChevronDown className="h-4 w-4 text-neutral-400 shrink-0" />}
            </button>

            {open && (
                <div className="mt-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 px-4 py-3 space-y-3">
                    {guide.subtitle && (
                        <p className="text-xs text-brand-600 dark:text-brand-400 font-semibold">{guide.subtitle}</p>
                    )}
                    <ol className="space-y-2 text-xs text-neutral-700 dark:text-neutral-300">
                        {guide.steps.map((step, i) => (
                            <li key={i} className="flex gap-2">
                                <span className="shrink-0 font-bold text-brand-600 dark:text-brand-400">{i + 1}.</span>
                                <span>{step}</span>
                            </li>
                        ))}
                    </ol>
                    {guide.link && (
                        <div className="pt-2 border-t border-neutral-200 dark:border-neutral-700">
                            <a
                                href={guide.link}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 dark:text-brand-400 hover:underline"
                            >
                                {guide.linkLabel} <ExternalLink className="h-3 w-3" />
                            </a>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

function CrmSyncOverviewCard({ isCrm }) {
    if (!isCrm) return null;

    return (
        <div className="rounded-2xl border border-indigo-200 dark:border-indigo-800/60 bg-gradient-to-r from-indigo-50/70 via-white to-purple-50/50 dark:from-indigo-950/20 dark:via-neutral-900 dark:to-purple-950/10 p-5 space-y-3 shadow-sm">
            <div className="flex items-center gap-2.5">
                <div className="h-8 w-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shrink-0">
                    <ArrowLeftRight className="h-4 w-4" />
                </div>
                <div>
                    <h4 className="text-sm font-bold text-neutral-900 dark:text-neutral-100">
                        Two-Way Synchronization (CRM ↔ Growbridge Connect)
                    </h4>
                    <p className="text-xs text-neutral-500 dark:text-neutral-400">
                        Automatic real-time sync for contacts, conversations, calls, and AI assistant summaries.
                    </p>
                </div>
            </div>

            <div className="grid sm:grid-cols-2 gap-3 pt-2 text-xs">
                <div className="p-3 rounded-xl bg-white dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 space-y-1">
                    <span className="font-bold text-indigo-600 dark:text-indigo-400 block">Growbridge → CRM Sync</span>
                    <ul className="space-y-1 text-neutral-600 dark:text-neutral-300 text-[11px]">
                        <li>• WhatsApp & SMS messages logged as activities</li>
                        <li>• Voice call duration, outcome & recording URLs</li>
                        <li>• AI interaction summaries pushed to contact notes</li>
                        <li>• New inbound leads created in CRM</li>
                    </ul>
                </div>

                <div className="p-3 rounded-xl bg-white dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 space-y-1">
                    <span className="font-bold text-indigo-600 dark:text-indigo-400 block">CRM → Growbridge Sync</span>
                    <ul className="space-y-1 text-neutral-600 dark:text-neutral-300 text-[11px]">
                        <li>• Contact names, phone numbers, and emails synced</li>
                        <li>• Lead owner assignments and lifecycle stages</li>
                        <li>• Customer company names, tags and custom fields</li>
                        <li>• Conflict resolution with automatic duplicate prevention</li>
                    </ul>
                </div>
            </div>
        </div>
    );
}

export default function IntegrationsEdit({
    provider,
    label,
    category,
    fields = [],
    config = {},
    webhookUrls = null,
    callbackUrl = null,
    storageStats = null
}) {
    const { data, setData, post, processing } = useForm({
        enabled: config.enabled ?? false,
        mode: config.mode ?? 'live',
        credentials: { ...config.credentials },
    });

    const [testResult, setTestResult] = useState(null);
    const [testing, setTesting] = useState(false);
    const brand = BRAND[provider] ?? DEFAULT_BRAND;
    const isCrm = strStartsWith(provider, 'crm_');

    function strStartsWith(str, prefix) {
        return str && typeof str === 'string' && str.indexOf(prefix) === 0;
    }

    const setCredential = (key, val) => {
        setData('credentials', { ...data.credentials, [key]: val });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('admin.integrations.update', provider), { preserveScroll: true });
    };

    const handleTest = async () => {
        setTesting(true);
        setTestResult(null);
        try {
            const { data: res } = await axios.post(route('admin.integrations.test', provider));
            setTestResult(res);
        } catch (e) {
            setTestResult({ ok: false, message: e?.response?.data?.message || 'Connection test request failed.' });
        } finally {
            setTesting(false);
        }
    };

    return (
        <AdminLayout title={`Configure ${label}`}>
            <Head title={`Configure ${label} · Admin · Growbridge Connect`} />

            <div className="max-w-3xl mx-auto space-y-6 pb-12">
                {/* Header */}
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3.5">
                        <a
                            href={route('admin.integrations.index')}
                            className="h-10 w-10 rounded-xl bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 flex items-center justify-center text-neutral-600 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition shadow-xs"
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </a>
                        <div className={`flex items-center justify-center w-11 h-11 rounded-xl shrink-0 shadow-md ${brand.bg}`}>
                            {brand.icon}
                        </div>
                        <div>
                            <span className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">
                                {category}
                            </span>
                            <h1 className="text-xl font-bold text-neutral-900 dark:text-neutral-100 leading-tight">
                                {label}
                            </h1>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            disabled={testing}
                            onClick={handleTest}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 bg-white dark:bg-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition shadow-xs disabled:opacity-50"
                        >
                            <FlaskConical className={`h-3.5 w-3.5 ${testing ? 'animate-spin' : ''}`} />
                            {testing ? 'Testing...' : 'Test Connection'}
                        </button>
                    </div>
                </div>

                {/* AWS S3 Live Storage Overview Card */}
                {provider === 'storage_s3' && storageStats && (
                    <div className="rounded-2xl border border-orange-200 dark:border-orange-900/60 bg-gradient-to-br from-orange-50/50 via-white to-amber-50/30 dark:from-orange-950/20 dark:via-neutral-900 dark:to-amber-950/10 p-6 shadow-sm space-y-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="h-10 w-10 rounded-xl bg-orange-500 text-white flex items-center justify-center shadow-sm">
                                    <Database className="h-5 w-5" />
                                </div>
                                <div>
                                    <h3 className="text-base font-bold text-neutral-900 dark:text-white">AWS S3 Production Storage Hub</h3>
                                    <p className="text-xs text-neutral-500 dark:text-neutral-400">Workspace-scoped object storage with private encryption</p>
                                </div>
                            </div>
                            <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border ${
                                storageStats.is_connected
                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
                                    : 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border-amber-200 dark:border-amber-800'
                            }`}>
                                <span className={`h-2 w-2 rounded-full ${storageStats.is_connected ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'}`} />
                                {storageStats.status}
                            </span>
                        </div>

                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
                            <div className="p-3.5 rounded-xl bg-white dark:bg-neutral-800/90 border border-neutral-200 dark:border-neutral-700">
                                <span className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 block">S3 Bucket</span>
                                <span className="text-sm font-bold text-neutral-900 dark:text-white truncate block mt-0.5">{storageStats.bucket}</span>
                            </div>
                            <div className="p-3.5 rounded-xl bg-white dark:bg-neutral-800/90 border border-neutral-200 dark:border-neutral-700">
                                <span className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 block">AWS Region</span>
                                <span className="text-sm font-bold text-neutral-900 dark:text-white block mt-0.5">{storageStats.region}</span>
                            </div>
                            <div className="p-3.5 rounded-xl bg-white dark:bg-neutral-800/90 border border-neutral-200 dark:border-neutral-700">
                                <span className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 block">Storage Used</span>
                                <span className="text-sm font-bold text-neutral-900 dark:text-white block mt-0.5">{storageStats.total_storage_formatted}</span>
                            </div>
                            <div className="p-3.5 rounded-xl bg-white dark:bg-neutral-800/90 border border-neutral-200 dark:border-neutral-700">
                                <span className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 block">Object Count</span>
                                <span className="text-sm font-bold text-neutral-900 dark:text-white block mt-0.5">{storageStats.total_objects.toLocaleString()}</span>
                            </div>
                        </div>

                        {storageStats.last_tested_at && (
                            <p className="text-[11px] text-neutral-500 dark:text-neutral-400">
                                Last Connection Test: <span className="font-semibold text-neutral-700 dark:text-neutral-200">{storageStats.last_tested_at}</span>
                                {storageStats.last_test_message && <span className="ml-1 text-neutral-400">({storageStats.last_test_message})</span>}
                            </p>
                        )}
                    </div>
                )}

                {/* Test Result Alert */}
                {testResult && (
                    <div className={`rounded-2xl border p-5 text-sm space-y-3 shadow-xs ${
                        testResult.ok
                            ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border-emerald-200 dark:border-emerald-800'
                            : 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-200 border-rose-200 dark:border-rose-800'
                    }`}>
                        <div className="flex items-start gap-3">
                            {testResult.ok ? (
                                <CheckCircle className="h-5 w-5 text-emerald-600 shrink-0 mt-0.5" />
                            ) : (
                                <XCircle className="h-5 w-5 text-rose-600 shrink-0 mt-0.5" />
                            )}
                            <div>
                                <p className="font-bold">{testResult.ok ? 'Connection Verified' : 'Connection Failed'}</p>
                                <p className="text-xs mt-0.5 opacity-90">{testResult.message}</p>
                            </div>
                        </div>

                        {/* 5-point test breakdown if available */}
                        {testResult.checks && Object.keys(testResult.checks).length > 0 && (
                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-2 border-t border-neutral-200/60 dark:border-neutral-700/60">
                                {Object.entries(testResult.checks).map(([k, c]) => (
                                    <div key={k} className="flex items-center gap-1.5 text-xs">
                                        {c.passed ? (
                                            <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                                        ) : (
                                            <XCircle className="h-3.5 w-3.5 text-rose-500" />
                                        )}
                                        <span className="font-medium truncate">{c.label}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {/* Webhooks / Callbacks if available */}
                <WebhookUrlCard urls={webhookUrls} />

                {/* Two-Way CRM Sync Overview */}
                <CrmSyncOverviewCard isCrm={isCrm} />

                {/* Setup Guide */}
                <SetupGuideCard provider={provider} />

                {/* Configuration Form */}
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Status & Environment Toggles */}
                    <div className="rounded-2xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-5 space-y-4 shadow-sm">
                        <h3 className="text-sm font-bold text-neutral-900 dark:text-neutral-100">Service Status & Mode</h3>
                        <div className="flex flex-wrap items-center gap-6">
                            <label className="flex items-center gap-2.5 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.enabled}
                                    onChange={e => setData('enabled', e.target.checked)}
                                    className="h-4 w-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500"
                                />
                                <span className="text-sm font-semibold text-neutral-800 dark:text-neutral-200">
                                    Enable Integration
                                </span>
                            </label>

                            <div className="flex items-center gap-2 ml-auto">
                                <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                                    Environment:
                                </span>
                                <select
                                    value={data.mode}
                                    onChange={e => setData('mode', e.target.value)}
                                    className="rounded-xl border border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-800 px-3 py-1.5 text-xs font-semibold text-neutral-800 dark:text-neutral-200"
                                >
                                    <option value="live">Live / Production</option>
                                    <option value="test">Sandbox / Testing</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* Credentials Fields */}
                    <div className="rounded-2xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-6 space-y-5 shadow-sm">
                        <div className="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-3">
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-neutral-100">Credentials & Settings</h3>
                            <span className="text-[11px] text-neutral-400 flex items-center gap-1">
                                <ShieldCheck className="h-3.5 w-3.5 text-emerald-600" /> AES-256 Encrypted
                            </span>
                        </div>

                        {provider === 'storage_local' ? (
                            <div className="rounded-xl bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-800 p-4 text-xs text-sky-800 dark:text-sky-200 flex items-start gap-2.5">
                                <HardDrive className="h-5 w-5 text-sky-600 shrink-0 mt-0.5" />
                                <div>
                                    <p className="font-semibold">Local Server Disk Storage</p>
                                    <p className="mt-0.5 opacity-90">No API keys required. Files are stored directly in your server's <code className="bg-sky-100 dark:bg-sky-800 px-1 rounded">storage/app/public</code> directory and served via the storage symlink.</p>
                                </div>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {fields.map(f => (
                                    f.type === 'password' ? (
                                        <SecretField
                                            key={f.key}
                                            label={f.label}
                                            fieldKey={f.key}
                                            value={data.credentials[f.key] ?? ''}
                                            onChange={setCredential}
                                            required={f.required}
                                            hint={f.hint}
                                        />
                                    ) : (
                                        <PlainField
                                            key={f.key}
                                            label={f.label}
                                            fieldKey={f.key}
                                            value={data.credentials[f.key] ?? ''}
                                            onChange={setCredential}
                                            required={f.required}
                                            hint={f.hint}
                                            type={f.type}
                                            options={f.options ?? []}
                                        />
                                    )
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Action Bar */}
                    <div className="flex items-center justify-between pt-2">
                        <a
                            href={route('admin.integrations.index')}
                            className="text-xs font-semibold text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200"
                        >
                            Cancel
                        </a>

                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm transition shadow-md shadow-brand-600/25 disabled:opacity-50"
                        >
                            <Save className="h-4 w-4" />
                            {processing ? 'Saving...' : 'Save Integration'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
