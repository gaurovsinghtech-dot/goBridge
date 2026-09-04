import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Card, Button, Input, Select, Badge, Modal } from '@/Components/ui';
import { Megaphone, Plus, Trash2, CheckCircle2, ToggleLeft, ToggleRight, AlertTriangle } from 'lucide-react';
import { toast } from 'sonner';

export default function SystemAnnouncements({ announcements = [], plans = [] }) {
    const [createModal, setCreateModal] = useState(false);

    const { data, setData, post, processing, reset, errors } = useForm({
        title: '',
        message: '',
        type: 'info',
        target: 'all',
        target_id: '',
    });

    const handleCreate = (e) => {
        e.preventDefault();
        post(route('admin.announcements.store'), {
            onSuccess: () => {
                toast.success('Announcement published successfully.');
                setCreateModal(false);
                reset();
            },
        });
    };

    const handleToggle = (id) => {
        router.post(route('admin.announcements.toggle', id), {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Announcement status updated.'),
        });
    };

    const handleDelete = (id) => {
        if (!confirm('Are you sure you want to delete this announcement?')) return;
        router.delete(route('admin.announcements.destroy', id), {
            onSuccess: () => toast.success('Announcement deleted.'),
        });
    };

    return (
        <AdminLayout>
            <Head title="System Announcements — Super Admin" />

            <div className="space-y-6 max-w-6xl mx-auto">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-brand-50 text-brand-700 dark:bg-neutral-800 dark:text-brand-400 text-xs font-semibold uppercase mb-1">
                            <Megaphone className="w-3.5 h-3.5" /> Broadcast Notices
                        </div>
                        <h1 className="text-xl font-bold text-slate-900 dark:text-white">
                            System Announcements
                        </h1>
                        <p className="text-xs text-slate-500 dark:text-neutral-400">
                            Broadcast maintenance warnings, new features, and platform updates directly into customer dashboards.
                        </p>
                    </div>

                    <Button onClick={() => setCreateModal(true)} size="sm" className="bg-brand-600 hover:bg-brand-700 text-white gap-1.5 text-xs">
                        <Plus className="w-3.5 h-3.5" /> New Announcement
                    </Button>
                </div>

                <Card className="border-slate-200 dark:border-neutral-800 overflow-hidden">
                    {announcements.length === 0 ? (
                        <div className="p-12 text-center text-xs text-slate-400">
                            <Megaphone className="w-10 h-10 mx-auto mb-2 text-slate-300 dark:text-neutral-600" />
                            No active system announcements.
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100 dark:divide-neutral-800">
                            {announcements.map((ann) => (
                                <div key={ann.id} className="p-5 flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                                    <div className="space-y-1 max-w-2xl">
                                        <div className="flex items-center gap-2">
                                            <Badge variant={ann.type === 'danger' ? 'danger' : ann.type === 'warning' ? 'neutral' : 'success'} className="uppercase text-[10px]">
                                                {ann.type}
                                            </Badge>
                                            <h3 className="font-bold text-sm text-slate-900 dark:text-white">{ann.title}</h3>
                                            <Badge variant={ann.active ? 'success' : 'neutral'} className="text-[10px]">
                                                {ann.active ? 'Live' : 'Hidden'}
                                            </Badge>
                                        </div>
                                        <p className="text-xs text-slate-600 dark:text-neutral-300 leading-relaxed">
                                            {ann.message}
                                        </p>
                                        <div className="text-[11px] text-slate-400 font-mono">
                                            Target: {ann.target} • Published: {new Date(ann.created_at).toLocaleDateString()}
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 shrink-0">
                                        <Button size="sm" variant="ghost" className="text-xs" onClick={() => handleToggle(ann.id)}>
                                            {ann.active ? 'Disable' : 'Enable'}
                                        </Button>
                                        <Button size="sm" variant="ghost" className="text-rose-500 hover:text-rose-700" onClick={() => handleDelete(ann.id)}>
                                            <Trash2 className="w-3.5 h-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>

            {/* Create Announcement Modal */}
            <Modal
                show={createModal}
                onClose={() => setCreateModal(false)}
                title="Create System Announcement"
            >
                <form onSubmit={handleCreate} className="space-y-4">
                    <div>
                        <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                            Announcement Title *
                        </label>
                        <Input
                            placeholder="e.g. Scheduled Maintenance Notice"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            required
                        />
                        {errors.title && <p className="text-xs text-rose-500 mt-1">{errors.title}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                            Message Content *
                        </label>
                        <textarea
                            className="w-full text-xs rounded-xl border border-slate-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-3 text-slate-900 dark:text-white"
                            rows={3}
                            placeholder="Details of the announcement displayed to users..."
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            required
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                Notice Severity
                            </label>
                            <Select value={data.type} onChange={(e) => setData('type', e.target.value)}>
                                <option value="info">Info (Blue)</option>
                                <option value="warning">Warning (Amber)</option>
                                <option value="danger">Urgent / Maintenance (Red)</option>
                                <option value="success">Feature / News (Green)</option>
                            </Select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                Target Audience
                            </label>
                            <Select value={data.target} onChange={(e) => setData('target', e.target.value)}>
                                <option value="all">All Organizations</option>
                                <option value="plan">Specific Plan</option>
                            </Select>
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-4 border-t border-slate-100 dark:border-neutral-800">
                        <Button type="button" variant="ghost" onClick={() => setCreateModal(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing} className="bg-brand-600 hover:bg-brand-700 text-white text-xs">
                            Publish Notice
                        </Button>
                    </div>
                </form>
            </Modal>
        </AdminLayout>
    );
}
