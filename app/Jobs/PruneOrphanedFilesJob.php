<?php

namespace App\Jobs;

use App\Services\Storage\FileCleanupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PruneOrphanedFilesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $daysOld = 7
    ) {}

    public function handle(FileCleanupService $cleanupService): void
    {
        Log::info("Starting PruneOrphanedFilesJob for objects deleted over {$this->daysOld} days ago.");
        $count = $cleanupService->pruneOrphanedFiles($this->daysOld);
        Log::info("Completed PruneOrphanedFilesJob: {$count} files removed.");
    }
}
