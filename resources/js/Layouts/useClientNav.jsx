import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    LayoutDashboard, Users, Megaphone, Inbox, Bot,
    Workflow, PhoneCall, BarChart3, Sliders, Settings,
    Share2, FileText, BookOpen, FlaskConical, Mic, Zap, Headset, ListTodo, Lock, CreditCard,
    Sparkles, PhoneForwarded, Radio, Wallet,
} from 'lucide-react';

const iconClass = 'h-4 w-4';

function safeRoute(name, fallback = '#') {
    try {
        if (typeof route === 'function' && route().has(name)) {
            return route(name);
        }
        return fallback;
    } catch {
        return fallback;
    }
}

/**
 * Adds live unread/folder counts (as a badge) and correct active-state
 * highlighting to the 'inbox' nav group's items. Route-based active checks
 * can't tell these apart (they're all the same route with different query
 * strings), so this matches on the actual current folder/counts instead.
 * Used by both InboxLayout (where filters/counts already come from page
 * props) and ClientLayout (which fetches counts once via nav-counts).
 */
export function enrichInboxNavGroups(groups, { filters, counts, channel } = {}) {
    // filters is only present when actually on an Inbox page (InboxLayout
    // passes its page props through). Elsewhere (ClientLayout), there's no
    // active folder to speak of — leave each item's own activePattern-based
    // check alone rather than overriding it to "folder === null" (which
    // would wrongly mark "All Conversations" active on every other page).
    const hasFilters = filters !== undefined;
    const activeFolder = filters?.folder ?? null;
    const activeChannel = channel ?? filters?.channel ?? null;

    return groups.map((group) => {
        if (group.key !== 'inbox') return group;

        return {
            ...group,
            items: group.items.map((item) => ({
                ...item,
                ...(hasFilters ? { active: () => item.folder === activeFolder && !activeChannel } : {}),
                badge: counts?.[item.countKey] > 0 ? counts[item.countKey] : null,
            })),
        };
    });
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

    const topItems = [
        {
            label: t('nav.dashboard', 'Dashboard'),
            href: safeRoute('client.dashboard'),
            icon: <LayoutDashboard className={iconClass} />,
            activePattern: 'client.dashboard',
            dataTour: 'nav-dashboard',
        },
        {
            label: t('nav.campaigns', 'Campaigns'),
            href: safeRoute('client.campaigns.index'),
            icon: <Megaphone className={iconClass} />,
            activePattern: 'client.campaigns.*',
            dataTour: 'nav-campaigns',
        },
    ];

    // Matches InboxLayout.jsx's VIEWS labels exactly, hardcoded rather than
    // translated — a stored translation override for these keys (e.g.
    // inbox.view_all -> "View All") would otherwise hijack this list while the
    // real Inbox page's own copy of these labels (which isn't run through
    // t()) stays unaffected.
    const inboxItems = [
        { label: 'All Conversations', href: safeRoute('client.inbox.index'), activePattern: 'client.inbox.index', folder: null, countKey: 'all', icon: <Inbox className={iconClass} /> },
        { label: 'Unread', href: `${safeRoute('client.inbox.index')}?folder=unread`, folder: 'unread', countKey: 'unread' },
        { label: 'Assigned to Me', href: `${safeRoute('client.inbox.index')}?folder=mine`, folder: 'mine', countKey: 'mine' },
        { label: 'AI Conversations', href: `${safeRoute('client.inbox.index')}?folder=ai`, folder: 'ai', countKey: 'ai' },
        { label: 'Open', href: `${safeRoute('client.inbox.index')}?folder=open`, folder: 'open', countKey: 'open' },
        { label: 'Closed', href: `${safeRoute('client.inbox.index')}?folder=closed`, folder: 'closed', countKey: 'closed' },
    ];

    // Hardcoded rather than translated — same reason as inboxItems above: a
    // stored translation override for nav.contacts_* keys was hijacking these
    // into generic "Contacts X" labels.
    const contactsItems = [
        { label: 'All Contacts', href: safeRoute('client.contacts.index'), icon: <Users className={iconClass} />, activePattern: 'client.contacts.*', dataTour: 'nav-contacts' },
        { label: 'Pipeline Board', href: safeRoute('client.crm.dashboard'), activePattern: 'client.crm.dashboard' },
        { label: 'Pipeline Stages', href: safeRoute('client.crm.pipelines.index'), activePattern: 'client.crm.pipelines.*' },
        { label: 'Segments', href: safeRoute('client.segments.index'), activePattern: 'client.segments.*' },
    ];

    const aiItems = [
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
    ];

    // Voice & Telephony Modules (Unlocked or Pro Badge)
    const voiceItems = [
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
    ];

    const channelItems = [
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
    ];

    const accountItems = [
        {
            label: t('nav.api_connections', 'Integrations & CRM'),
            href: safeRoute('client.crm.integrations.index', '/app/crm/integrations'),
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
        { type: 'flat', key: 'top', items: topItems },
        { type: 'group', key: 'inbox', label: t('nav.inbox', 'Inbox'), icon: <Inbox className={iconClass} />, items: inboxItems, countsRoute: 'client.inbox.nav-counts' },
        { type: 'group', key: 'contacts', label: t('nav.contacts', 'Contacts & Leads'), icon: <Users className={iconClass} />, items: contactsItems },
        { type: 'group', key: 'ai', label: t('nav.group_ai', 'AI & Automation'), icon: <Sparkles className={iconClass} />, items: aiItems },
        { type: 'group', key: 'voice', label: t('nav.group_voice', 'Voice & Calling'), icon: <PhoneForwarded className={iconClass} />, items: voiceItems },
        { type: 'group', key: 'channels', label: t('nav.group_channels', 'Channels'), icon: <Radio className={iconClass} />, items: channelItems },
        { type: 'group', key: 'account', label: t('nav.group_account', 'Account'), icon: <Wallet className={iconClass} />, items: accountItems },
    ];
}
