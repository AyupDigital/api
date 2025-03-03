<?php

namespace App\Console\Commands\Ck;

use App\Models\Collection;
use App\Models\OrganisationEvent;
use App\Models\Page;
use App\Models\Service;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class FixCollectionsTableTreeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ck:collections-tree';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adds values for the _lft and _rgt columns in the collections table.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $counter = 1;

        foreach (Collection::orderBy('id')->get() as $collection) {
            $this->info("Processing collection {$collection->id}");
            $collection->_lft = $counter++;

            $collection->_rgt = $counter++;

            $collection->save();
        }

        return Command::SUCCESS;
    }
}
