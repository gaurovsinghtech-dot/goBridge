import { Head, router, usePage } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { useCallback, useContext, useState, createContext } from 'react';
import {
    ArrowLeft, Save, Play, Pause, Copy, Check, RefreshCw,
    X, Zap, Mail, Phone, Clock, GitBranch,
    Tag, Scissors, UserCog, Megaphone, Bot, Webhook,
    Plus, Trash2, Settings, UserRound, Link2, MessageCircle, FileText,
    ShoppingBag, PackageCheck, XCircle, ShoppingCart, UserPlus,
    Image, Layers, MousePointerClick, List, HelpCircle, Workflow, Sparkles,
    UserCheck, ExternalLink, MapPin, BarChart3, CalendarClock, Video,
    ClipboardList, ClipboardCheck, Store, Sheet, LayoutTemplate, AlertTriangle, GripVertical,
    FlaskConical, Loader2, CheckCircle2, MinusCircle, AlertCircle,
} from 'lucide-react';
import { ChannelBrandIcon } from '@/Components/BrandIcons';
import MediaUpload from '@/Components/MediaUpload';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';
import {
    ReactFlow,
    ReactFlowProvider,
    addEdge,
    Background,
    Controls,
    MiniMap,
    useNodesState,
    useEdgesState,
    useReactFlow,
    Panel,
    Handle,
    Position,
    MarkerType,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';

/* ─── Constants ─────────────────────────────────────────────── */

const TRIGGER_TYPES = [
    { value: 'contact.created',    labelKey: 'automation.trigger_contact_created',    Icon: UserRound      },
    { value: 'contact.tag_added',  labelKey: 'automation.trigger_tag_added',          Icon: Tag            },
    { value: 'message.received',   labelKey: 'automation.trigger_message_received',   Icon: MessageCircle  },
    { value: 'campaign.sent',      labelKey: 'automation.trigger_campaign_sent',      Icon: Megaphone      },
    { value: 'campaign.reply',     labelKey: 'automation.trigger_campaign_reply',     Icon: MessageCircle  },
    { value: 'lead.stage_changed', labelKey: 'automation.trigger_stage_changed',      Icon: Layers         },
    { value: 'voice.call_completed', labelKey: 'automation.trigger_voice_completed',   Icon: Phone          },
    { value: 'form.submitted',     labelKey: 'automation.trigger_form_submitted',     Icon: FileText       },
    { value: 'webhook.received',   labelKey: 'automation.trigger_webhook_received',   Icon: Link2          },
    { value: 'order.placed',       labelKey: 'automation.trigger_order_placed',       Icon: ShoppingBag    },
    { value: 'order.fulfilled',    labelKey: 'automation.trigger_order_fulfilled',    Icon: PackageCheck   },
    { value: 'order.cancelled',    labelKey: 'automation.trigger_order_cancelled',    Icon: XCircle        },
    { value: 'cart.abandoned',     labelKey: 'automation.trigger_cart_abandoned',     Icon: ShoppingCart   },
    { value: 'customer.created',   labelKey: 'automation.trigger_customer_created',   Icon: UserPlus       },
];

// Categories rendered (in order) in the node palette — mirrors the product node list.
const CATEGORY_ORDER = ['send', 'listen', 'logic', 'ai', 'contact', 'engage', 'commerce', 'integrations'];

const NODE_DEFS = {
    // ── SEND ──────────────────────────────────────────────────────────────
    send_whatsapp:       { labelKey: 'automation.node_send_whatsapp',       defaultLabel: 'Send WhatsApp',       color: '#25D366', bg: '#064e3b', icon: 'whatsapp',        category: 'send' },
    send_instagram:      { labelKey: 'automation.node_send_instagram',      defaultLabel: 'Send Instagram',      color: '#E1306C', bg: '#701a75', icon: 'instagram',       category: 'send' },
    send_messenger:      { labelKey: 'automation.node_send_messenger',      defaultLabel: 'Send Messenger',      color: '#0084FF', bg: '#1e3a8a', icon: 'messenger',       category: 'send' },
    send_template:       { labelKey: 'automation.node_send_template',       defaultLabel: 'Send Template',       color: '#16a34a', bg: '#064e3b', icon: LayoutTemplate,    category: 'send' },
    send_media:          { labelKey: 'automation.node_send_media',          defaultLabel: 'Send Media',          color: '#0d9488', bg: '#134e4a', icon: Image,             category: 'send' },
    send_sequence:       { labelKey: 'automation.node_send_sequence',       defaultLabel: 'Send Sequence',       color: '#0891b2', bg: '#164e63', icon: Layers,            category: 'send' },
    quick_replies:       { labelKey: 'automation.node_quick_replies',       defaultLabel: 'Quick Replies',       color: '#a855f7', bg: '#581c87', icon: MousePointerClick, category: 'send' },
    list_message:        { labelKey: 'automation.node_list_message',        defaultLabel: 'List Message',        color: '#9333ea', bg: '#581c87', icon: List,              category: 'send' },
    send_sms:            { labelKey: 'automation.node_send_sms',            defaultLabel: 'Send SMS',            color: '#6366f1', bg: '#312e81', icon: Phone,             category: 'send' },
    send_email:          { labelKey: 'automation.node_send_email',          defaultLabel: 'Send Email',          color: '#0ea5e9', bg: '#0c4a6e', icon: Mail,              category: 'send' },
    // ── LISTEN ────────────────────────────────────────────────────────────
    ask_question:        { labelKey: 'automation.node_ask_question',        defaultLabel: 'Ask Question',        color: '#f97316', bg: '#7c2d12', icon: HelpCircle,        category: 'listen' },
    // ── LOGIC ─────────────────────────────────────────────────────────────
    condition:           { labelKey: 'automation.node_condition',           defaultLabel: 'Condition (If/Else)', color: '#a855f7', bg: '#581c87', icon: GitBranch,         category: 'logic' },
    wait:                { labelKey: 'automation.node_wait',                defaultLabel: 'Wait / Delay',        color: '#f59e0b', bg: '#78350f', icon: Clock,             category: 'logic' },
    goal_reached:        { labelKey: 'automation.node_goal_reached',        defaultLabel: 'Goal Reached',        color: '#10b981', bg: '#064e3b', icon: CheckCircle2,      category: 'logic' },
    webhook:             { labelKey: 'automation.node_webhook',             defaultLabel: 'Outgoing Webhook',    color: '#64748b', bg: '#1e293b', icon: Webhook,           category: 'logic' },
    run_subflow:         { labelKey: 'automation.node_run_subflow',         defaultLabel: 'Run Sub-flow',        color: '#64748b', bg: '#1e293b', icon: Workflow,          category: 'logic' },
    // ── AI ────────────────────────────────────────────────────────────────
    ai_reply:            { labelKey: 'automation.node_ai_reply',            defaultLabel: 'AI Auto-Reply',       color: '#c084fc', bg: '#581c87', icon: Sparkles,          category: 'ai' },
    ai_detect_intent:    { labelKey: 'automation.node_ai_detect_intent',    defaultLabel: 'AI Intent Classifier',color: '#e879f9', bg: '#701a75', icon: Sparkles,          category: 'ai' },
    voice_call:          { labelKey: 'automation.node_voice_call',          defaultLabel: 'Trigger AI Voice Call',color: '#3b82f6', bg: '#1e3a8a', icon: Phone,            category: 'ai' },
    // ── CONTACT ───────────────────────────────────────────────────────────
    add_tag:             { labelKey: 'automation.node_add_tag',             defaultLabel: 'Add Tag',             color: '#10b981', bg: '#064e3b', icon: Tag,               category: 'contact' },
    remove_tag:          { labelKey: 'automation.node_remove_tag',          defaultLabel: 'Remove Tag',          color: '#f43f5e', bg: '#881337', icon: Scissors,          category: 'contact' },
    update_contact:      { labelKey: 'automation.node_update_contact',      defaultLabel: 'Update Contact',      color: '#0ea5e9', bg: '#0c4a6e', icon: UserCog,           category: 'contact' },
    assign_agent:        { labelKey: 'automation.node_assign_agent',        defaultLabel: 'Assign Agent',        color: '#38bdf8', bg: '#0c4a6e', icon: UserCheck,         category: 'contact' },
    human_handoff:       { labelKey: 'automation.node_human_handoff',       defaultLabel: 'Human Handoff',       color: '#f97316', bg: '#7c2d12', icon: UserCheck,         category: 'contact' },
    create_task:         { labelKey: 'automation.node_create_task',         defaultLabel: 'Create CRM Task',     color: '#38bdf8', bg: '#0c4a6e', icon: ClipboardCheck,    category: 'contact' },
    add_note:            { labelKey: 'automation.node_add_note',            defaultLabel: 'Add Contact Note',    color: '#64748b', bg: '#1e293b', icon: FileText,          category: 'contact' },
    add_to_campaign:     { labelKey: 'automation.node_add_to_campaign',     defaultLabel: 'Add to Campaign',     color: '#f97316', bg: '#7c2d12', icon: Megaphone,         category: 'contact' },
    // ── ENGAGE ────────────────────────────────────────────────────────────
    cta_button:          { labelKey: 'automation.node_cta_button',          defaultLabel: 'CTA Link Button',     color: '#f43f5e', bg: '#881337', icon: ExternalLink,      category: 'engage' },
    send_location:       { labelKey: 'automation.node_send_location',       defaultLabel: 'Send Location',       color: '#ef4444', bg: '#7f1d1d', icon: MapPin,            category: 'engage' },
    send_poll:           { labelKey: 'automation.node_send_poll',           defaultLabel: 'Send Interactive Poll',color: '#c084fc', bg: '#581c87', icon: BarChart3,        category: 'engage' },
    run_chatbot:         { labelKey: 'automation.node_run_chatbot',         defaultLabel: 'Run Chatbot Agent',   color: '#a855f7', bg: '#581c87', icon: Bot,               category: 'engage' },
    book_appointment:    { labelKey: 'automation.node_book_appointment',    defaultLabel: 'Book Appointment',    color: '#3b82f6', bg: '#1e3a8a', icon: CalendarClock,     category: 'engage' },
    google_meet:         { labelKey: 'automation.node_google_meet',         defaultLabel: 'Create Google Meet',  color: '#22c55e', bg: '#064e3b', icon: Video,             category: 'engage' },
    whatsapp_form:       { labelKey: 'automation.node_whatsapp_form',       defaultLabel: 'Send WhatsApp Form',  color: '#06b6d4', bg: '#164e63', icon: ClipboardList,     category: 'engage' },
    // ── COMMERCE ──────────────────────────────────────────────────────────
    whatsapp_catalog:    { labelKey: 'automation.node_whatsapp_catalog',    defaultLabel: 'Send WhatsApp Catalog',color: '#22c55e', bg: '#064e3b', icon: ShoppingBag,       category: 'commerce' },
    woocommerce_product: { labelKey: 'automation.node_woocommerce_product', defaultLabel: 'WooCommerce Product', color: '#a855f7', bg: '#581c87', icon: ShoppingCart,      category: 'commerce' },
    shopify_product:     { labelKey: 'automation.node_shopify_product',     defaultLabel: 'Shopify Product',     color: '#84cc16', bg: '#365314', icon: Store,             category: 'commerce' },
    // ── INTEGRATIONS ──────────────────────────────────────────────────────
    google_sheets:       { labelKey: 'automation.node_google_sheets',       defaultLabel: 'Google Sheets Sync',  color: '#10b981', bg: '#064e3b', icon: Sheet,             category: 'integrations' },
    google_docs:         { labelKey: 'automation.node_google_docs',         defaultLabel: 'Generate Google Doc', color: '#3b82f6', bg: '#1e3a8a', icon: FileText,          category: 'integrations' },
    google_forms:        { labelKey: 'automation.node_google_forms',        defaultLabel: 'Google Forms Sync',   color: '#a855f7', bg: '#581c87', icon: ClipboardCheck,    category: 'integrations' },
};

function getNodeDefLabel(type, t) {
    const def = NODE_DEFS[type];
    if (!def) return type;
    const tr = t(def.labelKey);
    if (tr && tr !== def.labelKey && !tr.startsWith('automation.node_')) {
        return tr;
    }
    return def.defaultLabel || type;
}

function getTriggerDefLabel(trigger, t) {
    if (!trigger) return t('automation.select_trigger');
    const tr = t(trigger.labelKey);
    if (tr && tr !== trigger.labelKey && !tr.startsWith('automation.trigger_')) {
        return tr;
    }
    const fallbacks = {
        'contact.created': 'Contact Created',
        'contact.tag_added': 'Tag Added to Contact',
        'message.received': 'Message Received',
        'campaign.sent': 'Broadcast Campaign Sent',
        'campaign.reply': 'Campaign Reply Received',
        'lead.stage_changed': 'Lead Stage Changed',
        'voice.call_completed': 'AI Call Completed',
        'form.submitted': 'Form Submitted',
        'webhook.received': 'Webhook Trigger Received',
        'order.placed': 'Ecommerce Order Placed',
        'order.fulfilled': 'Ecommerce Order Fulfilled',
        'order.cancelled': 'Ecommerce Order Cancelled',
        'cart.abandoned': 'Cart Abandoned',
        'customer.created': 'New Customer Created',
    };
    return fallbacks[trigger.value] || trigger.value;
}

const CONDITION_FIELDS = [
    { value: 'contact.name',      labelKey: 'automation.cond_field_contact_name' },
    { value: 'contact.email',     labelKey: 'automation.cond_field_contact_email' },
    { value: 'contact.phone',     labelKey: 'automation.cond_field_contact_phone' },
    { value: 'contact.tag',       labelKey: 'automation.cond_field_contact_tag' },
    { value: 'message.body',      labelKey: 'automation.cond_field_message_body' },
    { value: 'context.key',       labelKey: 'automation.cond_field_context_key' },
];

const CONDITION_OPERATORS = [
    { value: 'equals',       labelKey: 'automation.op_equals' },
    { value: 'not_equals',   labelKey: 'automation.op_not_equals' },
    { value: 'contains',     labelKey: 'automation.op_contains' },
    { value: 'not_contains', labelKey: 'automation.op_not_contains' },
    { value: 'exists',       labelKey: 'automation.op_exists' },
    { value: 'not_exists',   labelKey: 'automation.op_not_exists' },
];

const UPDATE_FIELDS = [
    { value: 'name',   labelKey: 'common.name' },
    { value: 'email',  labelKey: 'common.email' },
    { value: 'phone',  labelKey: 'automation.update_field_phone' },
    { value: 'notes',  labelKey: 'automation.update_field_notes' },
];

/* ─── Resources (builder reference data from the controller) ───── */
function useResources() {
    const { props } = usePage();
    return props.resources ?? {};
}

/* ─── Node icon helper ───────────────────────────────────────── */
function NodeIcon({ nodeType, size = 14 }) {
    const def = NODE_DEFS[nodeType];
    if (!def) return <Settings size={size} />;
    if (def.icon === 'whatsapp' || def.icon === 'sms' || def.icon === 'email') {
        return <ChannelBrandIcon channel={def.icon} className={`h-[${size}px] w-[${size}px] shrink-0`} />;
    }
    const Icon = def.icon;
    return <Icon size={size} />;
}

/* Lets custom nodes reach the builder's configure/delete handlers (context crosses the ReactFlow boundary). */
const NodeActionsContext = createContext({ onConfigure: () => {}, onDelete: () => {} });

/* ─── Custom Nodes ───────────────────────────────────────────── */
function BaseNode({ id, data, selected }) {
    const { t } = useTranslation();
    const { onConfigure, onDelete } = useContext(NodeActionsContext);
    const { nodeType, label, configured } = data;
    const def = NODE_DEFS[nodeType];
    const defLabel = getNodeDefLabel(nodeType, t);
    const isCondition = nodeType === 'condition';

    const hasLabel = label && label !== defLabel;
    const summary = hasLabel ? label : (configured ? summarizeConfig(data, t) : '');

    return (
        <div
            style={{
                background: '#ffffff',
                border: `1.5px solid ${selected ? '#2563eb' : '#e5e5e5'}`,
                borderRadius: 14,
                minWidth: 230,
                boxShadow: selected
                    ? '0 0 0 2px #2563eb, 0 8px 24px rgba(37,99,235,0.15)'
                    : '0 2px 8px rgba(0,0,0,0.06)',
                transition: 'all 0.15s ease-in-out',
                overflow: 'hidden',
                position: 'relative',
            }}
        >
            {/* Top Accent Bar */}
            <div style={{ height: 3, background: selected ? '#2563eb' : '#d4d4d4' }} />

            <Handle type="target" position={Position.Top} style={{ background: '#ffffff', width: 10, height: 10, border: '2px solid #2563eb' }} />

            {/* Row: icon chip · text · actions */}
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 12px' }}>
                <span style={{
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    width: 32, height: 32, borderRadius: 10, flexShrink: 0,
                    background: '#f5f5f5', color: '#171717',
                    border: '1px solid #e5e5e5',
                }}>
                    <NodeIcon nodeType={nodeType} size={16} />
                </span>

                <div style={{ minWidth: 0, flex: 1 }}>
                    <div style={{ fontSize: 12.5, fontWeight: 700, color: '#171717', lineHeight: 1.3, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {defLabel}
                    </div>
                    <div style={{
                        fontSize: 11, lineHeight: 1.3, marginTop: 2,
                        color: summary ? '#52525b' : '#a1a1aa',
                        fontStyle: summary ? 'normal' : 'italic',
                        overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
                    }}>
                        {summary || t('automation.click_to_configure')}
                    </div>
                </div>

                <div className="nodrag" style={{ display: 'flex', alignItems: 'center', gap: 2, flexShrink: 0 }}>
                    <button
                        className="nodrag"
                        title={t('common.settings')}
                        onClick={(e) => { e.stopPropagation(); onConfigure(id); }}
                        style={{
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            width: 24, height: 24, padding: 0, borderRadius: 6, border: 'none',
                            background: 'transparent', color: '#71717a', cursor: 'pointer',
                            transition: 'all 0.15s',
                        }}
                        onMouseEnter={e => { e.currentTarget.style.background = '#f5f5f5'; e.currentTarget.style.color = '#171717'; }}
                        onMouseLeave={e => { e.currentTarget.style.background = 'transparent'; e.currentTarget.style.color = '#71717a'; }}
                    >
                        <Settings size={13} />
                    </button>
                    <button
                        className="nodrag"
                        title={t('common.delete')}
                        onClick={(e) => { e.stopPropagation(); onDelete(id); }}
                        style={{
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            width: 24, height: 24, padding: 0, borderRadius: 6, border: 'none',
                            background: 'transparent', color: '#71717a', cursor: 'pointer',
                            transition: 'all 0.15s',
                        }}
                        onMouseEnter={e => { e.currentTarget.style.background = '#f5f5f5'; e.currentTarget.style.color = '#ef4444'; }}
                        onMouseLeave={e => { e.currentTarget.style.background = 'transparent'; e.currentTarget.style.color = '#71717a'; }}
                    >
                        <Trash2 size={13} />
                    </button>
                </div>
            </div>

            {/* Handles */}
            {isCondition ? (
                <>
                    <div style={{ display: 'flex', justifyContent: 'space-between', padding: '0 14px 8px', fontSize: 10, fontWeight: 700 }}>
                        <span style={{ color: '#2563eb' }}>✓ {t('common.yes')}</span>
                        <span style={{ color: '#71717a' }}>✗ {t('common.no')}</span>
                    </div>
                    <Handle type="source" id="true"  position={Position.Bottom} style={{ left: '30%', background: '#ffffff', width: 10, height: 10, border: '2px solid #2563eb' }} />
                    <Handle type="source" id="false" position={Position.Bottom} style={{ left: '70%', background: '#ffffff', width: 10, height: 10, border: '2px solid #9ca3af' }} />
                </>
            ) : (
                <Handle type="source" position={Position.Bottom} style={{ background: '#ffffff', width: 10, height: 10, border: '2px solid #2563eb' }} />
            )}
        </div>
    );
}

function TriggerNode({ data, selected }) {
    const { t } = useTranslation();
    const trigger = TRIGGER_TYPES.find(tr => tr.value === data.triggerType);
    const label = getTriggerDefLabel(trigger, t);

    return (
        <div style={{
            background: '#ffffff',
            border: `1.5px solid ${selected ? '#2563eb' : '#e5e5e5'}`,
            borderRadius: 14,
            minWidth: 230,
            boxShadow: selected
                ? '0 0 0 2px #2563eb, 0 8px 24px rgba(37,99,235,0.15)'
                : '0 2px 8px rgba(0,0,0,0.06)',
            transition: 'all 0.15s ease-in-out',
            overflow: 'hidden',
        }}>
            {/* Top Blue Accent Bar */}
            <div style={{ height: 3, background: '#2563eb' }} />

            <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 12px' }}>
                <span style={{
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    width: 32, height: 32, borderRadius: 10, flexShrink: 0,
                    background: '#eff6ff', color: '#2563eb',
                    border: '1px solid #bfdbfe',
                }}>
                    <Zap size={16} />
                </span>
                <div style={{ minWidth: 0, flex: 1 }}>
                    <div style={{ fontSize: 9.5, fontWeight: 800, color: '#2563eb', letterSpacing: '0.08em', textTransform: 'uppercase', lineHeight: 1.3 }}>
                        {t('automation.trigger')}
                    </div>
                    <div style={{ fontSize: 12.5, fontWeight: 700, color: '#171717', display: 'flex', alignItems: 'center', gap: 6, marginTop: 2, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {trigger?.Icon && <trigger.Icon size={13} style={{ color: '#71717a', flexShrink: 0 }} />}
                        <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{label}</span>
                    </div>
                </div>
            </div>
            <Handle type="source" position={Position.Bottom} style={{ background: '#ffffff', width: 10, height: 10, border: '2px solid #2563eb' }} />
        </div>
    );
}

function clip(str, n = 40) {
    if (!str) return '';
    return str.length > n ? str.slice(0, n) + '…' : str;
}

function summarizeConfig(data, t) {
    const { nodeType } = data;
    switch (nodeType) {
        case 'wait': return data.amount ? `${data.amount} ${data.unit ?? 'minutes'}` : '';
        case 'condition': return data.field ? `${data.field} ${data.operator ?? '='} ${data.value ?? ''}` : '';
        case 'add_tag':
        case 'remove_tag': return data.tag ?? '';
        case 'send_whatsapp':
        case 'send_sms': return clip(data.body);
        case 'send_email': return data.subject ?? '';
        case 'update_contact': return data.field ? `${data.field} = ${data.value ?? ''}` : '';
        case 'add_to_campaign': return data.campaign_id ? t('automation.campaign_ref', { id: data.campaign_id }) : '';
        case 'ai_reply':
        case 'run_chatbot': return clip(data.prompt || data.chatbot_id || '');
        case 'webhook': return data.url ? `${data.method ?? 'POST'} ${clip(data.url, 28)}` : '';
        case 'send_template': return data.template_name ?? '';
        case 'send_media': return data.link ? `${data.media_type ?? 'image'} · ${clip(data.link, 24)}` : (data.media_type ?? '');
        case 'send_sequence': return Array.isArray(data.steps) && data.steps.length ? t('automation.seq_steps', { count: data.steps.length }) : '';
        case 'quick_replies': return clip(data.body, 30);
        case 'list_message': return clip(data.body, 30);
        case 'ask_question': return data.question ? `${clip(data.question, 28)} → {{${data.variable || 'answer'}}}` : '';
        case 'run_subflow': return data.subflow_name ?? data.automation_uuid ?? '';
        case 'assign_agent': return data.agent_name ?? (data.user_id ? `#${data.user_id}` : t('automation.assign_unassigned'));
        case 'cta_button': return data.display_text ? `${data.display_text} · ${clip(data.url, 22)}` : clip(data.url, 28);
        case 'send_location': return data.name || (data.latitude ? `${data.latitude}, ${data.longitude}` : '');
        case 'send_poll': return clip(data.question, 30);
        case 'book_appointment':
        case 'google_meet': return data.start ? `${clip(data.summary, 18)} · ${data.start}` : clip(data.summary, 24);
        case 'whatsapp_form': return data.flow_id ? `flow ${data.flow_id}` : '';
        case 'whatsapp_catalog': return clip(data.body, 30);
        case 'woocommerce_product':
        case 'shopify_product': return data.product_id ? `#${data.product_id}` : '';
        case 'google_sheets': return data.spreadsheet_id ? `${data.mode ?? 'append'} · ${clip(data.range, 18)}` : '';
        case 'google_docs': return clip(data.title, 28);
        case 'google_forms': return data.form_id ? `${data.mode === 'read_response' ? 'read' : 'share'} · ${clip(data.form_id, 18)}` : '';
        default: return '';
    }
}

const nodeTypes = {
    automationNode: BaseNode,
    triggerNode: TriggerNode,
};

/* ─── Config Panel ───────────────────────────────────────────── */
const FIELD_COMPONENTS = {
    send_whatsapp: WhatsAppFields,
    send_sms: SmsFields,
    send_email: EmailFields,
    send_template: TemplateFields,
    send_media: MediaFields,
    send_sequence: SequenceFields,
    quick_replies: QuickRepliesFields,
    list_message: ListMessageFields,
    ask_question: AskQuestionFields,
    wait: WaitFields,
    condition: ConditionFields,
    webhook: WebhookFields,
    run_subflow: SubflowFields,
    ai_reply: AIReplyFields,
    add_tag: TagFields,
    remove_tag: TagFields,
    update_contact: UpdateContactFields,
    assign_agent: AssignAgentFields,
    add_to_campaign: CampaignFields,
    cta_button: CtaButtonFields,
    send_location: LocationFields,
    send_poll: PollFields,
    run_chatbot: RunChatbotFields,
    book_appointment: BookAppointmentFields,
    google_meet: GoogleMeetFields,
    whatsapp_form: WhatsappFormFields,
    whatsapp_catalog: WhatsappCatalogFields,
    woocommerce_product: (p) => <ProductFields {...p} platform="woocommerce" />,
    shopify_product: (p) => <ProductFields {...p} platform="shopify" />,
    google_sheets: GoogleSheetsFields,
    google_docs: GoogleDocsFields,
    google_forms: GoogleFormsFields,
};

function ConfigPanel({ node, onClose, onChange, onDelete }) {
    const { t } = useTranslation();
    if (!node) return null;
    const { nodeType } = node.data;
    const def = NODE_DEFS[nodeType];
    const defLabel = def ? t(def.labelKey) : nodeType;
    const d = node.data;
    const set = (key, val) => onChange(node.id, { ...d, [key]: val, configured: true });
    const Fields = FIELD_COMPONENTS[nodeType];

    return (
        <div style={{
            position: 'absolute', top: 0, right: 0, bottom: 0, width: 320,
            background: '#ffffff', borderLeft: '1px solid #e5e5e5',
            boxShadow: '-4px 0 24px rgba(0,0,0,0.08)',
            zIndex: 10, display: 'flex', flexDirection: 'column',
            borderRadius: '0 0 12px 0',
        }}>
            {/* Header */}
            <div style={{
                background: '#fafafa', borderBottom: '1px solid #e5e5e5', padding: '14px 16px',
                display: 'flex', alignItems: 'center', justifyContent: 'space-between',
            }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <span style={{ color: '#171717' }}><NodeIcon nodeType={nodeType} size={15} /></span>
                    <span style={{ color: '#171717', fontWeight: 700, fontSize: 13 }}>{defLabel}</span>
                </div>
                <button onClick={onClose} style={{ color: '#71717a', background: 'none', border: 'none', cursor: 'pointer', display: 'flex' }}>
                    <X size={16} />
                </button>
            </div>

            {/* Fields */}
            <div style={{ flex: 1, overflowY: 'auto', padding: 16 }} className="space-y-4">
                {/* Label */}
                <Field label={t('automation.node_label_optional')}>
                    <input className={inputCls} value={d.label ?? ''} onChange={e => set('label', e.target.value)} placeholder={defLabel} />
                </Field>

                {/* Per-type fields */}
                {Fields && <Fields d={d} set={set} />}

                {/* Token hint */}
                <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8, padding: '8px 10px', fontSize: 10, color: '#64748b' }}>
                    <strong style={{ color: '#1e293b' }}>{t('automation.available_tokens')}</strong> <code>{'{{contact.name}}'}</code>, <code>{'{{contact.email}}'}</code>, <code>{'{{contact.phone}}'}</code>, <code>{'{{message.body}}'}</code>, <code>{'{{context.key}}'}</code>
                </div>
            </div>

            {/* Drawer Footer: Save & Delete Actions */}
            <div style={{
                padding: '12px 16px', borderTop: '1px solid #e5e5e5',
                background: '#fafafa', display: 'flex', gap: 8, alignItems: 'center'
            }}>
                <button
                    onClick={onClose}
                    style={{
                        flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6,
                        background: '#2563eb', color: '#ffffff', border: '1px solid #2563eb', borderRadius: 10,
                        padding: '9px 0', fontSize: 12, fontWeight: 700, cursor: 'pointer',
                        boxShadow: '0 2px 6px rgba(37,99,235,0.25)', transition: 'all 0.15s'
                    }}
                >
                    <Check size={14} /> {t('common.save')}
                </button>
                {onDelete && (
                    <button
                        onClick={onDelete}
                        title={t('automation.delete_node')}
                        style={{
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            width: 36, height: 36, background: '#ffffff', border: '1px solid #e5e5e5', borderRadius: 10,
                            color: '#71717a', cursor: 'pointer', flexShrink: 0, transition: 'all 0.15s'
                        }}
                        onMouseEnter={e => { e.currentTarget.style.background = '#fef2f2'; e.currentTarget.style.color = '#ef4444'; e.currentTarget.style.borderColor = '#fca5a5'; }}
                        onMouseLeave={e => { e.currentTarget.style.background = '#ffffff'; e.currentTarget.style.color = '#71717a'; e.currentTarget.style.borderColor = '#e5e5e5'; }}
                    >
                        <Trash2 size={15} />
                    </button>
                )}
            </div>
        </div>
    );
}

const inputCls = "w-full rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-2 text-xs text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:outline-none focus:ring-1 focus:ring-blue-600 focus:border-blue-600 transition";
const textareaCls = "w-full rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-2 text-xs text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:outline-none focus:ring-1 focus:ring-blue-600 focus:border-blue-600 transition resize-none";
const selectCls = "w-full rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 px-3 py-2 text-xs text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-blue-600 focus:border-blue-600 transition";
const labelCls = "block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1";

function Field({ label, children }) {
    return <div><label className={labelCls}>{label}</label>{children}</div>;
}

/* ─── Trigger Config Panel ───────────────────────────────────────
   Edits the automation's trigger directly from the trigger node on the canvas.
   trigger_type / trigger_config remain the saved source of truth. */
function TriggerConfigPanel({ automation, onTypeChange, onConfigChange, webhookUrl, copied, onCopy, onGenerateToken, generatingToken, onClose }) {
    const { t } = useTranslation();
    const triggerType = automation.trigger_type ?? '';
    const orderTokens = triggerType === 'cart.abandoned'
        ? ['{{context.cart_total}}', '{{context.recovery_url}}', '{{context.order_currency}}']
        : ['{{context.order_number}}', '{{context.order_total}}', '{{context.order_currency}}', '{{context.tracking_url}}', '{{context.store_name}}'];

    return (
        <div style={{
            position: 'absolute', top: 0, right: 0, bottom: 0, width: 320,
            background: '#ffffff', borderLeft: '1px solid #e5e5e5',
            boxShadow: '-4px 0 24px rgba(0,0,0,0.08)',
            zIndex: 10, display: 'flex', flexDirection: 'column',
            borderRadius: '0 0 12px 0',
        }}>
            {/* Header */}
            <div style={{ background: '#fafafa', borderBottom: '1px solid #e5e5e5', padding: '14px 16px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <Zap size={15} color="#2563eb" />
                    <span style={{ color: '#171717', fontWeight: 700, fontSize: 13 }}>{t('automation.trigger')}</span>
                </div>
                <button onClick={onClose} style={{ color: '#71717a', background: 'none', border: 'none', cursor: 'pointer', display: 'flex' }}>
                    <X size={16} />
                </button>
            </div>

            {/* Fields */}
            <div style={{ flex: 1, overflowY: 'auto', padding: 16 }} className="space-y-4">
                <p style={{ fontSize: 11, color: '#71717a' }}>{t('automation.trigger_panel_intro')}</p>

                <Field label={t('automation.trigger')}>
                    <select className={selectCls} value={triggerType} onChange={e => onTypeChange(e.target.value)}>
                        <option value="">{t('automation.select_trigger')}</option>
                        {TRIGGER_TYPES.map(tr => <option key={tr.value} value={tr.value}>{t(tr.labelKey)}</option>)}
                    </select>
                </Field>

                {triggerType === 'message.received' && (
                    <Field label={t('automation.keyword_filter_optional')}>
                        <p style={{ fontSize: 10, color: '#64748b', marginBottom: 6 }}>{t('automation.keyword_filter_hint')}</p>
                        <input
                            className={inputCls}
                            value={(automation.trigger_config?.keywords ?? []).join(', ')}
                            onChange={e => onConfigChange({ keywords: e.target.value.split(',').map(k => k.trim()).filter(Boolean) })}
                            placeholder={t('automation.placeholder_keywords')}
                        />
                    </Field>
                )}

                {triggerType === 'webhook.received' && (
                    <Field label={t('automation.webhook_url')}>
                        {webhookUrl ? (
                            <div style={{ display: 'flex', gap: 4, alignItems: 'center' }}>
                                <input readOnly className={inputCls} value={webhookUrl} style={{ fontFamily: 'monospace', fontSize: 10 }} />
                                <button onClick={onCopy} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#2563eb', display: 'flex', padding: 4 }}>
                                    {copied ? <Check size={14} color="#10b981" /> : <Copy size={14} />}
                                </button>
                            </div>
                        ) : (
                            <p style={{ fontSize: 11, color: '#64748b' }}>{t('automation.no_token_yet')}</p>
                        )}
                        <button onClick={onGenerateToken} disabled={generatingToken} style={{ marginTop: 8, display: 'flex', alignItems: 'center', gap: 4, fontSize: 11, color: '#2563eb', background: 'none', border: 'none', cursor: 'pointer', padding: 0 }}>
                            <RefreshCw size={11} style={{ animation: generatingToken ? 'spin 1s linear infinite' : 'none' }} />
                            {automation.trigger_token ? t('automation.regenerate_token') : t('automation.generate_token')}
                        </button>
                    </Field>
                )}

                {['order.placed', 'order.fulfilled', 'order.cancelled', 'cart.abandoned'].includes(triggerType) && (
                    <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8, padding: '8px 10px' }}>
                        <p style={{ fontSize: 10, fontWeight: 700, color: '#64748b', marginBottom: 4 }}>{t('automation.available_tokens_heading')}</p>
                        <p style={{ fontSize: 10, color: '#94a3b8', marginBottom: 6 }}>{t('automation.available_tokens_hint')}</p>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
                            {orderTokens.map(tok => (
                                <code key={tok} style={{ fontSize: 9, color: '#475569', background: '#e2e8f0', borderRadius: 4, padding: '2px 5px' }}>{tok}</code>
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Drawer Footer: Save Action */}
            <div style={{
                padding: '12px 16px', borderTop: '1px solid #e5e5e5',
                background: '#fafafa', display: 'flex', gap: 8, alignItems: 'center'
            }}>
                <button
                    onClick={onClose}
                    style={{
                        width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6,
                        background: '#2563eb', color: '#ffffff', border: '1px solid #2563eb', borderRadius: 10,
                        padding: '9px 0', fontSize: 12, fontWeight: 700, cursor: 'pointer',
                        boxShadow: '0 2px 6px rgba(37,99,235,0.25)', transition: 'all 0.15s'
                    }}
                >
                    <Check size={14} /> {t('common.save')}
                </button>
            </div>
        </div>
    );
}

function CheckField({ label, checked, onChange }) {
    return (
        <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12, color: '#374151', cursor: 'pointer' }}>
            <input type="checkbox" checked={!!checked} onChange={e => onChange(e.target.checked)} style={{ width: 14, height: 14 }} />
            {label}
        </label>
    );
}

function ChannelSelect({ d, set, imageOnlyHint = false }) {
    const { t } = useTranslation();
    const ch = d.channel ?? 'whatsapp';
    return (
        <>
            <Field label={t('automation.field_channel')}>
                <select className={selectCls} value={ch} onChange={e => set('channel', e.target.value)}>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="messenger">Messenger</option>
                    <option value="instagram">Instagram</option>
                    <option value="sms">SMS</option>
                </select>
            </Field>
            {(ch === 'messenger' || ch === 'instagram') && (
                <div style={{ display: 'flex', gap: 6, background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: 8, padding: '8px 10px', fontSize: 10, color: '#1e40af' }}>
                    <AlertTriangle size={14} style={{ flexShrink: 0, marginTop: 1 }} />
                    <span>{imageOnlyHint ? t('automation.channel_meta_image_hint') : t('automation.channel_meta_hint')}</span>
                </div>
            )}
        </>
    );
}

function GoogleWarning() {
    const { t } = useTranslation();
    const r = useResources();
    if (r.integrations?.google) return null;
    return (
        <div style={{ display: 'flex', gap: 6, background: '#fffbeb', border: '1px solid #fde68a', borderRadius: 8, padding: '8px 10px', fontSize: 10, color: '#92400e' }}>
            <AlertTriangle size={14} style={{ flexShrink: 0, marginTop: 1 }} />
            <span>{t('automation.google_not_configured')}</span>
        </div>
    );
}

/* ─── Field components ────────────────────────────────────────── */

function WhatsAppFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_message_body_required')}>
                <textarea className={textareaCls} rows={4} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_whatsapp_body', { token: '{{contact.name}}' })} />
            </Field>
            <ChannelSelect d={d} set={set} />
        </>
    );
}

function SmsFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <Field label={t('automation.field_message_body_required')}>
            <textarea className={textareaCls} rows={4} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_sms_body', { token: '{{contact.name}}' })} />
        </Field>
    );
}

function EmailFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_subject_required')}>
                <input className={inputCls} value={d.subject ?? ''} onChange={e => set('subject', e.target.value)} placeholder={t('automation.placeholder_email_subject')} />
            </Field>
            <Field label={t('automation.field_from_name_optional')}>
                <input className={inputCls} value={d.from_name ?? ''} onChange={e => set('from_name', e.target.value)} placeholder={t('automation.placeholder_from_name')} />
            </Field>
            <Field label={t('automation.field_body_required')}>
                <textarea className={textareaCls} rows={6} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_email_body', { token: '{{contact.name}}' })} />
            </Field>
        </>
    );
}

function templateBodyText(components) {
    const body = (Array.isArray(components) ? components : []).find(c => (c.type || '').toUpperCase() === 'BODY');
    return body?.text || '';
}

function templateBodyVarCount(components) {
    const text = templateBodyText(components);
    const matches = text.match(/\{\{\s*(\d+)\s*\}\}/g) || [];
    return matches.reduce((max, m) => Math.max(max, parseInt(m.replace(/[^\d]/g, ''), 10) || 0), 0);
}

function TemplateFields({ d, set }) {
    const { t } = useTranslation();
    const { templates = [] } = useResources();
    const tpl = templates.find(x => x.name === d.template_name && x.language === d.language)
        || templates.find(x => x.name === d.template_name);
    const varCount = tpl ? templateBodyVarCount(tpl.components) : 0;
    const vars = Array.isArray(d.variables)
        ? d.variables
        : (typeof d.variables === 'string' && d.variables ? d.variables.split('\n') : []);

    const onPick = (val) => {
        const [name, language] = val.split('||');
        set('template_name', name);
        set('language', language || 'en');
        set('variables', []); // reset on template change so indices match the new body
    };
    const setVar = (i, val) => {
        const next = Array.from({ length: varCount }, (_, idx) => vars[idx] ?? '');
        next[i] = val;
        set('variables', next);
    };

    return (
        <>
            <Field label={t('automation.field_template_required')}>
                {templates.length ? (
                    <select className={selectCls} value={tpl ? `${tpl.name}||${tpl.language}` : ''} onChange={e => onPick(e.target.value)}>
                        <option value="">{t('automation.select_template')}</option>
                        {templates.map(x => (
                            <option key={`${x.name}-${x.language}`} value={`${x.name}||${x.language}`}>
                                {x.name} ({x.language}){x.status && x.status !== 'APPROVED' ? ` · ${x.status}` : ''}
                            </option>
                        ))}
                    </select>
                ) : (
                    <input className={inputCls} value={d.template_name ?? ''} onChange={e => set('template_name', e.target.value)} placeholder="my_template_name" />
                )}
            </Field>

            {!templates.length && (
                <Field label={t('automation.field_language')}>
                    <input className={inputCls} value={d.language ?? 'en'} onChange={e => set('language', e.target.value)} placeholder="en" />
                </Field>
            )}

            {tpl && tpl.status && tpl.status !== 'APPROVED' && (
                <div style={{ display: 'flex', gap: 6, background: '#fffbeb', border: '1px solid #fde68a', borderRadius: 8, padding: '8px 10px', fontSize: 10, color: '#92400e' }}>
                    <AlertTriangle size={14} style={{ flexShrink: 0, marginTop: 1 }} />
                    <span>{t('automation.template_not_approved', { status: tpl.status })}</span>
                </div>
            )}

            {tpl && templateBodyText(tpl.components) && (
                <div>
                    <label className={labelCls}>{t('automation.template_preview')}</label>
                    <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8, padding: '8px 10px', fontSize: 11, color: '#475569', whiteSpace: 'pre-wrap' }}>
                        {templateBodyText(tpl.components)}
                    </div>
                </div>
            )}

            {varCount > 0 && Array.from({ length: varCount }).map((_, i) => (
                <Field key={i} label={t('automation.template_var_n', { n: i + 1 })}>
                    <input className={inputCls} value={vars[i] ?? ''} onChange={e => setVar(i, e.target.value)} placeholder={t('automation.template_var_placeholder', { n: i + 1 })} />
                </Field>
            ))}

            {!tpl && templates.length === 0 && (
                <Field label={t('automation.field_template_vars_optional')}>
                    <textarea className={textareaCls} rows={3} value={typeof d.variables === 'string' ? d.variables : ''} onChange={e => set('variables', e.target.value)} placeholder={t('automation.placeholder_template_vars')} />
                </Field>
            )}
        </>
    );
}

function mediaAccept(type) {
    return type === 'video' ? 'video/*'
        : type === 'audio' ? 'audio/*'
            : type === 'document' ? '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv'
                : 'image/*';
}

function MediaFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_media_type')}>
                <select className={selectCls} value={d.media_type ?? 'image'} onChange={e => set('media_type', e.target.value)}>
                    <option value="image">{t('automation.media_image')}</option>
                    <option value="video">{t('automation.media_video')}</option>
                    <option value="document">{t('automation.media_document')}</option>
                    <option value="audio">{t('automation.media_audio')}</option>
                </select>
            </Field>
            <MediaUpload
                label={t('automation.field_media_required')}
                value={d.link ?? ''}
                onChange={url => set('link', url)}
                accept={mediaAccept(d.media_type)}
                collection="automation"
                placeholder="https://example.com/file.pdf"
            />
            {d.media_type !== 'audio' && (
                <Field label={t('automation.field_caption_optional')}>
                    <textarea className={textareaCls} rows={2} value={d.caption ?? ''} onChange={e => set('caption', e.target.value)} placeholder={t('automation.placeholder_caption')} />
                </Field>
            )}
            {d.media_type === 'document' && (
                <Field label={t('automation.field_filename_optional')}>
                    <input className={inputCls} value={d.filename ?? ''} onChange={e => set('filename', e.target.value)} placeholder="invoice.pdf" />
                </Field>
            )}
            <ChannelSelect d={d} set={set} imageOnlyHint />
        </>
    );
}

function SequenceFields({ d, set }) {
    const { t } = useTranslation();
    const steps = Array.isArray(d.steps) ? d.steps : [];
    const update = (i, patch) => set('steps', steps.map((s, idx) => idx === i ? { ...s, ...patch } : s));
    const add = (kind) => set('steps', [...steps, kind === 'media' ? { kind: 'media', media_type: 'image', link: '', caption: '' } : { kind: 'text', body: '' }]);
    const remove = (i) => set('steps', steps.filter((_, idx) => idx !== i));

    return (
        <>
            <p style={{ fontSize: 10, color: '#64748b' }}>{t('automation.sequence_hint')}</p>
            {steps.map((s, i) => (
                <div key={i} style={{ border: '1px solid #e5e7eb', borderRadius: 8, padding: 8 }} className="space-y-2">
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                        <span style={{ fontSize: 10, fontWeight: 700, color: '#64748b' }}>{t('automation.step_n', { n: i + 1 })}</span>
                        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                            <select className="rounded border border-gray-200 text-[10px] px-1 py-0.5" value={s.kind ?? 'text'} onChange={e => update(i, { kind: e.target.value })}>
                                <option value="text">{t('automation.step_text')}</option>
                                <option value="media">{t('automation.step_media')}</option>
                            </select>
                            <button onClick={() => remove(i)} style={{ color: '#dc2626', background: 'none', border: 'none', cursor: 'pointer', display: 'flex' }}><Trash2 size={13} /></button>
                        </div>
                    </div>
                    {s.kind === 'media' ? (
                        <>
                            <select className={selectCls} value={s.media_type ?? 'image'} onChange={e => update(i, { media_type: e.target.value })}>
                                <option value="image">{t('automation.media_image')}</option>
                                <option value="video">{t('automation.media_video')}</option>
                                <option value="document">{t('automation.media_document')}</option>
                                <option value="audio">{t('automation.media_audio')}</option>
                            </select>
                            <MediaUpload
                                value={s.link ?? ''}
                                onChange={url => update(i, { link: url })}
                                accept={mediaAccept(s.media_type)}
                                collection="automation"
                                placeholder="https://example.com/file.jpg"
                            />
                            <input className={inputCls} value={s.caption ?? ''} onChange={e => update(i, { caption: e.target.value })} placeholder={t('automation.field_caption_optional')} />
                        </>
                    ) : (
                        <textarea className={textareaCls} rows={2} value={s.body ?? ''} onChange={e => update(i, { body: e.target.value })} placeholder={t('automation.placeholder_whatsapp_body', { token: '{{contact.name}}' })} />
                    )}
                </div>
            ))}
            <div style={{ display: 'flex', gap: 6 }}>
                <button onClick={() => add('text')} style={addBtnStyle}><Plus size={11} /> {t('automation.step_text')}</button>
                <button onClick={() => add('media')} style={addBtnStyle}><Plus size={11} /> {t('automation.step_media')}</button>
            </div>
        </>
    );
}

const addBtnStyle = {
    display: 'flex', alignItems: 'center', gap: 4, flex: 1, justifyContent: 'center',
    border: '1px dashed #cbd5e1', borderRadius: 8, padding: '6px 0', fontSize: 11,
    fontWeight: 600, color: '#475569', background: '#f8fafc', cursor: 'pointer',
};

function QuickRepliesFields({ d, set }) {
    const { t } = useTranslation();
    const buttons = Array.isArray(d.buttons) ? d.buttons : ['', '', ''];
    const setBtn = (i, val) => {
        const next = [...buttons];
        next[i] = val;
        set('buttons', next);
    };
    return (
        <>
            <Field label={t('automation.field_message_body_required')}>
                <textarea className={textareaCls} rows={3} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_quick_replies_body')} />
            </Field>
            {[0, 1, 2].map(i => (
                <Field key={i} label={t('automation.field_button_n', { n: i + 1 })}>
                    <input className={inputCls} maxLength={20} value={buttons[i] ?? ''} onChange={e => setBtn(i, e.target.value)} placeholder={i === 0 ? t('automation.placeholder_button_required') : t('automation.placeholder_button_optional')} />
                </Field>
            ))}
        </>
    );
}

function ListMessageFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_message_body_required')}>
                <textarea className={textareaCls} rows={3} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_list_body')} />
            </Field>
            <Field label={t('automation.field_list_button')}>
                <input className={inputCls} maxLength={20} value={d.button_label ?? ''} onChange={e => set('button_label', e.target.value)} placeholder={t('automation.placeholder_list_button')} />
            </Field>
            <Field label={t('automation.field_section_title_optional')}>
                <input className={inputCls} maxLength={24} value={d.section_title ?? ''} onChange={e => set('section_title', e.target.value)} placeholder={t('automation.placeholder_section_title')} />
            </Field>
            <Field label={t('automation.field_list_rows_required')}>
                <textarea className={textareaCls} rows={4} value={d.rows ?? ''} onChange={e => set('rows', e.target.value)} placeholder={t('automation.placeholder_list_rows')} />
            </Field>
        </>
    );
}

function AskQuestionFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_question_required')}>
                <textarea className={textareaCls} rows={3} value={d.question ?? ''} onChange={e => set('question', e.target.value)} placeholder={t('automation.placeholder_question')} />
            </Field>
            <Field label={t('automation.field_save_to_var_required')}>
                <input className={inputCls} value={d.variable ?? 'answer'} onChange={e => set('variable', e.target.value)} placeholder="answer" />
            </Field>
            <ChannelSelect d={d} set={set} />
            <div style={{ background: '#fff7ed', border: '1px solid #fed7aa', borderRadius: 8, padding: '8px 10px', fontSize: 10, color: '#9a3412' }}>
                {t('automation.ask_question_hint', { var: `{{context.${d.variable || 'answer'}}}` })}
            </div>
        </>
    );
}

function WaitFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <div className="flex gap-2">
            <div className="flex-1">
                <label className={labelCls}>{t('automation.field_amount_required')}</label>
                <input type="number" min={1} className={inputCls} value={d.amount ?? ''} onChange={e => set('amount', e.target.value)} placeholder="1" />
            </div>
            <div className="flex-1">
                <label className={labelCls}>{t('automation.field_unit')}</label>
                <select className={selectCls} value={d.unit ?? 'minutes'} onChange={e => set('unit', e.target.value)}>
                    <option value="minutes">{t('automation.unit_minutes')}</option>
                    <option value="hours">{t('automation.unit_hours')}</option>
                    <option value="days">{t('automation.unit_days')}</option>
                </select>
            </div>
        </div>
    );
}

function ConditionFields({ d, set }) {
    const { t } = useTranslation();
    const noValue = d.operator === 'exists' || d.operator === 'not_exists';
    return (
        <>
            <Field label={t('automation.field_check_field_required')}>
                <select className={selectCls} value={d.field ?? ''} onChange={e => set('field', e.target.value)}>
                    <option value="">{t('automation.select_field')}</option>
                    {CONDITION_FIELDS.map(f => <option key={f.value} value={f.value}>{t(f.labelKey)}</option>)}
                </select>
            </Field>
            <Field label={t('automation.field_operator')}>
                <select className={selectCls} value={d.operator ?? 'equals'} onChange={e => set('operator', e.target.value)}>
                    {CONDITION_OPERATORS.map(o => <option key={o.value} value={o.value}>{t(o.labelKey)}</option>)}
                </select>
            </Field>
            {!noValue && (
                <Field label={t('automation.field_value_required')}>
                    <input className={inputCls} value={d.value ?? ''} onChange={e => set('value', e.target.value)} placeholder={t('automation.placeholder_compare_value')} />
                </Field>
            )}
            <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: 8, padding: '8px 10px', fontSize: 10, color: '#166534' }}>
                <strong>✓ {t('common.yes')}</strong> — {t('automation.condition_yes_hint')}<br />
                <strong>✗ {t('common.no')}</strong> — {t('automation.condition_no_hint')}
            </div>
        </>
    );
}

function TagFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <Field label={t('automation.field_tag_name_required')}>
            <input className={inputCls} value={d.tag ?? ''} onChange={e => set('tag', e.target.value)} placeholder={t('automation.placeholder_tag_name')} />
        </Field>
    );
}

function UpdateContactFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_field_to_update_required')}>
                <select className={selectCls} value={d.field ?? ''} onChange={e => set('field', e.target.value)}>
                    <option value="">{t('automation.select_field')}</option>
                    {UPDATE_FIELDS.map(f => <option key={f.value} value={f.value}>{t(f.labelKey)}</option>)}
                </select>
            </Field>
            <Field label={t('automation.field_new_value_required')}>
                <input className={inputCls} value={d.value ?? ''} onChange={e => set('value', e.target.value)} placeholder={t('automation.placeholder_new_value', { token: '{{token}}' })} />
            </Field>
        </>
    );
}

function CampaignFields({ d, set }) {
    const { t } = useTranslation();
    const { campaigns = [] } = useResources();
    return (
        <Field label={t('automation.field_campaign_required')}>
            {campaigns.length ? (
                <select className={selectCls} value={d.campaign_id ?? ''} onChange={e => set('campaign_id', e.target.value)}>
                    <option value="">{t('automation.select_campaign')}</option>
                    {campaigns.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
            ) : (
                <input type="number" className={inputCls} value={d.campaign_id ?? ''} onChange={e => set('campaign_id', e.target.value)} placeholder="123" />
            )}
        </Field>
    );
}

function SubflowFields({ d, set }) {
    const { t } = useTranslation();
    const { subflows = [] } = useResources();
    const pick = (uuid) => {
        set('automation_uuid', uuid);
        set('subflow_name', subflows.find(s => s.uuid === uuid)?.name ?? '');
    };
    return (
        <Field label={t('automation.field_subflow_required')}>
            {subflows.length ? (
                <select className={selectCls} value={d.automation_uuid ?? ''} onChange={e => pick(e.target.value)}>
                    <option value="">{t('automation.select_subflow')}</option>
                    {subflows.map(s => <option key={s.uuid} value={s.uuid}>{s.name}{s.status !== 'active' ? ` (${s.status})` : ''}</option>)}
                </select>
            ) : (
                <p style={{ fontSize: 11, color: '#94a3b8' }}>{t('automation.no_subflows')}</p>
            )}
        </Field>
    );
}

function AIReplyFields({ d, set }) {
    const { t } = useTranslation();
    const { chatbots = [] } = useResources();
    return (
        <>
            <Field label={t('automation.field_chatbot_optional')}>
                <select className={selectCls} value={d.chatbot_id ?? ''} onChange={e => set('chatbot_id', e.target.value)}>
                    <option value="">{t('automation.ai_use_prompt')}</option>
                    {chatbots.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
            </Field>
            <Field label={d.chatbot_id ? t('automation.field_prompt_optional') : t('automation.field_prompt_instructions_required')}>
                <textarea className={textareaCls} rows={5} value={d.prompt ?? ''} onChange={e => set('prompt', e.target.value)} placeholder={t('automation.placeholder_ai_prompt')} />
            </Field>
            <ChannelSelect d={d} set={set} />
        </>
    );
}

function RunChatbotFields({ d, set }) {
    const { t } = useTranslation();
    const { chatbots = [] } = useResources();
    return (
        <>
            <Field label={t('automation.field_chatbot_required')}>
                {chatbots.length ? (
                    <select className={selectCls} value={d.chatbot_id ?? ''} onChange={e => set('chatbot_id', e.target.value)}>
                        <option value="">{t('automation.select_chatbot')}</option>
                        {chatbots.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                ) : (
                    <p style={{ fontSize: 11, color: '#94a3b8' }}>{t('automation.no_chatbots')}</p>
                )}
            </Field>
            <Field label={t('automation.field_prompt_optional')}>
                <textarea className={textareaCls} rows={3} value={d.prompt ?? ''} onChange={e => set('prompt', e.target.value)} placeholder={t('automation.placeholder_chatbot_prompt')} />
            </Field>
            <ChannelSelect d={d} set={set} />
        </>
    );
}

function AssignAgentFields({ d, set }) {
    const { t } = useTranslation();
    const { agents = [] } = useResources();
    const pick = (id) => {
        set('user_id', id);
        set('agent_name', agents.find(a => String(a.id) === String(id))?.name ?? '');
    };
    return (
        <Field label={t('automation.field_assign_to')}>
            <select className={selectCls} value={d.user_id ?? ''} onChange={e => pick(e.target.value)}>
                <option value="">{t('automation.assign_unassigned')}</option>
                {agents.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
            </select>
        </Field>
    );
}

function CtaButtonFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_message_body_required')}>
                <textarea className={textareaCls} rows={3} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_cta_body')} />
            </Field>
            <Field label={t('automation.field_button_text_required')}>
                <input className={inputCls} maxLength={20} value={d.display_text ?? ''} onChange={e => set('display_text', e.target.value)} placeholder={t('automation.placeholder_button_text')} />
            </Field>
            <Field label={t('automation.field_url_required')}>
                <input className={inputCls} value={d.url ?? ''} onChange={e => set('url', e.target.value)} placeholder="https://example.com" />
            </Field>
        </>
    );
}

function LocationFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <div className="flex gap-2">
                <div className="flex-1">
                    <label className={labelCls}>{t('automation.field_latitude_required')}</label>
                    <input className={inputCls} value={d.latitude ?? ''} onChange={e => set('latitude', e.target.value)} placeholder="37.4220" />
                </div>
                <div className="flex-1">
                    <label className={labelCls}>{t('automation.field_longitude_required')}</label>
                    <input className={inputCls} value={d.longitude ?? ''} onChange={e => set('longitude', e.target.value)} placeholder="-122.0841" />
                </div>
            </div>
            <Field label={t('automation.field_place_name_optional')}>
                <input className={inputCls} value={d.name ?? ''} onChange={e => set('name', e.target.value)} placeholder={t('automation.placeholder_place_name')} />
            </Field>
            <Field label={t('automation.field_address_optional')}>
                <input className={inputCls} value={d.address ?? ''} onChange={e => set('address', e.target.value)} placeholder={t('automation.placeholder_address')} />
            </Field>
        </>
    );
}

function PollFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_question_required')}>
                <textarea className={textareaCls} rows={2} value={d.question ?? ''} onChange={e => set('question', e.target.value)} placeholder={t('automation.placeholder_poll_question')} />
            </Field>
            <Field label={t('automation.field_poll_options_required')}>
                <textarea className={textareaCls} rows={4} value={d.options ?? ''} onChange={e => set('options', e.target.value)} placeholder={t('automation.placeholder_poll_options')} />
            </Field>
            <p style={{ fontSize: 10, color: '#64748b' }}>{t('automation.poll_hint')}</p>
        </>
    );
}

function BookAppointmentFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <GoogleWarning />
            <Field label={t('automation.field_summary_required')}>
                <input className={inputCls} value={d.summary ?? ''} onChange={e => set('summary', e.target.value)} placeholder={t('automation.placeholder_appointment_summary')} />
            </Field>
            <Field label={t('automation.field_start_required')}>
                <input className={inputCls} value={d.start ?? ''} onChange={e => set('start', e.target.value)} placeholder="2026-07-01 14:00 or {{context.start}}" />
            </Field>
            <div className="flex gap-2">
                <div className="flex-1">
                    <label className={labelCls}>{t('automation.field_duration_min')}</label>
                    <input type="number" min={1} className={inputCls} value={d.duration_minutes ?? ''} onChange={e => set('duration_minutes', e.target.value)} placeholder="30" />
                </div>
                <div className="flex-1">
                    <label className={labelCls}>{t('automation.field_timezone_optional')}</label>
                    <input className={inputCls} value={d.timezone ?? ''} onChange={e => set('timezone', e.target.value)} placeholder="UTC" />
                </div>
            </div>
            <Field label={t('automation.field_calendar_id_optional')}>
                <input className={inputCls} value={d.calendar_id ?? ''} onChange={e => set('calendar_id', e.target.value)} placeholder="primary" />
            </Field>
            <CheckField label={t('automation.field_send_confirmation')} checked={d.send_confirmation} onChange={v => set('send_confirmation', v)} />
        </>
    );
}

function GoogleMeetFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <GoogleWarning />
            <Field label={t('automation.field_summary_required')}>
                <input className={inputCls} value={d.summary ?? ''} onChange={e => set('summary', e.target.value)} placeholder={t('automation.placeholder_meeting_summary')} />
            </Field>
            <Field label={t('automation.field_start_required')}>
                <input className={inputCls} value={d.start ?? ''} onChange={e => set('start', e.target.value)} placeholder="2026-07-01 14:00 or {{context.start}}" />
            </Field>
            <div className="flex gap-2">
                <div className="flex-1">
                    <label className={labelCls}>{t('automation.field_duration_min')}</label>
                    <input type="number" min={1} className={inputCls} value={d.duration_minutes ?? ''} onChange={e => set('duration_minutes', e.target.value)} placeholder="30" />
                </div>
                <div className="flex-1">
                    <label className={labelCls}>{t('automation.field_timezone_optional')}</label>
                    <input className={inputCls} value={d.timezone ?? ''} onChange={e => set('timezone', e.target.value)} placeholder="UTC" />
                </div>
            </div>
            <CheckField label={t('automation.field_send_link')} checked={d.send_link ?? true} onChange={v => set('send_link', v)} />
        </>
    );
}

function WhatsappFormFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_flow_id_required')}>
                <input className={inputCls} value={d.flow_id ?? ''} onChange={e => set('flow_id', e.target.value)} placeholder="1234567890" />
            </Field>
            <Field label={t('automation.field_message_body_required')}>
                <textarea className={textareaCls} rows={3} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_form_body')} />
            </Field>
            <Field label={t('automation.field_flow_cta')}>
                <input className={inputCls} maxLength={20} value={d.flow_cta ?? ''} onChange={e => set('flow_cta', e.target.value)} placeholder={t('automation.placeholder_flow_cta')} />
            </Field>
            <Field label={t('automation.field_flow_screen_optional')}>
                <input className={inputCls} value={d.screen ?? ''} onChange={e => set('screen', e.target.value)} placeholder="WELCOME_SCREEN" />
            </Field>
        </>
    );
}

function WhatsappCatalogFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_message_body_required')}>
                <textarea className={textareaCls} rows={3} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_catalog_body')} />
            </Field>
            <Field label={t('automation.field_thumbnail_product_optional')}>
                <input className={inputCls} value={d.thumbnail_product_retailer_id ?? ''} onChange={e => set('thumbnail_product_retailer_id', e.target.value)} placeholder="SKU_123" />
            </Field>
        </>
    );
}

function ProductFields({ d, set, platform }) {
    const { t } = useTranslation();
    const { stores = [] } = useResources();
    const platformStores = stores.filter(s => s.platform === platform);
    return (
        <>
            <Field label={t('automation.field_store_required')}>
                {platformStores.length ? (
                    <select className={selectCls} value={d.store_id ?? ''} onChange={e => set('store_id', e.target.value)}>
                        <option value="">{t('automation.select_store')}</option>
                        {platformStores.map(s => <option key={s.id} value={s.id}>{s.name || s.platform}</option>)}
                    </select>
                ) : (
                    <p style={{ fontSize: 11, color: '#94a3b8' }}>{t('automation.no_stores', { platform })}</p>
                )}
            </Field>
            <Field label={t('automation.field_product_id_required')}>
                <input className={inputCls} value={d.product_id ?? ''} onChange={e => set('product_id', e.target.value)} placeholder={t('automation.placeholder_product_id')} />
            </Field>
            <Field label={t('automation.field_intro_text_optional')}>
                <textarea className={textareaCls} rows={2} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_product_intro')} />
            </Field>
        </>
    );
}

function GoogleSheetsFields({ d, set }) {
    const { t } = useTranslation();
    const mode = d.mode ?? 'append';
    return (
        <>
            <GoogleWarning />
            <Field label={t('automation.field_sheets_mode')}>
                <select className={selectCls} value={mode} onChange={e => set('mode', e.target.value)}>
                    <option value="append">{t('automation.sheets_append')}</option>
                    <option value="read">{t('automation.sheets_read')}</option>
                </select>
            </Field>
            <Field label={t('automation.field_spreadsheet_id_required')}>
                <input className={inputCls} value={d.spreadsheet_id ?? ''} onChange={e => set('spreadsheet_id', e.target.value)} placeholder="1AbC...xyz" />
            </Field>
            <Field label={t('automation.field_range_required')}>
                <input className={inputCls} value={d.range ?? ''} onChange={e => set('range', e.target.value)} placeholder="Sheet1!A:D" />
            </Field>
            {mode === 'append' ? (
                <Field label={t('automation.field_row_values_required')}>
                    <textarea className={textareaCls} rows={4} value={d.values ?? ''} onChange={e => set('values', e.target.value)} placeholder={t('automation.placeholder_row_values')} />
                </Field>
            ) : (
                <Field label={t('automation.field_result_var')}>
                    <input className={inputCls} value={d.result_var ?? ''} onChange={e => set('result_var', e.target.value)} placeholder="sheet" />
                </Field>
            )}
        </>
    );
}

function GoogleDocsFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <GoogleWarning />
            <Field label={t('automation.field_template_doc_id_required')}>
                <input className={inputCls} value={d.template_doc_id ?? ''} onChange={e => set('template_doc_id', e.target.value)} placeholder="1AbC...xyz" />
            </Field>
            <Field label={t('automation.field_doc_title')}>
                <input className={inputCls} value={d.title ?? ''} onChange={e => set('title', e.target.value)} placeholder={t('automation.placeholder_doc_title', { token: '{{contact.name}}' })} />
            </Field>
            <Field label={t('automation.field_replacements_optional')}>
                <textarea className={textareaCls} rows={4} value={d.replacements ?? ''} onChange={e => set('replacements', e.target.value)} placeholder={t('automation.placeholder_replacements')} />
            </Field>
            <CheckField label={t('automation.field_send_doc_link')} checked={d.send_link} onChange={v => set('send_link', v)} />
        </>
    );
}

function GoogleFormsFields({ d, set }) {
    const { t } = useTranslation();
    const mode = d.mode ?? 'send_link';
    return (
        <>
            <GoogleWarning />
            <Field label={t('automation.field_forms_mode')}>
                <select className={selectCls} value={mode} onChange={e => set('mode', e.target.value)}>
                    <option value="send_link">{t('automation.forms_send_link')}</option>
                    <option value="read_response">{t('automation.forms_read_response')}</option>
                </select>
            </Field>
            <Field label={t('automation.field_form_id_required')}>
                <input className={inputCls} value={d.form_id ?? ''} onChange={e => set('form_id', e.target.value)} placeholder="1AbC...xyz" />
            </Field>
            {mode === 'send_link' ? (
                <>
                    <Field label={t('automation.field_message_body_optional')}>
                        <textarea className={textareaCls} rows={2} value={d.body ?? ''} onChange={e => set('body', e.target.value)} placeholder={t('automation.placeholder_form_intro')} />
                    </Field>
                    <CheckField label={t('automation.field_send_form_link')} checked={d.send_link ?? true} onChange={v => set('send_link', v)} />
                </>
            ) : (
                <Field label={t('automation.field_result_var')}>
                    <input className={inputCls} value={d.result_var ?? ''} onChange={e => set('result_var', e.target.value)} placeholder="form" />
                </Field>
            )}
        </>
    );
}

function WebhookFields({ d, set }) {
    const { t } = useTranslation();
    return (
        <>
            <Field label={t('automation.field_url_required')}>
                <input className={inputCls} value={d.url ?? ''} onChange={e => set('url', e.target.value)} placeholder="https://example.com/webhook" />
            </Field>
            <Field label={t('automation.field_method')}>
                <select className={selectCls} value={d.method ?? 'POST'} onChange={e => set('method', e.target.value)}>
                    <option>POST</option>
                    <option>GET</option>
                    <option>PUT</option>
                    <option>PATCH</option>
                </select>
            </Field>
            <Field label={t('automation.field_headers_json_optional')}>
                <textarea className={textareaCls} rows={3} value={d.headers ?? ''} onChange={e => set('headers', e.target.value)} placeholder={'{"Authorization": "Bearer {{token}}"}'} />
            </Field>
            <Field label={t('automation.field_payload_json_optional')}>
                <textarea className={textareaCls} rows={4} value={d.payload ?? ''} onChange={e => set('payload', e.target.value)} placeholder={'{"contact_id": "{{contact.id}}"}'} />
            </Field>
        </>
    );
}

/* ─── Edge defaults ──────────────────────────────────────────── */
const defaultEdgeOptions = {
    animated: true,
    style: { stroke: '#6366f1', strokeWidth: 2 },
    markerEnd: { type: MarkerType.ArrowClosed, color: '#6366f1' },
};

/* ─── Builder Inner ──────────────────────────────────────────── */
function makeNode(type, existingCount, position) {
    const id = `${type}-${Date.now()}`;
    return {
        id,
        type: 'automationNode',
        position: position ?? { x: 300, y: 200 + existingCount * 100 },
        data: { nodeType: type, label: '', configured: false },
    };
}

function serializeNodes(nodes) {
    return nodes.map(n => ({
        id: n.id,
        type: n.data?.nodeType ?? n.type,
        position: n.position,
        data: n.data,
    }));
}

const TRIGGER_NODE_ID = 'trigger-1';

function deserializeNodes(raw) {
    return (raw ?? []).map(n => {
        // The trigger node is seeded by the backend as type 'trigger'; older saves use 'triggerNode'.
        if (n.data?.triggerType !== undefined || n.type === 'triggerNode' || n.type === 'trigger') {
            return { ...n, type: 'triggerNode', deletable: false, data: { ...n.data } };
        }
        return {
            ...n,
            type: 'automationNode',
            data: { ...n.data, nodeType: n.data?.nodeType ?? n.type },
        };
    });
}

// Guarantee a single, non-deletable trigger node anchored at the top of the canvas, with its
// display synced to the automation's trigger_type (the backend source of truth).
function withTriggerNode(nodes, triggerType) {
    if (!nodes.some(n => n.type === 'triggerNode')) {
        return [
            { id: TRIGGER_NODE_ID, type: 'triggerNode', position: { x: 250, y: 50 }, deletable: false, data: { triggerType, label: 'Trigger' } },
            ...nodes,
        ];
    }
    return nodes.map(n => n.type === 'triggerNode'
        ? { ...n, deletable: false, data: { ...n.data, triggerType } }
        : n);
}

/* ─── Test & AI Generate modals ──────────────────────────────── */
const overlayStyle = { position: 'fixed', inset: 0, zIndex: 60, background: 'rgba(0,0,0,0.5)', backdropFilter: 'blur(8px)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 };
const modalStyle = { background: '#ffffff', borderRadius: 16, border: '1px solid #e5e5e5', boxShadow: '0 20px 60px rgba(0,0,0,0.12)', maxWidth: '92vw', overflow: 'hidden' };
const modalHeaderStyle = { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '14px 16px', borderBottom: '1px solid #e5e5e5', background: '#fafafa' };
const modalFooterStyle = { display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: 8, padding: '12px 16px', borderTop: '1px solid #e5e5e5', background: '#fafafa' };
const iconBtnStyle = { background: 'none', border: 'none', cursor: 'pointer', color: '#71717a', display: 'flex' };
const ghostBtnStyle = { borderRadius: 8, padding: '7px 14px', fontSize: 12, fontWeight: 600, border: '1px solid #e5e5e5', background: '#ffffff', color: '#171717', cursor: 'pointer' };
const primaryBtnStyle = { display: 'flex', alignItems: 'center', gap: 6, borderRadius: 8, padding: '7px 14px', fontSize: 12, fontWeight: 700, border: '1px solid #2563eb', background: '#2563eb', color: '#ffffff', cursor: 'pointer' };
const chipBtnStyle = { borderRadius: 999, padding: '5px 10px', fontSize: 10.5, fontWeight: 500, border: '1px solid #e5e5e5', background: '#f5f5f5', color: '#52525b', cursor: 'pointer' };

const RESULT_META = {
    ok:      { Icon: CheckCircle2, color: '#2563eb' },
    skipped: { Icon: MinusCircle,  color: '#a1a1aa' },
    error:   { Icon: AlertCircle,  color: '#ef4444' },
};

function TestResultModal({ result, loading, onClose, onRerun }) {
    const { t } = useTranslation();
    return (
        <div onClick={onClose} style={overlayStyle}>
            <div onClick={e => e.stopPropagation()} style={{ ...modalStyle, width: 560, maxHeight: '82vh', display: 'flex', flexDirection: 'column' }}>
                <div style={modalHeaderStyle}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <span style={{ display: 'flex', width: 30, height: 30, borderRadius: 8, background: '#eff6ff', color: '#2563eb', border: '1px solid #bfdbfe', alignItems: 'center', justifyContent: 'center' }}><FlaskConical size={16} /></span>
                        <div>
                            <div style={{ fontSize: 14, fontWeight: 700, color: '#171717' }}>{t('automation.test_title')}</div>
                            <div style={{ fontSize: 11, color: '#71717a' }}>{t('automation.test_subtitle')}</div>
                        </div>
                    </div>
                    <button onClick={onClose} style={iconBtnStyle}><X size={18} /></button>
                </div>

                <div style={{ padding: 16, overflowY: 'auto', flex: 1 }}>
                    {loading ? (
                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '36px 0' }}>
                            <Loader2 size={22} className="animate-spin" style={{ color: '#2563eb' }} />
                            <span style={{ fontSize: 12, color: '#71717a', marginTop: 8 }}>{t('automation.test_running')}</span>
                        </div>
                    ) : !result ? null : !result.ok ? (
                        <div style={{ display: 'flex', gap: 8, background: '#fef2f2', border: '1px solid #fca5a5', borderRadius: 10, padding: '12px 14px', fontSize: 12.5, color: '#991b1b' }}>
                            <AlertTriangle size={16} style={{ flexShrink: 0, marginTop: 1, color: '#dc2626' }} /> {result.error}
                        </div>
                    ) : (
                        <>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12, fontSize: 11.5, color: '#52525b', background: '#fafafa', border: '1px solid #e5e5e5', borderRadius: 8, padding: '8px 10px' }}>
                                <span style={{ color: '#171717', fontWeight: 600 }}>{t('automation.test_steps_count', { count: result.steps.length })}</span>
                                {result.contact && <span style={{ color: '#71717a' }}>· {t('automation.test_sample_contact', { name: result.contact.name })}</span>}
                            </div>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                {result.steps.map((s, i) => {
                                    const meta = RESULT_META[s.result] ?? RESULT_META.ok;
                                    const def = NODE_DEFS[s.node_type];
                                    return (
                                        <div key={i} style={{ display: 'flex', gap: 10, padding: '10px 12px', border: '1px solid #e5e5e5', borderRadius: 10, background: '#ffffff' }}>
                                            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', width: 18 }}>
                                                <span style={{ fontSize: 9, fontWeight: 700, color: '#71717a' }}>{i + 1}</span>
                                                <span style={{ marginTop: 4, color: '#2563eb', display: 'flex' }}><NodeIcon nodeType={s.node_type} size={15} /></span>
                                            </div>
                                            <div style={{ flex: 1, minWidth: 0 }}>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                                    <span style={{ fontSize: 12, fontWeight: 600, color: '#171717' }}>{s.label || (def ? t(def.labelKey) : s.node_type)}</span>
                                                    {s.branch && <span style={{ fontSize: 9, fontWeight: 700, padding: '1px 6px', borderRadius: 999, background: s.branch === 'true' ? '#eff6ff' : '#f4f4f5', color: s.branch === 'true' ? '#2563eb' : '#71717a' }}>{s.branch === 'true' ? t('common.yes') : t('common.no')}</span>}
                                                </div>
                                                <div style={{ fontSize: 11, color: '#71717a', marginTop: 2, wordBreak: 'break-word' }}>{s.message}</div>
                                            </div>
                                            <meta.Icon size={16} style={{ color: meta.color, flexShrink: 0, marginTop: 2 }} />
                                        </div>
                                    );
                                })}
                            </div>
                            <div style={{ display: 'flex', gap: 6, marginTop: 14, fontSize: 10.5, color: '#71717a', alignItems: 'flex-start' }}>
                                <AlertCircle size={13} style={{ flexShrink: 0, marginTop: 1 }} /> {t('automation.test_disclaimer')}
                            </div>
                        </>
                    )}
                </div>

                <div style={modalFooterStyle}>
                    <button onClick={onClose} style={ghostBtnStyle}>{t('common.close')}</button>
                    <button onClick={onRerun} disabled={loading} style={{ ...primaryBtnStyle, opacity: loading ? 0.6 : 1 }}>
                        {loading ? <Loader2 size={13} className="animate-spin" /> : <FlaskConical size={13} />} {t('automation.test_run_again')}
                    </button>
                </div>
            </div>
        </div>
    );
}

function deleteTargetName(node, t) {
    const nt = node?.data?.nodeType;
    const def = NODE_DEFS[nt];
    return node?.data?.label || (def ? t(def.labelKey) : nt) || t('automation.this_node');
}

function ConfirmDeleteModal({ target, onCancel, onConfirm }) {
    const { t } = useTranslation();
    const nodes = target?.nodes ?? [];
    const body = nodes.length > 1
        ? t('automation.delete_nodes_confirm_body', { count: nodes.length })
        : t('automation.delete_node_confirm_body', { name: deleteTargetName(nodes[0], t) });
    return (
        <div onClick={onCancel} style={overlayStyle}>
            <div onClick={e => e.stopPropagation()} style={{ ...modalStyle, width: 384 }}>
                <div style={{ padding: '20px 20px 4px', display: 'flex', gap: 12 }}>
                    <span style={{ display: 'flex', width: 38, height: 38, borderRadius: 10, background: '#fef2f2', border: '1px solid #fca5a5', color: '#dc2626', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}><Trash2 size={18} /></span>
                    <div>
                        <div style={{ fontSize: 14, fontWeight: 700, color: '#171717' }}>{t('automation.delete_node_confirm_title')}</div>
                        <div style={{ fontSize: 12, color: '#71717a', marginTop: 4, lineHeight: 1.5 }}>{body}</div>
                    </div>
                </div>
                <div style={modalFooterStyle}>
                    <button onClick={onCancel} style={ghostBtnStyle}>{t('common.cancel')}</button>
                    <button onClick={onConfirm} style={{ ...primaryBtnStyle, background: '#dc2626', borderColor: '#dc2626', color: '#ffffff' }}><Trash2 size={13} /> {t('common.delete')}</button>
                </div>
            </div>
        </div>
    );
}

const AI_EXAMPLES = ['automation.ai_example_welcome', 'automation.ai_example_abandoned', 'automation.ai_example_faq'];

function AiGenerateModal({ prompt, setPrompt, loading, error, onClose, onGenerate }) {
    const { t } = useTranslation();
    return (
        <div onClick={loading ? undefined : onClose} style={overlayStyle}>
            <div onClick={e => e.stopPropagation()} style={{ ...modalStyle, width: 520 }}>
                <div style={modalHeaderStyle}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <span style={{ display: 'flex', width: 30, height: 30, borderRadius: 8, background: '#2563eb', color: '#ffffff', alignItems: 'center', justifyContent: 'center' }}><Sparkles size={16} /></span>
                        <div>
                            <div style={{ fontSize: 14, fontWeight: 700, color: '#171717' }}>{t('automation.ai_title')}</div>
                            <div style={{ fontSize: 11, color: '#71717a' }}>{t('automation.ai_subtitle')}</div>
                        </div>
                    </div>
                    <button onClick={onClose} disabled={loading} style={iconBtnStyle}><X size={18} /></button>
                </div>
                <div style={{ padding: 16 }} className="space-y-3">
                    <textarea autoFocus rows={5} className={textareaCls} value={prompt} onChange={e => setPrompt(e.target.value)} placeholder={t('automation.ai_placeholder')} disabled={loading} />
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                        {AI_EXAMPLES.map(k => (
                            <button key={k} disabled={loading} onClick={() => setPrompt(t(k))} style={chipBtnStyle}>{t(k)}</button>
                        ))}
                    </div>
                    {error && <div style={{ display: 'flex', gap: 8, background: '#fef2f2', border: '1px solid #fca5a5', borderRadius: 8, padding: '8px 10px', fontSize: 11.5, color: '#991b1b' }}><AlertTriangle size={14} style={{ flexShrink: 0, marginTop: 1, color: '#dc2626' }} />{error}</div>}
                    <div style={{ fontSize: 10.5, color: '#71717a', display: 'flex', gap: 6, alignItems: 'flex-start' }}><AlertCircle size={13} style={{ flexShrink: 0, marginTop: 1 }} />{t('automation.ai_disclaimer')}</div>
                </div>
                <div style={modalFooterStyle}>
                    <button onClick={onClose} disabled={loading} style={ghostBtnStyle}>{t('common.cancel')}</button>
                    <button onClick={onGenerate} disabled={loading || !prompt.trim()} style={{ ...primaryBtnStyle, opacity: (loading || !prompt.trim()) ? 0.6 : 1 }}>
                        {loading ? <><Loader2 size={13} className="animate-spin" /> {t('automation.ai_generating')}</> : <><Sparkles size={13} /> {t('automation.ai_generate')}</>}
                    </button>
                </div>
            </div>
        </div>
    );
}

function AutomationBuilderInner({ automation: initial }) {
    const { t } = useTranslation();
    const [automation, setAutomation] = useState(initial);
    const [nodes, setNodes, onNodesChange] = useNodesState(
        withTriggerNode(deserializeNodes(initial.nodes ?? []), initial.trigger_type ?? '')
    );
    const [edges, setEdges, onEdgesChange] = useEdgesState(
        (initial.edges ?? []).map(e => ({
            ...e,
            animated: true,
            style: { stroke: e.sourceHandle === 'false' ? '#9ca3af' : '#2563eb', strokeWidth: 2 },
            markerEnd: { type: MarkerType.ArrowClosed, color: e.sourceHandle === 'false' ? '#9ca3af' : '#2563eb' },
        }))
    );
    const [saving, setSaving] = useState(false);
    const [selectedNode, setSelectedNode] = useState(null);
    const [copied, setCopied] = useState(false);
    const [generatingToken, setGeneratingToken] = useState(false);
    const [search, setSearch] = useState('');
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState(null);
    const [showTest, setShowTest] = useState(false);
    const [aiOpen, setAiOpen] = useState(false);
    const [aiPrompt, setAiPrompt] = useState('');
    const [aiLoading, setAiLoading] = useState(false);
    const [aiError, setAiError] = useState(null);
    const [confirmDelete, setConfirmDelete] = useState(null);

    const webhookUrl = automation.trigger_token
        ? `${window.location.origin}/webhooks/automation/${automation.trigger_token}`
        : null;

    const copyWebhookUrl = () => {
        if (!webhookUrl) return;
        navigator.clipboard.writeText(webhookUrl).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };

    const generateToken = () => {
        setGeneratingToken(true);
        axios.post(route('client.automations.generate-token', automation.uuid))
            .then(res => setAutomation(a => ({ ...a, trigger_token: res.data.trigger_token })))
            .finally(() => setGeneratingToken(false));
    };

    const onConnect = useCallback((params) => {
        const isNo = params.sourceHandle === 'false';
        setEdges(eds => addEdge({
            ...params,
            animated: true,
            style: { stroke: isNo ? '#9ca3af' : '#2563eb', strokeWidth: 2 },
            markerEnd: { type: MarkerType.ArrowClosed, color: isNo ? '#9ca3af' : '#2563eb' },
        }, eds));
    }, []);

    const { screenToFlowPosition, deleteElements } = useReactFlow();

    // Single confirmation gate for every delete path (node trash icon, panel button, Delete key).
    const onBeforeDelete = useCallback(({ nodes: delNodes, edges: delEdges }) => {
        if (!delNodes || delNodes.length === 0) return Promise.resolve(true);
        return new Promise((resolve) => setConfirmDelete({ nodes: delNodes, edges: delEdges, resolve }));
    }, []);

    const resolveDelete = (ok) => {
        confirmDelete?.resolve?.(ok);
        if (ok) setSelectedNode(null);
        setConfirmDelete(null);
    };

    const addNode = (type, position) => {
        const n = makeNode(type, nodes.length, position);
        setNodes(nds => [...nds, n]);
    };

    const onDragStart = (e, type) => {
        e.dataTransfer.setData('application/automation-node', type);
        e.dataTransfer.effectAllowed = 'move';
    };

    const onDragOver = useCallback((e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }, []);

    const onDrop = useCallback((e) => {
        e.preventDefault();
        const type = e.dataTransfer.getData('application/automation-node');
        if (!type || !NODE_DEFS[type]) return;
        const position = screenToFlowPosition({ x: e.clientX, y: e.clientY });
        setNodes(nds => [...nds, makeNode(type, nds.length, position)]);
    }, [screenToFlowPosition, setNodes]);

    const updateNodeData = (nodeId, newData) => {
        setNodes(nds => nds.map(n => n.id === nodeId ? { ...n, data: newData } : n));
        setSelectedNode(prev => prev?.id === nodeId ? { ...prev, data: newData } : prev);
    };

    const deleteNode = (nodeId) => {
        deleteElements({ nodes: [{ id: nodeId }] });
    };

    const configureNode = (nodeId) => {
        const node = nodes.find(n => n.id === nodeId);
        if (node) setSelectedNode(node);
    };

    const setTriggerType = (value) => {
        setAutomation(a => ({ ...a, trigger_type: value }));
        setNodes(nds => nds.map(n => n.type === 'triggerNode' ? { ...n, data: { ...n.data, triggerType: value } } : n));
    };

    const setTriggerConfig = (patch) =>
        setAutomation(a => ({ ...a, trigger_config: { ...(a.trigger_config ?? {}), ...patch } }));

    const save = () => {
        setSaving(true);
        router.put(route('client.automations.update', automation.uuid), {
            nodes: serializeNodes(nodes),
            edges: edges.map(e => ({ id: e.id, source: e.source, target: e.target, sourceHandle: e.sourceHandle, targetHandle: e.targetHandle })),
            trigger_type: automation.trigger_type,
            trigger_config: automation.trigger_config,
            name: automation.name,
        }, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    const toggleStatus = () => {
        const newStatus = automation.status === 'active' ? 'paused' : 'active';
        router.put(route('client.automations.update', automation.uuid), { status: newStatus }, {
            preserveScroll: true,
            onSuccess: () => setAutomation(a => ({ ...a, status: newStatus })),
        });
    };

    const runTest = () => {
        setShowTest(true);
        setTesting(true);
        setTestResult(null);
        axios.post(route('client.automations.test', automation.uuid), {
            nodes: serializeNodes(nodes),
            edges: edges.map(e => ({ id: e.id, source: e.source, target: e.target, sourceHandle: e.sourceHandle })),
            trigger_type: automation.trigger_type,
            trigger_config: automation.trigger_config,
        })
            .then(res => setTestResult(res.data))
            .catch(err => setTestResult({ ok: false, error: err.response?.data?.error || err.response?.data?.message || t('automation.test_failed'), steps: [] }))
            .finally(() => setTesting(false));
    };

    const applyGraph = (graph) => {
        setNodes(withTriggerNode(deserializeNodes(graph.nodes ?? []), graph.trigger_type ?? ''));
        setEdges((graph.edges ?? []).map(e => ({
            ...e,
            animated: true,
            style: { stroke: e.sourceHandle === 'false' ? '#9ca3af' : '#2563eb', strokeWidth: 2 },
            markerEnd: { type: MarkerType.ArrowClosed, color: e.sourceHandle === 'false' ? '#9ca3af' : '#2563eb' },
        })));
        setAutomation(a => ({ ...a, trigger_type: graph.trigger_type ?? a.trigger_type, trigger_config: graph.trigger_config ?? a.trigger_config, name: graph.name || a.name }));
        setSelectedNode(null);
    };

    const generateAi = () => {
        setAiLoading(true);
        setAiError(null);
        axios.post(route('client.automations.generate'), { prompt: aiPrompt, persist: false })
            .then(res => {
                if (res.data?.ok && res.data.graph) {
                    applyGraph(res.data.graph);
                    setAiOpen(false);
                    setAiPrompt('');
                } else {
                    setAiError(res.data?.error || t('automation.ai_failed'));
                }
            })
            .catch(err => setAiError(err.response?.data?.error || err.response?.data?.message || t('automation.ai_failed')))
            .finally(() => setAiLoading(false));
    };

    const q = search.trim().toLowerCase();
    const grouped = CATEGORY_ORDER.map(cat => ({
        cat,
        items: Object.entries(NODE_DEFS).filter(([type, def]) =>
            def.category === cat && (q === '' || type.includes(q) || t(def.labelKey).toLowerCase().includes(q))
        ),
    })).filter(g => g.items.length > 0);

    const defaultEdgeOptions = {
        style: { stroke: '#2563eb', strokeWidth: 2 },
        animated: true,
    };

    return (
        <NodeActionsContext.Provider value={{ onConfigure: configureNode, onDelete: deleteNode }}>
        <div style={{ display: 'flex', height: 'calc(100vh - 140px)', borderRadius: 18, overflow: 'hidden', border: '1px solid #e5e5e5', boxShadow: '0 4px 20px rgba(0,0,0,0.05)', background: '#fafafa' }}>
            {/* ── Sidebar Palette ── */}
            <div style={{ width: 250, display: 'flex', flexDirection: 'column', background: '#ffffff', borderRight: '1px solid #e5e5e5', overflowY: 'auto' }}>
                {/* Node palette */}
                <div style={{ padding: 14, flex: 1 }}>
                    <div style={{ fontSize: 10, fontWeight: 800, color: '#171717', letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: 4 }}>{t('automation.add_node')}</div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 10, color: '#71717a', marginBottom: 10 }}>
                        <GripVertical size={11} /> {t('automation.drag_node_hint')}
                    </div>

                    <input
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        placeholder={t('automation.search_nodes')}
                        style={{
                            width: '100%', borderRadius: 10, border: '1px solid #e5e5e5',
                            background: '#fafafa', color: '#171717', padding: '7px 10px',
                            fontSize: 11.5, marginBottom: 14, boxSizing: 'border-box', outline: 'none',
                        }}
                    />

                    {grouped.map(({ cat, items }) => (
                        <div key={cat} style={{ marginBottom: 14 }}>
                            <div style={{ fontSize: 9.5, fontWeight: 800, color: '#71717a', letterSpacing: '0.12em', textTransform: 'uppercase', marginBottom: 6 }}>
                                {t(`automation.category_${cat}`)}
                            </div>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                                {items.map(([type, def]) => {
                                    const itemLabel = getNodeDefLabel(type, t);
                                    return (
                                        <button
                                            key={type}
                                            draggable
                                            onDragStart={e => onDragStart(e, type)}
                                            onClick={() => addNode(type)}
                                            title={t('automation.drag_node_hint')}
                                            style={{
                                                display: 'flex', alignItems: 'center', gap: 9, padding: '8px 10px',
                                                borderRadius: 10, border: '1px solid #e5e5e5', background: '#ffffff',
                                                cursor: 'grab', textAlign: 'left', fontSize: 11.5, color: '#171717',
                                                transition: 'all 0.15s ease', boxShadow: '0 1px 3px rgba(0,0,0,0.04)',
                                            }}
                                            onMouseEnter={e => { e.currentTarget.style.borderColor = '#2563eb'; e.currentTarget.style.background = '#f8fafc'; }}
                                            onMouseLeave={e => { e.currentTarget.style.borderColor = '#e5e5e5'; e.currentTarget.style.background = '#ffffff'; }}
                                        >
                                            <span style={{
                                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                                width: 24, height: 24, borderRadius: 7, flexShrink: 0,
                                                background: '#f5f5f5', color: '#171717', border: '1px solid #e5e5e5',
                                            }}>
                                                <NodeIcon nodeType={type} size={13} />
                                            </span>
                                            <span style={{ fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{itemLabel}</span>
                                            <GripVertical size={11} style={{ marginLeft: 'auto', color: '#a1a1aa', flexShrink: 0 }} />
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* ── Canvas ── */}
            <div style={{ flex: 1, position: 'relative' }} onDrop={onDrop} onDragOver={onDragOver}>
                <ReactFlow
                    nodes={nodes}
                    edges={edges}
                    onNodesChange={onNodesChange}
                    onEdgesChange={onEdgesChange}
                    onConnect={onConnect}
                    onNodeClick={(_, node) => setSelectedNode(node)}
                    onPaneClick={() => setSelectedNode(null)}
                    onBeforeDelete={onBeforeDelete}
                    nodeTypes={nodeTypes}
                    defaultEdgeOptions={defaultEdgeOptions}
                    fitView
                    deleteKeyCode="Delete"
                >
                    <Background color="#e5e5e5" gap={20} size={1} />
                    <Controls style={{ bottom: 20, left: 20, background: '#ffffff', border: '1px solid #e5e5e5', borderRadius: 12, fill: '#171717' }} />
                    <MiniMap
                        nodeColor="#2563eb"
                        maskColor="rgba(250, 250, 250, 0.7)"
                        style={{ background: '#ffffff', border: '1px solid #e5e5e5', borderRadius: 12 }}
                    />

                    {/* Top toolbar */}
                    <Panel position="top-right">
                        <div style={{
                            display: 'flex', alignItems: 'center', gap: 8,
                            background: '#ffffff', borderRadius: 14, border: '1px solid #e5e5e5',
                            padding: '8px 12px', boxShadow: '0 4px 16px rgba(0,0,0,0.06)',
                        }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 6, paddingRight: 10, borderRight: '1px solid #e5e5e5' }}>
                                <div style={{ width: 8, height: 8, borderRadius: '50%', background: automation.status === 'active' ? '#2563eb' : '#a1a1aa', boxShadow: automation.status === 'active' ? '0 0 8px #2563eb' : 'none' }} />
                                <span style={{ fontSize: 11.5, color: automation.status === 'active' ? '#171717' : '#71717a', fontWeight: 600 }}>{t(`automation.status_${automation.status}`)}</span>
                            </div>

                            <button onClick={() => { setAiError(null); setAiOpen(true); }} title={t('automation.ai_title')} style={{
                                display: 'flex', alignItems: 'center', gap: 6, borderRadius: 10,
                                background: '#f5f5f5', padding: '7px 13px', fontSize: 12, fontWeight: 600,
                                color: '#171717', border: '1px solid #e5e5e5', cursor: 'pointer', transition: 'all 0.15s',
                            }}>
                                <Sparkles size={14} color="#2563eb" /> {t('automation.ai_generate_short')}
                            </button>

                            <button onClick={runTest} disabled={testing} title={t('automation.test_title')} style={{
                                display: 'flex', alignItems: 'center', gap: 6, borderRadius: 10,
                                background: '#f5f5f5', padding: '7px 13px', fontSize: 12, fontWeight: 600,
                                color: '#171717', border: '1px solid #e5e5e5', cursor: testing ? 'not-allowed' : 'pointer',
                                opacity: testing ? 0.7 : 1, transition: 'all 0.15s',
                            }}>
                                {testing ? <Loader2 size={14} className="animate-spin" /> : <FlaskConical size={14} color="#2563eb" />} {t('automation.test')}
                            </button>

                            <button onClick={save} disabled={saving} style={{
                                display: 'flex', alignItems: 'center', gap: 6, borderRadius: 10,
                                background: '#2563eb', padding: '7px 15px', fontSize: 12, fontWeight: 700,
                                color: '#ffffff', border: '1px solid #2563eb', cursor: saving ? 'not-allowed' : 'pointer',
                                opacity: saving ? 0.7 : 1, transition: 'all 0.15s',
                                boxShadow: '0 2px 6px rgba(37,99,235,0.25)',
                            }}>
                                <Save size={14} /> {saving ? t('automation.saving') : t('common.save')}
                            </button>

                            <button onClick={toggleStatus} style={{
                                display: 'flex', alignItems: 'center', gap: 6, borderRadius: 10,
                                padding: '7px 13px', fontSize: 12, fontWeight: 600, cursor: 'pointer',
                                background: '#f5f5f5', color: '#171717', border: '1px solid #e5e5e5',
                                transition: 'all 0.15s',
                            }}>
                                {automation.status === 'active'
                                    ? <><Pause size={14} /> {t('automation.pause')}</>
                                    : <><Play size={14} /> {t('automation.activate')}</>}
                            </button>
                        </div>
                    </Panel>

                    {/* Hint */}
                    <Panel position="bottom-center">
                        <div style={{ fontSize: 11, color: '#71717a', background: 'rgba(255,255,255,0.95)', backdropFilter: 'blur(8px)', borderRadius: 999, padding: '5px 16px', border: '1px solid #e5e5e5' }}>
                            {t('automation.canvas_hint')}
                        </div>
                    </Panel>
                </ReactFlow>

                {/* Node config panel */}
                {selectedNode && selectedNode.type === 'automationNode' && (
                    <>
                        <div onClick={() => setSelectedNode(null)} style={{ position: 'absolute', inset: 0, zIndex: 9 }} />
                        <div style={{ position: 'absolute', top: 0, right: 0, bottom: 0, zIndex: 10, pointerEvents: 'all' }}>
                            <ConfigPanel
                                node={selectedNode}
                                onClose={() => setSelectedNode(null)}
                                onChange={updateNodeData}
                                onDelete={() => deleteNode(selectedNode.id)}
                            />
                        </div>
                    </>
                )}

                {/* Trigger config panel */}
                {selectedNode && selectedNode.type === 'triggerNode' && (
                    <>
                        <div onClick={() => setSelectedNode(null)} style={{ position: 'absolute', inset: 0, zIndex: 9 }} />
                        <div style={{ position: 'absolute', top: 0, right: 0, bottom: 0, zIndex: 10, pointerEvents: 'all' }}>
                            <TriggerConfigPanel
                                automation={automation}
                                onTypeChange={setTriggerType}
                                onConfigChange={setTriggerConfig}
                                webhookUrl={webhookUrl}
                                copied={copied}
                                onCopy={copyWebhookUrl}
                                onGenerateToken={generateToken}
                                generatingToken={generatingToken}
                                onClose={() => setSelectedNode(null)}
                            />
                        </div>
                    </>
                )}
            </div>

            {showTest && <TestResultModal result={testResult} loading={testing} onClose={() => setShowTest(false)} onRerun={runTest} />}
            {aiOpen && <AiGenerateModal prompt={aiPrompt} setPrompt={setAiPrompt} loading={aiLoading} error={aiError} onClose={() => setAiOpen(false)} onGenerate={generateAi} />}
            {confirmDelete && <ConfirmDeleteModal target={confirmDelete} onCancel={() => resolveDelete(false)} onConfirm={() => resolveDelete(true)} />}
        </div>
        </NodeActionsContext.Provider>
    );
}

/* ─── Page ───────────────────────────────────────────────────── */
export default function AutomationBuilder({ automation }) {
    const { t } = useTranslation();
    return (
        <ClientLayout title={automation.name}>
            <Head title={`${automation.name} · ${t('automation.builder')}`} />
            <div className="space-y-4">
                <div className="flex items-center justify-between bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl px-5 py-3.5 shadow-xs">
                    <div className="flex items-center gap-3.5">
                        <Link href={route('client.automations.index')} className="flex items-center justify-center w-9 h-9 rounded-xl bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-200 dark:hover:bg-neutral-700 transition shadow-xs">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                        <div>
                            <div className="flex items-center gap-2.5">
                                <h2 className="text-base font-bold text-neutral-900 dark:text-neutral-100 leading-tight">{automation.name}</h2>
                                <span className="px-2.5 py-0.5 rounded-md bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider">
                                    Workflow Builder
                                </span>
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link href={route('client.automations.runs', automation.uuid)} className="flex items-center gap-1.5 text-xs font-semibold px-3.5 py-2 rounded-xl bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-200 dark:hover:bg-neutral-700 transition">
                            {t('automation.view_runs_arrow')}
                        </Link>
                    </div>
                </div>
                <ReactFlowProvider>
                    <AutomationBuilderInner automation={automation} />
                </ReactFlowProvider>
            </div>
        </ClientLayout>
    );
}
