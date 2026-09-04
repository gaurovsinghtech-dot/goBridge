import { useState, useEffect, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    CheckCircle2, Circle, AlertTriangle, ArrowRight, ArrowLeft,
    Building2, Phone, MessageSquare, PhoneCall, Bot, BookOpen,
    PlayCircle, Rocket, RefreshCw, Sparkles, Check, Globe, Clock,
    HelpCircle, Send, CheckCheck, UploadCloud, ChevronRight, ShieldCheck,
    Cpu, Star, ExternalLink, Zap, Share2, Workflow, ArrowLeftRight,
    FlaskConical, CheckCircle, Lock, MessageCircle, Layers
} from 'lucide-react';
import { Button, Input, Card, Badge } from '@/Components/ui';
import TimezonePicker from '@/Components/TimezonePicker';
import { toast } from 'sonner';
import axios from 'axios';

const COUNTRY_OPTIONS = [
    { code: 'IN', name: 'India (+91)', currency: 'INR (₹)' },
    { code: 'US', name: 'United States (+1)', currency: 'USD ($)' },
    { code: 'GB', name: 'United Kingdom (+44)', currency: 'GBP (£)' },
    { code: 'AE', name: 'United Arab Emirates (+971)', currency: 'AED' },
];

const AGENT_PURPOSES = [
    { key: 'sales', label: 'Sales & Lead Gen', desc: 'Qualify leads, share pricing, and close deals' },
    { key: 'support', label: 'Customer Support', desc: 'Resolve queries, handle FAQs, and create tickets' },
    { key: 'receptionist', label: 'Virtual Receptionist', desc: 'Answer chats/calls, route visitors, take messages' },
    { key: 'appointment', label: 'Appointment Booking', desc: 'Schedule meetings and send reminders' },
    { key: 'custom', label: 'Custom Assistant', desc: 'Tailored AI for your unique business workflows' },
];

const AI_ENGINES = [
    { key: 'openai', name: 'OpenAI GPT-4o', desc: 'High intelligence & natural multi-turn conversations' },
    { key: 'gemini', name: 'Google Gemini 1.5', desc: 'Ultra-fast speed & high context processing' },
    { key: 'claude', name: 'Anthropic Claude 3.5', desc: 'Nuanced reasoning & articulate customer support' },
];

const CRM_OPTIONS = [
    { key: 'hubspot', name: 'HubSpot', badge: 'bg-[#FF7A59]', desc: 'Contacts, deals, and communication timeline notes' },
    { key: 'salesforce', name: 'Salesforce', badge: 'bg-[#00A1E0]', desc: 'Enterprise contacts, leads, and call task logs' },
    { key: 'zoho', name: 'Zoho CRM', badge: 'bg-[#E42528]', desc: 'Contacts, leads, and interaction activities' },
    { key: 'pipedrive', name: 'Pipedrive', badge: 'bg-[#029b35]', desc: 'Persons, leads, deals, and activity tracking' },
    { key: 'freshsales', name: 'Freshsales', badge: 'bg-[#0081FE]', desc: 'Contacts, accounts, and call activity logs' },
    { key: 'dynamics', name: 'Dynamics 365', badge: 'bg-[#0078D4]', desc: 'Dataverse contacts and automated task logs' },
    { key: 'gohighlevel', name: 'GoHighLevel', badge: 'bg-[#1A56DB]', desc: 'Sub-account contacts, conversations & notes' },
    { key: 'custom', name: 'Custom CRM API', badge: 'bg-indigo-600', desc: 'Proprietary or in-house CRM via REST endpoints' },
];

export default function OnboardingWizard({
    progress,
    provisionedNumbers = [],
    wabas = [],
    voiceAgents = [],
    aiAgents = [],
    currencies = [],
    crmConnections = [],
    metaAppId = '',
    user = {},
}) {
    const steps = progress?.steps || [];
    const currentStepKey = progress?.current_step_key || 'account';
    const [activeKey, setActiveKey] = useState(currentStepKey);

    // Sync active step when progress changes
    useEffect(() => {
        if (progress?.current_step_key) {
            setActiveKey(progress.current_step_key);
        }
    }, [progress?.current_step_key]);

    const activeStepIndex = steps.findIndex(s => s.key === activeKey);
    const activeStep = steps[activeStepIndex] || steps[0];

    // Selected Service (whatsapp_only vs whatsapp_voice)
    const [selectedService, setSelectedService] = useState(progress?.service_type || 'whatsapp_only');
    const [isSavingService, setIsSavingService] = useState(false);

    // Local states for forms
    const [country, setCountry] = useState('IN');
    const [availableNumbers, setAvailableNumbers] = useState([]);
    const [selectedNumber, setSelectedNumber] = useState(provisionedNumbers[0]?.phone_number || '');
    const [isSearchingNumbers, setIsSearchingNumbers] = useState(false);
    const [isProvisioning, setIsProvisioning] = useState(false);
    const [provisionError, setProvisionError] = useState('');

    // Step WhatsApp state
    const [wabaData, setWabaData] = useState({
        waba_id: wabas[0]?.waba_id || '',
        phone_number_id: wabas[0]?.phone_number_id || '',
        phone_number: selectedNumber || '',
    });
    const [isConnectingWhatsApp, setIsConnectingWhatsApp] = useState(false);

    // Step Calling state (WhatsApp + Voice only)
    const [isConfiguringCalling, setIsConfiguringCalling] = useState(false);
    const [callingVerified, setCallingVerified] = useState(false);

    // Step AI state
    const [selectedAiEngine, setSelectedAiEngine] = useState('openai');
    const [agentData, setAgentData] = useState({
        name: 'Growbridge Assistant',
        purpose: 'sales',
        language: 'en',
        tone: 'professional',
        welcome_message: 'Hi there! Welcome to Growbridge Connect. How can I help you today?',
    });
    const [isCreatingAgent, setIsCreatingAgent] = useState(false);

    // Step CRM state (Optional)
    const [selectedCrm, setSelectedCrm] = useState('hubspot');
    const [crmCreds, setCrmCreds] = useState({});
    const [isConnectingCrm, setIsConnectingCrm] = useState(false);
    const [isTestingCrm, setIsTestingCrm] = useState(false);
    const [crmTestResult, setCrmTestResult] = useState(null);

    // Step Business state
    const [businessData, setBusinessData] = useState({
        name: progress?.workspace?.name || user?.company_name || 'My Business',
        industry: progress?.workspace?.industry || 'E-Commerce / Retail',
        website: progress?.workspace?.website || 'https://growbridge.co.in',
        country: progress?.workspace?.country || 'India',
        timezone: progress?.workspace?.timezone || 'Asia/Kolkata',
        currency_code: progress?.workspace?.currency_code || 'INR',
    });
    const [isSavingBusiness, setIsSavingBusiness] = useState(false);

    // Step Launch state
    const [isLaunching, setIsLaunching] = useState(false);

    // Load available numbers on mount or country change (voice plans)
    const searchNumbers = async (selectedCountry = country) => {
        setIsSearchingNumbers(true);
        setProvisionError('');
        try {
            const res = await axios.post(route('client.onboarding.numbers.search'), { country: selectedCountry });
            if (res.data.success && res.data.numbers) {
                setAvailableNumbers(res.data.numbers);
                if (res.data.numbers.length > 0 && !selectedNumber) {
                    setSelectedNumber(res.data.numbers[0].phone_number);
                }
            }
        } catch (err) {
            setProvisionError(err.response?.data?.message || 'Failed to fetch available numbers.');
        } finally {
            setIsSearchingNumbers(false);
        }
    };

    useEffect(() => {
        if (activeKey === 'phone' && availableNumbers.length === 0) {
            searchNumbers(country);
        }
    }, [activeKey, country]);

    // Handle Choose Service
    const handleSelectService = async (serviceType) => {
        setSelectedService(serviceType);
        setIsSavingService(true);
        try {
            const res = await axios.post(route('client.onboarding.service'), {
                service_type: serviceType,
            });
            if (res.data.success) {
                toast.success(res.data.message);
                router.reload({
                    only: ['progress'],
                    onSuccess: () => {
                        const nextStepKey = serviceType === 'whatsapp_voice' ? 'phone' : 'whatsapp';
                        setActiveKey(nextStepKey);
                    },
                });
            }
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save service selection.');
        } finally {
            setIsSavingService(false);
        }
    };

    // Handle Provision Twilio Number (Voice step)
    const handleProvisionNumber = async (num) => {
        setIsProvisioning(true);
        setProvisionError('');
        try {
            const res = await axios.post(route('client.onboarding.numbers.provision'), {
                phone_number: num,
                country: country,
            });
            if (res.data.success) {
                setSelectedNumber(res.data.number?.phone_number || num);
                toast.success(res.data.message || 'Phone line provisioned successfully!');
                router.reload({
                    only: ['progress', 'provisionedNumbers'],
                    onSuccess: () => setActiveKey('whatsapp'),
                });
            }
        } catch (err) {
            setProvisionError(err.response?.data?.message || 'Failed to provision number.');
            toast.error('Provisioning failed. ' + (err.response?.data?.message || ''));
        } finally {
            setIsProvisioning(false);
        }
    };

    // Handle Connect WhatsApp
    const handleConnectWhatsApp = async () => {
        setIsConnectingWhatsApp(true);
        try {
            const res = await axios.post(route('client.onboarding.whatsapp.connect'), {
                waba_id: wabaData.waba_id,
                phone_number_id: wabaData.phone_number_id,
                phone_number: wabaData.phone_number || selectedNumber,
            });
            if (res.data.success) {
                toast.success(res.data.message);
                router.reload({
                    only: ['progress', 'wabas'],
                    onSuccess: () => {
                        if (selectedService === 'whatsapp_voice') {
                            setActiveKey('calling');
                        } else {
                            setActiveKey('ai_agent');
                        }
                    },
                });
            }
        } catch (err) {
            toast.error(err.response?.data?.message || 'WhatsApp connection failed.');
        } finally {
            setIsConnectingWhatsApp(false);
        }
    };

    // Handle Configure Calling (Voice only)
    const handleConfigureCalling = async () => {
        setIsConfiguringCalling(true);
        try {
            const res = await axios.post(route('client.onboarding.calling.configure'), {
                phone_number: selectedNumber,
            });
            if (res.data.success) {
                setCallingVerified(true);
                toast.success(res.data.message);
                router.reload({
                    only: ['progress'],
                    onSuccess: () => setActiveKey('ai_agent'),
                });
            }
        } catch (err) {
            toast.error(err.response?.data?.message || 'Calling configuration failed.');
        } finally {
            setIsConfiguringCalling(false);
        }
    };

    // Handle Create AI Agent
    const handleCreateAiAgent = async () => {
        setIsCreatingAgent(true);
        try {
            const res = await axios.post(route('client.onboarding.ai-agent'), {
                ...agentData,
                engine: selectedAiEngine,
            });
            if (res.data.success) {
                toast.success(res.data.message);
                router.reload({
                    only: ['progress', 'aiAgents'],
                    onSuccess: () => {
                        if (selectedService === 'whatsapp_voice') {
                            setActiveKey('crm');
                        } else {
                            setActiveKey('business');
                        }
                    },
                });
            }
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to create AI agent.');
        } finally {
            setIsCreatingAgent(false);
        }
    };

    // Handle CRM Test & Connect
    const handleTestCrm = async () => {
        setIsTestingCrm(true);
        setCrmTestResult(null);
        try {
            const res = await axios.post(route('client.crm.integrations.test', selectedCrm), {
                credentials: crmCreds,
            });
            setCrmTestResult(res.data);
            if (res.data.ok) {
                toast.success('CRM connection verified!');
            } else {
                toast.error(res.data.message || 'CRM test failed.');
            }
        } catch (e) {
            setCrmTestResult({ ok: false, message: e?.response?.data?.message || 'Test failed' });
        } finally {
            setIsTestingCrm(false);
        }
    };

    const handleConnectCrm = async () => {
        setIsConnectingCrm(true);
        try {
            const res = await axios.post(route('client.onboarding.crm.save'), {
                provider: selectedCrm,
                credentials: crmCreds,
            });
            if (res.data.success) {
                toast.success(res.data.message || 'CRM integrated successfully!');
                router.reload({
                    only: ['progress', 'crmConnections'],
                    onSuccess: () => setActiveKey('business'),
                });
            }
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save CRM credentials.');
        } finally {
            setIsConnectingCrm(false);
        }
    };

    const handleSkipCrm = async () => {
        try {
            await axios.post(route('client.onboarding.crm.skip'));
            router.reload({
                only: ['progress'],
                onSuccess: () => setActiveKey('business'),
            });
        } catch {
            setActiveKey('business');
        }
    };

    // Handle Save Business Profile
    const handleSaveBusiness = async () => {
        setIsSavingBusiness(true);
        try {
            const res = await axios.post(route('client.onboarding.business'), businessData);
            if (res.data.success) {
                toast.success(res.data.message);
                router.reload({
                    only: ['progress'],
                    onSuccess: () => setActiveKey('launch'),
                });
            }
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save business details.');
        } finally {
            setIsSavingBusiness(false);
        }
    };

    // Handle Launch
    const handleLaunch = async () => {
        setIsLaunching(true);
        try {
            const res = await axios.post(route('client.onboarding.launch'));
            if (res.data.success) {
                toast.success(res.data.message);
                if (res.data.redirect) {
                    window.location.href = res.data.redirect;
                } else {
                    router.visit(route('client.dashboard'));
                }
            }
        } catch (err) {
            toast.error(err.response?.data?.message || 'Account launch blocked. Please verify all steps.');
        } finally {
            setIsLaunching(false);
        }
    };

    return (
        <div className="min-h-screen bg-neutral-950 text-neutral-100 flex flex-col font-sans selection:bg-brand-500 selection:text-white">
            <Head title="Account Setup · Growbridge Connect" />

            {/* Top Navigation Bar */}
            <header className="border-b border-neutral-800/80 bg-neutral-900/60 backdrop-blur-md sticky top-0 z-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="h-9 w-9 rounded-xl bg-brand-600 flex items-center justify-center font-bold text-white shadow-lg shadow-brand-600/30">
                            G
                        </div>
                        <div>
                            <span className="font-bold text-base text-white tracking-tight">Growbridge Connect</span>
                            <span className="text-[10px] text-neutral-400 block -mt-0.5">Plan-Based Onboarding Wizard</span>
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <div className="text-right hidden sm:block">
                            <span className="text-xs text-neutral-400">Step {activeStepIndex + 1} of {steps.length}</span>
                            <div className="text-xs font-bold text-brand-400">{progress?.percent || 0}% Completed</div>
                        </div>
                        <div className="w-24 sm:w-32 h-2 rounded-full bg-neutral-800 overflow-hidden">
                            <div
                                className="h-full bg-brand-500 rounded-full transition-all duration-500 shadow-sm"
                                style={{ width: `${progress?.percent || 0}%` }}
                            />
                        </div>
                    </div>
                </div>
            </header>

            {/* Main Content Layout */}
            <main className="flex-1 max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 grid grid-cols-1 lg:grid-cols-12 gap-8">
                {/* Left Sidebar: Dynamic Steps Pipeline */}
                <aside className="lg:col-span-4 space-y-4">
                    <div className="rounded-2xl border border-neutral-800 bg-neutral-900/50 backdrop-blur-sm p-5 space-y-4">
                        <div className="flex items-center justify-between border-b border-neutral-800 pb-3">
                            <h2 className="text-xs font-bold uppercase tracking-wider text-neutral-400">
                                {selectedService === 'whatsapp_voice' ? 'WhatsApp + Voice Setup' : 'WhatsApp API Setup'}
                            </h2>
                            <span className="text-[11px] font-semibold text-brand-400">
                                {progress?.done || 1} / {steps.length} Done
                            </span>
                        </div>

                        <div className="space-y-1.5">
                            {steps.map((step, idx) => {
                                const isCurrent = step.key === activeKey;
                                const isDone = step.completed;
                                const stepIcons = {
                                    account: <ShieldCheck className="h-4 w-4" />,
                                    choose_service: <Layers className="h-4 w-4" />,
                                    phone: <Phone className="h-4 w-4" />,
                                    whatsapp: <MessageSquare className="h-4 w-4" />,
                                    calling: <PhoneCall className="h-4 w-4" />,
                                    ai_agent: <Bot className="h-4 w-4" />,
                                    crm: <Share2 className="h-4 w-4" />,
                                    business: <Building2 className="h-4 w-4" />,
                                    launch: <Rocket className="h-4 w-4" />,
                                };

                                return (
                                    <button
                                        key={step.key}
                                        type="button"
                                        onClick={() => setActiveKey(step.key)}
                                        className={`w-full text-left p-3 rounded-xl flex items-center justify-between gap-3 transition-all duration-200 ${
                                            isCurrent
                                                ? 'bg-brand-950/60 border border-brand-500/50 text-white shadow-sm'
                                                : isDone
                                                    ? 'hover:bg-neutral-800/50 text-neutral-300'
                                                    : 'opacity-60 hover:opacity-100 text-neutral-400'
                                        }`}
                                    >
                                        <div className="flex items-center gap-3 min-w-0">
                                            <div className={`h-8 w-8 rounded-lg flex items-center justify-center shrink-0 ${
                                                isDone
                                                    ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                                                    : isCurrent
                                                        ? 'bg-brand-500 text-white shadow-md shadow-brand-500/30'
                                                        : 'bg-neutral-800 text-neutral-400'
                                            }`}>
                                                {isDone ? <Check className="h-4 w-4 stroke-[3]" /> : stepIcons[step.key] || idx + 1}
                                            </div>
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-1.5">
                                                    <p className={`text-xs font-bold truncate ${isCurrent ? 'text-brand-300' : 'text-neutral-200'}`}>
                                                        Step {idx + 1}: {step.title}
                                                    </p>
                                                    {!step.required && (
                                                        <span className="text-[9px] px-1.5 py-0.2 rounded bg-neutral-800 text-neutral-400 uppercase font-bold">
                                                            Optional
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="text-[11px] text-neutral-500 truncate">{step.subtitle}</p>
                                            </div>
                                        </div>

                                        {isDone && (
                                            <span className="h-2 w-2 rounded-full bg-emerald-400 shrink-0 shadow-sm shadow-emerald-400" />
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </aside>

                {/* Right Form Card */}
                <section className="lg:col-span-8">
                    <div className="rounded-2xl border border-neutral-800 bg-neutral-900/60 backdrop-blur-sm p-6 sm:p-8 space-y-6 shadow-xl">
                        {/* Step Header */}
                        <div className="border-b border-neutral-800 pb-5">
                            <div className="flex items-center gap-2">
                                <span className="text-[11px] font-bold uppercase tracking-wider text-brand-400 bg-brand-950/50 border border-brand-800/40 px-2.5 py-0.5 rounded-full">
                                    Step {activeStepIndex + 1} of {steps.length}
                                </span>
                                {activeStep?.completed && (
                                    <span className="text-[11px] font-bold text-emerald-400 bg-emerald-950/50 border border-emerald-800/40 px-2.5 py-0.5 rounded-full flex items-center gap-1">
                                        <CheckCircle2 className="h-3 w-3" /> Completed
                                    </span>
                                )}
                            </div>
                            <h3 className="text-xl sm:text-2xl font-bold text-white mt-2">
                                {activeStep?.title}
                            </h3>
                            <p className="text-sm text-neutral-400 mt-1">
                                {activeStep?.subtitle}
                            </p>
                        </div>

                        {/* STEP 1: CREATE ACCOUNT */}
                        {activeKey === 'account' && (
                            <div className="space-y-6">
                                <div className="p-4 rounded-xl bg-emerald-950/30 border border-emerald-800/40 flex items-start gap-3.5">
                                    <CheckCircle2 className="h-5 w-5 text-emerald-400 shrink-0 mt-0.5" />
                                    <div>
                                        <h4 className="text-sm font-bold text-emerald-300">Account Created & Verified</h4>
                                        <p className="text-xs text-emerald-400/80 mt-0.5">
                                            Signed in as <strong>{user?.name || 'Owner'}</strong> ({user?.email}). Your tenant workspace is ready for setup.
                                        </p>
                                    </div>
                                </div>

                                <div className="grid sm:grid-cols-2 gap-4 text-xs">
                                    <div className="p-3.5 rounded-xl border border-neutral-800 bg-neutral-950/50 space-y-1">
                                        <span className="text-neutral-500 uppercase font-bold text-[10px]">Company Name</span>
                                        <p className="font-semibold text-neutral-200">{user?.company_name || 'My Company'}</p>
                                    </div>
                                    <div className="p-3.5 rounded-xl border border-neutral-800 bg-neutral-950/50 space-y-1">
                                        <span className="text-neutral-500 uppercase font-bold text-[10px]">User Role</span>
                                        <p className="font-semibold text-neutral-200">Workspace Administrator</p>
                                    </div>
                                </div>

                                <div className="flex justify-end pt-4">
                                    <button
                                        type="button"
                                        onClick={() => setActiveKey('choose_service')}
                                        className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold transition shadow-lg shadow-brand-600/30"
                                    >
                                        Next: Choose Your Service <ArrowRight className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* STEP 2: CHOOSE SERVICE (WHATSAPP VS WHATSAPP + VOICE) */}
                        {activeKey === 'choose_service' && (
                            <div className="space-y-6">
                                <div className="space-y-1">
                                    <h4 className="text-sm font-bold text-white">Select How You Plan to Use Growbridge Connect</h4>
                                    <p className="text-xs text-neutral-400">
                                        Choose your service model. You can seamlessly upgrade your plan and unlock voice calling at any time.
                                    </p>
                                </div>

                                <div className="grid sm:grid-cols-2 gap-5">
                                    {/* Option 1: WhatsApp API */}
                                    <div
                                        onClick={() => setSelectedService('whatsapp_only')}
                                        className={`p-5 rounded-2xl border-2 transition-all cursor-pointer flex flex-col justify-between relative ${
                                            selectedService === 'whatsapp_only'
                                                ? 'border-emerald-500 bg-emerald-950/30 shadow-xl shadow-emerald-500/10'
                                                : 'border-neutral-800 bg-neutral-950 hover:border-neutral-700'
                                        }`}
                                    >
                                        <div className="space-y-3.5">
                                            <div className="flex items-center justify-between">
                                                <div className="h-10 w-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                                                    <MessageCircle className="h-5 w-5" />
                                                </div>
                                                <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-900/60 text-emerald-300 border border-emerald-700/50">
                                                    RECOMMENDED CORE
                                                </span>
                                            </div>

                                            <div>
                                                <h5 className="font-bold text-base text-white">WhatsApp API</h5>
                                                <p className="text-xs text-neutral-400 mt-1">
                                                    Connect your WhatsApp Business API and use Campaigns, Automations and AI Agents.
                                                </p>
                                            </div>

                                            <div className="border-t border-neutral-800/80 pt-3 space-y-2 text-xs">
                                                <div className="flex items-center gap-2 text-emerald-300">
                                                    <Check className="h-3.5 w-3.5 shrink-0" />
                                                    <span>Official WhatsApp Cloud API</span>
                                                </div>
                                                <div className="flex items-center gap-2 text-emerald-300">
                                                    <Check className="h-3.5 w-3.5 shrink-0" />
                                                    <span>Unified Multi-Agent Inbox</span>
                                                </div>
                                                <div className="flex items-center gap-2 text-emerald-300">
                                                    <Check className="h-3.5 w-3.5 shrink-0" />
                                                    <span>Campaigns & Automations</span>
                                                </div>
                                                <div className="flex items-center gap-2 text-emerald-300">
                                                    <Check className="h-3.5 w-3.5 shrink-0" />
                                                    <span>AI Chatbots & Knowledge Base</span>
                                                </div>
                                                <div className="flex items-center gap-2 text-neutral-500 line-through">
                                                    <Lock className="h-3.5 w-3.5 shrink-0" />
                                                    <span>Twilio Phone Lines & Calling (Locked)</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="mt-5 pt-3">
                                            <div className={`w-full py-2.5 rounded-xl text-xs font-bold text-center transition ${
                                                selectedService === 'whatsapp_only'
                                                    ? 'bg-emerald-600 text-white'
                                                    : 'bg-neutral-800 text-neutral-300'
                                            }`}>
                                                {selectedService === 'whatsapp_only' ? '✓ Selected Plan' : 'Select WhatsApp API'}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Option 2: WhatsApp + Voice & Calling */}
                                    <div
                                        onClick={() => setSelectedService('whatsapp_voice')}
                                        className={`p-5 rounded-2xl border-2 transition-all cursor-pointer flex flex-col justify-between relative ${
                                            selectedService === 'whatsapp_voice'
                                                ? 'border-brand-500 bg-brand-950/30 shadow-xl shadow-brand-500/10'
                                                : 'border-neutral-800 bg-neutral-950 hover:border-neutral-700'
                                        }`}
                                    >
                                        <div className="space-y-3.5">
                                            <div className="flex items-center justify-between">
                                                <div className="h-10 w-10 rounded-xl bg-brand-500/20 text-brand-400 flex items-center justify-center">
                                                    <PhoneCall className="h-5 w-5" />
                                                </div>
                                                <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-brand-900/60 text-brand-300 border border-brand-700/50">
                                                    ALL-IN-ONE SUITE
                                                </span>
                                            </div>

                                            <div>
                                                <h5 className="font-bold text-base text-white">WhatsApp + Voice & Calling</h5>
                                                <p className="text-xs text-neutral-400 mt-1">
                                                    WhatsApp messaging plus Twilio phone numbers, voice calls and AI Voice Agents.
                                                </p>
                                            </div>

                                            <div className="border-t border-neutral-800/80 pt-3 space-y-2 text-xs">
                                                <div className="flex items-center gap-2 text-brand-300">
                                                    <Check className="h-3.5 w-3.5 shrink-0" />
                                                    <span>Everything in WhatsApp API</span>
                                                </div>
                                                <div className="flex items-center gap-2 text-brand-300">
                                                    <Check className="h-3.5 w-3.5 shrink-0" />
                                                    <span>Twilio Virtual Phone Number</span>
                                                </div>
                                                <div className="flex items-center gap-2 text-brand-300">
                                                    <Check className="h-3.5 w-3.5 shrink-0" />
                                                    <span>Inbound & Outbound Calling</span>
                                                </div>
                                                <div className="flex items-center gap-2 text-brand-300">
                                                    <Check className="h-3.5 w-3.5 shrink-0" />
                                                    <span>AI Voice Agents & Studio</span>
                                                </div>
                                                <div className="flex items-center gap-2 text-brand-300">
                                                    <Check className="h-3.5 w-3.5 shrink-0" />
                                                    <span>Smart Calling Queue & Follow-ups</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="mt-5 pt-3">
                                            <div className={`w-full py-2.5 rounded-xl text-xs font-bold text-center transition ${
                                                selectedService === 'whatsapp_voice'
                                                    ? 'bg-brand-600 text-white'
                                                    : 'bg-neutral-800 text-neutral-300'
                                            }`}>
                                                {selectedService === 'whatsapp_voice' ? '✓ Selected Plan' : 'Select WhatsApp + Voice'}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between pt-4 border-t border-neutral-800">
                                    <button
                                        type="button"
                                        onClick={() => setActiveKey('account')}
                                        className="text-xs text-neutral-400 hover:text-neutral-200"
                                    >
                                        ← Back
                                    </button>

                                    <button
                                        type="button"
                                        disabled={isSavingService}
                                        onClick={() => handleSelectService(selectedService)}
                                        className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold transition shadow-lg shadow-brand-600/30 disabled:opacity-50"
                                    >
                                        {isSavingService ? (
                                            <><RefreshCw className="h-4 w-4 animate-spin" /> Saving Selection...</>
                                        ) : (
                                            <>Confirm & Continue <ArrowRight className="h-4 w-4" /></>
                                        )}
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* STEP 3 (VOICE ONLY): CHOOSE TWILIO PHONE NUMBER */}
                        {activeKey === 'phone' && (
                            <div className="space-y-6">
                                <div className="space-y-2">
                                    <label className="block text-xs font-bold uppercase tracking-wider text-neutral-400">
                                        Select Country / Region
                                    </label>
                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                        {COUNTRY_OPTIONS.map(c => (
                                            <button
                                                key={c.code}
                                                type="button"
                                                onClick={() => {
                                                    setCountry(c.code);
                                                    searchNumbers(c.code);
                                                }}
                                                className={`p-3 rounded-xl border text-xs font-semibold text-left transition ${
                                                    country === c.code
                                                        ? 'border-brand-500 bg-brand-950/40 text-white shadow-sm'
                                                        : 'border-neutral-800 bg-neutral-950 text-neutral-400 hover:bg-neutral-900'
                                                }`}
                                            >
                                                <span className="block">{c.name}</span>
                                                <span className="text-[10px] text-neutral-500">{c.currency}</span>
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <label className="block text-xs font-bold uppercase tracking-wider text-neutral-400">
                                        Available Phone Lines
                                    </label>

                                    {isSearchingNumbers ? (
                                        <div className="p-8 text-center text-xs text-neutral-400 rounded-xl border border-neutral-800 bg-neutral-950/50 flex flex-col items-center gap-2">
                                            <RefreshCw className="h-5 w-5 animate-spin text-brand-500" />
                                            <span>Searching available numbers with Voice & SMS capabilities...</span>
                                        </div>
                                    ) : availableNumbers.length > 0 ? (
                                        <div className="grid sm:grid-cols-2 gap-3">
                                            {availableNumbers.map(n => (
                                                <button
                                                    key={n.phone_number}
                                                    type="button"
                                                    onClick={() => setSelectedNumber(n.phone_number)}
                                                    className={`p-3.5 rounded-xl border text-left transition flex items-center justify-between ${
                                                        selectedNumber === n.phone_number
                                                            ? 'border-brand-500 bg-brand-950/50 text-white shadow-md'
                                                            : 'border-neutral-800 bg-neutral-950 text-neutral-300 hover:bg-neutral-900'
                                                    }`}
                                                >
                                                    <div>
                                                        <span className="font-mono font-bold text-sm">{n.phone_number}</span>
                                                        <span className="block text-[10px] text-neutral-400 mt-0.5">
                                                            Voice • SMS • AI Call Agent Ready
                                                        </span>
                                                    </div>
                                                    <div className="text-right">
                                                        <span className="text-xs font-semibold text-brand-400">{n.price || 'Included'}</span>
                                                        {selectedNumber === n.phone_number && (
                                                            <span className="block text-[10px] text-emerald-400 font-bold mt-0.5">SELECTED</span>
                                                        )}
                                                    </div>
                                                </button>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="p-4 rounded-xl border border-neutral-800 bg-neutral-950 text-xs text-neutral-400">
                                            No numbers currently listed. Click Refresh to reload available inventory.
                                        </div>
                                    )}
                                </div>

                                {provisionError && (
                                    <div className="p-3 rounded-xl bg-rose-950/40 border border-rose-800 text-rose-300 text-xs flex items-center gap-2">
                                        <AlertTriangle className="h-4 w-4 shrink-0" />
                                        {provisionError}
                                    </div>
                                )}

                                <div className="flex items-center justify-between pt-4">
                                    <button
                                        type="button"
                                        onClick={() => setActiveKey('choose_service')}
                                        className="text-xs text-neutral-400 hover:text-neutral-200"
                                    >
                                        ← Back to Service Selection
                                    </button>

                                    <div className="flex items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={() => searchNumbers(country)}
                                            className="text-xs text-neutral-400 hover:text-neutral-200 flex items-center gap-1.5 px-3 py-2 rounded-xl border border-neutral-800"
                                        >
                                            <RefreshCw className="h-3.5 w-3.5" /> Refresh
                                        </button>

                                        <button
                                            type="button"
                                            disabled={isProvisioning || !selectedNumber}
                                            onClick={() => handleProvisionNumber(selectedNumber)}
                                            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold transition shadow-lg shadow-brand-600/30 disabled:opacity-50"
                                        >
                                            {isProvisioning ? (
                                                <><RefreshCw className="h-4 w-4 animate-spin" /> Provisioning Line...</>
                                            ) : (
                                                <>Provision & Connect <ArrowRight className="h-4 w-4" /></>
                                            )}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* STEP: CONNECT WHATSAPP */}
                        {activeKey === 'whatsapp' && (
                            <div className="space-y-6">
                                <div className="p-4 rounded-xl bg-blue-950/30 border border-blue-800/40 space-y-2 text-xs text-blue-200">
                                    <div className="flex items-center gap-2 font-bold text-sm text-blue-300">
                                        <MessageSquare className="h-4 w-4 text-blue-400" /> WhatsApp Cloud API Integration
                                    </div>
                                    <p>
                                        Connect your official Meta WhatsApp Business Account (WABA). Your WhatsApp API handles inbox messaging, broadcasts, templates, and automations.
                                    </p>
                                </div>

                                <div className="space-y-4">
                                    <div>
                                        <label className="block text-xs font-bold uppercase tracking-wider text-neutral-400 mb-1">
                                            WhatsApp Business Account ID (WABA ID)
                                        </label>
                                        <Input
                                            value={wabaData.waba_id}
                                            onChange={e => setWabaData({ ...wabaData, waba_id: e.target.value })}
                                            placeholder="e.g. WABA-109283749"
                                            className="bg-neutral-950 border-neutral-700 text-neutral-100 text-xs"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold uppercase tracking-wider text-neutral-400 mb-1">
                                            WhatsApp Phone Number ID
                                        </label>
                                        <Input
                                            value={wabaData.phone_number_id}
                                            onChange={e => setWabaData({ ...wabaData, phone_number_id: e.target.value })}
                                            placeholder="e.g. PHONE-ID-88273619"
                                            className="bg-neutral-950 border-neutral-700 text-neutral-100 text-xs"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold uppercase tracking-wider text-neutral-400 mb-1">
                                            Display Business Phone Number
                                        </label>
                                        <Input
                                            value={wabaData.phone_number || selectedNumber}
                                            onChange={e => setWabaData({ ...wabaData, phone_number: e.target.value })}
                                            placeholder="+919876543210"
                                            className="bg-neutral-950 border-neutral-700 text-neutral-100 text-xs"
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center justify-between pt-4 border-t border-neutral-800">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (selectedService === 'whatsapp_voice') {
                                                setActiveKey('phone');
                                            } else {
                                                setActiveKey('choose_service');
                                            }
                                        }}
                                        className="text-xs text-neutral-400 hover:text-neutral-200"
                                    >
                                        ← Back
                                    </button>

                                    <button
                                        type="button"
                                        disabled={isConnectingWhatsApp}
                                        onClick={handleConnectWhatsApp}
                                        className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition shadow-lg shadow-blue-600/30 disabled:opacity-50"
                                    >
                                        {isConnectingWhatsApp ? (
                                            <><RefreshCw className="h-4 w-4 animate-spin" /> Verifying WABA...</>
                                        ) : (
                                            <>Save & Connect WhatsApp <ArrowRight className="h-4 w-4" /></>
                                        )}
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* STEP (VOICE ONLY): ENABLE CALLING (TWILIO VOICE) */}
                        {activeKey === 'calling' && selectedService === 'whatsapp_voice' && (
                            <div className="space-y-6">
                                <div className="p-4 rounded-xl bg-neutral-950 border border-neutral-800 space-y-3">
                                    <h4 className="text-sm font-bold text-white">Twilio Voice & Call Management</h4>
                                    <p className="text-xs text-neutral-400">
                                        Configure automated voice responses, incoming call forwarding, and real-time AI Voice Agent webhooks.
                                    </p>

                                    <div className="p-3.5 rounded-xl border border-neutral-800 bg-neutral-900 flex items-center justify-between">
                                        <div>
                                            <span className="text-xs font-bold text-neutral-200 block">Primary Calling Line</span>
                                            <span className="text-xs text-neutral-400 font-mono">{selectedNumber || '+919876543210'}</span>
                                        </div>
                                        <span className="px-2.5 py-1 rounded-full bg-emerald-950/60 border border-emerald-800/60 text-emerald-400 text-[10px] font-bold">
                                            VOICE ENABLED
                                        </span>
                                    </div>
                                </div>

                                <div className="grid sm:grid-cols-2 gap-3 text-xs">
                                    <div className="p-3 rounded-xl border border-neutral-800 bg-neutral-950/50">
                                        <span className="text-neutral-400 font-bold block mb-1">Inbound Voice Webhook</span>
                                        <code className="text-[10px] text-brand-400">/api/v1/webhooks/twilio/voice</code>
                                    </div>
                                    <div className="p-3 rounded-xl border border-neutral-800 bg-neutral-950/50">
                                        <span className="text-neutral-400 font-bold block mb-1">Status Callback Webhook</span>
                                        <code className="text-[10px] text-brand-400">/api/v1/webhooks/twilio/status</code>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between pt-4">
                                    <button
                                        type="button"
                                        onClick={() => setActiveKey('whatsapp')}
                                        className="text-xs text-neutral-400 hover:text-neutral-200"
                                    >
                                        ← Back
                                    </button>

                                    <button
                                        type="button"
                                        disabled={isConfiguringCalling}
                                        onClick={handleConfigureCalling}
                                        className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold transition shadow-lg shadow-brand-600/30 disabled:opacity-50"
                                    >
                                        {isConfiguringCalling ? (
                                            <><RefreshCw className="h-4 w-4 animate-spin" /> Enabling Voice...</>
                                        ) : (
                                            <>Enable Voice & Continue <ArrowRight className="h-4 w-4" /></>
                                        )}
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* STEP: CONFIGURE AI */}
                        {activeKey === 'ai_agent' && (
                            <div className="space-y-6">
                                <div className="space-y-3">
                                    <label className="block text-xs font-bold uppercase tracking-wider text-neutral-400">
                                        Choose Default AI Engine
                                    </label>
                                    <div className="grid sm:grid-cols-3 gap-3">
                                        {AI_ENGINES.map(engine => (
                                            <button
                                                key={engine.key}
                                                type="button"
                                                onClick={() => setSelectedAiEngine(engine.key)}
                                                className={`p-3 rounded-xl border text-left transition ${
                                                    selectedAiEngine === engine.key
                                                        ? 'border-emerald-500 bg-emerald-950/30 text-white shadow-md'
                                                        : 'border-neutral-800 bg-neutral-950 text-neutral-400 hover:bg-neutral-900'
                                                }`}
                                            >
                                                <div className="flex items-center justify-between mb-1">
                                                    <span className="font-bold text-xs">{engine.name}</span>
                                                    {selectedAiEngine === engine.key && (
                                                        <Check className="h-3 w-3 text-emerald-400" />
                                                    )}
                                                </div>
                                                <p className="text-[10px] text-neutral-500">{engine.desc}</p>
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                <div className="grid sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-bold uppercase tracking-wider text-neutral-400 mb-1">
                                            Agent / Bot Name
                                        </label>
                                        <Input
                                            value={agentData.name}
                                            onChange={e => setAgentData({ ...agentData, name: e.target.value })}
                                            placeholder="e.g. Sales Copilot"
                                            className="bg-neutral-950 border-neutral-700 text-neutral-100 text-xs"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold uppercase tracking-wider text-neutral-400 mb-1">
                                            Primary Purpose
                                        </label>
                                        <select
                                            value={agentData.purpose}
                                            onChange={e => setAgentData({ ...agentData, purpose: e.target.value })}
                                            className="w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 py-2 text-xs text-neutral-200 focus:outline-none focus:ring-2 focus:ring-brand-500"
                                        >
                                            {AGENT_PURPOSES.map(p => (
                                                <option key={p.key} value={p.key}>{p.label}</option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="sm:col-span-2">
                                        <label className="block text-xs font-bold uppercase tracking-wider text-neutral-400 mb-1">
                                            Welcome Message
                                        </label>
                                        <textarea
                                            rows={3}
                                            value={agentData.welcome_message}
                                            onChange={e => setAgentData({ ...agentData, welcome_message: e.target.value })}
                                            className="w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 py-2 text-xs text-neutral-200 focus:outline-none focus:ring-2 focus:ring-brand-500"
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center justify-between pt-4 border-t border-neutral-800">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (selectedService === 'whatsapp_voice') {
                                                setActiveKey('calling');
                                            } else {
                                                setActiveKey('whatsapp');
                                            }
                                        }}
                                        className="text-xs text-neutral-400 hover:text-neutral-200"
                                    >
                                        ← Back
                                    </button>

                                    <button
                                        type="button"
                                        disabled={isCreatingAgent}
                                        onClick={handleCreateAiAgent}
                                        className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition shadow-lg shadow-emerald-600/30 disabled:opacity-50"
                                    >
                                        {isCreatingAgent ? (
                                            <><RefreshCw className="h-4 w-4 animate-spin" /> Provisioning AI Assistant...</>
                                        ) : (
                                            <>Save AI Agent <ArrowRight className="h-4 w-4" /></>
                                        )}
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* STEP: CONNECT CRM (VOICE ONLY OR OPTIONAL) */}
                        {activeKey === 'crm' && selectedService === 'whatsapp_voice' && (
                            <div className="space-y-6">
                                <div className="p-4 rounded-xl bg-indigo-950/30 border border-indigo-800/40 space-y-2 text-xs text-indigo-200">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2 font-bold text-sm text-indigo-300">
                                            <Share2 className="h-4 w-4 text-indigo-400" /> Connect Your Existing CRM (Optional)
                                        </div>
                                        <span className="px-2 py-0.5 rounded-full bg-indigo-900/60 text-indigo-300 text-[10px] font-bold">
                                            TWO-WAY SYNC
                                        </span>
                                    </div>
                                    <p>
                                        Growbridge Connect integrates seamlessly with your existing CRM. We synchronize contacts, WhatsApp chats, calls, and AI summaries bidirectionally.
                                    </p>
                                </div>

                                {/* Select CRM Provider */}
                                <div className="space-y-2">
                                    <label className="block text-xs font-bold uppercase tracking-wider text-neutral-400">
                                        Choose Your CRM Provider
                                    </label>
                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                        {CRM_OPTIONS.map(crm => (
                                            <button
                                                key={crm.key}
                                                type="button"
                                                onClick={() => {
                                                    setSelectedCrm(crm.key);
                                                    setCrmTestResult(null);
                                                }}
                                                className={`p-3 rounded-xl border text-left transition ${
                                                    selectedCrm === crm.key
                                                        ? 'border-indigo-500 bg-indigo-950/40 text-white shadow-md'
                                                        : 'border-neutral-800 bg-neutral-950 text-neutral-400 hover:bg-neutral-900'
                                                }`}
                                            >
                                                <div className="flex items-center justify-between mb-1">
                                                    <span className="font-bold text-xs">{crm.name}</span>
                                                    {selectedCrm === crm.key && (
                                                        <Check className="h-3 w-3 text-indigo-400" />
                                                    )}
                                                </div>
                                                <p className="text-[10px] text-neutral-500 line-clamp-1">{crm.desc}</p>
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                {/* Dynamic Credentials Inputs */}
                                <div className="p-4 rounded-xl border border-neutral-800 bg-neutral-950 space-y-4">
                                    <h4 className="text-xs font-bold uppercase tracking-wider text-neutral-300">
                                        {CRM_OPTIONS.find(c => c.key === selectedCrm)?.name} Connection Details
                                    </h4>

                                    {selectedCrm === 'hubspot' && (
                                        <div>
                                            <label className="block text-xs font-bold text-neutral-400 mb-1">
                                                Private App Access Token <span className="text-rose-500">*</span>
                                            </label>
                                            <Input
                                                type="password"
                                                placeholder="pat-na1-..."
                                                value={crmCreds.access_token || ''}
                                                onChange={e => setCrmCreds({ ...crmCreds, access_token: e.target.value })}
                                                className="bg-neutral-900 border-neutral-700 text-xs text-white font-mono"
                                            />
                                        </div>
                                    )}

                                    {crmTestResult && (
                                        <div className={`p-3 rounded-xl border text-xs flex items-center gap-2 ${
                                            crmTestResult.ok ? 'bg-emerald-950/40 border-emerald-800 text-emerald-300' : 'bg-rose-950/40 border-rose-800 text-rose-300'
                                        }`}>
                                            {crmTestResult.ok ? <CheckCircle2 className="h-4 w-4 shrink-0" /> : <AlertTriangle className="h-4 w-4 shrink-0" />}
                                            <span>{crmTestResult.message}</span>
                                        </div>
                                    )}
                                </div>

                                <div className="flex items-center justify-between pt-4 border-t border-neutral-800">
                                    <button
                                        type="button"
                                        onClick={handleSkipCrm}
                                        className="text-xs text-neutral-400 hover:text-neutral-200 underline"
                                    >
                                        Skip CRM Step (Connect Later)
                                    </button>

                                    <div className="flex items-center gap-2">
                                        <button
                                            type="button"
                                            disabled={isTestingCrm}
                                            onClick={handleTestCrm}
                                            className="px-3.5 py-2.5 rounded-xl border border-neutral-700 text-xs font-semibold text-neutral-300 hover:bg-neutral-800 disabled:opacity-50 flex items-center gap-1.5"
                                        >
                                            <FlaskConical className={`h-3.5 w-3.5 ${isTestingCrm ? 'animate-spin' : ''}`} />
                                            {isTestingCrm ? 'Testing...' : 'Test Connection'}
                                        </button>

                                        <button
                                            type="button"
                                            disabled={isConnectingCrm}
                                            onClick={handleConnectCrm}
                                            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition shadow-lg shadow-indigo-600/30 disabled:opacity-50"
                                        >
                                            {isConnectingCrm ? (
                                                <><RefreshCw className="h-4 w-4 animate-spin" /> Connecting CRM...</>
                                            ) : (
                                                <>Save & Connect CRM <ArrowRight className="h-4 w-4" /></>
                                            )}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* STEP: BUSINESS PROFILE */}
                        {activeKey === 'business' && (
                            <div className="space-y-6">
                                <div className="grid sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-bold uppercase tracking-wider text-neutral-400 mb-1">
                                            Company / Business Name
                                        </label>
                                        <Input
                                            value={businessData.name}
                                            onChange={e => setBusinessData({ ...businessData, name: e.target.value })}
                                            className="bg-neutral-950 border-neutral-700 text-neutral-100 text-xs"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold uppercase tracking-wider text-neutral-400 mb-1">
                                            Industry / Sector
                                        </label>
                                        <Input
                                            value={businessData.industry}
                                            onChange={e => setBusinessData({ ...businessData, industry: e.target.value })}
                                            className="bg-neutral-950 border-neutral-700 text-neutral-100 text-xs"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold uppercase tracking-wider text-neutral-400 mb-1">
                                            Website URL
                                        </label>
                                        <Input
                                            value={businessData.website}
                                            onChange={e => setBusinessData({ ...businessData, website: e.target.value })}
                                            className="bg-neutral-950 border-neutral-700 text-neutral-100 text-xs"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold uppercase tracking-wider text-neutral-400 mb-1">
                                            Timezone
                                        </label>
                                        <TimezonePicker
                                            value={businessData.timezone}
                                            onChange={tz => setBusinessData({ ...businessData, timezone: tz })}
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center justify-between pt-4 border-t border-neutral-800">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (selectedService === 'whatsapp_voice') {
                                                setActiveKey('crm');
                                            } else {
                                                setActiveKey('ai_agent');
                                            }
                                        }}
                                        className="text-xs text-neutral-400 hover:text-neutral-200"
                                    >
                                        ← Back
                                    </button>

                                    <button
                                        type="button"
                                        disabled={isSavingBusiness}
                                        onClick={handleSaveBusiness}
                                        className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold transition shadow-lg shadow-brand-600/30 disabled:opacity-50"
                                    >
                                        {isSavingBusiness ? (
                                            <><RefreshCw className="h-4 w-4 animate-spin" /> Saving Profile...</>
                                        ) : (
                                            <>Save Profile & Finalize <ArrowRight className="h-4 w-4" /></>
                                        )}
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* STEP: COMPLETE & LAUNCH */}
                        {activeKey === 'launch' && (
                            <div className="space-y-6 text-center py-4">
                                <div className="inline-flex items-center justify-center h-16 w-16 rounded-3xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 mx-auto shadow-xl shadow-emerald-500/10">
                                    <Sparkles className="h-8 w-8" />
                                </div>

                                <div className="space-y-2 max-w-md mx-auto">
                                    <h4 className="text-2xl font-extrabold text-white">
                                        🎉 Your Growbridge Connect account is ready
                                    </h4>
                                    <p className="text-xs text-neutral-400">
                                        {selectedService === 'whatsapp_voice'
                                            ? 'Your virtual phone numbers, WhatsApp Cloud API channel, AI assistants, and CRM workspace are configured and ready.'
                                            : 'Your WhatsApp Business API channel, AI assistants, campaigns, and workspace are fully configured and ready.'}
                                    </p>
                                </div>

                                {/* Summary Grid */}
                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-left max-w-2xl mx-auto pt-2">
                                    <div className="p-3 rounded-xl border border-neutral-800 bg-neutral-950 space-y-1">
                                        <span className="text-[10px] text-neutral-500 font-bold uppercase">Plan Model</span>
                                        <p className="font-semibold text-xs text-brand-400 truncate">
                                            {selectedService === 'whatsapp_voice' ? 'WhatsApp + Voice' : 'WhatsApp API'}
                                        </p>
                                    </div>
                                    <div className="p-3 rounded-xl border border-neutral-800 bg-neutral-950 space-y-1">
                                        <span className="text-[10px] text-neutral-500 font-bold uppercase">WhatsApp API</span>
                                        <p className="text-xs font-bold text-emerald-400 truncate">Connected</p>
                                    </div>
                                    <div className="p-3 rounded-xl border border-neutral-800 bg-neutral-950 space-y-1">
                                        <span className="text-[10px] text-neutral-500 font-bold uppercase">AI Assistant</span>
                                        <p className="text-xs font-bold text-neutral-200 truncate">{agentData.name}</p>
                                    </div>
                                    <div className="p-3 rounded-xl border border-neutral-800 bg-neutral-950 space-y-1">
                                        <span className="text-[10px] text-neutral-500 font-bold uppercase">Business</span>
                                        <p className="text-xs font-bold text-neutral-200 truncate">{businessData.name}</p>
                                    </div>
                                </div>

                                <div className="pt-6">
                                    <button
                                        type="button"
                                        disabled={isLaunching}
                                        onClick={handleLaunch}
                                        className="inline-flex items-center gap-2.5 px-8 py-3.5 rounded-2xl bg-gradient-to-r from-brand-600 to-emerald-600 hover:from-brand-500 hover:to-emerald-500 text-white font-bold text-sm transition shadow-xl shadow-brand-600/30 disabled:opacity-50"
                                    >
                                        {isLaunching ? (
                                            <><RefreshCw className="h-4 w-4 animate-spin" /> Launching Workspace...</>
                                        ) : (
                                            <>Go to Dashboard <ArrowRight className="h-4 w-4" /></>
                                        )}
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </section>
            </main>
        </div>
    );
}
