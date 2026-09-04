<?php

use App\Http\Controllers\Client\PhoneNumberController;
use App\Modules\Voice\Http\Controllers\VoiceAgentController;
use App\Modules\Voice\Http\Controllers\VoiceCallController;
use App\Modules\Voice\Http\Controllers\VoiceWebhookController;
use Illuminate\Support\Facades\Route;

// Client web routes
Route::middleware(['web', 'client-app'])->group(function () {
    // Voice Agents, Twilio Phone Numbers & Calls
    Route::prefix('app/voice')->name('client.voice.')->middleware(['entitlement:voice_calling'])->group(function () {
        Route::get('/call-center', [\App\Modules\Voice\Http\Controllers\VoiceCallCenterController::class, 'index'])->name('call-center');
        Route::get('/call-center/live-feed', [\App\Modules\Voice\Http\Controllers\VoiceCallCenterController::class, 'liveFeed'])->name('call-center.live-feed');
        Route::get('/', [VoiceAgentController::class, 'index'])->name('index');
        Route::get('/studio', [\App\Modules\AI\Http\Controllers\AiVoiceStudioController::class, 'index'])->name('studio');
        Route::get('/create', [VoiceAgentController::class, 'create'])->name('create');
        Route::post('/', [VoiceAgentController::class, 'store'])->name('store');
        Route::get('/{voiceAgent}/edit', [VoiceAgentController::class, 'edit'])->name('edit');
        Route::put('/{voiceAgent}', [VoiceAgentController::class, 'update'])->name('update');
        Route::post('/{voiceAgent}/toggle', [VoiceAgentController::class, 'toggle'])->name('toggle');
        Route::delete('/{voiceAgent}', [VoiceAgentController::class, 'destroy'])->name('destroy');

        // Call History, Intelligence & Recordings (/app/voice/calls)
        Route::prefix('calls')->name('calls.')->group(function () {
            Route::get('/', [VoiceCallController::class, 'index'])->name('index');
            Route::get('/{call}', [VoiceCallController::class, 'show'])->name('show');
            Route::get('/{call}/transcript', [VoiceCallController::class, 'transcript'])->name('transcript');
            Route::post('/{call}/analyze', [VoiceCallController::class, 'analyze'])->name('analyze');
            Route::post('/{call}/follow-up', [VoiceCallController::class, 'followUp'])->name('follow-up');
        });

        // AI Voice Outbound Campaigns (/app/voice/campaigns)
        Route::prefix('campaigns')->name('campaigns.')->group(function () {
            Route::get('/', [\App\Modules\Voice\Http\Controllers\VoiceCampaignController::class, 'index'])->name('index');
            Route::get('/create', [\App\Modules\Voice\Http\Controllers\VoiceCampaignController::class, 'create'])->name('create');
            Route::post('/', [\App\Modules\Voice\Http\Controllers\VoiceCampaignController::class, 'store'])->name('store');
            Route::get('/{campaign}', [\App\Modules\Voice\Http\Controllers\VoiceCampaignController::class, 'show'])->name('show');
            Route::post('/{campaign}/start', [\App\Modules\Voice\Http\Controllers\VoiceCampaignController::class, 'start'])->name('start');
            Route::post('/{campaign}/pause', [\App\Modules\Voice\Http\Controllers\VoiceCampaignController::class, 'pause'])->name('pause');
            Route::post('/{campaign}/stop', [\App\Modules\Voice\Http\Controllers\VoiceCampaignController::class, 'stop'])->name('stop');
            Route::delete('/{campaign}', [\App\Modules\Voice\Http\Controllers\VoiceCampaignController::class, 'destroy'])->name('destroy');
            Route::get('/{campaign}/analytics', [\App\Modules\Voice\Http\Controllers\VoiceCampaignController::class, 'analytics'])->name('analytics');
        });

        // Smart Calling Queue (/app/voice/queue)
        Route::prefix('queue')->name('queue.')->group(function () {
            Route::get('/', [\App\Modules\Voice\Http\Controllers\SmartVoiceQueueController::class, 'index'])->name('index');
            Route::post('/{recipient}/dial', [\App\Modules\Voice\Http\Controllers\SmartVoiceQueueController::class, 'dialNow'])->name('dial');
            Route::post('/{recipient}/callback', [\App\Modules\Voice\Http\Controllers\SmartVoiceQueueController::class, 'scheduleCallback'])->name('callback');
            Route::post('/{recipient}/exclude', [\App\Modules\Voice\Http\Controllers\SmartVoiceQueueController::class, 'exclude'])->name('exclude');
            Route::post('/{recipient}/requeue', [\App\Modules\Voice\Http\Controllers\SmartVoiceQueueController::class, 'requeue'])->name('requeue');
        });

        // AI Follow-up & Callback Automation (/app/voice/follow-ups)
        Route::prefix('follow-ups')->name('follow-ups.')->group(function () {
            Route::get('/', [\App\Modules\Voice\Http\Controllers\VoiceFollowUpController::class, 'index'])->name('index');
            Route::get('/create', [\App\Modules\Voice\Http\Controllers\VoiceFollowUpController::class, 'create'])->name('create');
            Route::post('/', [\App\Modules\Voice\Http\Controllers\VoiceFollowUpController::class, 'store'])->name('store');
            Route::get('/rules', [\App\Modules\Voice\Http\Controllers\VoiceFollowUpController::class, 'rules'])->name('rules');
            Route::post('/rules', [\App\Modules\Voice\Http\Controllers\VoiceFollowUpController::class, 'storeRule'])->name('rules.store');
            Route::post('/rules/{rule}/toggle', [\App\Modules\Voice\Http\Controllers\VoiceFollowUpController::class, 'toggleRule'])->name('rules.toggle');
            Route::delete('/rules/{rule}', [\App\Modules\Voice\Http\Controllers\VoiceFollowUpController::class, 'destroyRule'])->name('rules.destroy');
            Route::get('/{followUp}', [\App\Modules\Voice\Http\Controllers\VoiceFollowUpController::class, 'show'])->name('show');
            Route::post('/{followUp}/complete', [\App\Modules\Voice\Http\Controllers\VoiceFollowUpController::class, 'complete'])->name('complete');
            Route::post('/{followUp}/reschedule', [\App\Modules\Voice\Http\Controllers\VoiceFollowUpController::class, 'reschedule'])->name('reschedule');
            Route::post('/{followUp}/cancel', [\App\Modules\Voice\Http\Controllers\VoiceFollowUpController::class, 'cancel'])->name('cancel');
        });

        // Twilio Phone Numbers Marketplace & Unified Business Numbers
        Route::get('/numbers', [PhoneNumberController::class, 'index'])->name('numbers.index');
        Route::post('/numbers', [PhoneNumberController::class, 'store'])->name('numbers.store');
        Route::get('/numbers/search', [PhoneNumberController::class, 'search'])->name('numbers.search');
        Route::post('/numbers/purchase', [PhoneNumberController::class, 'purchase'])->name('numbers.purchase');
        Route::put('/numbers/{phoneNumber}', [PhoneNumberController::class, 'update'])->name('numbers.update');
        Route::delete('/numbers/{phoneNumber}', [PhoneNumberController::class, 'destroy'])->name('numbers.destroy');

        // Unified Business Number Connections (WhatsApp & AI Agents)
        Route::post('/numbers/{phoneNumber}/whatsapp/connect', [PhoneNumberController::class, 'connectWhatsapp'])->name('numbers.whatsapp.connect');
        Route::post('/numbers/{phoneNumber}/whatsapp/embedded-signup', [PhoneNumberController::class, 'embeddedSignupWhatsapp'])->name('numbers.whatsapp.embedded-signup');
        Route::post('/numbers/{phoneNumber}/whatsapp/disconnect', [PhoneNumberController::class, 'disconnectWhatsapp'])->name('numbers.whatsapp.disconnect');
        Route::post('/numbers/{phoneNumber}/ai-agents', [PhoneNumberController::class, 'updateAiAgents'])->name('numbers.ai-agents');
        // Voice & Twilio Settings
        Route::get('/settings', [\App\Modules\Voice\Http\Controllers\VoiceSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [\App\Modules\Voice\Http\Controllers\VoiceSettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/test', [\App\Modules\Voice\Http\Controllers\VoiceSettingsController::class, 'testConnection'])->name('settings.test');
    });

    // Phone Module Aliases (/app/phone/*)
    Route::prefix('app/phone')->name('client.phone.')->middleware(['entitlement:voice_calling'])->group(function () {
        Route::get('/numbers', [PhoneNumberController::class, 'index'])->name('numbers.index');
        Route::get('/calls', [VoiceCallController::class, 'index'])->name('calls.index');
        Route::get('/settings', [\App\Modules\Voice\Http\Controllers\VoiceSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [\App\Modules\Voice\Http\Controllers\VoiceSettingsController::class, 'update'])->name('settings.update');
    });
});

// Telephony Webhooks (exempt from CSRF)
Route::middleware('throttle:webhooks')->prefix('webhooks/voice')->name('webhooks.voice.')->group(function () {
    Route::match(['get', 'post'], '/{provider}/incoming', [VoiceWebhookController::class, 'incoming'])->name('incoming');
    Route::match(['get', 'post'], '/{provider}/{call_uuid}', [VoiceWebhookController::class, 'handle'])->name('handle');
    Route::match(['get', 'post'], '/{provider}/{call_uuid}/gather', [VoiceWebhookController::class, 'gather'])->name('gather');
});
