<?php

use App\Modules\AI\Http\Controllers\AiChatbotController;
use App\Modules\AI\Http\Controllers\AiKnowledgeBaseController;
use App\Modules\AI\Http\Controllers\AiProviderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'client-app'])->prefix('app/ai')->name('client.ai.')->group(function () {
    // Provider configs
    Route::get('/providers', [AiProviderController::class, 'index'])->name('providers.index');
    Route::put('/providers/{provider}', [AiProviderController::class, 'update'])->name('providers.update');

    // Unified Knowledge Base (/app/ai/knowledge & /app/knowledge)
    Route::get('/knowledge', [AiKnowledgeBaseController::class, 'index'])->name('knowledge.index');
    Route::post('/knowledge/business', [AiKnowledgeBaseController::class, 'saveBusinessInfo'])->name('knowledge.business');
    Route::post('/knowledge/product', [AiKnowledgeBaseController::class, 'saveProduct'])->name('knowledge.product');
    Route::post('/knowledge/faq', [AiKnowledgeBaseController::class, 'saveFaq'])->name('knowledge.faq');
    Route::post('/knowledge/website', [AiKnowledgeBaseController::class, 'importWebsite'])->name('knowledge.website');
    Route::post('/knowledge/document', [AiKnowledgeBaseController::class, 'uploadDocument'])->name('knowledge.document');
    Route::post('/knowledge/text', [AiKnowledgeBaseController::class, 'saveText'])->name('knowledge.text');
    Route::post('/knowledge/settings', [AiKnowledgeBaseController::class, 'updateSettings'])->name('knowledge.settings');
    Route::post('/knowledge/process', [AiKnowledgeBaseController::class, 'processKnowledge'])->name('knowledge.process');
    Route::post('/knowledge/search', [AiKnowledgeBaseController::class, 'search'])->name('knowledge.search');
    Route::post('/knowledge/documents/{document}/reprocess', [AiKnowledgeBaseController::class, 'reprocessDocument'])->name('knowledge.document.reprocess');
    Route::post('/knowledge/documents/{document}/assign-agents', [AiKnowledgeBaseController::class, 'assignAgents'])->name('knowledge.document.assign-agents');
    Route::post('/knowledge/documents/{document}/toggle', [AiKnowledgeBaseController::class, 'toggleDocument'])->name('knowledge.document.toggle');
    Route::delete('/knowledge/documents/{document}', [AiKnowledgeBaseController::class, 'destroyDocument'])->name('knowledge.document.destroy');
    Route::post('/knowledge/gaps/{question}/resolve', [AiKnowledgeBaseController::class, 'resolveGap'])->name('knowledge.gaps.resolve');

    // Knowledge Bases CRUD (multi-KB compatibility)
    Route::get('/knowledge-bases', [AiKnowledgeBaseController::class, 'index'])->name('knowledge-bases.index');
    Route::post('/knowledge-bases', [AiKnowledgeBaseController::class, 'store'])->name('knowledge-bases.store')->middleware('limit:knowledge_bases,knowledge_bases');
    Route::get('/knowledge-bases/{kb}', [AiKnowledgeBaseController::class, 'show'])->name('knowledge-bases.show');
    Route::put('/knowledge-bases/{kb}', [AiKnowledgeBaseController::class, 'update'])->name('knowledge-bases.update');
    Route::delete('/knowledge-bases/{kb}', [AiKnowledgeBaseController::class, 'destroy'])->name('knowledge-bases.destroy');
    Route::post('/knowledge-bases/{kb}/documents', [AiKnowledgeBaseController::class, 'storeDocument'])->name('knowledge-bases.documents.store');
    Route::post('/knowledge-bases/{kb}/search', [AiKnowledgeBaseController::class, 'search'])->name('knowledge-bases.search');
    Route::delete('/documents/{document}', [AiKnowledgeBaseController::class, 'destroyDocument'])->name('documents.destroy');

    // AI Agents (Chatbots & No-Code Studio)
    Route::get('/chatbots', [AiChatbotController::class, 'index'])->name('chatbots.index');
    Route::get('/chatbots/create', [AiChatbotController::class, 'create'])->name('chatbots.create');
    Route::post('/chatbots', [AiChatbotController::class, 'store'])->name('chatbots.store');
    Route::get('/chatbots/{chatbot}', [AiChatbotController::class, 'show'])->name('chatbots.show');
    Route::get('/chatbots/{chatbot}/edit', [AiChatbotController::class, 'edit'])->name('chatbots.edit');
    Route::put('/chatbots/{chatbot}', [AiChatbotController::class, 'update'])->name('chatbots.update');
    Route::delete('/chatbots/{chatbot}', [AiChatbotController::class, 'destroy'])->name('chatbots.destroy');
    Route::post('/chatbots/{chatbot}/duplicate', [AiChatbotController::class, 'duplicate'])->name('chatbots.duplicate');
    Route::post('/chatbots/{chatbot}/publish', [AiChatbotController::class, 'publish'])->name('chatbots.publish');
    Route::post('/chatbots/{chatbot}/activate', [AiChatbotController::class, 'activate'])->name('chatbots.activate');
    Route::post('/chatbots/{chatbot}/pause', [AiChatbotController::class, 'pause'])->name('chatbots.pause');
    Route::post('/chatbots/{chatbot}/simulate', [AiChatbotController::class, 'simulate'])->name('chatbots.simulate');
    Route::post('/chatbots/{chatbot}/playground', [AiChatbotController::class, 'playground'])->name('chatbots.playground')->middleware('limit:ai_tokens_per_month,ai_tokens');

    // AI Agent Playground (/app/ai/playground)
    Route::get('/playground', [\App\Modules\AI\Http\Controllers\AiPlaygroundController::class, 'index'])->name('playground.index');
    Route::post('/playground/test', [\App\Modules\AI\Http\Controllers\AiPlaygroundController::class, 'test'])->name('playground.test');
    Route::post('/playground/feedback', [\App\Modules\AI\Http\Controllers\AiPlaygroundController::class, 'saveFeedback'])->name('playground.feedback');
    Route::post('/playground/{chatbot}/activate', [\App\Modules\AI\Http\Controllers\AiPlaygroundController::class, 'activate'])->name('playground.activate');

    // AI Analytics & Usage (/app/ai/analytics)
    Route::get('/analytics', [\App\Modules\AI\Http\Controllers\AiAnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/overview', [\App\Modules\AI\Http\Controllers\AiAnalyticsController::class, 'apiOverview'])->name('analytics.overview');
    Route::post('/analytics/questions/{question}/resolve', [\App\Modules\AI\Http\Controllers\AiAnalyticsController::class, 'resolveQuestion'])->name('analytics.question.resolve');

    // AI Voice Studio (/app/ai/voice-studio)
    Route::prefix('voice-studio')->middleware(['entitlement:ai_voice_agents'])->group(function () {
        Route::get('/', [\App\Modules\AI\Http\Controllers\AiVoiceStudioController::class, 'index'])->name('voice-studio.index');
        Route::get('/{voiceAgent}', [\App\Modules\AI\Http\Controllers\AiVoiceStudioController::class, 'index'])->name('voice-studio.show');
        Route::post('/', [\App\Modules\AI\Http\Controllers\AiVoiceStudioController::class, 'save'])->name('voice-studio.save');
        Route::post('/{voiceAgent}/activate', [\App\Modules\AI\Http\Controllers\AiVoiceStudioController::class, 'activate'])->name('voice-studio.activate');
        Route::post('/{voiceAgent}/pause', [\App\Modules\AI\Http\Controllers\AiVoiceStudioController::class, 'pause'])->name('voice-studio.pause');
        Route::post('/{voiceAgent}/simulate', [\App\Modules\AI\Http\Controllers\AiVoiceStudioController::class, 'simulate'])->name('voice-studio.simulate');
    });
});
