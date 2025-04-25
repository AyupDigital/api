<?php

namespace App\Console\Commands\Ck\AutoDelete;

use App\Models\File;
use Illuminate\Console\Command;

class PendingAssignmentFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ck:auto-delete:pending-assignment-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes all files that are still pending assignment and due for deletion';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $days = File::PEDNING_ASSIGNMENT_AUTO_DELETE_DAYS;

        $this->line("Deleting file created {$days} day(s) ago...");
        $pendingFiles = File::pendingAssignmentDueForDeletion()->get();
        $count = 0;
        $pendingFiles->each(function (File $file) use (&$count) {
            try {
                $file->delete();
                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to delete file {$file->id}: {$e->getMessage()}");
            }
        });
        $this->info("Deleted {$count} file(s).");
    }
}
