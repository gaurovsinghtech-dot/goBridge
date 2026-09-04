<?php

use App\Modules\Shared\Http\Controllers\ContactController;
use App\Modules\Shared\Http\Controllers\SegmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'client-app'])->prefix('app')->name('client.')->group(function () {
    // Contacts
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('/contacts/bulk-import', [ContactController::class, 'bulkImport'])->name('contacts.bulk-import');
    Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::post('/contacts/bulk', [ContactController::class, 'bulkStore'])->name('contacts.bulk-store');
    Route::post('/contacts/import', [ContactController::class, 'import'])->name('contacts.import');
    Route::post('/contacts/import-rows', [ContactController::class, 'importRows'])->name('contacts.import-rows');
    Route::delete('/contacts', [ContactController::class, 'bulkDestroy'])->name('contacts.bulk-destroy');
    Route::post('/contacts/bulk-tags', [ContactController::class, 'bulkTags'])->name('contacts.bulk-tags');
    Route::post('/contacts/bulk-segments', [ContactController::class, 'bulkSegments'])->name('contacts.bulk-segments');
    Route::get('/contacts/export', [ContactController::class, 'export'])->name('contacts.export');
    Route::get('/contacts/duplicates', [\App\Modules\Shared\Http\Controllers\ContactJourneyController::class, 'duplicates'])->name('contacts.duplicates');
    Route::get('/contacts/{contact}/timeline', [\App\Http\Controllers\Client\CustomerTimelineController::class, 'timeline'])->name('contacts.timeline');
    Route::post('/contacts/{contact}/merge', [\App\Http\Controllers\Client\CustomerTimelineController::class, 'merge'])->name('contacts.merge-contact');
    Route::post('/contacts/{contact}/notes', [\App\Http\Controllers\Client\CustomerTimelineController::class, 'addNote'])->name('contacts.notes');
    Route::post('/contacts/{contact}/opt-out', [\App\Modules\Shared\Http\Controllers\ContactJourneyController::class, 'toggleOptOut'])->name('contacts.opt-out');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');
    Route::post('/contacts/{contact}/avatar', [ContactController::class, 'uploadAvatar'])->name('contacts.avatar.upload');
    Route::delete('/contacts/{contact}/avatar', [ContactController::class, 'deleteAvatar'])->name('contacts.avatar.delete');

    // Customer 360 Aliases (/app/customers)
    Route::get('/customers', [ContactController::class, 'index'])->name('customers.index');
    Route::get('/customers/{contact}', [\App\Http\Controllers\Client\CustomerTimelineController::class, 'show'])->name('customers.show');
    Route::get('/customers/{contact}/timeline', [\App\Http\Controllers\Client\CustomerTimelineController::class, 'timeline'])->name('customers.timeline');
    Route::post('/customers/{contact}/merge', [\App\Http\Controllers\Client\CustomerTimelineController::class, 'merge'])->name('customers.merge');
    Route::post('/customers/{contact}/notes', [\App\Http\Controllers\Client\CustomerTimelineController::class, 'addNote'])->name('customers.notes');

    // Segments
    Route::get('/segments', [SegmentController::class, 'index'])->name('segments.index');
    Route::post('/segments', [SegmentController::class, 'store'])->name('segments.store');
    Route::put('/segments/{segment}', [SegmentController::class, 'update'])->name('segments.update');
    Route::delete('/segments/{segment}', [SegmentController::class, 'destroy'])->name('segments.destroy');
    Route::get('/segments/{segment}/contacts', [SegmentController::class, 'manageContacts'])->name('segments.contacts');
    Route::post('/segments/{segment}/contacts', [SegmentController::class, 'attachContacts'])->name('segments.contacts.attach');
    Route::delete('/segments/{segment}/contacts/{contact}', [SegmentController::class, 'detachContact'])->name('segments.contacts.detach');

    // Knowledge Base (/app/knowledge) Aliases
    Route::get('/knowledge', [\App\Modules\AI\Http\Controllers\AiKnowledgeBaseController::class, 'index'])->name('knowledge.index');
    Route::get('/knowledge/sources', [\App\Modules\AI\Http\Controllers\AiKnowledgeBaseController::class, 'index'])->name('knowledge.sources');
    Route::get('/knowledge/documents', [\App\Modules\AI\Http\Controllers\AiKnowledgeBaseController::class, 'index'])->name('knowledge.documents');
    Route::get('/knowledge/faqs', [\App\Modules\AI\Http\Controllers\AiKnowledgeBaseController::class, 'index'])->name('knowledge.faqs');
    Route::get('/knowledge/websites', [\App\Modules\AI\Http\Controllers\AiKnowledgeBaseController::class, 'index'])->name('knowledge.websites');
    Route::get('/knowledge/gaps', [\App\Modules\AI\Http\Controllers\AiKnowledgeBaseController::class, 'index'])->name('knowledge.gaps');

    // AI Agents Studio (/app/ai-agents) Aliases
    Route::get('/ai-agents', [\App\Modules\AI\Http\Controllers\AiChatbotController::class, 'index'])->name('ai-agents.index');
    Route::get('/ai-agents/create', [\App\Modules\AI\Http\Controllers\AiChatbotController::class, 'create'])->name('ai-agents.create');
    Route::post('/ai-agents', [\App\Modules\AI\Http\Controllers\AiChatbotController::class, 'store'])->name('ai-agents.store');
    Route::get('/ai-agents/{chatbot}', [\App\Modules\AI\Http\Controllers\AiChatbotController::class, 'show'])->name('ai-agents.show');
    Route::get('/ai-agents/{chatbot}/training', [\App\Modules\AI\Http\Controllers\AiChatbotController::class, 'show'])->name('ai-agents.training');
    Route::get('/ai-agents/{chatbot}/knowledge', [\App\Modules\AI\Http\Controllers\AiChatbotController::class, 'show'])->name('ai-agents.knowledge');
    Route::get('/ai-agents/{chatbot}/behavior', [\App\Modules\AI\Http\Controllers\AiChatbotController::class, 'show'])->name('ai-agents.behavior');
    Route::get('/ai-agents/{chatbot}/testing', [\App\Modules\AI\Http\Controllers\AiChatbotController::class, 'show'])->name('ai-agents.testing');
    Route::get('/ai-agents/{chatbot}/edit', [\App\Modules\AI\Http\Controllers\AiChatbotController::class, 'edit'])->name('ai-agents.edit');
    Route::put('/ai-agents/{chatbot}', [\App\Modules\AI\Http\Controllers\AiChatbotController::class, 'update'])->name('ai-agents.update');
    Route::delete('/ai-agents/{chatbot}', [\App\Modules\AI\Http\Controllers\AiChatbotController::class, 'destroy'])->name('ai-agents.destroy');
    Route::post('/ai-agents/{chatbot}/duplicate', [\App\Modules\AI\Http\Controllers\AiChatbotController::class, 'duplicate'])->name('ai-agents.duplicate');
    Route::post('/ai-agents/{chatbot}/publish', [\App\Modules\AI\Http\Controllers\AiChatbotController::class, 'publish'])->name('ai-agents.publish');
    Route::post('/ai-agents/{chatbot}/pause', [\App\Modules\AI\Http\Controllers\AiChatbotController::class, 'pause'])->name('ai-agents.pause');
    Route::post('/ai-agents/{chatbot}/simulate', [\App\Modules\AI\Http\Controllers\AiChatbotController::class, 'simulate'])->name('ai-agents.simulate');
});
