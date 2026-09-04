import { Head, router, useForm } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import {
    BookOpen, Building2, Package, HelpCircle, Globe, FileText,
    Plus, Sparkles, RefreshCw, CheckCircle2, AlertCircle, Clock,
    Trash2, Edit2, UploadCloud, ArrowRight, ExternalLink, Search,
    Check, X, ShieldCheck, ChevronRight, Sliders, Users, Eye,
    ToggleLeft, ToggleRight, Layers, FileCode, MessageSquare, AlertTriangle
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import axios from 'axios';

export default function KnowledgeIndex({
    kb,
    allSources = [],
    business = {},
    products = [],
    services = [],
    faqs = [],
    websites = [],
    documents = [],
    texts = [],
    availableAgents = [],
    knowledgeGaps = [],
    stats = {},
}) {
    const { t } = useTranslation();
    const [activeTab, setActiveTab] = useState('all'); // all, documents, faqs, websites, texts, business, gaps, settings
    const [searchQuery, setSearchQuery] = useState('');
    const [isUpdating, setIsUpdating] = useState(false);

    // Modals
    const [showAddModal, setShowAddModal] = useState(false);
    const [addModalType, setAddModalType] = useState('document'); // document, faq, website, text
    const [selectedDocForChunks, setSelectedDocForChunks] = useState(null);
    const [selectedDocForAgents, setSelectedDocForAgents] = useState(null);
    const [selectedAgentIds, setSelectedAgentIds] = useState([]);

    // Business Profile Form
    const { data: bData, setData: setBData, post: postBusiness, processing: bProcessing } = useForm({
        name: business?.name || '',
        industry: business?.industry || '',
        description: business?.description || '',
        business_hours: business?.business_hours || '',
        address: business?.address || '',
        phone: business?.phone || '',
        email: business?.email || '',
        website: business?.website || '',
        return_policy: business?.return_policy || '',
        shipping_policy: business?.shipping_policy || '',
        payment_info: business?.payment_info || '',
    });

    // Product Form
    const { data: pData, setData: setPData, post: postProduct, processing: pProcessing, reset: resetProduct } = useForm({
        name: '',
        price: '',
        currency: 'USD',
        description: '',
        features: '',
        availability: 'In Stock',
        sku: '',
    });

    // FAQ Form
    const { data: fData, setData: setFData, post: postFaq, processing: fProcessing, reset: resetFaq } = useForm({
        question: '',
        answer: '',
        category: 'faq',
        priority: 8,
        assigned_agents: [],
    });

    // Website Form
    const { data: wData, setData: setWData, post: postWebsite, processing: wProcessing, reset: resetWebsite } = useForm({
        url: '',
        crawl_option: 'page',
        assigned_agents: [],
    });

    // Document Form
    const { data: dData, setData: setDData, post: postDoc, processing: dProcessing, reset: resetDoc } = useForm({
        file: null,
        title: '',
        category: 'documents',
        assigned_agents: [],
    });

    // Text Form
    const { data: tData, setData: setTData, post: postText, processing: tProcessing, reset: resetText } = useForm({
        title: '',
        content: '',
        category: 'general',
        priority: 5,
        assigned_agents: [],
    });

    // Settings Form
    const { data: sData, setData: setSData, post: postSettings, processing: sProcessing } = useForm({
        answer_policy: kb?.answer_policy || 'strict_kb_only',
        allow_citations: kb?.allow_citations !== undefined ? Boolean(kb.allow_citations) : true,
        fallback_message: kb?.fallback_message || "I don't have enough information in my verified business knowledge to answer that accurately. Would you like me to connect you with a human specialist?",
    });

    // Handlers
    const handleSaveBusiness = (e) => {
        e.preventDefault();
        postBusiness(route('client.ai.knowledge.business'), {
            onSuccess: () => toast.success('Business profile updated and processed.'),
        });
    };

    const handleSaveFaq = (e) => {
        e.preventDefault();
        postFaq(route('client.ai.knowledge.faq'), {
            onSuccess: () => {
                toast.success('FAQ added to AI knowledge base.');
                resetFaq();
                setShowAddModal(false);
            },
        });
    };

    const handleImportWebsite = (e) => {
        e.preventDefault();
        postWebsite(route('client.ai.knowledge.website'), {
            onSuccess: () => {
                toast.success('Website crawled and indexed.');
                resetWebsite();
                setShowAddModal(false);
            },
        });
    };

    const handleUploadDoc = (e) => {
        e.preventDefault();
        if (!dData.file) return;
        postDoc(route('client.ai.knowledge.document'), {
            onSuccess: () => {
                toast.success('Document uploaded and processed.');
                resetDoc();
                setShowAddModal(false);
            },
        });
    };

    const handleSaveText = (e) => {
        e.preventDefault();
        postText(route('client.ai.knowledge.text'), {
            onSuccess: () => {
                toast.success('Text knowledge indexed successfully.');
                resetText();
                setShowAddModal(false);
            },
        });
    };

    const handleSaveSettings = (e) => {
        e.preventDefault();
        postSettings(route('client.ai.knowledge.settings'), {
            onSuccess: () => toast.success('Knowledge settings saved.'),
        });
    };

    const handleReprocess = async (docId) => {
        try {
            await axios.post(route('client.ai.knowledge.document.reprocess', docId));
            toast.success('Knowledge source reprocessed.');
            router.reload({ preserveScroll: true });
        } catch (e) {
            toast.error('Failed to reprocess source.');
        }
    };

    const handleToggleStatus = async (docId) => {
        try {
            const res = await axios.post(route('client.ai.knowledge.document.toggle', docId));
            toast.success(res.data?.message || 'Status updated.');
            router.reload({ preserveScroll: true });
        } catch (e) {
            toast.error('Failed to toggle status.');
        }
    };

    const handleDelete = async (docId) => {
        if (!confirm('Are you sure you want to remove this knowledge source?')) return;
        try {
            await axios.delete(route('client.ai.knowledge.document.destroy', docId));
            toast.success('Source deleted.');
            router.reload({ preserveScroll: true });
        } catch (e) {
            toast.error('Failed to delete source.');
        }
    };

    const handleSaveAgentAssignments = async () => {
        if (!selectedDocForAgents) return;
        try {
            await axios.post(route('client.ai.knowledge.document.assign-agents', selectedDocForAgents.uuid), {
                agent_ids: selectedAgentIds,
            });
            toast.success('Agent assignments updated.');
            setSelectedDocForAgents(null);
            router.reload({ preserveScroll: true });
        } catch (e) {
            toast.error('Failed to update agent assignments.');
        }
    };

    const handleCreateFaqFromGap = (gap) => {
        setFData('question', gap.question);
        setFData('category', gap.category_suggested || 'faq');
        setAddModalType('faq');
        setShowAddModal(true);
    };

    const handleResolveGap = async (gapId) => {
        try {
            await axios.post(route('client.ai.knowledge.gaps.resolve', gapId));
            toast.success('Knowledge gap marked resolved.');
            router.reload({ preserveScroll: true });
        } catch (e) {
            toast.error('Failed to resolve gap.');
        }
    };

    // Filter sources
    const filteredSources = allSources.filter(doc => {
        if (activeTab === 'documents') return doc.source_type === 'file';
        if (activeTab === 'faqs') return doc.source_type === 'faq';
        if (activeTab === 'websites') return doc.source_type === 'website';
        if (activeTab === 'texts') return doc.source_type === 'text' && doc.category !== 'business';
        return true;
    }).filter(doc => {
        if (!searchQuery) return true;
        const q = searchQuery.toLowerCase();
        return (doc.title && doc.title.toLowerCase().includes(q)) ||
               (doc.category && doc.category.toLowerCase().includes(q)) ||
               (doc.source_type && doc.source_type.toLowerCase().includes(q));
    });

    const getAgentName = (id) => {
        const found = availableAgents.find(a => String(a.id) === String(id));
        return found ? found.name : `Agent #${id}`;
    };

    return (
        <ClientLayout>
            <Head title="AI Knowledge Base & RAG — Growbridge Connect" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="p-2 rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400">
                                <BookOpen className="w-5 h-5" />
                            </span>
                            <h1 className="text-xl font-bold text-neutral-900 dark:text-white">AI Knowledge Base & RAG</h1>
                            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                <ShieldCheck className="w-3.5 h-3.5" /> Strict Anti-Hallucination
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-neutral-500">
                            Equip WhatsApp, Messenger, Instagram, Email, and AI Voice Agents with trusted business knowledge.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => {
                                setIsUpdating(true);
                                router.post(route('client.ai.knowledge.process'), {}, {
                                    onSuccess: () => {
                                        setIsUpdating(false);
                                        toast.success('All knowledge sources re-indexed.');
                                    },
                                    onError: () => setIsUpdating(false)
                                });
                            }}
                            disabled={isUpdating}
                            className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-semibold hover:bg-neutral-50 transition"
                        >
                            <RefreshCw className={`w-3.5 h-3.5 ${isUpdating ? 'animate-spin' : ''}`} />
                            {isUpdating ? 'Indexing...' : 'Reprocess All'}
                        </button>
                        <button
                            onClick={() => setShowAddModal(true)}
                            className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 text-white text-xs font-bold hover:bg-brand-700 shadow-sm transition"
                        >
                            <Plus className="w-4 h-4" /> Add Knowledge
                        </button>
                    </div>
                </div>

                {/* KPI Metrics */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <div className="p-3.5 rounded-2xl bg-white dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60">
                        <div className="flex items-center gap-2 text-neutral-500 text-xs mb-1">
                            <FileText className="w-4 h-4 text-blue-500" /> Documents
                        </div>
                        <div className="text-xl font-bold text-neutral-900 dark:text-white">
                            {stats.documents_count || 0}
                        </div>
                        <div className="text-[11px] text-neutral-400 mt-0.5">PDF, DOCX, TXT</div>
                    </div>

                    <div className="p-3.5 rounded-2xl bg-white dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60">
                        <div className="flex items-center gap-2 text-neutral-500 text-xs mb-1">
                            <HelpCircle className="w-4 h-4 text-amber-500" /> FAQs
                        </div>
                        <div className="text-xl font-bold text-neutral-900 dark:text-white">
                            {stats.faqs_count || 0}
                        </div>
                        <div className="text-[11px] text-neutral-400 mt-0.5">Priority Q&A Pairs</div>
                    </div>

                    <div className="p-3.5 rounded-2xl bg-white dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60">
                        <div className="flex items-center gap-2 text-neutral-500 text-xs mb-1">
                            <Globe className="w-4 h-4 text-indigo-500" /> Websites
                        </div>
                        <div className="text-xl font-bold text-neutral-900 dark:text-white">
                            {stats.websites_count || 0}
                        </div>
                        <div className="text-[11px] text-neutral-400 mt-0.5">Crawled URLs</div>
                    </div>

                    <div className="p-3.5 rounded-2xl bg-white dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60">
                        <div className="flex items-center gap-2 text-neutral-500 text-xs mb-1">
                            <Layers className="w-4 h-4 text-emerald-500" /> Total Sources
                        </div>
                        <div className="text-xl font-bold text-neutral-900 dark:text-white">
                            {stats.total_items || 0}
                        </div>
                        <div className="flex items-center gap-1.5 text-[10px] text-emerald-600 font-semibold mt-0.5">
                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> {stats.ready_count || 0} Ready
                        </div>
                    </div>

                    <div className="p-3.5 rounded-2xl bg-white dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60 col-span-2 md:col-span-1">
                        <div className="flex items-center gap-2 text-neutral-500 text-xs mb-1">
                            <AlertTriangle className="w-4 h-4 text-orange-500" /> Knowledge Gaps
                        </div>
                        <div className="text-xl font-bold text-neutral-900 dark:text-white">
                            {stats.gaps_count || 0}
                        </div>
                        <div className="text-[11px] text-orange-600 font-semibold mt-0.5">Unanswered Inquiries</div>
                    </div>
                </div>

                {/* Tabs & Filter Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-200 dark:border-neutral-800 pb-2">
                    <div className="flex items-center gap-1.5 overflow-x-auto text-xs pb-1 sm:pb-0">
                        {[
                            { id: 'all', label: 'All Sources', count: stats.total_items },
                            { id: 'documents', label: '📄 Documents', count: stats.documents_count },
                            { id: 'faqs', label: '❓ FAQs', count: stats.faqs_count },
                            { id: 'websites', label: '🌐 Websites', count: stats.websites_count },
                            { id: 'texts', label: '📝 Plain Text', count: stats.text_count },
                            { id: 'business', label: '🏢 Business Profile' },
                            { id: 'gaps', label: `⚠️ Knowledge Gaps (${stats.gaps_count || 0})` },
                            { id: 'settings', label: '⚙️ Settings & Anti-Hallucination' },
                        ].map(tab => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id)}
                                className={`px-3 py-1.5 rounded-xl font-semibold whitespace-nowrap transition flex items-center gap-1.5 ${
                                    activeTab === tab.id
                                        ? 'bg-brand-600 text-white shadow-xs'
                                        : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                                }`}
                            >
                                <span>{tab.label}</span>
                                {tab.count !== undefined && tab.count > 0 && (
                                    <span className={`px-1.5 py-0.2 rounded-full text-[10px] ${
                                        activeTab === tab.id ? 'bg-white/20 text-white' : 'bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300'
                                    }`}>
                                        {tab.count}
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>

                    {(activeTab === 'all' || activeTab === 'documents' || activeTab === 'faqs' || activeTab === 'websites' || activeTab === 'texts') && (
                        <div className="relative w-full sm:w-64">
                            <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={e => setSearchQuery(e.target.value)}
                                placeholder="Search knowledge sources..."
                                className="w-full pl-8 pr-3 py-1.5 text-xs bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl focus:outline-none"
                            />
                        </div>
                    )}
                </div>

                {/* ─── TAB 1: Sources List (All, Documents, FAQs, Websites, Texts) ─── */}
                {(activeTab === 'all' || activeTab === 'documents' || activeTab === 'faqs' || activeTab === 'websites' || activeTab === 'texts') && (
                    <div className="space-y-4">
                        {filteredSources.length === 0 ? (
                            <div className="p-12 text-center bg-white dark:bg-neutral-800/40 rounded-2xl border border-dashed border-neutral-300 dark:border-neutral-700 space-y-3">
                                <BookOpen className="w-10 h-10 text-neutral-400 mx-auto" />
                                <h3 className="text-sm font-bold text-neutral-800 dark:text-neutral-200">No knowledge sources found</h3>
                                <p className="text-xs text-neutral-500 max-w-sm mx-auto">
                                    Add documents, FAQs, websites, or plain text so your AI agents can accurately answer customer questions.
                                </p>
                                <button
                                    onClick={() => setShowAddModal(true)}
                                    className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 text-white text-xs font-bold hover:bg-brand-700"
                                >
                                    <Plus className="w-4 h-4" /> Add Knowledge Source
                                </button>
                            </div>
                        ) : (
                            <div className="bg-white dark:bg-neutral-800/60 rounded-2xl border border-neutral-200 dark:border-neutral-700/60 overflow-hidden shadow-xs">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead className="bg-neutral-50 dark:bg-neutral-900/50 text-neutral-400 uppercase text-[10px] tracking-wider border-b border-neutral-200 dark:border-neutral-700/60">
                                            <tr>
                                                <th className="py-3 px-4">Source Title</th>
                                                <th className="py-3 px-3">Type</th>
                                                <th className="py-3 px-3">Status</th>
                                                <th className="py-3 px-3">Chunks</th>
                                                <th className="py-3 px-3">Used By AI Agents</th>
                                                <th className="py-3 px-3">Last Indexed</th>
                                                <th className="py-3 px-4 text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800 text-neutral-700 dark:text-neutral-300">
                                            {filteredSources.map(doc => (
                                                <tr key={doc.id} className="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/50 transition">
                                                    <td className="py-3 px-4">
                                                        <div className="font-semibold text-neutral-900 dark:text-white max-w-xs truncate" title={doc.title}>
                                                            {doc.title}
                                                        </div>
                                                        <div className="text-[11px] text-neutral-400 capitalize">
                                                            {doc.category || 'General'}
                                                        </div>
                                                    </td>
                                                    <td className="py-3 px-3">
                                                        <span className={`px-2 py-0.5 rounded-md text-[10px] font-bold uppercase ${
                                                            doc.source_type === 'file' ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' :
                                                            doc.source_type === 'faq' ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' :
                                                            doc.source_type === 'website' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300' :
                                                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                        }`}>
                                                            {doc.source_type}
                                                        </span>
                                                    </td>
                                                    <td className="py-3 px-3">
                                                        <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold ${
                                                            doc.status === 'ready' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' :
                                                            doc.status === 'indexing' ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' :
                                                            doc.status === 'disabled' ? 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400' :
                                                            'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300'
                                                        }`}>
                                                            <span className={`w-1.5 h-1.5 rounded-full ${
                                                                doc.status === 'ready' ? 'bg-emerald-500' :
                                                                doc.status === 'indexing' ? 'bg-amber-500 animate-pulse' :
                                                                doc.status === 'disabled' ? 'bg-neutral-400' : 'bg-red-500'
                                                            }`}></span>
                                                            <span className="capitalize">{doc.status}</span>
                                                        </span>
                                                        {doc.error_message && (
                                                            <p className="text-[10px] text-red-500 truncate max-w-xs mt-0.5" title={doc.error_message}>
                                                                {doc.error_message}
                                                            </p>
                                                        )}
                                                    </td>
                                                    <td className="py-3 px-3 font-mono font-bold text-neutral-800 dark:text-neutral-200">
                                                        {doc.chunks_count || doc.chunks?.length || 0}
                                                    </td>
                                                    <td className="py-3 px-3">
                                                        <div className="flex flex-wrap gap-1 max-w-xs">
                                                            {!doc.assigned_agents || doc.assigned_agents.length === 0 ? (
                                                                <span className="px-2 py-0.5 rounded-md text-[10px] bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 font-semibold">
                                                                    🌐 All AI Agents
                                                                </span>
                                                            ) : (
                                                                doc.assigned_agents.map(agId => (
                                                                    <span key={agId} className="px-2 py-0.5 rounded-md text-[10px] bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 font-semibold">
                                                                        🤖 {getAgentName(agId)}
                                                                    </span>
                                                                ))
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="py-3 px-3 text-neutral-500 text-[11px]">
                                                        {doc.last_indexed_at ? new Date(doc.last_indexed_at).toLocaleDateString() : 'Pending'}
                                                    </td>
                                                    <td className="py-3 px-4 text-right space-x-1">
                                                        <button
                                                            onClick={() => setSelectedDocForChunks(doc)}
                                                            className="p-1.5 rounded-lg text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                                                            title="Inspect Chunks"
                                                        >
                                                            <Eye className="w-3.5 h-3.5" />
                                                        </button>
                                                        <button
                                                            onClick={() => {
                                                                setSelectedDocForAgents(doc);
                                                                setSelectedAgentIds(doc.assigned_agents || []);
                                                            }}
                                                            className="p-1.5 rounded-lg text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-950/30 transition"
                                                            title="Assign AI Agents"
                                                        >
                                                            <Users className="w-3.5 h-3.5" />
                                                        </button>
                                                        <button
                                                            onClick={() => handleReprocess(doc.uuid)}
                                                            className="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/30 transition"
                                                            title="Reprocess Source"
                                                        >
                                                            <RefreshCw className="w-3.5 h-3.5" />
                                                        </button>
                                                        <button
                                                            onClick={() => handleToggleStatus(doc.uuid)}
                                                            className="p-1.5 rounded-lg text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                                                            title={doc.status === 'disabled' ? 'Enable Source' : 'Disable Source'}
                                                        >
                                                            {doc.status === 'disabled' ? <ToggleLeft className="w-4 h-4 text-neutral-400" /> : <ToggleRight className="w-4 h-4 text-emerald-500" />}
                                                        </button>
                                                        <button
                                                            onClick={() => handleDelete(doc.uuid)}
                                                            className="p-1.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 transition"
                                                            title="Delete Source"
                                                        >
                                                            <Trash2 className="w-3.5 h-3.5" />
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* ─── TAB 2: Knowledge Gaps & Unanswered Questions ─── */}
                {activeTab === 'gaps' && (
                    <div className="space-y-4">
                        <div className="p-4 rounded-2xl bg-orange-50/50 dark:bg-orange-950/20 border border-orange-200 dark:border-orange-900/40 flex items-start gap-3">
                            <AlertTriangle className="w-5 h-5 text-orange-600 shrink-0 mt-0.5" />
                            <div>
                                <h3 className="text-xs font-bold text-orange-900 dark:text-orange-200">Knowledge Gap Feedback Loop</h3>
                                <p className="text-[11px] text-orange-700 dark:text-orange-300 mt-0.5">
                                    These are real customer questions from WhatsApp, Messenger, Voice, and Web where the AI couldn't find matching verified knowledge. Create FAQs from them to improve your AI's resolution rate.
                                </p>
                            </div>
                        </div>

                        {knowledgeGaps.length === 0 ? (
                            <div className="p-12 text-center bg-white dark:bg-neutral-800/40 rounded-2xl border border-neutral-200 dark:border-neutral-700 space-y-2">
                                <CheckCircle2 className="w-8 h-8 text-emerald-500 mx-auto" />
                                <h4 className="text-sm font-bold text-neutral-800 dark:text-neutral-200">No Knowledge Gaps Detected</h4>
                                <p className="text-xs text-neutral-500">Your AI agents are finding sufficient verified information for all customer questions.</p>
                            </div>
                        ) : (
                            <div className="bg-white dark:bg-neutral-800/60 rounded-2xl border border-neutral-200 dark:border-neutral-700/60 overflow-hidden">
                                <table className="w-full text-left text-xs">
                                    <thead className="bg-neutral-50 dark:bg-neutral-900/50 text-neutral-400 uppercase text-[10px] tracking-wider border-b border-neutral-200 dark:border-neutral-700">
                                        <tr>
                                            <th className="py-3 px-4">Unanswered Customer Question</th>
                                            <th className="py-3 px-3">Occurrences</th>
                                            <th className="py-3 px-3">Suggested Category</th>
                                            <th className="py-3 px-3">Last Asked</th>
                                            <th className="py-3 px-4 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800 text-neutral-700 dark:text-neutral-300">
                                        {knowledgeGaps.map(gap => (
                                            <tr key={gap.id} className="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/50 transition">
                                                <td className="py-3 px-4 font-semibold text-neutral-900 dark:text-white">
                                                    "{gap.question}"
                                                </td>
                                                <td className="py-3 px-3">
                                                    <span className="px-2 py-0.5 rounded-full bg-orange-100 dark:bg-orange-950/40 text-orange-700 dark:text-orange-300 font-bold text-[11px]">
                                                        {gap.occurrences}x
                                                    </span>
                                                </td>
                                                <td className="py-3 px-3 capitalize text-neutral-500">
                                                    {gap.category_suggested || 'General'}
                                                </td>
                                                <td className="py-3 px-3 text-neutral-500 text-[11px]">
                                                    {gap.last_asked_at ? new Date(gap.last_asked_at).toLocaleString() : 'Recent'}
                                                </td>
                                                <td className="py-3 px-4 text-right space-x-2">
                                                    <button
                                                        onClick={() => handleCreateFaqFromGap(gap)}
                                                        className="px-3 py-1 rounded-lg bg-brand-600 text-white font-bold text-[11px] hover:bg-brand-700 transition"
                                                    >
                                                        + Create FAQ
                                                    </button>
                                                    <button
                                                        onClick={() => handleResolveGap(gap.id)}
                                                        className="px-2 py-1 rounded-lg text-neutral-400 hover:text-neutral-600 text-[11px]"
                                                    >
                                                        Dismiss
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}

                {/* ─── TAB 3: Business Profile ─── */}
                {activeTab === 'business' && (
                    <div className="bg-white dark:bg-neutral-800/60 rounded-2xl border border-neutral-200 dark:border-neutral-700/60 p-6 space-y-6">
                        <div>
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                <Building2 className="w-4 h-4 text-brand-600" /> Core Business Information
                            </h3>
                            <p className="text-xs text-neutral-500 mt-0.5">
                                High-priority baseline knowledge used by all connected AI Voice, WhatsApp, and Web agents.
                            </p>
                        </div>

                        <form onSubmit={handleSaveBusiness} className="space-y-4 text-xs">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Company / Brand Name</label>
                                    <input
                                        type="text"
                                        value={bData.name}
                                        onChange={e => setBData('name', e.target.value)}
                                        className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white focus:outline-none"
                                        placeholder="e.g. Acme Corporation"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Industry / Sector</label>
                                    <input
                                        type="text"
                                        value={bData.industry}
                                        onChange={e => setBData('industry', e.target.value)}
                                        className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white focus:outline-none"
                                        placeholder="e.g. B2B SaaS & Automation"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Company Description & Value Proposition</label>
                                <textarea
                                    value={bData.description}
                                    onChange={e => setBData('description', e.target.value)}
                                    rows={3}
                                    className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white focus:outline-none"
                                    placeholder="Explain what your company does and who it helps..."
                                />
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Business Operating Hours</label>
                                    <input
                                        type="text"
                                        value={bData.business_hours}
                                        onChange={e => setBData('business_hours', e.target.value)}
                                        className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white focus:outline-none"
                                        placeholder="Mon - Fri: 9am - 6pm EST"
                                    />
                                </div>
                                <div>
                                    <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Contact Phone</label>
                                    <input
                                        type="text"
                                        value={bData.phone}
                                        onChange={e => setBData('phone', e.target.value)}
                                        className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white focus:outline-none"
                                        placeholder="+1 (555) 000-0000"
                                    />
                                </div>
                                <div>
                                    <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Support Email</label>
                                    <input
                                        type="email"
                                        value={bData.email}
                                        onChange={e => setBData('email', e.target.value)}
                                        className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white focus:outline-none"
                                        placeholder="support@example.com"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Return & Refund Policy</label>
                                    <textarea
                                        value={bData.return_policy}
                                        onChange={e => setBData('return_policy', e.target.value)}
                                        rows={2}
                                        className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white focus:outline-none"
                                        placeholder="Explain warranty, cancellation, and refund rules..."
                                    />
                                </div>
                                <div>
                                    <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Accepted Payment Methods</label>
                                    <textarea
                                        value={bData.payment_info}
                                        onChange={e => setBData('payment_info', e.target.value)}
                                        rows={2}
                                        className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white focus:outline-none"
                                        placeholder="Credit Card, Wire Transfer, PayPal, Stripe..."
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end pt-2">
                                <button
                                    type="submit"
                                    disabled={bProcessing}
                                    className="px-5 py-2 rounded-xl bg-brand-600 text-white font-bold hover:bg-brand-700 disabled:opacity-50 transition"
                                >
                                    {bProcessing ? 'Saving & Indexing...' : 'Save & Index Profile'}
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* ─── TAB 4: Answer Policy & Settings ─── */}
                {activeTab === 'settings' && (
                    <div className="bg-white dark:bg-neutral-800/60 rounded-2xl border border-neutral-200 dark:border-neutral-700/60 p-6 space-y-6">
                        <div>
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                <Sliders className="w-4 h-4 text-brand-600" /> Answer Policy & Anti-Hallucination Guardrails
                            </h3>
                            <p className="text-xs text-neutral-500 mt-0.5">
                                Ensure your AI agents never fabricate prices, policies, or commitments.
                            </p>
                        </div>

                        <form onSubmit={handleSaveSettings} className="space-y-5 text-xs max-w-2xl">
                            <div className="space-y-2">
                                <label className="block font-semibold text-neutral-800 dark:text-neutral-200">Knowledge Retrieval Policy</label>
                                <div className="space-y-2">
                                    <label className="flex items-start gap-3 p-3 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50/50 dark:bg-neutral-900/40 cursor-pointer">
                                        <input
                                            type="radio"
                                            name="answer_policy"
                                            value="strict_kb_only"
                                            checked={sData.answer_policy === 'strict_kb_only'}
                                            onChange={e => setSData('answer_policy', e.target.value)}
                                            className="mt-0.5 text-brand-600"
                                        />
                                        <div>
                                            <span className="font-bold text-neutral-900 dark:text-white block">Strict: Answer only from Knowledge Base (Recommended)</span>
                                            <span className="text-[11px] text-neutral-500 block mt-0.5">
                                                The AI will exclusively answer using facts found in your uploaded documents, FAQs, and website. If missing, it will politely offer human agent handoff.
                                            </span>
                                        </div>
                                    </label>

                                    <label className="flex items-start gap-3 p-3 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50/50 dark:bg-neutral-900/40 cursor-pointer">
                                        <input
                                            type="radio"
                                            name="answer_policy"
                                            value="general_allowed"
                                            checked={sData.answer_policy === 'general_allowed'}
                                            onChange={e => setSData('answer_policy', e.target.value)}
                                            className="mt-0.5 text-brand-600"
                                        />
                                        <div>
                                            <span className="font-bold text-neutral-900 dark:text-white block">Hybrid: Allow general AI knowledge fallback</span>
                                            <span className="text-[11px] text-neutral-500 block mt-0.5">
                                                Prioritizes your knowledge base, but uses model general knowledge for general conversation or casual greetings.
                                            </span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <label className="block font-semibold text-neutral-800 dark:text-neutral-200">Source Citations</label>
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={sData.allow_citations}
                                        onChange={e => setSData('allow_citations', e.target.checked)}
                                        className="rounded text-brand-600"
                                    />
                                    <span className="text-neutral-700 dark:text-neutral-300">
                                        Attach internal document / FAQ source references to generated responses.
                                    </span>
                                </label>
                            </div>

                            <div className="space-y-1">
                                <label className="block font-semibold text-neutral-800 dark:text-neutral-200">No-Knowledge Fallback Message</label>
                                <textarea
                                    value={sData.fallback_message}
                                    onChange={e => setSData('fallback_message', e.target.value)}
                                    rows={3}
                                    className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white focus:outline-none"
                                />
                                <p className="text-[11px] text-neutral-400">Spoken/Sent when the customer asks a question outside your knowledge base.</p>
                            </div>

                            <div className="pt-2">
                                <button
                                    type="submit"
                                    disabled={sProcessing}
                                    className="px-5 py-2 rounded-xl bg-brand-600 text-white font-bold hover:bg-brand-700 disabled:opacity-50 transition"
                                >
                                    {sProcessing ? 'Saving...' : 'Save Settings'}
                                </button>
                            </div>
                        </form>
                    </div>
                )}
            </div>

            {/* ─── MODAL: 4-in-1 Add Knowledge ─── */}
            {showAddModal && (
                <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
                        <div className="p-4 border-b border-neutral-100 dark:border-neutral-800 flex items-center justify-between">
                            <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                <Plus className="w-4 h-4 text-brand-600" /> Add Knowledge Source
                            </h3>
                            <button onClick={() => setShowAddModal(false)} className="text-neutral-400 hover:text-neutral-600">
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        {/* Modal Tabs */}
                        <div className="grid grid-cols-4 p-2 bg-neutral-50 dark:bg-neutral-800/50 border-b border-neutral-100 dark:border-neutral-800 text-xs">
                            {[
                                { id: 'document', label: '📄 Document' },
                                { id: 'faq', label: '❓ FAQ' },
                                { id: 'website', label: '🌐 Website' },
                                { id: 'text', label: '📝 Plain Text' },
                            ].map(t => (
                                <button
                                    key={t.id}
                                    type="button"
                                    onClick={() => setAddModalType(t.id)}
                                    className={`py-1.5 rounded-lg font-bold transition text-center ${
                                        addModalType === t.id
                                            ? 'bg-white dark:bg-neutral-700 text-brand-600 dark:text-white shadow-2xs'
                                            : 'text-neutral-500 hover:text-neutral-900'
                                    }`}
                                >
                                    {t.label}
                                </button>
                            ))}
                        </div>

                        <div className="p-4 overflow-y-auto flex-1 text-xs">
                            {/* Option 1: Document Upload */}
                            {addModalType === 'document' && (
                                <form onSubmit={handleUploadDoc} className="space-y-3">
                                    <div>
                                        <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Source Title (Optional)</label>
                                        <input
                                            type="text"
                                            value={dData.title}
                                            onChange={e => setDData('title', e.target.value)}
                                            placeholder="e.g. Enterprise Pricing Guide 2026"
                                            className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800"
                                        />
                                    </div>

                                    <div>
                                        <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Select File (PDF, DOCX, TXT, CSV)</label>
                                        <input
                                            type="file"
                                            accept=".pdf,.docx,.doc,.txt,.csv,.md"
                                            onChange={e => setDData('file', e.target.files[0])}
                                            required
                                            className="w-full text-xs text-neutral-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100"
                                        />
                                    </div>

                                    <div className="flex justify-end gap-2 pt-3">
                                        <button type="button" onClick={() => setShowAddModal(false)} className="px-3 py-1.5 rounded-xl text-neutral-500 hover:bg-neutral-100">Cancel</button>
                                        <button type="submit" disabled={dProcessing || !dData.file} className="px-4 py-1.5 rounded-xl bg-brand-600 text-white font-bold hover:bg-brand-700 disabled:opacity-50">
                                            {dProcessing ? 'Uploading & Indexing...' : 'Upload & Process'}
                                        </button>
                                    </div>
                                </form>
                            )}

                            {/* Option 2: FAQ */}
                            {addModalType === 'faq' && (
                                <form onSubmit={handleSaveFaq} className="space-y-3">
                                    <div>
                                        <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Question</label>
                                        <input
                                            type="text"
                                            value={fData.question}
                                            onChange={e => setFData('question', e.target.value)}
                                            placeholder="e.g. What is your WhatsApp API volume pricing?"
                                            required
                                            className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 font-medium"
                                        />
                                    </div>

                                    <div>
                                        <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Answer</label>
                                        <textarea
                                            value={fData.answer}
                                            onChange={e => setFData('answer', e.target.value)}
                                            rows={4}
                                            placeholder="Provide the exact factual answer..."
                                            required
                                            className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800"
                                        />
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Category</label>
                                            <input
                                                type="text"
                                                value={fData.category}
                                                onChange={e => setFData('category', e.target.value)}
                                                placeholder="e.g. Pricing"
                                                className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800"
                                            />
                                        </div>
                                        <div>
                                            <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Priority (1-10)</label>
                                            <input
                                                type="number"
                                                min={1}
                                                max={10}
                                                value={fData.priority}
                                                onChange={e => setFData('priority', Number(e.target.value))}
                                                className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800"
                                            />
                                        </div>
                                    </div>

                                    <div className="flex justify-end gap-2 pt-3">
                                        <button type="button" onClick={() => setShowAddModal(false)} className="px-3 py-1.5 rounded-xl text-neutral-500 hover:bg-neutral-100">Cancel</button>
                                        <button type="submit" disabled={fProcessing} className="px-4 py-1.5 rounded-xl bg-brand-600 text-white font-bold hover:bg-brand-700 disabled:opacity-50">
                                            {fProcessing ? 'Saving...' : 'Save FAQ'}
                                        </button>
                                    </div>
                                </form>
                            )}

                            {/* Option 3: Website */}
                            {addModalType === 'website' && (
                                <form onSubmit={handleImportWebsite} className="space-y-3">
                                    <div>
                                        <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Website URL</label>
                                        <input
                                            type="url"
                                            value={wData.url}
                                            onChange={e => setWData('url', e.target.value)}
                                            placeholder="https://example.com/pricing"
                                            required
                                            className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800"
                                        />
                                    </div>

                                    <div>
                                        <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Crawl Scope</label>
                                        <select
                                            value={wData.crawl_option}
                                            onChange={e => setWData('crawl_option', e.target.value)}
                                            className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800"
                                        >
                                            <option value="page">This exact page only</option>
                                            <option value="selected">Selected linked sections</option>
                                        </select>
                                    </div>

                                    <div className="flex justify-end gap-2 pt-3">
                                        <button type="button" onClick={() => setShowAddModal(false)} className="px-3 py-1.5 rounded-xl text-neutral-500 hover:bg-neutral-100">Cancel</button>
                                        <button type="submit" disabled={wProcessing} className="px-4 py-1.5 rounded-xl bg-brand-600 text-white font-bold hover:bg-brand-700 disabled:opacity-50">
                                            {wProcessing ? 'Importing...' : 'Start Import'}
                                        </button>
                                    </div>
                                </form>
                            )}

                            {/* Option 4: Plain Text */}
                            {addModalType === 'text' && (
                                <form onSubmit={handleSaveText} className="space-y-3">
                                    <div>
                                        <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Title</label>
                                        <input
                                            type="text"
                                            value={tData.title}
                                            onChange={e => setTData('title', e.target.value)}
                                            placeholder="e.g. Sales Policy & Discounts"
                                            required
                                            className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800"
                                        />
                                    </div>

                                    <div>
                                        <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Information Content</label>
                                        <textarea
                                            value={tData.content}
                                            onChange={e => setTData('content', e.target.value)}
                                            rows={6}
                                            placeholder="Paste your business details, terms, services..."
                                            required
                                            className="w-full p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800"
                                        />
                                    </div>

                                    <div className="flex justify-end gap-2 pt-3">
                                        <button type="button" onClick={() => setShowAddModal(false)} className="px-3 py-1.5 rounded-xl text-neutral-500 hover:bg-neutral-100">Cancel</button>
                                        <button type="submit" disabled={tProcessing} className="px-4 py-1.5 rounded-xl bg-brand-600 text-white font-bold hover:bg-brand-700 disabled:opacity-50">
                                            {tProcessing ? 'Saving...' : 'Save Knowledge'}
                                        </button>
                                    </div>
                                </form>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* ─── MODAL: Chunks Inspector ─── */}
            {selectedDocForChunks && (
                <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[85vh]">
                        <div className="p-4 border-b border-neutral-100 dark:border-neutral-800 flex items-center justify-between">
                            <div>
                                <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                    <FileCode className="w-4 h-4 text-brand-600" /> Chunks Inspector: {selectedDocForChunks.title}
                                </h3>
                                <p className="text-[11px] text-neutral-400">
                                    {selectedDocForChunks.chunks?.length || 0} indexed semantic chunks
                                </p>
                            </div>
                            <button onClick={() => setSelectedDocForChunks(null)} className="text-neutral-400 hover:text-neutral-600">
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <div className="p-4 overflow-y-auto space-y-3 flex-1 text-xs">
                            {(!selectedDocForChunks.chunks || selectedDocForChunks.chunks.length === 0) ? (
                                <p className="text-center text-neutral-400 py-6">No chunks found for this document.</p>
                            ) : (
                                selectedDocForChunks.chunks.map((chunk, idx) => (
                                    <div key={chunk.id || idx} className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/70 border border-neutral-200 dark:border-neutral-700 space-y-1">
                                        <div className="flex items-center justify-between text-[10px] font-bold text-neutral-400 uppercase tracking-wider">
                                            <span>Chunk #{chunk.ord !== undefined ? chunk.ord + 1 : idx + 1}</span>
                                            <span>{chunk.tokens || '~'} tokens</span>
                                        </div>
                                        <p className="text-neutral-700 dark:text-neutral-200 whitespace-pre-wrap leading-relaxed">
                                            {chunk.content}
                                        </p>
                                    </div>
                                ))
                            )}
                        </div>

                        <div className="p-3 border-t border-neutral-100 dark:border-neutral-800 flex justify-end">
                            <button
                                onClick={() => setSelectedDocForChunks(null)}
                                className="px-4 py-1.5 rounded-xl bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-semibold text-xs hover:bg-neutral-200"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ─── MODAL: Assign AI Agents ─── */}
            {selectedDocForAgents && (
                <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col">
                        <div className="p-4 border-b border-neutral-100 dark:border-neutral-800 flex items-center justify-between">
                            <div>
                                <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                                    <Users className="w-4 h-4 text-purple-600" /> Assign AI Agents
                                </h3>
                                <p className="text-[11px] text-neutral-400 truncate max-w-xs">{selectedDocForAgents.title}</p>
                            </div>
                            <button onClick={() => setSelectedDocForAgents(null)} className="text-neutral-400 hover:text-neutral-600">
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <div className="p-4 space-y-2 text-xs max-h-72 overflow-y-auto">
                            <p className="text-neutral-500 text-[11px] mb-2">
                                Select which AI agents are authorized to retrieve information from this source. Leave unchecked to allow all agents.
                            </p>

                            {availableAgents.length === 0 ? (
                                <p className="text-neutral-400 italic py-3 text-center">No AI agents found in this workspace.</p>
                            ) : (
                                availableAgents.map(ag => {
                                    const isChecked = selectedAgentIds.includes(String(ag.id));
                                    return (
                                        <label key={ag.id} className="flex items-center justify-between p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50/50 dark:bg-neutral-800/50 cursor-pointer hover:bg-neutral-100">
                                            <div className="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    checked={isChecked}
                                                    onChange={() => {
                                                        const idStr = String(ag.id);
                                                        setSelectedAgentIds(prev =>
                                                            prev.includes(idStr) ? prev.filter(x => x !== idStr) : [...prev, idStr]
                                                        );
                                                    }}
                                                    className="rounded text-brand-600"
                                                />
                                                <span className="font-semibold text-neutral-800 dark:text-neutral-200">{ag.name}</span>
                                            </div>
                                            <span className="text-[10px] uppercase font-bold text-neutral-400">{ag.type}</span>
                                        </label>
                                    );
                                })
                            )}
                        </div>

                        <div className="p-3 border-t border-neutral-100 dark:border-neutral-800 flex justify-end gap-2">
                            <button
                                onClick={() => setSelectedDocForAgents(null)}
                                className="px-3 py-1.5 rounded-xl text-neutral-500 hover:bg-neutral-100 text-xs"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handleSaveAgentAssignments}
                                className="px-4 py-1.5 rounded-xl bg-brand-600 text-white font-bold text-xs hover:bg-brand-700"
                            >
                                Save Assignments
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </ClientLayout>
    );
}
