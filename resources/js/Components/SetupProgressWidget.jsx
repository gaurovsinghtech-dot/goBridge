import { Link } from '@inertiajs/react';
import { Check, AlertTriangle, ArrowRight, Sparkles } from 'lucide-react';

function safeRoute(name, fallback = '#') {
    try {
        if (typeof window !== 'undefined' && typeof window.route === 'function') {
            if (window.route().has && window.route().has(name)) {
                return window.route(name);
            }
        }
    } catch (e) {
        // fallback
    }
    return fallback;
}

export default function SetupProgressWidget({ progress, className = '' }) {
    if (!progress || !progress.steps) return null;

    const { steps = [], percent = 0, done = 0, total = 8, is_complete = false } = progress;
    const onboardingUrl = safeRoute('client.onboarding', safeRoute('client.onboarding.show', '/app/onboarding'));

    if (is_complete && percent === 100) {
        return (
            <div className={`p-5 rounded-2xl bg-gradient-to-r from-emerald-50/90 via-teal-50/50 to-white dark:from-emerald-950/40 dark:via-teal-950/20 dark:to-neutral-900 border border-emerald-200/90 dark:border-emerald-500/30 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition-all duration-300 ${className}`}>
                <div className="flex items-center gap-3.5">
                    <div className="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30 flex items-center justify-center shrink-0 shadow-xs">
                        <Sparkles className="w-5 h-5" />
                    </div>
                    <div>
                        <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                            🎉 Setup Complete
                            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30">
                                100% Ready
                            </span>
                        </h3>
                        <p className="text-xs text-neutral-600 dark:text-neutral-400 mt-0.5">
                            Your Growbridge Connect account is fully configured across WhatsApp, Voice Calling, AI Agents, and CRM sync.
                        </p>
                    </div>
                </div>
                <Link
                    href={onboardingUrl}
                    className="px-3.5 py-2 rounded-xl text-xs font-semibold bg-white hover:bg-emerald-50 text-emerald-800 border border-emerald-300 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:text-emerald-300 dark:border-emerald-500/40 transition shadow-xs whitespace-nowrap inline-flex items-center justify-center cursor-pointer"
                >
                    Review Setup
                </Link>
            </div>
        );
    }

    return (
        <div className={`p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 shadow-sm ${className}`}>
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div>
                    <div className="flex items-center gap-2">
                        <span className="text-xs font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            Setup Progress
                        </span>
                        <span className="text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                            {done} of {total} steps completed
                        </span>
                    </div>
                    <h3 className="text-base font-bold text-neutral-900 dark:text-white mt-0.5">
                        Complete your workspace setup
                    </h3>
                </div>

                <Link
                    href={onboardingUrl}
                    className="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white text-xs font-bold shadow-sm inline-flex items-center justify-center transition-all duration-150 cursor-pointer"
                >
                    <span>Continue Setup</span>
                    <ArrowRight className="w-3.5 h-3.5 ml-1.5" />
                </Link>
            </div>

            {/* Progress Bar */}
            <div className="w-full bg-neutral-100 dark:bg-neutral-800 h-2.5 rounded-full overflow-hidden mb-4">
                <div
                    className="bg-gradient-to-r from-emerald-500 to-teal-400 h-full rounded-full transition-all duration-500"
                    style={{ width: `${Math.max(5, percent)}%` }}
                />
            </div>

            {/* Steps Badges Grid */}
            <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2">
                {steps.map((step) => {
                    const isCompleted = step.status === 'completed' || step.completed;
                    const isCurrent = step.status === 'in_progress' || step.is_current;
                    const isBlocked = step.status === 'blocked';
                    const isSkipped = step.status === 'skipped';

                    return (
                        <Link
                            key={step.key}
                            href={onboardingUrl}
                            className={`p-2 rounded-xl border text-xs flex items-center gap-2 transition-all hover:scale-[1.02] cursor-pointer ${
                                isCompleted
                                    ? 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-500/30 text-emerald-800 dark:text-emerald-300'
                                    : isCurrent
                                        ? 'bg-brand-50/50 dark:bg-neutral-800 border-brand-500/50 dark:border-emerald-500/40 text-neutral-900 dark:text-white font-bold ring-1 ring-emerald-500/20'
                                        : isBlocked
                                            ? 'bg-amber-50/50 dark:bg-amber-950/20 border-amber-500/30 text-amber-800 dark:text-amber-300'
                                            : 'bg-neutral-50 dark:bg-neutral-900/60 border-neutral-200 dark:border-neutral-800 text-neutral-500'
                            }`}
                        >
                            <div className="shrink-0">
                                {isCompleted ? (
                                    <Check className="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 stroke-[3]" />
                                ) : isBlocked ? (
                                    <AlertTriangle className="w-3.5 h-3.5 text-amber-500" />
                                ) : isCurrent ? (
                                    <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse block" />
                                ) : isSkipped ? (
                                    <span className="text-[10px] text-neutral-400">○</span>
                                ) : (
                                    <span className="text-[10px] text-neutral-400 font-bold">{step.id}</span>
                                )}
                            </div>
                            <span className="truncate font-medium text-[11px]">
                                {step.title.replace('Create ', '').replace('Choose ', '').replace('Connect ', '').replace('Complete ', '').replace('Configure ', '').replace('Add ', '').replace('Test Your ', '')}
                            </span>
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}
