import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    LayoutDashboard, Users, Megaphone, Inbox, Bot,
    Workflow, PhoneCall, BarChart3, Sliders, Settings,
    Share2, FileText, BookOpen, FlaskConical, Mic, Zap, Headset, ListTodo, Lock, CreditCard,
} from 'lucide-react';

const iconClass = 'h-4 w-4';

function safeRoute(name, ...args) {
    try { return route(name, ...args); } catch { return '#'; }
}

/**
 * Growbridge Connect — Main Navigation (Plan & Entitlement aware)
 */
export default function useClientNav() {
    const { t } = useTranslation();
    const { auth, entitlements: rootEntitlements } = usePage().props;
    const entitlements = rootEntitlements || auth?.entitlements || {};
    
    // Voice Entitlement
    const hasVoice = Boolean(entitlements.voice_calling);

    const primaryItems = [
        {
            label: t('nav.dashboard', 'Dashboard'),
            href: safeRoute('client.dashboard'),
            icon: <LayoutDashboard className={iconClass} />,
            activePattern: 'client.dashboard',
            dataTour: 'nav-dashboard',
        },
        {
            label: t('nav.inbox', 'Inbox'),
            href: safeRoute('client.inbox.index'),
            icon: <Inbox className={iconClass} />,
            activePattern: 'client.inbox.*',
            dataTour: 'nav-inbox',
        },
        {
            label: t('nav.contacts', 'Contacts & Leads'),
            href: safeRoute('client.contacts.index'),
            icon: <Users className={iconClass} />,
            activePattern: 'client.contacts.*',
            dataTour: 'nav-contacts',
        },
        {
            label: t('nav.campaigns', 'Campaigns'),
            href: safeRoute('client.campaigns.index'),
            icon: <Megaphone className={iconClass} />,
            activePattern: 'client.campaigns.*',
            dataTour: 'nav-campaigns',
        },
        {
            label: t('nav.automations', 'Automations'),
            href: safeRoute('client.automations.index'),
            icon: <Workflow className={iconClass} />,
            activePattern: 'client.automations.*',
            dataTour: 'nav-automations',
        },
        {
            label: t('nav.ai_agents', 'AI Agents'),
            href: safeRoute('client.ai.chatbots.index'),
            icon: <Bot className={iconClass} />,
            activePattern: 'client.ai.chatbots.*',
            dataTour: 'nav-ai-agents',
        },
        {
            label: t('nav.playground', 'AI Playground'),
            href: safeRoute('client.ai.playground.index'),
            icon: <FlaskConical className={iconClass} />,
            activePattern: 'client.ai.playground.*',
        },
        {
            label: t('nav.knowledge_base', 'Knowledge Base'),
            href: safeRoute('client.ai.knowledge.index'),
            icon: <BookOpen className={iconClass} />,
            activePattern: 'client.ai.knowledge.*',
        },
        {
            label: t('nav.ai_analytics', 'AI Analytics'),
            href: safeRoute('client.ai.analytics.index'),
            icon: <BarChart3 className={iconClass} />,
            activePattern: 'client.ai.analytics.*',
        },
        // Voice & Telephony Modules (Unlocked or Pro Badge)
        {
            label: t('nav.voice_studio', 'Voice Studio'),
            href: hasVoice ? safeRoute('client.ai.voice-studio.index') : safeRoute('client.pricing'),
            icon: <Mic className={iconClass} />,
            activePattern: 'client.ai.voice-studio.*',
            locked: !hasVoice,
            badge: !hasVoice ? '🔒' : null,
            dataTour: 'nav-voice-studio',
        },
        {
            label: t('nav.call_center', 'Call Center'),
            href: hasVoice ? safeRoute('client.voice.call-center') : safeRoute('client.pricing'),
            icon: <Headset className={iconClass} />,
            activePattern: 'client.voice.call-center',
            locked: !hasVoice,
            badge: !hasVoice ? '🔒' : null,
        },
        {
            label: t('nav.voice_campaigns', 'Voice Campaigns'),
            href: hasVoice ? safeRoute('client.voice.campaigns.index') : safeRoute('client.pricing'),
            icon: <PhoneCall className={iconClass} />,
            activePattern: 'client.voice.campaigns.*',
            locked: !hasVoice,
            badge: !hasVoice ? '🔒' : null,
        },
        {
            label: t('nav.smart_queue', 'Calling Queue'),
            href: hasVoice ? safeRoute('client.voice.queue.index') : safeRoute('client.pricing'),
            icon: <Zap className={iconClass} />,
            activePattern: 'client.voice.queue.*',
            locked: !hasVoice,
            badge: !hasVoice ? '🔒' : null,
        },
        {
            label: t('nav.voice_follow_ups', 'Follow-ups'),
            href: hasVoice ? safeRoute('client.voice.follow-ups.index') : safeRoute('client.pricing'),
            icon: <ListTodo className={iconClass} />,
            activePattern: 'client.voice.follow-ups.*',
            locked: !hasVoice,
            badge: !hasVoice ? '🔒' : null,
        },
        {
            label: t('nav.voice_agents', 'Voice Agents'),
            href: hasVoice ? safeRoute('client.voice.index') : safeRoute('client.pricing'),
            icon: <Bot className={iconClass} />,
            activePattern: 'client.voice.index',
            locked: !hasVoice,
            badge: !hasVoice ? '🔒' : null,
            dataTour: 'nav-voice-agents',
        },
        {
            label: t('nav.phone_numbers', 'Phone Numbers'),
            href: hasVoice ? safeRoute('client.voice.numbers.index', safeRoute('client.voice.call-center')) : safeRoute('client.pricing'),
            icon: <PhoneCall className={iconClass} />,
            activePattern: ['client.voice.numbers.*', 'client.voice.phone-numbers.*'],
            locked: !hasVoice,
            badge: !hasVoice ? '🔒' : null,
            dataTour: 'nav-phone-numbers',
        },
        {
            label: t('nav.channels', 'WhatsApp & Channels'),
            href: safeRoute('client.inbox.setup'),
            icon: <Share2 className={iconClass} />,
            activePattern: 'client.inbox.setup',
            dataTour: 'nav-whatsapp',
        },
        {
            label: t('nav.templates', 'Templates'),
            href: safeRoute('client.whatsapp.templates.index'),
            icon: <FileText className={iconClass} />,
            activePattern: 'client.whatsapp.templates.*',
            dataTour: 'nav-templates',
        },
        {
            label: t('nav.analytics', 'Analytics'),
            href: safeRoute('client.reports.inbox.index'),
            icon: <BarChart3 className={iconClass} />,
            activePattern: 'client.reports.*',
            dataTour: 'nav-analytics',
        },
        {
            label: t('nav.api_connections', 'Integrations & CRM'),
            href: safeRoute('client.crm.integrations.index', safeRoute('client.api-tokens.index', '/app/crm/integrations')),
            icon: <Sliders className={iconClass} />,
            activePattern: ['client.api-tokens.*', 'client.api.*', 'client.webhooks.*', 'client.crm.integrations.*'],
            dataTour: 'nav-integrations',
        },
        {
            label: t('nav.wallet', 'Wallet & Usage'),
            href: safeRoute('client.billing.wallet.index', '/app/billing/wallet'),
            icon: <CreditCard className={iconClass} />,
            activePattern: 'client.billing.wallet.*',
            dataTour: 'nav-wallet',
        },
        {
            label: t('nav.plans_and_billing', 'Plans & Billing'),
            href: safeRoute('client.subscription.show', safeRoute('client.pricing', '/app/subscription')),
            icon: <CreditCard className={iconClass} />,
            activePattern: ['client.subscription.*', 'client.pricing'],
            dataTour: 'nav-billing',
        },
        {
            label: t('nav.settings', 'Settings'),
            href: safeRoute('client.settings.index'),
            icon: <Settings className={iconClass} />,
            activePattern: 'client.settings.*',
            dataTour: 'nav-settings',
        },
    ];

    return [
        {
            type: 'group',
            label: t('nav.main_menu', 'Menu'),
            items: primaryItems,
        },
    ];
}
