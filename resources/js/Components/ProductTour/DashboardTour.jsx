import React, { useState, useEffect, useCallback, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    Sparkles,
    CheckCircle2,
    ArrowRight,
    ArrowLeft,
    X,
    Lock,
} from 'lucide-react';

/**
 * Growbridge Connect — Interactive Dashboard Product Tour
 */
export default function DashboardTour() {
    const { t } = useTranslation();
    const { auth, entitlements: rootEntitlements, dashboard_tour } = usePage().props;
    const entitlements = rootEntitlements || auth?.entitlements || {};
    const hasVoice = Boolean(entitlements.voice_calling || entitlements.ai_voice_agents);

    // Tour State:
    // isOpen: boolean
    // stepIndex: -1 = Welcome Modal, 0..(N-1) = Tour steps, N = Completion Modal
    const [isOpen, setIsOpen] = useState(false);
    const [stepIndex, setStepIndex] = useState(-1);
    const [targetRect, setTargetRect] = useState(null);
    const [isMobile, setIsMobile] = useState(false);
    const popoverRef = useRef(null);

    // Define tour steps dynamically based on plan entitlements
    const baseSteps = [
        {
            key: 'dashboard',
            title: '🏠 Dashboard',
            description: t('tour.steps.dashboard', {
                defaultValue: 'See your messages, contacts, campaigns, automation activity, AI usage and account status in one place.',
            }),
            targetSelector: '[data-tour="nav-dashboard"]',
        },
        {
            key: 'inbox',
            title: '💬 Inbox',
            description: t('tour.steps.inbox', {
                defaultValue: 'Manage WhatsApp conversations and reply to customers from one unified inbox.',
            }),
            targetSelector: '[data-tour="nav-inbox"]',
        },
        {
            key: 'contacts',
            title: '👥 Contacts',
            description: t('tour.steps.contacts', {
                defaultValue: 'Store, organize and segment your customers.',
            }),
            targetSelector: '[data-tour="nav-contacts"]',
        },
        {
            key: 'campaigns',
            title: '📢 Campaigns',
            description: t('tour.steps.campaigns', {
                defaultValue: 'Create and send WhatsApp marketing campaigns using approved templates.',
            }),
            targetSelector: '[data-tour="nav-campaigns"]',
        },
        {
            key: 'automations',
            title: '⚡ Automations',
            description: t('tour.steps.automations', {
                defaultValue: 'Automatically respond and perform actions based on customer events and triggers.',
            }),
            targetSelector: '[data-tour="nav-automations"]',
        },
        {
            key: 'ai_agents',
            title: '🤖 AI Agents',
            description: t('tour.steps.ai_agents', {
                defaultValue: 'Create AI assistants that can answer customer questions and handle conversations.',
            }),
            targetSelector: '[data-tour="nav-ai-agents"]',
        },
        {
            key: 'whatsapp',
            title: '📱 WhatsApp',
            description: t('tour.steps.whatsapp', {
                defaultValue: 'Connect and manage your WhatsApp Business API.',
            }),
            targetSelector: '[data-tour="nav-whatsapp"]',
        },
        {
            key: 'analytics',
            title: '📊 Analytics',
            description: t('tour.steps.analytics', {
                defaultValue: 'Track campaign delivery, conversations, engagement and performance.',
            }),
            targetSelector: '[data-tour="nav-analytics"]',
        },
        {
            key: 'integrations',
            title: '🔗 Integrations',
            description: t('tour.steps.integrations', {
                defaultValue: 'Connect supported CRM and other services.',
            }),
            targetSelector: '[data-tour="nav-integrations"]',
        },
        {
            key: 'billing',
            title: '💳 Plans & Billing',
            description: t('tour.steps.billing', {
                defaultValue: 'Manage your subscription, usage and upgrades.',
            }),
            targetSelector: '[data-tour="nav-billing"]',
        },
    ];

    const voiceSteps = [
        {
            key: 'voice_agents',
            title: '☎️ AI Voice Agents',
            description: t('tour.steps.voice_agents', {
                defaultValue: 'Build AI-powered calling agents and manage your voice conversations.',
            }),
            targetSelector: '[data-tour="nav-voice-agents"]',
        },
        {
            key: 'phone_numbers',
            title: '📞 Phone Numbers',
            description: t('tour.steps.phone_numbers', {
                defaultValue: 'Manage your Twilio or supported phone-provider numbers.',
            }),
            targetSelector: '[data-tour="nav-phone-numbers"]',
        },
    ];

    const steps = hasVoice ? [...baseSteps, ...voiceSteps] : baseSteps;

    // Detect mobile viewport
    useEffect(() => {
        const checkMobile = () => {
            setIsMobile(window.innerWidth < 1024);
        };
        checkMobile();
        window.addEventListener('resize', checkMobile);
        return () => window.removeEventListener('resize', checkMobile);
    }, []);

    // Check if tour should auto-start on mount
    useEffect(() => {
        if (dashboard_tour && dashboard_tour.should_show && !dashboard_tour.is_completed && !dashboard_tour.is_skipped) {
            setIsOpen(true);
            setStepIndex(-1);
        }
    }, [dashboard_tour]);

    // Listen for custom restart event from Help & Support
    useEffect(() => {
        const handleRestart = () => {
            setStepIndex(-1);
            setIsOpen(true);
        };
        window.addEventListener('start-dashboard-tour', handleRestart);
        return () => window.removeEventListener('start-dashboard-tour', handleRestart);
    }, []);

    // Update target element coordinates when step changes
    const updateTargetPosition = useCallback(() => {
        if (!isOpen || stepIndex < 0 || stepIndex >= steps.length) {
            setTargetRect(null);
            return;
        }

        const current = steps[stepIndex];
        const target = document.querySelector(current.targetSelector);

        if (target) {
            const rect = target.getBoundingClientRect();
            setTargetRect({
                top: rect.top,
                left: rect.left,
                width: rect.width,
                height: rect.height,
                bottom: rect.bottom,
                right: rect.right,
            });
        } else {
            setTargetRect(null);
        }
    }, [isOpen, stepIndex, steps]);

    useEffect(() => {
        updateTargetPosition();
        const timeoutId = setTimeout(updateTargetPosition, 100);
        window.addEventListener('scroll', updateTargetPosition, true);
        window.addEventListener('resize', updateTargetPosition);
        return () => {
            clearTimeout(timeoutId);
            window.removeEventListener('scroll', updateTargetPosition, true);
            window.removeEventListener('resize', updateTargetPosition);
        };
    }, [updateTargetPosition]);

    // Backend sync helpers
    const saveProgress = async (step) => {
        try {
            await fetch(route('client.tour.progress'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ tour_key: 'dashboard_tour', step }),
            });
        } catch {
            // Silently handle transient errors
        }
    };

    const markComplete = async () => {
        try {
            await fetch(route('client.tour.complete'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ tour_key: 'dashboard_tour' }),
            });
        } catch {
            // Silently handle transient errors
        }
    };

    const markSkipped = async () => {
        try {
            await fetch(route('client.tour.skip'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ tour_key: 'dashboard_tour' }),
            });
        } catch {
            // Silently handle transient errors
        }
    };

    // Navigation actions
    const handleStartTour = () => {
        setStepIndex(0);
        saveProgress(0);
    };

    const handleNext = () => {
        if (stepIndex < steps.length - 1) {
            const nextStep = stepIndex + 1;
            setStepIndex(nextStep);
            saveProgress(nextStep);
        } else {
            // Reached completion
            setStepIndex(steps.length);
            markComplete();
        }
    };

    const handleBack = () => {
        if (stepIndex > 0) {
            const prevStep = stepIndex - 1;
            setStepIndex(prevStep);
            saveProgress(prevStep);
        }
    };

    const handleSkip = () => {
        setIsOpen(false);
        markSkipped();
    };

    const handleFinish = () => {
        setIsOpen(false);
        markComplete();
    };

    // Keyboard accessibility: Escape to skip/close, ArrowRight for Next, ArrowLeft for Back
    useEffect(() => {
        const handleKeyDown = (e) => {
            if (!isOpen) return;

            if (e.key === 'Escape') {
                e.preventDefault();
                handleSkip();
            } else if (e.key === 'ArrowRight' && stepIndex >= 0 && stepIndex < steps.length) {
                e.preventDefault();
                handleNext();
            } else if (e.key === 'ArrowLeft' && stepIndex > 0 && stepIndex < steps.length) {
                e.preventDefault();
                handleBack();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, stepIndex, steps.length]);

    if (!isOpen) return null;

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Welcome Screen Modal (stepIndex === -1)
    // ─────────────────────────────────────────────────────────────────────────
    if (stepIndex === -1) {
        return (
            <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-in fade-in duration-200">
                <div className="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white dark:bg-[#0d1f1a] border border-emerald-500/30 dark:border-emerald-500/20 shadow-2xl p-6 sm:p-8 text-center space-y-6">
                    {/* Glowing emerald accent glow */}
                    <div className="absolute -top-12 -left-12 w-40 h-40 bg-emerald-500/15 rounded-full blur-3xl pointer-events-none" />
                    <div className="absolute -bottom-12 -right-12 w-40 h-40 bg-emerald-500/15 rounded-full blur-3xl pointer-events-none" />

                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 shadow-inner">
                        <Sparkles className="h-8 w-8" />
                    </div>

                    <div className="space-y-2">
                        <h2 className="text-2xl font-bold tracking-tight text-neutral-900 dark:text-white">
                            👋 {t('tour.welcome_title', { defaultValue: 'Welcome to Growbridge Connect' })}
                        </h2>
                        <p className="text-sm sm:text-base text-neutral-600 dark:text-neutral-300">
                            {t('tour.welcome_subtitle', { defaultValue: 'Your all-in-one platform for WhatsApp marketing, automation and AI.' })}
                        </p>
                    </div>

                    <div className="rounded-xl bg-neutral-50 dark:bg-black/30 border border-neutral-200/60 dark:border-neutral-800/80 p-4 text-left space-y-2">
                        <div className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                            {t('tour.quick_tour', { defaultValue: "Let's take a quick tour" })}
                        </div>
                        <ul className="text-xs text-neutral-600 dark:text-neutral-300 space-y-1.5 list-disc list-inside">
                            <li>{t('tour.tip_1', { defaultValue: 'WhatsApp Business API & Omnichannel Inbox' })}</li>
                            <li>{t('tour.tip_2', { defaultValue: 'Marketing Campaigns, Audiences & Smart Automations' })}</li>
                            <li>{t('tour.tip_3', { defaultValue: 'AI Agents, Knowledge Bases & Live Analytics' })}</li>
                            {hasVoice && <li>{t('tour.tip_4', { defaultValue: 'AI Voice Calling & Twilio Phone Numbers' })}</li>}
                        </ul>
                    </div>

                    <div className="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                        <button
                            type="button"
                            onClick={handleStartTour}
                            className="w-full sm:w-auto px-6 py-2.5 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/25 transition flex items-center justify-center gap-2"
                        >
                            {t('tour.start_tour', { defaultValue: 'Start Tour' })}
                            <ArrowRight className="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            onClick={handleSkip}
                            className="w-full sm:w-auto px-5 py-2.5 rounded-xl text-sm font-medium text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                        >
                            {t('tour.skip', { defaultValue: 'Skip' })}
                        </button>
                    </div>
                </div>
            </div>
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Completion Screen Modal (stepIndex >= steps.length)
    // ─────────────────────────────────────────────────────────────────────────
    if (stepIndex >= steps.length) {
        return (
            <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-in fade-in duration-200">
                <div className="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white dark:bg-[#0d1f1a] border border-emerald-500/30 dark:border-emerald-500/20 shadow-2xl p-6 sm:p-8 text-center space-y-6">
                    <div className="absolute -top-12 -right-12 w-40 h-40 bg-emerald-500/15 rounded-full blur-3xl pointer-events-none" />

                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 shadow-inner">
                        <CheckCircle2 className="h-8 w-8" />
                    </div>

                    <div className="space-y-2">
                        <h2 className="text-2xl font-bold text-neutral-900 dark:text-white">
                            🎉 {t('tour.complete_title', { defaultValue: "You're all set!" })}
                        </h2>
                        <p className="text-sm sm:text-base text-neutral-600 dark:text-neutral-300">
                            {t('tour.complete_subtitle', { defaultValue: 'Start by connecting WhatsApp and creating your first campaign.' })}
                        </p>
                    </div>

                    <div className="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                        <button
                            type="button"
                            onClick={handleFinish}
                            className="w-full sm:w-auto px-6 py-2.5 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/25 transition flex items-center justify-center gap-2"
                        >
                            {t('tour.explore_dashboard', { defaultValue: 'Explore Dashboard' })}
                        </button>
                    </div>
                </div>
            </div>
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Step Spotlight Popover Tooltip (0 <= stepIndex < steps.length)
    // ─────────────────────────────────────────────────────────────────────────
    const currentStep = steps[stepIndex];
    const totalSteps = steps.length;
    const progressPercent = Math.round(((stepIndex + 1) / totalSteps) * 100);

    // Calculate Popover Position relative to target element
    let popoverStyle = {};
    let arrowPosition = 'left';

    if (targetRect && !isMobile) {
        const spaceOnRight = window.innerWidth - targetRect.right;
        const popoverWidth = 360;
        const popoverHeight = 230;

        if (spaceOnRight >= popoverWidth + 24) {
            // Position to the right of target (Sidebar items)
            const targetMiddleY = targetRect.top + targetRect.height / 2;
            const topPos = Math.max(20, Math.min(window.innerHeight - popoverHeight - 20, targetMiddleY - (popoverHeight / 2)));
            popoverStyle = {
                top: `${topPos}px`,
                left: `${targetRect.right + 20}px`,
            };
            arrowPosition = 'left';
        } else {
            // Position below
            const topPos = Math.min(window.innerHeight - popoverHeight - 20, targetRect.bottom + 16);
            popoverStyle = {
                top: `${topPos}px`,
                left: `${Math.max(20, Math.min(window.innerWidth - popoverWidth - 20, targetRect.left))}px`,
            };
            arrowPosition = 'top';
        }
    }

    const stepLabel = `Step ${stepIndex + 1} of ${totalSteps}`;

    return (
        <div className="fixed inset-0 z-50 pointer-events-none">
            {/* Backdrop overlay */}
            <div
                className="absolute inset-0 bg-black/60 backdrop-blur-[2px] transition-opacity duration-200 pointer-events-auto"
                onClick={handleSkip}
            />

            {/* Target Highlight Cutout / Glow Frame */}
            {targetRect && (
                <div
                    className="absolute z-50 rounded-xl border-2 border-emerald-400 dark:border-emerald-400 shadow-[0_0_0_9999px_rgba(0,0,0,0.65)] ring-4 ring-emerald-400/30 transition-all duration-300 pointer-events-none"
                    style={{
                        top: targetRect.top - 4,
                        left: targetRect.left - 4,
                        width: targetRect.width + 8,
                        height: targetRect.height + 8,
                    }}
                />
            )}

            {/* Interactive Step Card Popover */}
            <div
                ref={popoverRef}
                className={[
                    'absolute z-50 pointer-events-auto w-[calc(100vw-32px)] sm:w-[360px] max-w-[380px] rounded-2xl bg-white dark:bg-[#0d1f1a] border border-emerald-500/30 dark:border-emerald-500/20 shadow-2xl p-5 space-y-4 transition-all duration-300 animate-in fade-in zoom-in-95',
                    isMobile || !targetRect ? 'bottom-6 left-1/2 -translate-x-1/2' : '',
                ].join(' ')}
                style={!isMobile && targetRect ? popoverStyle : {}}
            >
                {/* Visual Arrow pointing towards target on desktop */}
                {!isMobile && targetRect && arrowPosition === 'left' && (
                    <div className="absolute -left-2 top-1/2 -translate-y-1/2 w-4 h-4 bg-white dark:bg-[#0d1f1a] border-l border-b border-emerald-500/30 dark:border-emerald-500/20 rotate-45" />
                )}

                {/* Header: Step counter badge & close button */}
                <div className="flex items-center justify-between gap-2 border-b border-neutral-100 dark:border-neutral-800/80 pb-3">
                    <div className="flex items-center gap-2">
                        <span className="text-xs font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-500/15 px-3 py-1 rounded-full border border-emerald-500/25">
                            {stepLabel}
                        </span>
                        {!hasVoice && (currentStep.key === 'voice_agents' || currentStep.key === 'phone_numbers') && (
                            <span className="text-[10px] font-semibold text-amber-400 bg-amber-500/15 px-2 py-0.5 rounded border border-amber-500/20 flex items-center gap-1">
                                <Lock className="h-3 w-3" /> Pro Plan
                            </span>
                        )}
                    </div>

                    <button
                        type="button"
                        onClick={handleSkip}
                        title={t('tour.skip', { defaultValue: 'Skip Tour' })}
                        className="p-1 rounded-lg text-neutral-400 hover:text-neutral-700 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>

                {/* Progress Bar */}
                <div className="w-full bg-neutral-100 dark:bg-neutral-800 rounded-full h-1.5 overflow-hidden">
                    <div
                        className="bg-gradient-to-r from-emerald-500 to-teal-400 h-1.5 rounded-full transition-all duration-300"
                        style={{ width: `${progressPercent}%` }}
                    />
                </div>

                {/* Content */}
                <div className="space-y-1.5">
                    <h3 className="text-base font-bold text-neutral-900 dark:text-white">
                        {currentStep.title}
                    </h3>
                    <p className="text-xs sm:text-sm text-neutral-600 dark:text-neutral-300 leading-relaxed">
                        {currentStep.description}
                    </p>
                </div>

                {/* Footer Controls: Back, Skip, Next/Finish */}
                <div className="flex items-center justify-between gap-2 pt-2 border-t border-neutral-100 dark:border-neutral-800/80">
                    <button
                        type="button"
                        onClick={handleBack}
                        disabled={stepIndex === 0}
                        className={[
                            'px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition',
                            stepIndex === 0
                                ? 'text-neutral-300 dark:text-neutral-600 cursor-not-allowed'
                                : 'text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800',
                        ].join(' ')}
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        {t('common.back', { defaultValue: 'Back' })}
                    </button>

                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={handleSkip}
                            className="text-xs text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 font-medium px-2 py-1 transition"
                        >
                            {t('tour.skip', { defaultValue: 'Skip' })}
                        </button>
                        <button
                            type="button"
                            onClick={handleNext}
                            className="px-4 py-1.5 rounded-lg text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm shadow-emerald-600/30 transition flex items-center gap-1.5"
                        >
                            {stepIndex === totalSteps - 1 ? (
                                <>
                                    {t('tour.finish', { defaultValue: 'Finish' })}
                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                </>
                            ) : (
                                <>
                                    {t('common.next', { defaultValue: 'Next' })}
                                    <ArrowRight className="h-3.5 w-3.5" />
                                </>
                            )}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
