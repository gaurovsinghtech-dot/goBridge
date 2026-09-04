<?php

namespace App\Console\Commands;

use App\Services\Storage\FileCleanupService;
use Illuminate\Console\Command;

class PruneOrphanStorageFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:prune-orphans {--days=7 : Minimum age in days for soft-deleted or orphan files to be permanently deleted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune orphaned and expired soft-deleted files from AWS S3 object storage';

    /**
     * Execute the console command.
     */
    public function handle(FileCleanupService $cleanupService): int
    {
        $days = (int) $this->option('days');
        $this->info("Scanning AWS S3 and database for orphaned files older than {$days} days...");

        $pruned = $cleanupService->pruneOrphanedFiles($days);

        $this->info("Successfully pruned {$pruned} orphaned/expired files from storage.");

        return Command::SUCCESS;
    }
}
