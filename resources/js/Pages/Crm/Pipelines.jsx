import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import ClientLayout from '@/Layouts/ClientLayout';
import { Plus, Trash2, Edit2, Layers, CheckCircle2, ChevronLeft } from 'lucide-react';

export default function CrmPipelines({ pipelines, colors }) {
    const [selectedPipeline, setSelectedPipeline] = useState(pipelines?.[0] || null);
    const [showStageModal, setShowStageModal] = useState(false);
    const [editingStage, setEditingStage] = useState(null);

    const [stageForm, setStageForm] = useState({
        name: '',
        color: 'blue',
        probability: 50,
        is_won: false,
        is_lost: false,
    });

    const handleOpenStageModal = (stage = null) => {
        if (stage) {
            setEditingStage(stage);
            setStageForm({
                name: stage.name,
                color: stage.color,
                probability: stage.probability,
                is_won: stage.is_won,
                is_lost: stage.is_lost,
            });
        } else {
            setEditingStage(null);
            setStageForm({ name: '', color: 'blue', probability: 50, is_won: false, is_lost: false });
        }
        setShowStageModal(true);
    };

    const handleSaveStage = (e) => {
        e.preventDefault();
        if (editingStage) {
            router.put(route('client.crm.stages.update', editingStage.id), stageForm, {
                onSuccess: () => setShowStageModal(false),
            });
        } else {
            router.post(route('client.crm.stages.store', selectedPipeline.id), stageForm, {
                onSuccess: () => setShowStageModal(false),
            });
        }
    };

    const handleDeleteStage = (stageId) => {
        if (confirm('Are you sure you want to delete this stage?')) {
            router.delete(route('client.crm.stages.destroy', stageId));
        }
    };

    return (
        <ClientLayout>
            <Head title="CRM Pipelines & Stages" />

            <div className="p-6 space-y-6 max-w-5xl mx-auto">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-neutral-900 dark:text-white">Pipeline Stages & Funnel Settings</h1>
                        <p className="text-sm text-neutral-500 mt-1">Configure customized stages, colors, and win probabilities.</p>
                    </div>

                    <button
                        onClick={() => handleOpenStageModal()}
                        className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-lg bg-brand-600 text-white hover:bg-brand-700 transition"
                    >
                        <Plus className="h-4 w-4" />
                        Add Stage
                    </button>
                </div>

                <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-100 dark:divide-neutral-800 shadow-xs">
                    {selectedPipeline?.stages?.map((stage, idx) => (
                        <div key={stage.id} className="p-4 flex items-center justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <span className="font-mono text-xs text-neutral-400 font-bold w-4">#{idx + 1}</span>
                                <span className="h-3 w-3 rounded-full bg-brand-500" />
                                <div>
                                    <h4 className="font-semibold text-sm text-neutral-900 dark:text-white">{stage.name}</h4>
                                    <span className="text-xs text-neutral-500">{stage.probability}% win probability</span>
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                <button
                                    onClick={() => handleOpenStageModal(stage)}
                                    className="p-1.5 rounded-lg text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                >
                                    <Edit2 className="h-4 w-4" />
                                </button>
                                {!stage.is_won && !stage.is_lost && (
                                    <button
                                        onClick={() => handleDeleteStage(stage.id)}
                                        className="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Stage Modal */}
            {showStageModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-2xl">
                        <h3 className="font-bold text-base mb-4 text-neutral-900 dark:text-white">
                            {editingStage ? 'Edit Stage' : 'New Stage'}
                        </h3>
                        <form onSubmit={handleSaveStage} className="space-y-3.5 text-sm">
                            <div>
                                <label className="block text-xs font-semibold mb-1">Stage Name</label>
                                <input
                                    required
                                    type="text"
                                    value={stageForm.name}
                                    onChange={(e) => setStageForm({ ...stageForm, name: e.target.value })}
                                    className="w-full p-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold mb-1">Win Probability (%)</label>
                                <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    value={stageForm.probability}
                                    onChange={(e) => setStageForm({ ...stageForm, probability: parseInt(e.target.value) || 0 })}
                                    className="w-full p-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800"
                                />
                            </div>
                            <div className="flex justify-end gap-2 pt-3">
                                <button type="button" onClick={() => setShowStageModal(false)} className="px-3 py-1.5 rounded text-neutral-500">Cancel</button>
                                <button type="submit" className="px-4 py-1.5 rounded-lg bg-brand-600 text-white font-semibold">Save Stage</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </ClientLayout>
    );
}
