<?php

namespace App\Console\Commands;

use App\Models\Audit;
use App\Models\Location;
use App\Models\Organisation;
use App\Models\OrganisationEvent;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Console\Command;

final class CleanSoftDeletesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'clean:soft-deletes';

    /**
     * @var string
     */
    protected $description = 'Clean up soft-deleted records older than 12 months';

    public function handle()
    {
        $models = [
            Location::class,
            Organisation::class,
            OrganisationEvent::class,
            Page::class,
            ServiceLocation::class,
            Service::class,
            User::class,
        ];

        $thresholdDate = now()->subMonths(12);
        foreach ($models as $model) {
            $models = $model::onlyTrashed()
                ->where('deleted_at', '<', $thresholdDate)
                ->get();
            if ($models->isEmpty()) {
                $this->info("No soft-deleted records older than 12 months found for model: {$model}");
                continue;
            }

            

            $models->each(function ($record) use ($model) {
                Audit::query()->create([
                    'action' => Audit::ACTION_DELETE,
                    'description' => "Soft-deleted record older than 12 months for model: {$model}, deleted: {$record->id}",
                    'ip_address' => '0.0.0.0'
                ]);
                $record->forceDelete();
            });
            $this->info("Soft-deleted records older than 12 months for model: {$model} have been deleted.");
        }
    }
}
