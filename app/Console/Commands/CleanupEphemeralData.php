<?php

namespace App\Console\Commands;

use App\Modules\Voice\Models\TelephonyApiLog;
use App\Modules\Voice\Models\TelephonyWebhookLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CleanupEphemeralData extends Command
{
    protected $signature = 'app:cleanup-ephemeral-data {--days=30 : Number of days of logs to retain}';
    protected $description = 'Safely prunes old API logs, webhook logs, and temporary export files.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $this->info("Cleaning up ephemeral records older than {$days} days ({$cutoff->toDateString()})...");

        // 1. Prune Telephony API Logs
        $apiLogsDeleted = TelephonyApiLog::where('created_at', '<', $cutoff)->delete();
        $this->line("• Deleted {$apiLogsDeleted} expired API logs.");

        // 2. Prune Webhook Logs
        $webhookLogsDeleted = TelephonyWebhookLog::where('created_at', '<', $cutoff)->delete();
        $this->line("• Deleted {$webhookLogsDeleted} expired webhook logs.");

        // 3. Prune old failed jobs (older than 60 days)
        try {
            $failedJobsDeleted = DB::table('failed_jobs')->where('failed_at', '<', now()->subDays(60))->delete();
            $this->line("• Deleted {$failedJobsDeleted} old failed jobs.");
        } catch (\Throwable $e) {}

        // 4. Prune temporary export files
        $tempDir = storage_path('app/temp');
        if (File::isDirectory($tempDir)) {
            $files = File::files($tempDir);
            $tempDeleted = 0;
            foreach ($files as $file) {
                if (now()->timestamp - File::lastModified($file) > 86400 * 2) { // older than 2 days
                    File::delete($file);
                    $tempDeleted++;
                }
            }
            $this->line("• Cleaned {$tempDeleted} temporary export files.");
        }

        $this->info('Ephemeral data cleanup completed successfully.');

        return self::SUCCESS;
    }
}
