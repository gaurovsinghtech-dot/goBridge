<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Client\ApiTokenController;
use App\Http\Controllers\Client\AuditLogController as ClientAuditLogController;
use App\Http\Controllers\Client\BillingController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\InvitationController;
use App\Http\Controllers\Client\MediaController;
use App\Http\Controllers\Client\NotificationController;
use App\Http\Controllers\Client\OnboardingController;
use App\Http\Controllers\Client\SearchController;
use App\Http\Controllers\Client\Settings\DataExportController;
use App\Http\Controllers\Client\SettingsController as ClientSettingsController;
use App\Http\Controllers\Client\SubscriptionController as ClientSubscriptionController;
use App\Http\Controllers\Client\SupportTicketController;
use App\Http\Controllers\Client\TeamController;
use App\Http\Controllers\Client\WebhookEndpointController;
use App\Http\Controllers\Client\WebPushController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['verified'])->group(function () {
    // Dashboard & Global Search
    Route::get('/dashboard', ClientDashboardController::class)->name('dashboard');
    Route::get('/search', [\App\Http\Controllers\Client\GlobalSearchController::class, 'search'])->name('search');

    // Interactive Product Tour
    Route::prefix('tour')->name('tour.')->group(function () {
        Route::get('/status/{tour_key?}', [\App\Http\Controllers\Client\ProductTourController::class, 'status'])->name('status');
        Route::post('/progress', [\App\Http\Controllers\Client\ProductTourController::class, 'progress'])->name('progress');
        Route::post('/complete', [\App\Http\Controllers\Client\ProductTourController::class, 'complete'])->name('complete');
        Route::post('/skip', [\App\Http\Controllers\Client\ProductTourController::class, 'skip'])->name('skip');
        Route::post('/reset', [\App\Http\Controllers\Client\ProductTourController::class, 'reset'])->name('reset');
    });

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/{notification}/mark-read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');

    // Smart Segmentation Preview & Contact Merge
    Route::post('/segments/preview', function (\Illuminate\Http\Request $request, \App\Modules\Shared\Services\SegmentResolver $resolver) {
        $workspace = $request->user()->currentWorkspace;
        $rules = $request->input('rules', []);
        $count = $resolver->previewCount($workspace->id, $rules);
        return response()->json(['count' => $count]);
    })->name('segments.preview');

    Route::post('/contacts/merge', function (\Illuminate\Http\Request $request, \App\Services\Contacts\ContactMergeService $mergeService) {
        $workspace = $request->user()->currentWorkspace;
        $validated = $request->validate([
            'primary_id' => ['required', 'exists:contacts,id'],
            'secondary_id' => ['required', 'exists:contacts,id'],
        ]);
        $primary = \App\Modules\Shared\Models\Contact::where('workspace_id', $workspace->id)->findOrFail($validated['primary_id']);
        $secondary = \App\Modules\Shared\Models\Contact::where('workspace_id', $workspace->id)->findOrFail($validated['secondary_id']);
        $merged = $mergeService->merge($primary, $secondary, $request->user());
        return response()->json(['success' => true, 'contact' => $merged]);
    })->name('contacts.merge');

    // CRM Pipeline, Leads, Deals, Tasks, Companies, Custom Fields, Imports, Notes & Reports
    Route::prefix('crm')->name('crm.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Client\Crm\CrmDashboardController::class, 'index'])->name('dashboard');
        Route::get('/pipeline', [\App\Http\Controllers\Client\Crm\CrmDashboardController::class, 'index'])->name('pipeline');
        Route::get('/pipelines', [\App\Http\Controllers\Client\Crm\CrmPipelineController::class, 'index'])->name('pipelines.index');
        Route::post('/pipelines', [\App\Http\Controllers\Client\Crm\CrmPipelineController::class, 'store'])->name('pipelines.store');
        Route::put('/pipelines/{pipeline}', [\App\Http\Controllers\Client\Crm\CrmPipelineController::class, 'update'])->name('pipelines.update');
        Route::delete('/pipelines/{pipeline}', [\App\Http\Controllers\Client\Crm\CrmPipelineController::class, 'destroy'])->name('pipelines.destroy');
        Route::post('/pipelines/{pipeline}/stages', [\App\Http\Controllers\Client\Crm\CrmPipelineController::class, 'storeStage'])->name('stages.store');
        Route::post('/pipelines/{pipeline}/stages/reorder', [\App\Http\Controllers\Client\Crm\CrmPipelineController::class, 'reorderStages'])->name('stages.reorder');
        Route::put('/stages/{stage}', [\App\Http\Controllers\Client\Crm\CrmPipelineController::class, 'updateStage'])->name('stages.update');
        Route::delete('/stages/{stage}', [\App\Http\Controllers\Client\Crm\CrmPipelineController::class, 'destroyStage'])->name('stages.destroy');

        // Leads
        Route::get('/leads', [\App\Http\Controllers\Client\Crm\CrmLeadController::class, 'index'])->name('leads.index');
        Route::post('/leads', [\App\Http\Controllers\Client\Crm\CrmLeadController::class, 'store'])->name('leads.store');
        Route::get('/leads/export', [\App\Http\Controllers\Client\Crm\CrmLeadController::class, 'export'])->name('leads.export');
        Route::post('/leads/bulk', [\App\Http\Controllers\Client\Crm\CrmLeadController::class, 'bulk'])->name('leads.bulk');
        Route::get('/leads/{uuid}', [\App\Http\Controllers\Client\Crm\CrmLeadController::class, 'show'])->name('leads.show');
        Route::put('/leads/{uuid}', [\App\Http\Controllers\Client\Crm\CrmLeadController::class, 'update'])->name('leads.update');
        Route::delete('/leads/{uuid}', [\App\Http\Controllers\Client\Crm\CrmLeadController::class, 'destroy'])->name('leads.destroy');
        Route::post('/leads/{uuid}/stage', [\App\Http\Controllers\Client\Crm\CrmLeadController::class, 'updateStage'])->name('leads.stage');
        Route::post('/leads/{uuid}/convert', [\App\Http\Controllers\Client\Crm\CrmLeadController::class, 'convert'])->name('leads.convert');
        Route::post('/leads/{uuid}/qualify-ai', [\App\Http\Controllers\Client\Crm\CrmLeadController::class, 'qualifyAi'])->name('leads.qualify-ai');
        Route::post('/leads/{uuid}/assign', [\App\Http\Controllers\Client\Crm\CrmLeadController::class, 'assign'])->name('leads.assign');

        // Companies
        Route::get('/companies', [\App\Http\Controllers\Client\Crm\CrmCompanyController::class, 'index'])->name('companies.index');
        Route::post('/companies', [\App\Http\Controllers\Client\Crm\CrmCompanyController::class, 'store'])->name('companies.store');
        Route::get('/companies/{company}', [\App\Http\Controllers\Client\Crm\CrmCompanyController::class, 'show'])->name('companies.show');
        Route::put('/companies/{company}', [\App\Http\Controllers\Client\Crm\CrmCompanyController::class, 'update'])->name('companies.update');
        Route::delete('/companies/{company}', [\App\Http\Controllers\Client\Crm\CrmCompanyController::class, 'destroy'])->name('companies.destroy');

        // Deals
        Route::get('/deals', [\App\Http\Controllers\Client\Crm\CrmDealController::class, 'index'])->name('deals.index');
        Route::post('/deals', [\App\Http\Controllers\Client\Crm\CrmDealController::class, 'store'])->name('deals.store');
        Route::get('/deals/{deal}', [\App\Http\Controllers\Client\Crm\CrmDealController::class, 'show'])->name('deals.show');
        Route::put('/deals/{deal}', [\App\Http\Controllers\Client\Crm\CrmDealController::class, 'update'])->name('deals.update');
        Route::delete('/deals/{deal}', [\App\Http\Controllers\Client\Crm\CrmDealController::class, 'destroy'])->name('deals.destroy');
        Route::post('/deals/{deal}/stage', [\App\Http\Controllers\Client\Crm\CrmDealController::class, 'updateStage'])->name('deals.stage');
        Route::put('/deals/{deal}/status', [\App\Http\Controllers\Client\Crm\CrmDealController::class, 'updateStatus'])->name('deals.status');

        // Tasks & Follow-ups
        Route::get('/tasks', [\App\Http\Controllers\Client\Crm\CrmTaskController::class, 'index'])->name('tasks.index');
        Route::get('/tasks/follow-ups', [\App\Http\Controllers\Client\Crm\CrmTaskController::class, 'followUps'])->name('tasks.follow-ups');
        Route::post('/tasks', [\App\Http\Controllers\Client\Crm\CrmTaskController::class, 'store'])->name('tasks.store');
        Route::get('/tasks/{task}', [\App\Http\Controllers\Client\Crm\CrmTaskController::class, 'show'])->name('tasks.show');
        Route::put('/tasks/{task}', [\App\Http\Controllers\Client\Crm\CrmTaskController::class, 'update'])->name('tasks.update');
        Route::delete('/tasks/{task}', [\App\Http\Controllers\Client\Crm\CrmTaskController::class, 'destroy'])->name('tasks.destroy');
        Route::put('/tasks/{task}/status', [\App\Http\Controllers\Client\Crm\CrmTaskController::class, 'updateStatus'])->name('tasks.status');

        // Custom Fields
        Route::get('/custom-fields', [\App\Http\Controllers\Client\Crm\CrmCustomFieldController::class, 'index'])->name('custom-fields.index');
        Route::post('/custom-fields', [\App\Http\Controllers\Client\Crm\CrmCustomFieldController::class, 'store'])->name('custom-fields.store');
        Route::delete('/custom-fields/{customField}', [\App\Http\Controllers\Client\Crm\CrmCustomFieldController::class, 'destroy'])->name('custom-fields.destroy');

        // CSV Import
        Route::get('/import', [\App\Http\Controllers\Client\Crm\CrmImportController::class, 'index'])->name('import.index');
        Route::post('/import/upload', [\App\Http\Controllers\Client\Crm\CrmImportController::class, 'upload'])->name('import.upload');
        Route::post('/import/preview', [\App\Http\Controllers\Client\Crm\CrmImportController::class, 'preview'])->name('import.preview');
        Route::post('/import/process', [\App\Http\Controllers\Client\Crm\CrmImportController::class, 'process'])->name('import.process');

        Route::post('/notes', [\App\Http\Controllers\Client\Crm\CrmNoteController::class, 'store'])->name('notes.store');
        Route::get('/reports', [\App\Http\Controllers\Client\Crm\CrmReportController::class, 'index'])->name('reports.index');

        // External CRM & Business Systems Integrations
        Route::get('/integrations', [\App\Http\Controllers\Client\Crm\ClientCrmIntegrationController::class, 'index'])->name('integrations.index');
        Route::post('/integrations/{provider}/connect', [\App\Http\Controllers\Client\Crm\ClientCrmIntegrationController::class, 'connect'])->name('integrations.connect');
        Route::post('/integrations/{provider}/disconnect', [\App\Http\Controllers\Client\Crm\ClientCrmIntegrationController::class, 'disconnect'])->name('integrations.disconnect');
        Route::post('/integrations/{provider}/sync', [\App\Http\Controllers\Client\Crm\ClientCrmIntegrationController::class, 'syncNow'])->name('integrations.sync');
        Route::post('/integrations/{provider}/test', [\App\Http\Controllers\Client\Crm\ClientCrmIntegrationController::class, 'testConnection'])->name('integrations.test');
    });

    // Subscription
    Route::get('/subscription', [ClientSubscriptionController::class, 'show'])->name('subscription.show');
    Route::post('/subscription/change-plan', [ClientSubscriptionController::class, 'changePlan'])->name('subscription.change-plan');
    Route::get('/subscription/invoice/{transaction}', [ClientSubscriptionController::class, 'invoiceDownload'])->name('subscription.invoice');
    Route::delete('/subscription', [ClientSubscriptionController::class, 'destroy'])->name('subscription.destroy');

    // Customer Wallet & Centralized Metered Usage
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/wallet', [\App\Http\Controllers\Client\WalletController::class, 'index'])->name('wallet.index');
        Route::post('/wallet/topup', [\App\Http\Controllers\Client\WalletController::class, 'topup'])->name('wallet.topup');
        Route::post('/wallet/settings', [\App\Http\Controllers\Client\WalletController::class, 'updateSettings'])->name('wallet.settings');
        Route::get('/estimate-campaign', [\App\Http\Controllers\Client\WalletController::class, 'estimateCampaign'])->name('estimate-campaign');
    });

    // Coupon validation
    Route::post('/coupon/check', [ClientSubscriptionController::class, 'couponCheck'])->name('coupon.check');

    // Billing & Pricing
    Route::get('/billing', [\App\Http\Controllers\Billing\CustomerBillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/plans', [\App\Http\Controllers\Billing\CustomerBillingController::class, 'plans'])->name('billing.plans');
    Route::post('/billing/checkout', [\App\Http\Controllers\Billing\CustomerBillingController::class, 'checkout'])->name('billing.checkout');
    Route::post('/billing/verify', [\App\Http\Controllers\Billing\CustomerBillingController::class, 'verifyPayment'])->name('billing.verify');
    Route::post('/billing/cancel', [\App\Http\Controllers\Billing\CustomerBillingController::class, 'cancel'])->name('billing.cancel');
    Route::get('/billing/invoices/{invoice}/download', [\App\Http\Controllers\Billing\CustomerBillingController::class, 'downloadInvoice'])->name('billing.invoice.download');
    Route::get('/pricing', [PricingController::class, 'index'])->name('pricing');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    // Team management (client admins only)
    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('/team', [TeamController::class, 'store'])->name('team.store');
    Route::put('/team/{member}', [TeamController::class, 'update'])->name('team.update');
    Route::delete('/team/{member}', [TeamController::class, 'destroy'])->name('team.destroy');

    // Audit log (client admins only)
    Route::get('/audit-log', [ClientAuditLogController::class, 'index'])->name('audit-log.index');

    // Settings & Mobile App
    Route::get('/mobile-app', [\App\Http\Controllers\Client\AppDownloadController::class, 'index'])->name('mobile-app.index');
    Route::get('/settings', [ClientSettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [ClientSettingsController::class, 'update'])->name('settings.update');
    Route::get('/settings/workspace', [\App\Http\Controllers\Client\WorkspaceSettingsController::class, 'show'])->name('settings.workspace');
    Route::put('/settings/workspace', [\App\Http\Controllers\Client\WorkspaceSettingsController::class, 'update'])->name('settings.workspace.update');
    Route::post('/settings/workspace/logo', [\App\Http\Controllers\Client\WorkspaceSettingsController::class, 'uploadLogo'])->name('settings.workspace.logo.upload');
    Route::delete('/settings/workspace/logo', [\App\Http\Controllers\Client\WorkspaceSettingsController::class, 'removeLogo'])->name('settings.workspace.logo.delete');
    Route::get('/settings/notifications', [ClientSettingsController::class, 'notifications'])->name('settings.notifications');
    Route::get('/settings/data-export', [DataExportController::class, 'index'])->name('settings.data-export');
    Route::post('/settings/data-export', [DataExportController::class, 'store'])->name('settings.data-export.store');

    // Workspaces (switcher)
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // 2FA
    Route::get('/profile/two-factor', [TwoFactorController::class, 'show'])->name('profile.2fa');
    Route::post('/profile/two-factor/enable', [TwoFactorController::class, 'enable'])->name('profile.2fa.enable');
    Route::post('/profile/two-factor/disable', [TwoFactorController::class, 'disable'])->name('profile.2fa.disable');
    Route::post('/profile/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateCodes'])->name('profile.2fa.recovery-codes');

    // Session management
    Route::get('/profile/sessions', [SessionController::class, 'index'])->name('profile.sessions');
    Route::delete('/profile/sessions', [SessionController::class, 'destroy'])->name('profile.sessions.destroy');

    // Team invitations (send/revoke, client admin only)
    Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

    // API Tokens, Connections & Docs
    Route::get('/api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::get('/api/tokens', [ApiTokenController::class, 'index'])->name('api.tokens');
    Route::get('/api/connections', [ApiTokenController::class, 'index'])->name('api.connections');
    Route::get('/api-docs', fn () => Inertia::render('client/Api/Docs'))->name('api-docs');
    Route::get('/api/docs', fn () => Inertia::render('client/Api/Docs'))->name('api.docs');

    // Media Library
    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::post('/media', [MediaController::class, 'store'])->name('media.store');
    Route::delete('/media/{medium}', [MediaController::class, 'destroy'])->name('media.destroy');

    // Onboarding Wizard (9-Step Pipeline)
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding');
    Route::get('/onboarding-show', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding/numbers/search', [OnboardingController::class, 'searchNumbers'])->name('onboarding.numbers.search');
    Route::post('/onboarding/numbers/provision', [OnboardingController::class, 'provisionNumber'])->name('onboarding.numbers.provision');
    Route::post('/onboarding/whatsapp/connect', [OnboardingController::class, 'connectWhatsApp'])->name('onboarding.whatsapp.connect');
    Route::post('/onboarding/calling/configure', [OnboardingController::class, 'configureCalling'])->name('onboarding.calling.configure');
    Route::post('/onboarding/business', [OnboardingController::class, 'saveBusiness'])->name('onboarding.business');
    Route::post('/onboarding/ai-agent', [OnboardingController::class, 'createAiAgent'])->name('onboarding.ai-agent');
    Route::post('/onboarding/crm/save', [OnboardingController::class, 'saveCrm'])->name('onboarding.crm.save');
    Route::post('/onboarding/crm/skip', [OnboardingController::class, 'skipCrm'])->name('onboarding.crm.skip');
    Route::post('/onboarding/knowledge/upload', [OnboardingController::class, 'addKnowledge'])->name('onboarding.knowledge.upload');
    Route::post('/onboarding/knowledge/skip', [OnboardingController::class, 'skipKnowledge'])->name('onboarding.knowledge.skip');
    Route::post('/onboarding/test/run', [OnboardingController::class, 'runTest'])->name('onboarding.test.run');
    Route::post('/onboarding/service', [OnboardingController::class, 'selectService'])->name('onboarding.service');
    Route::post('/onboarding/launch', [OnboardingController::class, 'launch'])->name('onboarding.launch');
    Route::post('/onboarding/complete', [OnboardingController::class, 'completeStep'])->name('onboarding.complete');

    // Global Search (⌘K)
    Route::get('/search', [SearchController::class, 'search'])->name('search');

    // Support Tickets
    Route::get('/support', [SupportTicketController::class, 'index'])->name('support.index');
    Route::get('/support/create', [SupportTicketController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportTicketController::class, 'store'])->name('support.store');
    Route::get('/support/{supportTicket}', [SupportTicketController::class, 'show'])->name('support.show');
    Route::post('/support/{supportTicket}/reply', [SupportTicketController::class, 'reply'])->name('support.reply');

    // In-app Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::post('/notification-preferences', [NotificationController::class, 'updatePreferences'])->name('notification-preferences.update');

    // Webhook Endpoints
    Route::get('/webhooks', [WebhookEndpointController::class, 'index'])->name('webhooks.index');
    Route::post('/webhooks', [WebhookEndpointController::class, 'store'])->name('webhooks.store');
    Route::put('/webhooks/{webhookEndpoint}', [WebhookEndpointController::class, 'update'])->name('webhooks.update');
    Route::delete('/webhooks/{webhookEndpoint}', [WebhookEndpointController::class, 'destroy'])->name('webhooks.destroy');
    Route::post('/webhooks/{webhookEndpoint}/rotate-secret', [WebhookEndpointController::class, 'rotateSecret'])->name('webhooks.rotate-secret');
    Route::post('/webhooks/{webhookEndpoint}/test', [WebhookEndpointController::class, 'testDelivery'])->name('webhooks.test');
    Route::get('/webhooks/{webhookEndpoint}/deliveries', [WebhookEndpointController::class, 'deliveries'])->name('webhooks.deliveries');

    // Web Push subscriptions
    Route::post('/push/subscribe', [WebPushController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [WebPushController::class, 'unsubscribe'])->name('push.unsubscribe');
});
