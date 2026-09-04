import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Button, Card, Badge } from '@/Components/ui';
import {
    Zap, ArrowLeft, Plus, Sparkles, PhoneCall,
    MessageSquare, Mail, Layers, CheckCircle2,
} from 'lucide-react';
import { toast } from 'sonner';

export default function AutomationTemplates({ templates = [] }) {
    const [installing, setInstalling] = useState(null);

    const handleInstall = (template) => {
        setInstalling(template.key);
        router.post(route('client.automations.templates.install', template.key), {}, {
            onSuccess: () => toast.success('Template installed! You can now customize nodes.'),
            onFinish: () => setInstalling(null),
        });
    };

    return (
        <ClientLayout>
            <Head title="Omnichannel Journey Templates — Growbridge Connect" />

            <div className="space-y-6 max-w-6xl mx-auto">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link href={route('client.automations.index')}>
                            <Button type="button" variant="ghost" size="sm" className="p-2">
                                <ArrowLeft className="w-4 h-4" />
                            </Button>
                        </Link>
                        <div>
                            <div className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-brand-50 text-brand-700 dark:bg-neutral-800 dark:text-brand-400 text-xs font-semibold uppercase mb-1">
                                <Sparkles className="w-3 h-3" /> Pre-Built Blueprints
                            </div>
                            <h1 className="text-xl font-bold text-slate-900 dark:text-white">
                                Omnichannel Customer Journey Templates
                            </h1>
                            <p className="text-xs text-slate-500 dark:text-neutral-400">
                                1-Click install production-ready workflows with AI agents, smart delays, and voice calling.
                            </p>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    {templates.map((tpl) => (
                        <Card key={tpl.key} className="p-5 flex flex-col justify-between border-slate-200 dark:border-neutral-800 hover:shadow-md transition-shadow">
                            <div>
                                <div className="flex items-start justify-between gap-2 mb-2">
                                    <span className="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-brand-50 text-brand-700 dark:bg-neutral-800 dark:text-brand-400">
                                        {tpl.category}
                                    </span>
                                    <span className="text-[11px] text-slate-400 font-mono">
                                        {tpl.nodes_count} Nodes
                                    </span>
                                </div>

                                <h3 className="font-semibold text-slate-900 dark:text-white text-sm mb-1.5">
                                    {tpl.title}
                                </h3>

                                <p className="text-xs text-slate-500 dark:text-neutral-400 leading-relaxed line-clamp-3 mb-4">
                                    {tpl.description}
                                </p>
                            </div>

                            <div className="pt-3 border-t border-slate-100 dark:border-neutral-800 flex items-center justify-between gap-2">
                                <div className="flex items-center gap-1.5 text-xs text-slate-400">
                                    {tpl.channels?.map((ch) => (
                                        <span key={ch} className="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-neutral-700 text-slate-600 dark:text-neutral-300 text-[10px] font-mono capitalize">
                                            {ch.replace('_', ' ')}
                                        </span>
                                    ))}
                                </div>

                                <Button
                                    size="sm"
                                    disabled={installing === tpl.key}
                                    onClick={() => handleInstall(tpl)}
                                    className="bg-brand-600 hover:bg-brand-700 text-white gap-1 text-xs"
                                >
                                    <Plus className="w-3.5 h-3.5" />
                                    {installing === tpl.key ? 'Installing...' : 'Use Template'}
                                </Button>
                            </div>
                        </Card>
                    ))}
                </div>
            </div>
        </ClientLayout>
    );
}
