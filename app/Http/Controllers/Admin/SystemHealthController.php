<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Services\Storage\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Inertia;
use Inertia\Response;

class SystemHealthController extends Controller
{
    public function __construct(
        protected StorageService $storageService
    ) {}

    public function index(Request $request): Response
    {
        // 1. Application & Runtime
        $appEnv = config('app.env');
        $appDebug = config('app.debug');
        $isHttps = $request->isSecure() || str_starts_with((string) config('app.url'), 'https://');
        $phpVersion = PHP_VERSION;
        $laravelVersion = app()->version();
        $sessionDriver = config('session.driver');
        $secureCookies = config('session.secure');

        // 2. Database Health & Latency
        $dbConnection = config('database.default');
        $dbStatus = 'healthy';
        $dbLatency = 0;
        $dbName = config("database.connections.{$dbConnection}.database", 'laravel');

        try {
            $start = microtime(true);
            DB::selectOne('SELECT 1');
            $dbLatency = round((microtime(true) - $start) * 1000, 2);
        } catch (\Throwable $e) {
            $dbStatus = 'error';
        }

        // 3. AWS S3 Storage
        $s3Stats = $this->storageService->getStorageStats();

        // 4. Queue
        $queueDriver = config('queue.default');
        $queuePending = 0;
        $failedJobsCount = 0;
        try {
            $queuePending = Queue::size();
            $failedJobsCount = DB::table('failed_jobs')->count();
        } catch (\Throwable) {}

        // 5. Cron Scheduler Heartbeat
        $lastCronRun = SystemSetting::get('system.last_cron_run_at') ?? \Illuminate\Support\Facades\Cache::get(\App\Http\Controllers\Admin\CronSetupController::HEARTBEAT_KEY);
        $cronStatus = 'healthy';
        $cronMinutesAgo = null;

        if ($lastCronRun) {
            $cronMinutesAgo = (int) now()->parse($lastCronRun)->diffInMinutes(now());
            if ($cronMinutesAgo > 5) {
                $cronStatus = 'warning';
            }
        } else {
            $cronStatus = 'warning';
        }

        // 6. External Providers Configuration & Connectivity
        // WhatsApp
        $metaConfig = IntegrationConfig::forProvider('meta_app');
        $metaCreds = $metaConfig?->credentials ?? [];
        $whatsappConfigured = ! empty($metaCreds['system_user_token']) || ! empty(env('WHATSAPP_TOKEN'));

        // Twilio
        $twilioConfig = IntegrationConfig::forProvider('twilio');
        $twilioCreds = $twilioConfig?->credentials ?? [];
        $twilioConfigured = ! empty($twilioCreds['account_sid']) || ! empty(env('TWILIO_ACCOUNT_SID'));

        // Email / SMTP
        $mailMailer = config('mail.default');
        $mailHost = config('mail.mailers.smtp.host');
        $mailFrom = config('mail.from.address');
        $mailConfigured = ! empty($mailFrom) && ($mailMailer !== 'log' || app()->environment('local', 'testing'));

        // AI Provider
        $aiConfig = IntegrationConfig::forProvider('ai_providers');
        $aiCreds = $aiConfig?->credentials ?? [];
        $aiDefault = $aiCreds['default_provider'] ?? 'openai';
        $aiConfigured = ! empty($aiCreds['openai_api_key']) || ! empty($aiCreds['anthropic_api_key']) || ! empty($aiCreds['gemini_api_key']) || ! empty(env('OPENAI_API_KEY'));

        // 7. Webhook Ingest Status
        $webhookStatus = [
            'whatsapp_webhook' => [
                'name' => 'WhatsApp Cloud Webhook',
                'url' => url('/api/v1/webhooks/whatsapp'),
                'status' => $whatsappConfigured ? 'active' : 'unconfigured',
            ],
            'twilio_voice_webhook' => [
                'name' => 'Twilio Voice / IVR Webhook',
                'url' => url('/webhooks/twilio/voice'),
                'status' => $twilioConfigured ? 'active' : 'unconfigured',
            ],
            'razorpay_webhook' => [
                'name' => 'Razorpay Payment Webhook',
                'url' => url('/webhooks/razorpay'),
                'status' => ! empty(env('RAZORPAY_KEY')) ? 'active' : 'unconfigured',
            ],
        ];

        // 8. Database Backup Status
        $lastBackupAt = SystemSetting::get('system.last_backup_at');
        $lastBackupFilename = SystemSetting::get('system.last_backup_filename');
        $lastBackupSizeMb = SystemSetting::get('system.last_backup_size_mb');
        $lastBackupStatus = SystemSetting::get('system.last_backup_status', 'never_run');

        return Inertia::render('Admin/SystemHealth', [
            'diagnostics' => [
                'app_env' => $appEnv,
                'app_debug' => (bool) $appDebug,
                'is_https' => $isHttps,
                'php_version' => $phpVersion,
                'laravel_version' => $laravelVersion,
                'session_driver' => $sessionDriver,
                'secure_cookies' => (bool) $secureCookies,
                'db_connection' => $dbConnection,
                'db_name' => $dbName,
                'db_status' => $dbStatus,
                'db_latency_ms' => $dbLatency,
                'storage_writable' => is_writable(storage_path()),
                'queue_driver' => $queueDriver,
                'queue_pending_jobs' => $queuePending,
                'failed_jobs_count' => $failedJobsCount,
                'cron_status' => $cronStatus,
                'last_cron_run' => $lastCronRun ? now()->parse($lastCronRun)->format('M d, Y H:i:s') : 'Never',
                'cron_minutes_ago' => $cronMinutesAgo,
                'last_backup_at' => $lastBackupAt ? now()->parse($lastBackupAt)->format('M d, Y H:i:s') : 'Never',
                'last_backup_filename' => $lastBackupFilename,
                'last_backup_size_mb' => $lastBackupSizeMb,
                'last_backup_status' => $lastBackupStatus,
            ],
            's3' => $s3Stats,
            'providers' => [
                'whatsapp' => [
                    'name' => 'WhatsApp Cloud API',
                    'configured' => $whatsappConfigured,
                    'status' => $whatsappConfigured ? 'connected' : 'needs_configuration',
                ],
                'twilio' => [
                    'name' => 'Twilio Telephony & Voice',
                    'configured' => $twilioConfigured,
                    'status' => $twilioConfigured ? 'connected' : 'needs_configuration',
                ],
                'email' => [
                    'name' => 'Email / SMTP Service',
                    'mailer' => $mailMailer,
                    'host' => $mailHost,
                    'from' => $mailFrom,
                    'configured' => $mailConfigured,
                    'status' => $mailConfigured ? 'connected' : 'needs_configuration',
                ],
                'ai' => [
                    'name' => 'AI Engine (LLM)',
                    'provider' => ucfirst($aiDefault),
                    'configured' => $aiConfigured,
                    'status' => $aiConfigured ? 'connected' : 'needs_configuration',
                ],
            ],
            'webhooks' => $webhookStatus,
        ]);
    }

    /**
     * Trigger on-demand database backup
     */
    public function runBackup(Request $request): RedirectResponse
    {
        try {
            Artisan::call('db:backup');
            return back()->with('success', 'Database backup completed successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Database backup failed: ' . $e->getMessage());
        }
    }

    /**
     * Send a test email to verify SMTP configuration
     */
    public function testEmail(Request $request): JsonResponse
    {
        $toEmail = $request->input('email', auth('admin')->user()?->email);

        if (! filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['ok' => false, 'message' => 'Invalid destination email address.']);
        }

        try {
            Mail::raw('This is a Growbridge Connect production email verification test.', function ($msg) use ($toEmail) {
                $msg->to($toEmail)->subject('Growbridge Connect — SMTP Production Test');
            });

            return response()->json(['ok' => true, 'message' => "Test email dispatched successfully to {$toEmail}."]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Email dispatch failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Retry all failed queue jobs
     */
    public function retryFailedJobs(Request $request): RedirectResponse
    {
        try {
            Artisan::call('queue:retry', ['id' => ['all']]);
            return back()->with('success', 'All failed queue jobs have been pushed back for retry.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Retry failed: ' . $e->getMessage());
        }
    }
}
