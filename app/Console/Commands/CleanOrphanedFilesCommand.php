<?php

namespace App\Console\Commands;

use App\Services\Storage\FileCleanupService;
use Illuminate\Console\Command;

class CleanOrphanedFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:clean-orphans {--days=7 : Number of days files must have been soft-deleted before permanent pruning}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune orphaned and expired soft-deleted files from AWS S3 object storage.';

    /**
     * Execute the console command.
     */
    public function handle(FileCleanupService $cleanupService): int
    {
        $days = (int) $this->option('days');
        $this->info("Scanning AWS S3 and database for soft-deleted files older than {$days} days...");

        $pruned = $cleanupService->pruneOrphanedFiles($days);

        $this->info("Successfully pruned {$pruned} orphaned file(s) from S3 object storage.");

        return self::SUCCESS;
    }
}
