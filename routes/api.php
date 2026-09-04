<?php

use App\Http\Controllers\Api\V1\AiChatbotApiController;
use App\Http\Controllers\Api\V1\AiKnowledgeBaseApiController;
use App\Http\Controllers\Api\V1\AnalyticsApiController;
use App\Http\Controllers\Api\V1\AuditLogApiController;
use App\Http\Controllers\Api\V1\AutomationApiController;
use App\Http\Controllers\Api\V1\CampaignApiController;
use App\Http\Controllers\Api\V1\ContactApiController;
use App\Http\Controllers\Api\V1\ConversationApiController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MessageApiController;
use App\Http\Controllers\Api\V1\MobileAuthController;
use App\Http\Controllers\Api\V1\MobileConversationController;
use App\Http\Controllers\Api\V1\MobileInboxController;
use App\Http\Controllers\Api\V1\NotificationApiController;
use App\Http\Controllers\Api\V1\OutboundWebhookApiController;
use App\Http\Controllers\Api\V1\SegmentApiController;
use App\Http\Controllers\Api\V1\SocialPostApiController;
use App\Http\Controllers\Api\V1\SubscriptionApiController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\WorkspaceApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — v1
|--------------------------------------------------------------------------
|
| All routes here are prefixed with /api/v1 and guarded by Sanctum.
| Authenticate with: Authorization: Bearer <token>
|
*/

// ─── Mobile Auth (public — no token required for login) ───────────────────────
Route::prefix('v1/auth')->middleware(['throttle:10,1'])->group(function () {
    Route::post('/login', [MobileAuthController::class, 'login']);
});

Route::prefix('v1/auth')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [MobileAuthController::class, 'logout']);
    Route::get('/me', [MobileAuthController::class, 'me']);
    Route::post('/profile', [MobileAuthController::class, 'updateProfile'])->middleware('demo');
});

// ─── Mobile Inbox API (agent-facing: full conversation + inbox actions) ───────
// `demo` blocks writes (POST/PATCH/DELETE) in demo mode while GET reads pass,
// keeping the mobile app a consistent read-only showcase like the web app.
Route::prefix('v1/mobile')->middleware(['auth:sanctum', 'throttle:api', 'demo'])->group(function () {
    // Growbridge Connect — Unified Mobile API
    Route::get('/bootstrap', [\App\Http\Controllers\Api\V1\MobileAppController::class, 'bootstrap']);
    Route::get('/feed', [\App\Http\Controllers\Api\V1\MobileAppController::class, 'conversations']);
    Route::get('/chat/{id}', [\App\Http\Controllers\Api\V1\MobileAppController::class, 'conversationDetail']);
    Route::post('/chat/{id}/send', [\App\Http\Controllers\Api\V1\MobileAppController::class, 'sendMessage']);
    Route::post('/chat/{id}/ai-assist', [\App\Http\Controllers\Api\V1\MobileAppController::class, 'aiAssist']);
    Route::post('/chat/{id}/handoff', [\App\Http\Controllers\Api\V1\MobileAppController::class, 'handoff']);
    Route::get('/calls', [\App\Http\Controllers\Api\V1\MobileAppController::class, 'calls']);
    Route::post('/calls/initiate', [\App\Http\Controllers\Api\V1\MobileAppController::class, 'initiateCall']);
    Route::get('/contacts-list', [\App\Http\Controllers\Api\V1\MobileAppController::class, 'contacts']);
    Route::post('/push-token', [\App\Http\Controllers\Api\V1\MobileAppController::class, 'registerPushToken']);
    Route::get('/app-release/check', [\App\Http\Controllers\Api\V1\MobileAppController::class, 'checkAppRelease']);

    // Conversations
    Route::get('/conversations', [MobileConversationController::class, 'index']);
    Route::get('/conversations/{uuid}', [MobileConversationController::class, 'show']);
    Route::get('/conversations/{uuid}/messages', [MobileConversationController::class, 'messages']);
    Route::post('/conversations', [MobileConversationController::class, 'start']);
    Route::post('/conversations/{uuid}/reply', [MobileConversationController::class, 'reply']);
    Route::patch('/conversations/{uuid}/assign', [MobileConversationController::class, 'assign']);
    Route::patch('/conversations/{uuid}/status', [MobileConversationController::class, 'updateStatus']);
    Route::post('/conversations/{uuid}/typing', [MobileConversationController::class, 'typing']);
    Route::post('/conversations/{uuid}/handover', [MobileConversationController::class, 'handover']);

    // Notes
    Route::get('/conversations/{uuid}/notes', [MobileConversationController::class, 'notes']);
    Route::post('/conversations/{uuid}/notes', [MobileConversationController::class, 'storeNote']);

    // Labels
    Route::post('/conversations/{uuid}/labels', [MobileConversationController::class, 'attachLabel']);
    Route::delete('/conversations/{uuid}/labels/{labelId}', [MobileConversationController::class, 'detachLabel']);

    // Inbox setup data
    Route::get('/inbox/setup', [MobileInboxController::class, 'setup']);
    Route::get('/inbox/templates', [MobileInboxController::class, 'templates']);
    Route::get('/inbox/labels', [MobileInboxController::class, 'labels']);
    Route::get('/inbox/canned-replies', [MobileInboxController::class, 'cannedReplies']);

    // Contacts
    Route::get('/contacts/search', [MobileInboxController::class, 'contactSearch']);
    Route::get('/contacts/{id}', [MobileInboxController::class, 'contact']);
});

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api', 'demo'])->group(function () {

    // ─── Account ─────────────────────────────────────────────────────────────
    Route::get('/me', [MeController::class, 'show']);
    Route::patch('/me', [MeController::class, 'update']);
    Route::get('/workspaces', [WorkspaceApiController::class, 'index']);
    Route::get('/subscription', [SubscriptionApiController::class, 'show']);
    Route::get('/usage', [SubscriptionApiController::class, 'usage']);
    Route::get('/audit-log', [AuditLogApiController::class, 'index']);
    Route::get('/notifications', [NotificationApiController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationApiController::class, 'markRead']);
    Route::get('/tokens', [TokenController::class, 'index']);
    Route::post('/tokens', [TokenController::class, 'store']);
    Route::delete('/tokens/{tokenId}', [TokenController::class, 'destroy']);
    Route::get('/token-scopes', [TokenController::class, 'scopes']);

    // ─── Contacts (contacts:read / contacts:write) ────────────────────────────
    Route::get('/contacts', [ContactApiController::class, 'index'])
        ->middleware('api.ability:contacts:read');
    Route::post('/contacts', [ContactApiController::class, 'store'])
        ->middleware('api.ability:contacts:write');
    Route::get('/contacts/{id}', [ContactApiController::class, 'show'])
        ->middleware('api.ability:contacts:read');
    Route::patch('/contacts/{id}', [ContactApiController::class, 'update'])
        ->middleware('api.ability:contacts:write');
    Route::delete('/contacts/{id}', [ContactApiController::class, 'destroy'])
        ->middleware('api.ability:contacts:write');

    // ─── Segments (contacts:read / contacts:write) ────────────────────────────
    Route::get('/segments', [SegmentApiController::class, 'index'])
        ->middleware('api.ability:contacts:read');
    Route::post('/segments', [SegmentApiController::class, 'store'])
        ->middleware('api.ability:contacts:write');
    Route::get('/segments/{id}/contacts', [SegmentApiController::class, 'contacts'])
        ->middleware('api.ability:contacts:read');

    // ─── Campaigns (campaigns:read / campaigns:write) ─────────────────────────
    Route::get('/campaigns', [CampaignApiController::class, 'index'])
        ->middleware('api.ability:campaigns:read');
    Route::post('/campaigns', [CampaignApiController::class, 'store'])
        ->middleware('api.ability:campaigns:write');
    Route::get('/campaigns/{id}', [CampaignApiController::class, 'show'])
        ->middleware('api.ability:campaigns:read');
    Route::patch('/campaigns/{id}', [CampaignApiController::class, 'update'])
        ->middleware('api.ability:campaigns:write');
    Route::delete('/campaigns/{id}', [CampaignApiController::class, 'destroy'])
        ->middleware('api.ability:campaigns:write');
    Route::post('/campaigns/{id}/send', [CampaignApiController::class, 'launch'])
        ->middleware(['api.ability:campaigns:write', 'limit:campaigns_per_month,campaigns']);
    Route::post('/campaigns/{id}/launch', [CampaignApiController::class, 'launch'])
        ->middleware(['api.ability:campaigns:write', 'limit:campaigns_per_month,campaigns']);
    Route::post('/campaigns/{id}/pause', [CampaignApiController::class, 'pause'])
        ->middleware('api.ability:campaigns:write');
    Route::post('/campaigns/{id}/resume', [CampaignApiController::class, 'resume'])
        ->middleware('api.ability:campaigns:write');
    Route::post('/campaigns/{id}/cancel', [CampaignApiController::class, 'cancel'])
        ->middleware('api.ability:campaigns:write');
    Route::post('/campaigns/{id}/duplicate', [CampaignApiController::class, 'duplicate'])
        ->middleware('api.ability:campaigns:write');
    Route::post('/campaigns/{id}/test', [CampaignApiController::class, 'test'])
        ->middleware('api.ability:campaigns:write');
    Route::get('/campaigns/{id}/recipients', [CampaignApiController::class, 'recipients'])
        ->middleware('api.ability:campaigns:read');

    // ─── Messages (messages:write) ────────────────────────────────────────────
    Route::post('/messages/send', [MessageApiController::class, 'send'])
        ->middleware(['api.ability:messages:write', 'throttle:60,1']);

    // ─── Conversations (conversations:read / conversations:write) ───────────
    Route::get('/conversations', [ConversationApiController::class, 'index'])
        ->middleware('api.ability:conversations:read');
    Route::get('/conversations/{id}/messages', [ConversationApiController::class, 'messages'])
        ->middleware('api.ability:conversations:read');
    Route::post('/conversations/{id}/ai/reply', [ConversationApiController::class, 'aiReply'])
        ->middleware('api.ability:conversations:write');
    Route::post('/conversations/{id}/ai/enable', [ConversationApiController::class, 'aiEnable'])
        ->middleware('api.ability:conversations:write');
    Route::post('/conversations/{id}/ai/disable', [ConversationApiController::class, 'aiDisable'])
        ->middleware('api.ability:conversations:write');
    Route::post('/conversations/{id}/handoff', [ConversationApiController::class, 'handoff'])
        ->middleware('api.ability:conversations:write');
    Route::post('/conversations/{id}/assign', [ConversationApiController::class, 'assign'])
        ->middleware('api.ability:conversations:write');

    // ─── Outbound Webhooks (webhooks:write) ───────────────────────────────────
    Route::get('/webhooks', [OutboundWebhookApiController::class, 'index'])
        ->middleware('api.ability:webhooks:write');
    Route::post('/webhooks', [OutboundWebhookApiController::class, 'store'])
        ->middleware('api.ability:webhooks:write');
    Route::delete('/webhooks/{id}', [OutboundWebhookApiController::class, 'destroy'])
        ->middleware('api.ability:webhooks:write');

    // ─── AI — Agents (ai:read / ai:write) ───────────────────────────────────
    Route::get('/ai/agents', [\App\Http\Controllers\Api\V1\AiAgentApiController::class, 'index'])
        ->middleware('api.ability:ai:read,ai:write');
    Route::post('/ai/agents', [\App\Http\Controllers\Api\V1\AiAgentApiController::class, 'store'])
        ->middleware('api.ability:ai:write');
    Route::get('/ai/agents/{id}', [\App\Http\Controllers\Api\V1\AiAgentApiController::class, 'show'])
        ->middleware('api.ability:ai:read,ai:write');
    Route::put('/ai/agents/{id}', [\App\Http\Controllers\Api\V1\AiAgentApiController::class, 'update'])
        ->middleware('api.ability:ai:write');
    Route::delete('/ai/agents/{id}', [\App\Http\Controllers\Api\V1\AiAgentApiController::class, 'destroy'])
        ->middleware('api.ability:ai:write');
    Route::post('/ai/agents/{id}/activate', [\App\Http\Controllers\Api\V1\AiAgentApiController::class, 'activate'])
        ->middleware('api.ability:ai:write');
    Route::post('/ai/agents/{id}/pause', [\App\Http\Controllers\Api\V1\AiAgentApiController::class, 'pause'])
        ->middleware('api.ability:ai:write');
    Route::post('/ai/agents/{id}/test', [\App\Http\Controllers\Api\V1\AiAgentApiController::class, 'test'])
        ->middleware('api.ability:ai:write');

    // Legacy alias
    Route::get('/ai/chatbots', [\App\Http\Controllers\Api\V1\AiChatbotApiController::class, 'index'])
        ->middleware('api.ability:ai:read');
    Route::post('/ai/chatbots/{id}/chat', [\App\Http\Controllers\Api\V1\AiChatbotApiController::class, 'chat'])
        ->middleware('api.ability:ai:write');

    // ─── AI — Knowledge Bases (ai:read / ai:write) ────────────────────────────
    Route::get('/ai/knowledge-bases', [AiKnowledgeBaseApiController::class, 'index'])
        ->middleware('api.ability:ai:read,ai:write');
    Route::post('/ai/knowledge-bases', [AiKnowledgeBaseApiController::class, 'store'])
        ->middleware('api.ability:ai:write');
    Route::get('/ai/knowledge-bases/{id}', [AiKnowledgeBaseApiController::class, 'show'])
        ->middleware('api.ability:ai:read,ai:write');
    Route::post('/ai/knowledge-bases/{id}/publish', [AiKnowledgeBaseApiController::class, 'publish'])
        ->middleware('api.ability:ai:write');
    Route::post('/ai/knowledge-bases/{id}/search', [AiKnowledgeBaseApiController::class, 'search'])
        ->middleware('api.ability:ai:read,ai:write');
    Route::post('/ai/knowledge-bases/{id}/documents', [AiKnowledgeBaseApiController::class, 'addDocument'])
        ->middleware('api.ability:ai:write');
    Route::delete('/ai/knowledge-bases/{kbId}/documents/{docId}', [AiKnowledgeBaseApiController::class, 'destroyDocument'])
        ->middleware('api.ability:ai:write');

    // ─── Automations (automations:read / automations:write) ──────────────────
    Route::get('/automations', [AutomationApiController::class, 'index'])
        ->middleware('api.ability:automations:read,automations:write');
    Route::post('/automations', [AutomationApiController::class, 'store'])
        ->middleware('api.ability:automations:write');
    Route::get('/automations/{id}', [AutomationApiController::class, 'show'])
        ->middleware('api.ability:automations:read,automations:write');
    Route::put('/automations/{id}', [AutomationApiController::class, 'update'])
        ->middleware('api.ability:automations:write');
    Route::delete('/automations/{id}', [AutomationApiController::class, 'destroy'])
        ->middleware('api.ability:automations:write');
    Route::post('/automations/{id}/activate', [AutomationApiController::class, 'activate'])
        ->middleware('api.ability:automations:write');
    Route::post('/automations/{id}/pause', [AutomationApiController::class, 'pause'])
        ->middleware('api.ability:automations:write');
    Route::post('/automations/{id}/test', [AutomationApiController::class, 'test'])
        ->middleware('api.ability:automations:write');
    Route::post('/automations/{id}/trigger', [AutomationApiController::class, 'trigger'])
        ->middleware('api.ability:automations:write');
    Route::get('/automations/{id}/runs', [AutomationApiController::class, 'runs'])
        ->middleware('api.ability:automations:read,automations:write');

    // ─── Social (social:write) ────────────────────────────────────────────────
    Route::get('/social/accounts', [SocialPostApiController::class, 'accounts'])
        ->middleware('api.ability:social:write');
    Route::post('/social/posts', [SocialPostApiController::class, 'store'])
        ->middleware(['api.ability:social:write', 'limit:social_posts_per_month,social_posts']);

    // ─── Voice Telephony (voice:call) ────────────────────────────────────────
    Route::post('/calls', [\App\Http\Controllers\Api\V1\VoiceApiController::class, 'initiateCall'])
        ->middleware(['api.ability:voice:call', 'throttle:30,1']);

    // ─── Global Omnichannel Search (contacts:read) ─────────────────────────
    Route::get('/search', [\App\Http\Controllers\Api\V1\SearchApiController::class, 'search'])
        ->middleware('api.ability:contacts:read');

    // ─── CRM Pipeline, Leads, Contacts, Companies, Deals, Tasks, Timeline, Analytics ──
    Route::prefix('crm')->group(function () {
        // Leads
        Route::get('/leads', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'listLeads'])->middleware('api.ability:contacts:read,leads:read');
        Route::post('/leads', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'storeLead'])->middleware('api.ability:contacts:write,leads:write');
        Route::get('/leads/{id}', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'getLead'])->middleware('api.ability:contacts:read,leads:read');
        Route::put('/leads/{id}', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'updateLead'])->middleware('api.ability:contacts:write,leads:write');
        Route::delete('/leads/{id}', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'deleteLead'])->middleware('api.ability:contacts:write,leads:write');
        Route::post('/leads/{id}/stage', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'moveStage'])->middleware('api.ability:contacts:write,leads:write');

        // Companies
        Route::get('/companies', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'listCompanies'])->middleware('api.ability:contacts:read,leads:read');
        Route::post('/companies', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'storeCompany'])->middleware('api.ability:contacts:write,leads:write');
        Route::get('/companies/{id}', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'getCompany'])->middleware('api.ability:contacts:read,leads:read');
        Route::put('/companies/{id}', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'updateCompany'])->middleware('api.ability:contacts:write,leads:write');
        Route::delete('/companies/{id}', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'deleteCompany'])->middleware('api.ability:contacts:write,leads:write');

        // Deals
        Route::get('/deals', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'listDeals'])->middleware('api.ability:contacts:read,leads:read');
        Route::post('/deals', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'storeDeal'])->middleware('api.ability:contacts:write,leads:write');
        Route::get('/deals/{id}', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'getDeal'])->middleware('api.ability:contacts:read,leads:read');
        Route::put('/deals/{id}', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'updateDeal'])->middleware('api.ability:contacts:write,leads:write');
        Route::delete('/deals/{id}', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'deleteDeal'])->middleware('api.ability:contacts:write,leads:write');

        // Tasks
        Route::get('/tasks', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'listTasks'])->middleware('api.ability:contacts:read,leads:read');
        Route::post('/tasks', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'storeTask'])->middleware('api.ability:contacts:write,leads:write');
        Route::put('/tasks/{id}', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'updateTask'])->middleware('api.ability:contacts:write,leads:write');
        Route::delete('/tasks/{id}', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'deleteTask'])->middleware('api.ability:contacts:write,leads:write');

        // Pipelines, Custom Fields, Timeline, Analytics
        Route::get('/pipelines', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'listPipelines'])->middleware('api.ability:contacts:read,leads:read');
        Route::get('/custom-fields', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'listCustomFields'])->middleware('api.ability:contacts:read,leads:read');
        Route::get('/contacts/{id}/timeline', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'getTimeline'])->middleware('api.ability:contacts:read,leads:read');
        Route::get('/analytics', [\App\Http\Controllers\Api\V1\CrmApiController::class, 'getAnalytics'])->middleware('api.ability:analytics:read,contacts:read');
    });

    // ─── Analytics (analytics:read) ───────────────────────────────────────────
    Route::prefix('analytics')->middleware('api.ability:analytics:read')->group(function () {
        Route::get('/messages', [AnalyticsApiController::class, 'messages']);
        Route::get('/ai-usage', [AnalyticsApiController::class, 'aiUsage']);
        Route::get('/campaign/{campaign}/funnel', [AnalyticsApiController::class, 'campaignFunnel']);
        Route::get('/conversations', [AnalyticsApiController::class, 'conversations']);
    });
});

// ─── Public Website Lead Form Intake & Webhook Intake (rate-limited) ─────────
Route::prefix('v1/public')->middleware(['throttle:60,1'])->group(function () {
    Route::post('/leads/{workspaceToken}', [\App\Http\Controllers\Api\V1\LeadApiController::class, 'publicCapture']);
    Route::post('/automations/webhook/{publicKey}', [AutomationApiController::class, 'publicWebhook']);
});

// ─── CRM Inbound Webhook Ingress (HubSpot, Salesforce, Zoho, Pipedrive, Custom) ─
Route::any('v1/webhooks/crm/{provider}', [\App\Http\Controllers\Api\V1\CrmWebhookController::class, 'handle'])
    ->middleware('throttle:120,1');


