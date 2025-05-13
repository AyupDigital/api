<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearForeverCachesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-forever';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes all caches that are stored forever.';

    /**
     * The cache keys to be cleared.
     *
     * @var array
     */
    protected $cacheKeys = [
        'Role::serviceWorker',
        'Role::serviceAdmin',
        'Role::organisationAdmin',
        'Role::contentAdmin',
        'Role::globalAdmin',
        'Role::superAdmin',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Clearing all caches...');

        foreach ($this->cacheKeys as $key) {
            Cache::forget($key);
            $this->info("Cleared cache for: {$key}");
        }

        $this->info('All caches cleared successfully.');

        return Command::SUCCESS;
    }
}
