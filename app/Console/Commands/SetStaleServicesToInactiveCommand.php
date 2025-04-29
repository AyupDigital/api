<?php

namespace App\Console\Commands;

use App\Actions\SetStaleServicesToInactiveAction;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SetStaleServicesToInactiveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:set-stale-services-to-inactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set stale services to inactive for the last year';

    /**
     * Execute the console command.
     */
    public function handle(SetStaleServicesToInactiveAction $setStaleServicesToInactiveAction): int
    {
        $setStaleServicesToInactiveAction->handle(CarbonImmutable::now()->subYear());

        $this->info('Stale services have been disabled.');

        return Command::SUCCESS;
    }
}
