<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AiDashboardController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\CmsPageController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CronSetupController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmailSystemController;
use App\Http\Controllers\Admin\LocaleController;
use App\Modules\Integrations\Http\Controllers\IntegrationConfigController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PaymentGatewayConfigController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PusherSettingsController;
use App\Http\Controllers\Admin\QueueController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\TaxRateController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\TwilioAdminController;
use Illuminate\Support\Facades\Route;

// Dashboard
Route::get('/dashboard', DashboardController::class)->name('dashboard')->middleware('permission:view_dashboard');

// Clients (tenant users)
Route::get('/clients', [ClientController::class, 'index'])->name('clients.index')->middleware('permission:view_clients');
Route::get('/clients/export', [ClientController::class, 'export'])->name('clients.export')->middleware('permission:view_clients');
Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create')->middleware('permission:create_clients');
Route::post('/clients', [ClientController::class, 'store'])->name('clients.store')->middleware('permission:create_clients');
Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show')->middleware('permission:view_clients');
Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit')->middleware('permission:update_clients');
Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update')->middleware('permission:update_clients');
Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy')->middleware('permission:delete_clients');
Route::post('/clients/{client}/assign-plan', [ClientController::class, 'assignPlan'])->name('clients.assign-plan')->middleware('permission:update_clients');
Route::get('/clients/{client}/users', [ClientController::class, 'users'])->name('clients.users.index')->middleware('permission:view_clients');
Route::post('/clients/{client}/users', [ClientController::class, 'storeUser'])->name('clients.users.store')->middleware('permission:update_clients');
Route::put('/clients/{client}/users/{user}', [ClientController::class, 'updateUser'])->name('clients.users.update')->middleware('permission:update_clients');
Route::delete('/clients/{client}/users/{user}', [ClientController::class, 'destroyUser'])->name('clients.users.destroy')->middleware('permission:delete_clients');
Route::post('/clients/{client}/impersonate', [ClientController::class, 'impersonate'])->name('clients.impersonate')->middleware('permission:impersonate_clients');
Route::post('/clients/{client}/toggle-status', [ClientController::class, 'toggleStatus'])->name('clients.toggle-status')->middleware('permission:update_clients');
Route::post('/clients/{client}/resend-verification', [ClientController::class, 'resendVerification'])->name('clients.resend-verification')->middleware('permission:update_clients');
Route::post('/clients/{client}/change-password', [ClientController::class, 'changePassword'])->name('clients.change-password')->middleware('permission:update_clients');

// Plans (packages)
Route::get('/plans', [PlanController::class, 'index'])->name('plans.index')->middleware('permission:view_plans');
Route::get('/plans/create', [PlanController::class, 'create'])->name('plans.create')->middleware('permission:create_plans');
Route::post('/plans', [PlanController::class, 'store'])->name('plans.store')->middleware('permission:create_plans');
Route::get('/plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit')->middleware('permission:update_plans');
Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update')->middleware('permission:update_plans');
Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy')->middleware('permission:delete_plans');
Route::post('/plans/{plan}/toggle-status', [PlanController::class, 'toggleStatus'])->name('plans.toggle-status')->middleware('permission:update_plans');
Route::post('/plans/reorder', [PlanController::class, 'reorder'])->name('plans.reorder')->middleware('permission:update_plans');

// Subscriptions
Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index')->middleware('permission:view_subscriptions');
Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show')->middleware('permission:view_subscriptions');
Route::put('/subscriptions/{subscription}/plan', [SubscriptionController::class, 'updatePlan'])->name('subscriptions.update-plan')->middleware('permission:manage_subscriptions');
Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel')->middleware('permission:manage_subscriptions');

// Payments & Transactions
Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index')->middleware('permission:view_payment_gateways');
Route::post('/payments/{transaction}/refund', [TransactionController::class, 'refund'])->name('payments.refund')->middleware('permission:view_payment_gateways');
Route::get('/payment-gateways', [PaymentGatewayConfigController::class, 'index'])->name('payment-gateways.index')->middleware('permission:view_payment_gateways');
Route::get('/payment-gateways/{gateway}', [PaymentGatewayConfigController::class, 'show'])->name('payment-gateways.show')->middleware('permission:manage_payment_gateways');
Route::match(['put', 'post'], '/payment-gateways/{gateway}', [PaymentGatewayConfigController::class, 'update'])->name('payment-gateways.update')->middleware('permission:manage_payment_gateways');

// Provider Cost Ledger, Margins & Centralized Pricing Management
Route::prefix('billing')->name('billing.')->middleware('permission:view_payment_gateways')->group(function () {
    Route::get('/provider-costs', [\App\Http\Controllers\Admin\ProviderBillingController::class, 'index'])->name('provider-costs.index');
    Route::put('/pricing-rules/{rule}', [\App\Http\Controllers\Admin\ProviderBillingController::class, 'updatePricingRule'])->name('pricing-rules.update');
    Route::post('/wallets/{wallet}/adjust', [\App\Http\Controllers\Admin\ProviderBillingController::class, 'adjustWallet'])->name('wallets.adjust');
});

// Coupons
Route::get('/coupons', [CouponController::class, 'index'])->name('coupons.index')->middleware('permission:view_plans');
Route::post('/coupons', [CouponController::class, 'store'])->name('coupons.store')->middleware('permission:create_plans');
Route::put('/coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update')->middleware('permission:create_plans');
Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy')->middleware('permission:delete_plans');

// Tax Rates
Route::get('/tax-rates', [TaxRateController::class, 'index'])->name('tax-rates.index')->middleware('permission:view_plans');
Route::post('/tax-rates', [TaxRateController::class, 'store'])->name('tax-rates.store')->middleware('permission:create_plans');
Route::put('/tax-rates/{taxRate}', [TaxRateController::class, 'update'])->name('tax-rates.update')->middleware('permission:create_plans');
Route::delete('/tax-rates/{taxRate}', [TaxRateController::class, 'destroy'])->name('tax-rates.destroy')->middleware('permission:delete_plans');

// Locales / Languages
Route::get('/locales', [LocaleController::class, 'index'])->name('locales.index')->middleware('permission:view_languages');
Route::post('/locales', [LocaleController::class, 'store'])->name('locales.store')->middleware('permission:manage_languages');
Route::put('/locales/{locale}', [LocaleController::class, 'update'])->name('locales.update')->middleware('permission:manage_languages');
Route::post('/locales/{locale}/set-default', [LocaleController::class, 'setDefault'])->name('locales.set-default')->middleware('permission:manage_languages');
Route::delete('/locales/{locale}', [LocaleController::class, 'destroy'])->name('locales.destroy')->middleware('permission:manage_languages');

// Translations (admin)
Route::put('/translations', [TranslationController::class, 'update'])->name('translations.update')->middleware('permission:manage_languages');
Route::post('/translations/bulk', [TranslationController::class, 'bulkUpdate'])->name('translations.bulk')->middleware('permission:manage_languages');
Route::post('/translations/auto-translate', [TranslationController::class, 'autoTranslateMissing'])->name('translations.auto-translate')->middleware('permission:manage_languages');

// Currencies
Route::get('/currencies', [CurrencyController::class, 'index'])->name('currencies.index')->middleware('permission:view_currencies');
Route::post('/currencies', [CurrencyController::class, 'store'])->name('currencies.store')->middleware('permission:manage_currencies');
Route::put('/currencies/{currency}', [CurrencyController::class, 'update'])->name('currencies.update')->middleware('permission:manage_currencies');

// Email System (SMTP + Email Templates)
Route::get('/email-system', [EmailSystemController::class, 'index'])->name('email-system.index')->middleware('permission:view_email_settings');
Route::put('/email-templates/{template}', [EmailSystemController::class, 'updateTemplate'])->name('email-templates.update')->middleware('permission:manage_email_settings');
Route::post('/smtp-configurations', [EmailSystemController::class, 'storeSmtp'])->name('smtp-configurations.store')->middleware('permission:manage_email_settings');
Route::put('/smtp-configurations/{smtpConfiguration}', [EmailSystemController::class, 'updateSmtp'])->name('smtp-configurations.update')->middleware('permission:manage_email_settings');
Route::delete('/smtp-configurations/{smtpConfiguration}', [EmailSystemController::class, 'destroySmtp'])->name('smtp-configurations.destroy')->middleware('permission:manage_email_settings');
Route::post('/smtp-configurations/{smtpConfiguration}/activate', [EmailSystemController::class, 'activateSmtp'])->name('smtp-configurations.activate')->middleware('permission:manage_email_settings');
Route::post('/smtp-configurations/test', [EmailSystemController::class, 'testEmail'])->name('smtp-configurations.test')->middleware('permission:manage_email_settings');
Route::post('/email-templates/{template}/test', [EmailSystemController::class, 'testTemplate'])->name('email-templates.test')->middleware('permission:manage_email_settings');

// Mobile App Management
Route::get('/app-management/android', [\App\Http\Controllers\Admin\AndroidAppManagementController::class, 'index'])->name('app-management.android.index')->middleware('permission:view_settings');
Route::post('/app-management/android', [\App\Http\Controllers\Admin\AndroidAppManagementController::class, 'update'])->name('app-management.android.update')->middleware('permission:manage_settings');
Route::post('/app-management/android/upload', [\App\Http\Controllers\Admin\AndroidAppManagementController::class, 'uploadApk'])->name('app-management.android.upload')->middleware('permission:manage_settings');

// Settings
Route::get('/settings', [SystemSettingsController::class, 'index'])->name('settings.index')->middleware('permission:view_settings');
Route::match(['put', 'post'], '/settings', [SystemSettingsController::class, 'update'])->name('settings.update')->middleware('permission:manage_settings');
Route::match(['put', 'post'], '/settings/landing-page', [SystemSettingsController::class, 'updateLandingPage'])->name('landing-page.update')->middleware('permission:manage_settings');
Route::match(['put', 'post'], '/settings/general', [SystemSettingsController::class, 'updateGeneral'])->name('settings.general.update')->middleware('permission:manage_settings');
Route::post('/settings/logo', [SystemSettingsController::class, 'uploadLogo'])->name('settings.logo.upload')->middleware('permission:manage_settings');
Route::delete('/settings/logo', [SystemSettingsController::class, 'deleteLogo'])->name('settings.logo.delete')->middleware('permission:manage_settings');
Route::post('/settings/favicon', [SystemSettingsController::class, 'uploadFavicon'])->name('settings.favicon.upload')->middleware('permission:manage_settings');
Route::delete('/settings/favicon', [SystemSettingsController::class, 'deleteFavicon'])->name('settings.favicon.delete')->middleware('permission:manage_settings');
Route::post('/settings/maintenance/toggle', [SystemSettingsController::class, 'toggleMaintenance'])->name('settings.maintenance.toggle')->middleware('permission:manage_settings');
Route::post('/settings/cache/clear', [SystemSettingsController::class, 'clearCache'])->name('settings.cache.clear')->middleware('permission:manage_settings');

// Integrations — WhatsApp
Route::prefix('integrations/whatsapp')->name('integrations.whatsapp.')->middleware('permission:view_settings')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\AdminWhatsappIntegrationController::class, 'index'])->name('index');
    Route::post('/test-api', [\App\Http\Controllers\Admin\AdminWhatsappIntegrationController::class, 'testApi'])->name('test-api');
    Route::post('/test-webhook', [\App\Http\Controllers\Admin\AdminWhatsappIntegrationController::class, 'testWebhook'])->name('test-webhook');
});

// Audit Log (system-wide)
Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index')->middleware('permission:view_audit_logs');
Route::get('/audit-log/{auditLog}', [AuditLogController::class, 'show'])->name('audit-log.show')->middleware('permission:view_audit_logs');

// CMS Pages
Route::get('/cms-pages', [CmsPageController::class, 'index'])->name('cms-pages.index')->middleware('permission:view_settings');
Route::get('/cms-pages/create', [CmsPageController::class, 'create'])->name('cms-pages.create')->middleware('permission:manage_settings');
Route::post('/cms-pages', [CmsPageController::class, 'store'])->name('cms-pages.store')->middleware('permission:manage_settings');
Route::put('/cms-pages/{cmsPage}', [CmsPageController::class, 'update'])->name('cms-pages.update')->middleware('permission:manage_settings');
Route::delete('/cms-pages/{cmsPage}', [CmsPageController::class, 'destroy'])->name('cms-pages.destroy')->middleware('permission:manage_settings');

// Support Tickets (admin inbox)
Route::get('/support', [SupportTicketController::class, 'index'])->name('support.index')->middleware('permission:view_settings');
Route::get('/support/create', [SupportTicketController::class, 'create'])->name('support.create')->middleware('permission:manage_settings');
Route::post('/support', [SupportTicketController::class, 'store'])->name('support.store')->middleware('permission:manage_settings');
Route::get('/support/{supportTicket}', [SupportTicketController::class, 'show'])->name('support.show')->middleware('permission:view_settings');
Route::post('/support/{supportTicket}/reply', [SupportTicketController::class, 'reply'])->name('support.reply')->middleware('permission:view_settings');
Route::post('/support/{supportTicket}/status', [SupportTicketController::class, 'updateStatus'])->name('support.status')->middleware('permission:manage_settings');

// Cron / Scheduler setup guide
Route::get('/cron-setup', [CronSetupController::class, 'index'])->name('cron-setup.index')->middleware('permission:view_settings');

// Queue / Failed Jobs monitor
Route::get('/queue', [QueueController::class, 'index'])->name('queue.index')->middleware('permission:view_settings');
Route::post('/queue/{id}/retry', [QueueController::class, 'retryFailed'])->name('queue.retry')->middleware('permission:manage_settings');
Route::delete('/queue/{id}', [QueueController::class, 'deleteFailed'])->name('queue.delete-failed')->middleware('permission:manage_settings');
Route::post('/queue/retry-all', [QueueController::class, 'retryAll'])->name('queue.retry-all')->middleware('permission:manage_settings');
Route::post('/queue/flush', [QueueController::class, 'flushFailed'])->name('queue.flush')->middleware('permission:manage_settings');

// Pusher (real-time broadcasting)
Route::get('/pusher-settings', [PusherSettingsController::class, 'index'])->name('pusher-settings.index')->middleware('permission:manage_settings');
Route::put('/pusher-settings', [PusherSettingsController::class, 'update'])->name('pusher-settings.update')->middleware('permission:manage_settings');
Route::post('/pusher-settings/test', [PusherSettingsController::class, 'test'])->name('pusher-settings.test')->middleware('permission:manage_settings');

// Storage & AWS S3 Control Center
Route::get('/storage', [\App\Http\Controllers\Admin\AdminStorageDashboardController::class, 'index'])->name('storage.index')->middleware('permission:view_settings');
Route::post('/storage/prune-orphans', [\App\Http\Controllers\Admin\AdminStorageDashboardController::class, 'pruneOrphans'])->name('storage.prune-orphans')->middleware('permission:manage_settings');
Route::post('/storage/test-connection', [\App\Http\Controllers\Admin\AdminStorageDashboardController::class, 'testConnection'])->name('storage.test-connection')->middleware('permission:manage_settings');
Route::get('/settings/integrations/storage', fn () => redirect()->route('admin.integrations.edit', 'storage_s3'))->name('settings.integrations.storage')->middleware('permission:manage_integrations');

// Integrations (system-level credential management)
Route::get('/integrations', [IntegrationConfigController::class, 'index'])->name('integrations.index')->middleware('permission:manage_integrations');
Route::get('/integrations/audit-log', [IntegrationConfigController::class, 'auditLogIndex'])->name('integrations.audit-log')->middleware('permission:manage_integrations');
Route::get('/integrations/{provider}', [IntegrationConfigController::class, 'edit'])->name('integrations.edit')->middleware('permission:manage_integrations');
Route::match(['put', 'post'], '/integrations/{provider}', [IntegrationConfigController::class, 'update'])->name('integrations.update')->middleware('permission:manage_integrations');
Route::post('/integrations/{provider}/test', [IntegrationConfigController::class, 'test'])->name('integrations.test')->middleware('permission:manage_integrations');
Route::post('/integrations/{provider}/toggle', [IntegrationConfigController::class, 'toggle'])->name('integrations.toggle')->middleware('permission:manage_integrations');
Route::post('/integrations/{provider}/rotate', [IntegrationConfigController::class, 'rotate'])->name('integrations.rotate')->middleware('permission:manage_integrations');
Route::post('/integrations/{provider}/set-default', [IntegrationConfigController::class, 'setDefault'])->name('integrations.set-default')->middleware('permission:manage_integrations');

// Twilio & Virtual Phone Numbers Control Center
Route::get('/twilio', [TwilioAdminController::class, 'index'])->name('twilio.index')->middleware('permission:view_settings');
Route::get('/phone-numbers', [TwilioAdminController::class, 'index'])->name('phone-numbers.index')->middleware('permission:view_settings');

// AI Dashboard
Route::get('/ai', [AiDashboardController::class, 'index'])->name('ai.index')->middleware('permission:view_settings');

// Admin users (Admin Management)
Route::get('/admins', [AdminUserController::class, 'index'])->name('admins.index')->middleware('permission:view_admins');
Route::post('/admins', [AdminUserController::class, 'store'])->name('admins.store')->middleware('permission:create_admins');
Route::put('/admins/{adminUser}', [AdminUserController::class, 'update'])->name('admins.update')->middleware('permission:update_admins');
Route::delete('/admins/{adminUser}', [AdminUserController::class, 'destroy'])->name('admins.destroy')->middleware('permission:delete_admins');
Route::post('/admins/{adminUser}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('admins.toggle-status')->middleware('permission:update_admins');

// System Health Diagnostics & Operations
Route::get('/system-health', [\App\Http\Controllers\Admin\SystemHealthController::class, 'index'])->name('system-health.index')->middleware('permission:view_settings');
Route::post('/system-health/run-backup', [\App\Http\Controllers\Admin\SystemHealthController::class, 'runBackup'])->name('system-health.run-backup')->middleware('permission:manage_settings');
Route::post('/system-health/test-email', [\App\Http\Controllers\Admin\SystemHealthController::class, 'testEmail'])->name('system-health.test-email')->middleware('permission:manage_settings');
Route::post('/system-health/retry-failed-jobs', [\App\Http\Controllers\Admin\SystemHealthController::class, 'retryFailedJobs'])->name('system-health.retry-failed-jobs')->middleware('permission:manage_settings');

// System Announcements
Route::get('/announcements', [\App\Http\Controllers\Admin\SystemAnnouncementController::class, 'index'])->name('announcements.index')->middleware('permission:manage_settings');
Route::post('/announcements', [\App\Http\Controllers\Admin\SystemAnnouncementController::class, 'store'])->name('announcements.store')->middleware('permission:manage_settings');
Route::post('/announcements/{id}/toggle', [\App\Http\Controllers\Admin\SystemAnnouncementController::class, 'toggle'])->name('announcements.toggle')->middleware('permission:manage_settings');
Route::delete('/announcements/{id}', [\App\Http\Controllers\Admin\SystemAnnouncementController::class, 'destroy'])->name('announcements.destroy')->middleware('permission:manage_settings');

// System & Audit Logs
Route::get('/system-logs', [\App\Http\Controllers\Admin\SystemLogsController::class, 'index'])->name('system-logs.index')->middleware('permission:view_settings');
