import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { CheckCircle2, Calendar, User, ChevronLeft, AlertCircle } from 'lucide-react';

export default function CrmTasks({ tasks, teamMembers }) {
    const handleToggleStatus = (task) => {
        const nextStatus = task.status === 'completed' ? 'pending' : 'completed';
        router.put(route('client.crm.tasks.status', task.id), { status: nextStatus }, { preserveScroll: true });
    };

    return (
        <ClientLayout>
            <Head title="CRM Follow-up Tasks" />

            <div className="p-6 space-y-6 max-w-5xl mx-auto">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-neutral-900 dark:text-white">Sales Tasks & Follow-ups</h1>
                        <p className="text-sm text-neutral-500 mt-1">Manage scheduled client interactions, callbacks, and demos.</p>
                    </div>

                    <Link
                        href={route('client.crm.dashboard')}
                        className="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-200"
                    >
                        <ChevronLeft className="h-4 w-4" />
                        Pipeline Board
                    </Link>
                </div>

                <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-100 dark:divide-neutral-800 shadow-xs">
                    {tasks?.data?.map((task) => (
                        <div key={task.id} className="p-4 flex items-center justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    checked={task.status === 'completed'}
                                    onChange={() => handleToggleStatus(task)}
                                    className="h-4 w-4 rounded text-brand-600 focus:ring-brand-500 cursor-pointer"
                                />
                                <div>
                                    <h4 className={`font-semibold text-sm ${task.status === 'completed' ? 'line-through text-neutral-400' : 'text-neutral-900 dark:text-white'}`}>
                                        {task.title}
                                    </h4>
                                    <div className="flex items-center gap-3 text-xs text-neutral-400 mt-1">
                                        {task.contact && (
                                            <Link href={route('client.crm.leads.show', task.contact.uuid)} className="hover:underline font-medium text-brand-600">
                                                {task.contact.first_name} {task.contact.last_name}
                                            </Link>
                                        )}
                                        {task.due_at && (
                                            <span className="flex items-center gap-1">
                                                <Calendar className="h-3 w-3" />
                                                Due: {task.due_at}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <span className={`px-2 py-0.5 text-[10px] font-bold uppercase rounded ${
                                task.priority === 'urgent' ? 'bg-red-100 text-red-700' : 'bg-neutral-100 text-neutral-600'
                            }`}>
                                {task.priority}
                            </span>
                        </div>
                    ))}

                    {tasks?.data?.length === 0 && (
                        <div className="p-12 text-center text-sm text-neutral-400">
                            No follow-up tasks scheduled.
                        </div>
                    )}
                </div>
            </div>
        </ClientLayout>
    );
}
