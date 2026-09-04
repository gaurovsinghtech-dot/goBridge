import { Head, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';
import {
    CheckCircle, XCircle, Clock, ChevronRight, ToggleLeft, ToggleRight,
    FlaskConical, Star, BookOpen, ChevronDown, ChevronUp, AlertTriangle,
    ShieldCheck, PhoneCall, MessageSquare, Sparkles, HardDrive, MapPin,
    Database, Server, Check, ArrowRight, ExternalLink, RefreshCw, Cpu,
    Layers, Zap, CloudUpload, Share2, Network, ArrowLeftRight, Workflow
} from 'lucide-react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';

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
        title: 'Meta WhatsApp Business API — Setup Guide',
        subtitle: 'WhatsApp Cloud API · WABA · Embedded Signup · Webhooks · Message Templates',
        steps: [
            'Go to developers.facebook.com → "My Apps" → "Create App" (Type: Business).',
            'Under App Settings → Basic, copy your App ID and App Secret.',
            'In the App Dashboard, add the "WhatsApp" product to your app.',
            'In Meta Business Suite → Settings → System Users, create an Admin System User with whatsapp_business_messaging & whatsapp_business_management permissions, then copy the System User Token.',
            'Under Facebook Login for Business → Configurations, create a configuration for Embedded Signup and copy its Configuration ID.',
            'Set your Webhook callback URL to the URL shown in the configure screen with your Verify Token.',
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
    storage_s3: {
        title: 'Amazon S3 Setup',
        steps: [
            'Sign in to the AWS Console at aws.amazon.com and create an S3 bucket.',
            'In IAM → Users, create a programmatic user with AmazonS3FullAccess.',
            'Paste the Access Key ID, Secret Access Key, Bucket Name, and Region below.',
        ],
        link: 'https://s3.console.aws.amazon.com',
        linkLabel: 'AWS S3 Console',
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
        accentBorder: 'border-rose-300 dark:border-rose-700',
        accentBg: 'bg-rose-50 dark:bg-rose-900/20',
        badgeColor: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
        icon: <PhoneCall className="h-5 w-5 text-white" />,
        subtitle: 'Phone Numbers • SMS • Voice • Calling • AI Voice Agents',
    },
    meta_app: {
        bg: 'bg-[#0866FF]',
        color: '#0866FF',
        accentBorder: 'border-blue-300 dark:border-blue-700',
        accentBg: 'bg-blue-50 dark:bg-blue-900/20',
        badgeColor: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        icon: <MessageSquare className="h-5 w-5 text-white" />,
        subtitle: 'WhatsApp Cloud API • WABA • Webhooks • Embedded Signup',
    },
    ai_providers: {
        bg: 'bg-emerald-600',
        color: '#10B981',
        accentBorder: 'border-emerald-300 dark:border-emerald-700',
        accentBg: 'bg-emerald-50 dark:bg-emerald-900/20',
        badgeColor: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        icon: <Sparkles className="h-5 w-5 text-white" />,
        subtitle: 'OpenAI • Gemini • Claude • Chatbots & AI Agents',
    },
    storage_local: {
        bg: 'bg-sky-600',
        color: '#0284c7',
        accentBorder: 'border-sky-300 dark:border-sky-700',
        accentBg: 'bg-sky-50 dark:bg-sky-900/20',
        badgeColor: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
        icon: <HardDrive className="h-5 w-5 text-white" />,
        subtitle: 'Files • Media • Recordings • Default Server Storage',
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
        accentBorder: 'border-amber-300 dark:border-amber-700',
        accentBg: 'bg-amber-50 dark:bg-amber-900/20',
        badgeColor: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        icon: <MapPin className="h-5 w-5 text-white" />,
        subtitle: 'Business locations & address search',
    },
    storage_s3: {
        bg: 'bg-orange-500',
        color: '#FF9900',
        icon: <CloudUpload className="h-5 w-5 text-white" />,
        subtitle: 'Amazon S3 Object Storage',
    },
    storage_do: {
        bg: 'bg-blue-600',
        color: '#0080FF',
        icon: <CloudUpload className="h-5 w-5 text-white" />,
        subtitle: 'DigitalOcean Spaces CDN Storage',
    },
    storage_wasabi: {
        bg: 'bg-green-600',
        color: '#3CBA54',
        icon: <CloudUpload className="h-5 w-5 text-white" />,
        subtitle: 'Wasabi High-Performance Cloud Storage',
    },
};

const DEFAULT_BRAND = {
    bg: 'bg-neutral-600',
    color: '#6b7280',
    icon: <Layers className="h-5 w-5 text-white" />,
    subtitle: 'Integration service',
};

function SetupGuide({ provider }) {
    const [open, setOpen] = useState(false);
    const guide = SETUP_GUIDES[provider];
    if (!guide) return null;

    return (
        <div className="border-t border-neutral-100 dark:border-neutral-800 pt-2 mt-1">
            <button
                type="button"
                onClick={() => setOpen(v => !v)}
                className="flex items-center gap-1.5 text-xs text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 font-medium transition w-full text-left"
            >
                <BookOpen className="h-3.5 w-3.5 shrink-0" />
                Setup Guide & Instructions
                {open ? <ChevronUp className="h-3 w-3 ml-auto" /> : <ChevronDown className="h-3 w-3 ml-auto" />}
            </button>
            {open && (
                <div className="mt-2 rounded-lg bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 px-3.5 py-3 space-y-2 text-xs">
                    <div>
                        <p className="font-semibold text-neutral-900 dark:text-neutral-100">{guide.title}</p>
                        {guide.subtitle && <p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5">{guide.subtitle}</p>}
                    </div>
                    <ol className="space-y-1.5 pt-1 text-neutral-700 dark:text-neutral-300">
                        {guide.steps.map((s, i) => (
                            <li key={i} className="flex gap-2">
                                <span className="font-semibold text-brand-600 dark:text-brand-400 shrink-0">{i + 1}.</span>
                                <span>{s}</span>
                            </li>
                        ))}
                    </ol>
                    {guide.link && (
                        <div className="pt-2 border-t border-neutral-200 dark:border-neutral-700">
                            <a
                                href={guide.link}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 text-[11px] font-medium text-brand-600 dark:text-brand-400 hover:underline"
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

function LaunchReadinessCard({ readiness }) {
    if (!readiness) return null;
    const { is_ready, completed_count, total_required, items = [], blocked_reason, blocked_provider } = readiness;

    return (
        <div className={`rounded-2xl border p-6 transition-all duration-300 shadow-sm ${
            is_ready
                ? 'border-emerald-200 dark:border-emerald-800/60 bg-gradient-to-r from-emerald-50/80 via-white to-emerald-50/40 dark:from-emerald-950/20 dark:via-neutral-900 dark:to-emerald-950/10 shadow-emerald-500/5'
                : 'border-amber-200 dark:border-amber-800/60 bg-gradient-to-r from-amber-50/80 via-white to-amber-50/40 dark:from-amber-950/20 dark:via-neutral-900 dark:to-amber-950/10'
        }`}>
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-neutral-200/80 dark:border-neutral-800">
                <div className="flex items-center gap-3.5">
                    <div className={`h-11 w-11 rounded-xl flex items-center justify-center shrink-0 ${
                        is_ready
                            ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/20'
                            : 'bg-amber-500 text-white shadow-lg shadow-amber-500/20'
                    }`}>
                        {is_ready ? <ShieldCheck className="h-6 w-6" /> : <AlertTriangle className="h-6 w-6" />}
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <h2 className="text-lg font-bold text-neutral-900 dark:text-neutral-100">
                                {is_ready ? 'Platform Setup — Ready for Launch' : 'Platform Setup — Launch Blocked'}
                            </h2>
                            <span className={`px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase ${
                                is_ready
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300'
                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300'
                            }`}>
                                {completed_count} / {total_required} Complete
                            </span>
                        </div>
                        <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                            {is_ready
                                ? 'All required core services (Twilio, WhatsApp, AI, Storage, Database) are connected and verified.'
                                : (blocked_reason || 'Critical integrations are missing credentials before launching customer onboarding.')}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    {is_ready ? (
                        <div className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 text-white font-semibold text-xs shadow-md shadow-emerald-600/25">
                            <Check className="h-4 w-4 stroke-[3]" /> READY FOR LAUNCH
                        </div>
                    ) : blocked_provider ? (
                        <a
                            href={route('admin.integrations.edit', blocked_provider)}
                            className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-semibold text-xs transition shadow-md shadow-amber-600/25"
                        >
                            Configure Missing Service <ArrowRight className="h-3.5 w-3.5" />
                        </a>
                    ) : null}
                </div>
            </div>

            {/* Checklist Grid */}
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-2.5 pt-4">
                {items.map((item) => {
                    const isOk = item.status === 'connected';
                    const isReq = item.required;
                    return (
                        <div
                            key={item.key}
                            className={`p-3 rounded-xl border flex flex-col justify-between gap-2 transition ${
                                isOk
                                    ? 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-200/80 dark:border-emerald-800/40'
                                    : isReq
                                        ? 'bg-amber-50/40 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800/40'
                                        : 'bg-neutral-50 dark:bg-neutral-800/40 border-neutral-200 dark:border-neutral-700/60'
                            }`}
                        >
                            <div className="flex items-center justify-between">
                                <span className={`text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded ${
                                    isReq
                                        ? 'bg-neutral-200/80 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300'
                                        : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-500'
                                }`}>
                                    {isReq ? 'Required' : 'Optional'}
                                </span>
                                {isOk ? (
                                    <CheckCircle className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                ) : isReq ? (
                                    <AlertTriangle className="h-4 w-4 text-amber-500" />
                                ) : (
                                    <span className="h-3.5 w-3.5 rounded-full border border-neutral-300 dark:border-neutral-600" />
                                )}
                            </div>
                            <div>
                                <p className="text-xs font-semibold text-neutral-900 dark:text-neutral-100 truncate">{item.name}</p>
                                <p className="text-[10px] text-neutral-500 dark:text-neutral-400 mt-0.5 truncate">{item.message}</p>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function IntegrationCard({ item, onTest, onSetDefault, testing, settingDefault }) {
    const isStorage = ['storage_local', 'storage_s3', 'storage_do', 'storage_wasabi'].includes(item.provider);
    const brand = BRAND[item.provider] ?? DEFAULT_BRAND;
    const isDefaultStorage = isStorage && item.is_default;
    const isConnected = item.configured && item.enabled;

    return (
        <div className={`rounded-2xl border p-5 flex flex-col justify-between gap-4 transition duration-200 ${
            isDefaultStorage
                ? `${brand.accentBorder ?? 'border-sky-300 dark:border-sky-700'} ${brand.accentBg ?? 'bg-sky-50 dark:bg-sky-900/20'}`
                : 'border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-sm hover:shadow-md'
        }`}>
            <div className="space-y-3">
                {/* Header */}
                <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <div className={`flex items-center justify-center w-10 h-10 rounded-xl shrink-0 shadow-md ${brand.bg}`}>
                            {brand.icon}
                        </div>
                        <div>
                            <div className="flex items-center gap-2 flex-wrap">
                                <h4 className="font-bold text-sm text-neutral-900 dark:text-neutral-100 leading-tight">
                                    {item.label}
                                </h4>
                                {isDefaultStorage && (
                                    <span className="text-[10px] px-2 py-0.5 rounded-full bg-sky-500 text-white font-bold flex items-center gap-1">
                                        <Star className="h-2.5 w-2.5 fill-white" /> DEFAULT
                                    </span>
                                )}
                            </div>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 line-clamp-1">
                                {brand.subtitle}
                            </p>
                        </div>
                    </div>

                    {/* Status Pill */}
                    <div className="shrink-0">
                        {isConnected ? (
                            <span className="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                <span className="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                                Connected
                            </span>
                        ) : item.configured ? (
                            <span className="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                Disabled
                            </span>
                        ) : (
                            <span className="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
                                ○ Not configured
                            </span>
                        )}
                    </div>
                </div>

                {/* Subtitle / Details */}
                {item.default_provider && (
                    <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-medium">
                        <Cpu className="h-3.5 w-3.5 text-brand-600 dark:text-brand-400" />
                        <span>Default Provider: <strong>{item.default_provider.toUpperCase()}</strong></span>
                    </div>
                )}

                {item.sync_direction && (
                    <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-medium">
                        <ArrowLeftRight className="h-3.5 w-3.5 text-indigo-500" />
                        <span>Mode: <strong className="capitalize">{item.sync_direction.replace('_', ' ')}</strong></span>
                    </div>
                )}

                {/* Test Status Info */}
                <div className="flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                    {item.last_test_status === 'ok' ? (
                        <CheckCircle className="h-3.5 w-3.5 text-emerald-500 shrink-0" />
                    ) : item.last_test_status === 'fail' ? (
                        <XCircle className="h-3.5 w-3.5 text-rose-500 shrink-0" />
                    ) : (
                        <Clock className="h-3.5 w-3.5 text-neutral-400 shrink-0" />
                    )}
                    <span className="truncate">{item.last_test_message || (isConnected ? 'Operational & connected' : 'Not tested yet')}</span>
                </div>

                <SetupGuide provider={item.provider} />
            </div>

            {/* Actions Toolbar */}
            <div className="flex items-center gap-2 pt-3 border-t border-neutral-100 dark:border-neutral-800 flex-wrap">
                <a
                    href={route('admin.integrations.edit', item.provider)}
                    className="flex-1 text-center rounded-xl bg-neutral-900 hover:bg-neutral-800 text-white dark:bg-neutral-100 dark:hover:bg-white dark:text-neutral-900 px-3 py-2 text-xs font-semibold transition flex items-center justify-center gap-1 shadow-sm"
                >
                    Configure <ChevronRight className="h-3.5 w-3.5" />
                </a>

                {item.configured && (
                    <button
                        type="button"
                        disabled={testing === item.provider}
                        onClick={() => onTest(item.provider)}
                        className="flex items-center gap-1.5 rounded-xl border border-neutral-200 dark:border-neutral-700 px-3 py-2 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition disabled:opacity-50"
                    >
                        <FlaskConical className={`h-3.5 w-3.5 ${testing === item.provider ? 'animate-spin' : ''}`} />
                        {testing === item.provider ? 'Testing...' : 'Test Connection'}
                    </button>
                )}

                {isStorage && item.enabled && !isDefaultStorage && (
                    <button
                        type="button"
                        disabled={settingDefault === item.provider}
                        onClick={() => onSetDefault(item.provider)}
                        className="flex items-center gap-1 rounded-xl border border-sky-300 dark:border-sky-700 px-3 py-2 text-xs font-semibold text-sky-700 dark:text-sky-300 hover:bg-sky-50 dark:hover:bg-sky-900/20 transition disabled:opacity-50"
                    >
                        <Star className="h-3 w-3" />
                        {settingDefault === item.provider ? 'Setting...' : 'Set as Default'}
                    </button>
                )}
            </div>
        </div>
    );
}

export default function IntegrationsIndex({ grouped = {}, launchReadiness = null }) {
    const { props } = usePage();
    const flash = props.flash ?? {};
    const [testing, setTesting] = useState(null);
    const [testResults, setTestResults] = useState({});
    const [settingDefault, setSettingDefault] = useState(null);

    const handleTest = async (provider) => {
        setTesting(provider);
        try {
            const { data } = await axios.post(route('admin.integrations.test', provider));
            setTestResults(r => ({ ...r, [provider]: data }));
            router.reload({ only: ['grouped', 'launchReadiness'] });
        } catch (e) {
            setTestResults(r => ({
                ...r,
                [provider]: { ok: false, message: e?.response?.data?.message || 'Connection test request failed.' },
            }));
        } finally {
            setTesting(null);
        }
    };

    const handleSetDefault = (provider) => {
        setSettingDefault(provider);
        router.post(route('admin.integrations.set-default', provider), {}, {
            onFinish: () => setSettingDefault(null),
        });
    };

    const corePlatform = grouped['Core Platform'] || [];
    const crmSystems = grouped['CRM & Business Systems'] || [];
    const optionalServices = grouped['Optional Services'] || [];
    const advancedStorage = grouped['Advanced Storage'] || [];

    return (
        <AdminLayout title="Integrations & Services">
            <Head title="Integrations · Admin · Growbridge Connect" />

            <div className="space-y-8 max-w-7xl mx-auto pb-12">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-neutral-900 dark:text-neutral-100 tracking-tight">
                            Platform Integrations
                        </h1>
                        <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                            Connect and manage the core telecommunications, AI engines, WhatsApp API, external CRMs, and storage backends powering Growbridge Connect.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <a
                            href={route('admin.integrations.audit-log')}
                            className="inline-flex items-center gap-1.5 text-xs font-semibold text-neutral-700 dark:text-neutral-300 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl px-3.5 py-2 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition shadow-sm"
                        >
                            <ShieldCheck className="h-4 w-4 text-brand-600 dark:text-brand-400" />
                            Integration Audit Log
                        </a>
                    </div>
                </div>

                {/* Flash Messages */}
                {flash.success && (
                    <div className="rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800 px-4 py-3 text-sm flex items-center gap-2">
                        <CheckCircle className="h-4 w-4 text-emerald-600 shrink-0" />
                        {flash.success}
                    </div>
                )}
                {flash.error && (
                    <div className="rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-200 border border-rose-200 dark:border-rose-800 px-4 py-3 text-sm flex items-center gap-2">
                        <XCircle className="h-4 w-4 text-rose-600 shrink-0" />
                        {flash.error}
                    </div>
                )}

                {/* Platform Setup / Launch Readiness Checklist */}
                <LaunchReadinessCard readiness={launchReadiness} />

                {/* SECTION 1: CORE PLATFORM */}
                <div className="space-y-4">
                    <div className="flex items-center justify-between border-b border-neutral-200 dark:border-neutral-800 pb-2">
                        <div>
                            <h2 className="text-sm font-bold uppercase tracking-wider text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                                <Zap className="h-4 w-4 text-emerald-600" /> CORE PLATFORM (REQUIRED)
                            </h2>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                                Essential infrastructure required for customer onboarding, phone numbers, WhatsApp, AI agents, and media storage.
                            </p>
                        </div>
                        <span className="text-xs font-semibold text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 px-2.5 py-1 rounded-full border border-emerald-200 dark:border-emerald-800">
                            4 Core Services
                        </span>
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-2">
                        {corePlatform.map(item => (
                            <IntegrationCard
                                key={item.provider}
                                item={testResults[item.provider] ? { ...item, ...testResults[item.provider] } : item}
                                onTest={handleTest}
                                onSetDefault={handleSetDefault}
                                testing={testing}
                                settingDefault={settingDefault}
                            />
                        ))}
                    </div>
                </div>

                {/* SECTION 2: CRM & BUSINESS SYSTEMS */}
                {crmSystems.length > 0 && (
                    <div className="space-y-4 pt-4">
                        <div className="flex items-center justify-between border-b border-neutral-200 dark:border-neutral-800 pb-2">
                            <div>
                                <h2 className="text-sm font-bold uppercase tracking-wider text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                                    <Share2 className="h-4 w-4 text-indigo-500" /> CRM & BUSINESS SYSTEMS (TWO-WAY SYNC)
                                </h2>
                                <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                                    Connect customer existing CRMs. Growbridge acts as an omnichannel integration layer without requiring data migration.
                                </p>
                            </div>
                            <span className="text-xs font-semibold text-indigo-600 bg-indigo-50 dark:bg-indigo-950/40 px-2.5 py-1 rounded-full border border-indigo-200 dark:border-indigo-800">
                                {crmSystems.length} CRM Connectors
                            </span>
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            {crmSystems.map(item => (
                                <IntegrationCard
                                    key={item.provider}
                                    item={testResults[item.provider] ? { ...item, ...testResults[item.provider] } : item}
                                    onTest={handleTest}
                                    onSetDefault={handleSetDefault}
                                    testing={testing}
                                    settingDefault={settingDefault}
                                />
                            ))}
                        </div>
                    </div>
                )}

                {/* SECTION 3: OPTIONAL SERVICES */}
                {optionalServices.length > 0 && (
                    <div className="space-y-4 pt-4">
                        <div className="border-b border-neutral-200 dark:border-neutral-800 pb-2">
                            <h2 className="text-sm font-bold uppercase tracking-wider text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                                <MapPin className="h-4 w-4 text-amber-500" /> OPTIONAL SERVICES
                            </h2>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                                Enhance functionality for business locations and maps search.
                            </p>
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            {optionalServices.map(item => (
                                <IntegrationCard
                                    key={item.provider}
                                    item={testResults[item.provider] ? { ...item, ...testResults[item.provider] } : item}
                                    onTest={handleTest}
                                    onSetDefault={handleSetDefault}
                                    testing={testing}
                                    settingDefault={settingDefault}
                                />
                            ))}
                        </div>
                    </div>
                )}

                {/* SECTION 4: ADVANCED STORAGE */}
                {advancedStorage.length > 0 && (
                    <div className="space-y-4 pt-4">
                        <div className="flex items-center justify-between border-b border-neutral-200 dark:border-neutral-800 pb-2">
                            <div>
                                <h2 className="text-sm font-bold uppercase tracking-wider text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                                    <Database className="h-4 w-4 text-sky-500" /> ADVANCED CLOUD STORAGE (OPTIONAL)
                                </h2>
                                <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                                    Optional cloud object storage backends for scalable multi-region recordings and media backups.
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            {advancedStorage.map(item => (
                                <IntegrationCard
                                    key={item.provider}
                                    item={testResults[item.provider] ? { ...item, ...testResults[item.provider] } : item}
                                    onTest={handleTest}
                                    onSetDefault={handleSetDefault}
                                    testing={testing}
                                    settingDefault={settingDefault}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
