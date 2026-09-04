import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Button, Card, Badge } from '@/Components/ui';
import { Users, ArrowLeft, Merge, ShieldCheck, Check, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

export default function ContactDuplicates({ duplicates = [] }) {
    const [merging, setMerging] = useState(false);

    const handleMerge = (masterId, duplicateId) => {
        if (!confirm('Merge this duplicate contact into the master contact? All conversation history, calls, and tags will be combined.')) return;

        setMerging(true);
        router.post(route('client.contacts.merge', masterId), {
            duplicate_contact_id: duplicateId,
        }, {
            preserveScroll: true,
            onSuccess: () => toast.success('Contacts merged successfully.'),
            onFinish: () => setMerging(false),
        });
    };

    return (
        <ClientLayout>
            <Head title="Duplicate Contacts — Growbridge Connect" />

            <div className="space-y-6 max-w-5xl mx-auto">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Link href={route('client.contacts.index')}>
                            <Button type="button" variant="ghost" size="sm" className="p-2">
                                <ArrowLeft className="w-4 h-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-xl font-bold text-slate-900 dark:text-white">Possible Duplicate Contacts</h1>
                            <p className="text-xs text-slate-500 dark:text-neutral-400">
                                Review potential duplicates detected across WhatsApp, Instagram, and Email before merging.
                            </p>
                        </div>
                    </div>
                </div>

                <Card className="border-slate-200 dark:border-neutral-800 overflow-hidden">
                    {duplicates.length === 0 ? (
                        <div className="p-12 text-center">
                            <ShieldCheck className="w-10 h-10 text-emerald-500 mx-auto mb-2" />
                            <h3 className="text-sm font-semibold text-slate-900 dark:text-white">Clean Contact Database</h3>
                            <p className="text-xs text-slate-400 mt-1">
                                No unresolved duplicate contacts detected. New inbound interactions are matched automatically.
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100 dark:divide-neutral-800">
                            {duplicates.map((dup) => (
                                <div key={dup.id} className="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-semibold text-slate-900 dark:text-white text-sm">
                                                {dup.full_name || 'Unnamed Contact'}
                                            </span>
                                            <Badge variant="neutral" className="text-[10px]">Possible Duplicate</Badge>
                                        </div>
                                        <div className="flex items-center gap-4 text-xs text-slate-500 dark:text-neutral-400 mt-1">
                                            <span>Phone: {dup.phone_e164 || 'None'}</span>
                                            <span>Email: {dup.email || 'None'}</span>
                                            <span>Source: {dup.source || 'Direct'}</span>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        {dup.duplicate_of_id && (
                                            <Button
                                                size="sm"
                                                disabled={merging}
                                                onClick={() => handleMerge(dup.duplicate_of_id, dup.id)}
                                                className="bg-brand-600 hover:bg-brand-700 text-white gap-1.5 text-xs"
                                            >
                                                <Merge className="w-3.5 h-3.5" /> Merge into #{dup.duplicate_of_id}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>
        </ClientLayout>
    );
}
