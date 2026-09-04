import { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Button, Card, Badge, Modal, Input, Select } from '@/Components/ui';
import {
    PhoneCall, Plus, ArrowLeft, CheckCircle2, ShieldCheck,
    Bot, Sliders, ToggleLeft, ToggleRight, Trash2, PhoneIncoming,
} from 'lucide-react';
import { toast } from 'sonner';

export default function TelephonyNumbers({ numbers = [], agents = [] }) {
    const [addModal, setAddModal] = useState(false);
    const [editingNumber, setEditingNumber] = useState(null);

    const { data, setData, post, put, processing, reset, errors } = useForm({
        phone_number: '',
        provider: 'twilio',
        assigned_voice_agent_id: '',
        direction: 'both',
        is_default: false,
        status: 'connected',
    });

    const handleOpenAdd = () => {
        reset();
        setEditingNumber(null);
        setAddModal(true);
    };

    const handleOpenEdit = (num) => {
        setEditingNumber(num);
        setData({
            phone_number: num.phone_number,
            provider: num.provider,
            assigned_voice_agent_id: num.assigned_voice_agent_id || '',
            direction: num.direction,
            is_default: num.is_default,
            status: num.status,
        });
        setAddModal(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingNumber) {
            put(route('client.voice.numbers.update', editingNumber.uuid), {
                onSuccess: () => {
                    toast.success('Phone number updated successfully.');
                    setAddModal(false);
                },
            });
        } else {
            post(route('client.voice.numbers.store'), {
                onSuccess: () => {
                    toast.success('Phone number added successfully.');
                    setAddModal(false);
                },
            });
        }
    };

    const handleToggle = (num) => {
        router.post(route('client.voice.numbers.toggle', num.uuid), {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Phone number status updated.'),
        });
    };

    const handleDelete = (num) => {
        if (confirm('Are you sure you want to remove this phone number?')) {
            router.delete(route('client.voice.numbers.destroy', num.uuid), {
                onSuccess: () => toast.success('Phone number removed.'),
            });
        }
    };

    return (
        <ClientLayout>
            <Head title="Telephony Phone Numbers — Growbridge Connect" />

            <div className="space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link href={route('client.voice.index')}>
                            <Button type="button" variant="ghost" size="sm" className="p-2">
                                <ArrowLeft className="w-4 h-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-xl font-bold text-slate-900 dark:text-white">Telephony Phone Numbers</h1>
                            <p className="text-xs text-slate-500 dark:text-neutral-400">
                                Manage virtual numbers and assign AI Voice Agents for inbound/outbound call routing.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link href={route('client.voice.settings.index')}>
                            <Button variant="outline" size="sm" className="gap-1.5 text-xs">
                                <Sliders className="w-3.5 h-3.5" /> Twilio Voice Settings
                            </Button>
                        </Link>
                        <Button onClick={handleOpenAdd} size="sm" className="bg-brand-600 hover:bg-brand-700 text-white gap-1.5 text-xs">
                            <Plus className="w-3.5 h-3.5" /> Add Phone Number
                        </Button>
                    </div>
                </div>

                <Card className="border-slate-200 dark:border-neutral-800 overflow-hidden">
                    {numbers.length === 0 ? (
                        <div className="p-12 text-center">
                            <PhoneCall className="w-10 h-10 text-slate-300 dark:text-neutral-600 mx-auto mb-2" />
                            <p className="text-sm font-medium text-slate-600 dark:text-neutral-300">No phone numbers configured yet</p>
                            <p className="text-xs text-slate-400 mt-1 mb-4">
                                Add your Twilio, Exotel, or Plivo virtual numbers to begin routing AI voice calls.
                            </p>
                            <Button onClick={handleOpenAdd} size="sm" className="bg-brand-600 hover:bg-brand-700 text-white gap-1.5">
                                <Plus className="w-3.5 h-3.5" /> Add First Number
                            </Button>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-50 dark:bg-neutral-800/60 text-slate-500 dark:text-neutral-400 uppercase font-semibold border-b border-slate-200 dark:border-neutral-800">
                                    <tr>
                                        <th className="px-5 py-3">Phone Number</th>
                                        <th className="px-5 py-3">Provider</th>
                                        <th className="px-5 py-3">Assigned AI Agent</th>
                                        <th className="px-5 py-3">Direction</th>
                                        <th className="px-5 py-3">Status</th>
                                        <th className="px-5 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-neutral-800">
                                    {numbers.map((num) => (
                                        <tr key={num.id} className="hover:bg-slate-50/50 dark:hover:bg-neutral-800/30">
                                            <td className="px-5 py-3 font-semibold text-slate-900 dark:text-white">
                                                <div className="flex items-center gap-2">
                                                    <span>{num.phone_number}</span>
                                                    {num.is_default && (
                                                        <span className="text-[10px] px-1.5 py-0.5 rounded bg-brand-100 text-brand-800 font-bold uppercase">
                                                            Default
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-5 py-3 capitalize text-slate-600 dark:text-neutral-300">
                                                {num.provider === 'twilio' ? 'Twilio Voice' : num.provider}
                                            </td>
                                            <td className="px-5 py-3 text-slate-700 dark:text-neutral-200 font-medium">
                                                {num.voice_agent ? (
                                                    <div className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-brand-50 text-brand-700 dark:bg-neutral-800 dark:text-brand-400">
                                                        <Bot className="w-3 h-3" />
                                                        {num.voice_agent.name}
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400">Unassigned</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3 capitalize text-slate-600 dark:text-neutral-400">
                                                {num.direction}
                                            </td>
                                            <td className="px-5 py-3">
                                                <Badge variant={num.status === 'connected' ? 'success' : 'neutral'} className="capitalize">
                                                    {num.status}
                                                </Badge>
                                            </td>
                                            <td className="px-5 py-3 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Button size="sm" variant="ghost" className="p-1 h-7 text-xs" onClick={() => handleToggle(num)}>
                                                        {num.status === 'connected' ? 'Disable' : 'Enable'}
                                                    </Button>
                                                    <Button size="sm" variant="outline" className="p-1 h-7 text-xs" onClick={() => handleOpenEdit(num)}>
                                                        Edit
                                                    </Button>
                                                    <Button size="sm" variant="ghost" className="p-1 h-7 text-rose-500 hover:text-rose-700" onClick={() => handleDelete(num)}>
                                                        <Trash2 className="w-3.5 h-3.5" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
            </div>

            {/* Add / Edit Modal */}
            <Modal
                show={addModal}
                onClose={() => setAddModal(false)}
                title={editingNumber ? 'Edit Phone Number' : 'Add Virtual Phone Number'}
            >
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                            Phone Number (E.164 format) *
                        </label>
                        <Input
                            placeholder="+919876543210"
                            value={data.phone_number}
                            onChange={(e) => setData('phone_number', e.target.value)}
                            required
                        />
                        {errors.phone_number && <p className="text-xs text-rose-500 mt-1">{errors.phone_number}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                Provider *
                            </label>
                            <Select
                                value={data.provider}
                                onChange={(e) => setData('provider', e.target.value)}
                            >
                                <option value="twilio">Twilio Voice</option>
                                <option value="exotel">Exotel</option>
                                <option value="plivo">Plivo</option>
                            </Select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                                Call Direction
                            </label>
                            <Select
                                value={data.direction}
                                onChange={(e) => setData('direction', e.target.value)}
                            >
                                <option value="both">Inbound & Outbound</option>
                                <option value="inbound">Inbound Only</option>
                                <option value="outbound">Outbound Only</option>
                            </Select>
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-slate-700 dark:text-neutral-200 mb-1">
                            Assigned AI Voice Agent
                        </label>
                        <Select
                            value={data.assigned_voice_agent_id}
                            onChange={(e) => setData('assigned_voice_agent_id', e.target.value)}
                        >
                            <option value="">None (Manual / Forwarding)</option>
                            {agents.map((ag) => (
                                <option key={ag.id} value={ag.id}>{ag.name} ({ag.provider})</option>
                            ))}
                        </Select>
                    </div>

                    <div className="flex items-center gap-2 pt-2">
                        <input
                            type="checkbox"
                            id="is_default"
                            checked={data.is_default}
                            onChange={(e) => setData('is_default', e.target.checked)}
                            className="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                        />
                        <label htmlFor="is_default" className="text-xs text-slate-700 dark:text-neutral-200">
                            Set as default caller ID for outbound calls
                        </label>
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-4 border-t border-slate-100 dark:border-neutral-800">
                        <Button type="button" variant="ghost" onClick={() => setAddModal(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing} className="bg-brand-600 hover:bg-brand-700 text-white">
                            {editingNumber ? 'Save Changes' : 'Add Number'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </ClientLayout>
    );
}
