<?php

namespace App\Actions;

use App\Models\Service;
use Carbon\CarbonImmutable;

class SetStaleServicesToInactiveAction
{
    public function handle(CarbonImmutable $lastModifiedBefore): int {
        return Service::query()
            ->where('last_modified_at', '<=', $lastModifiedBefore)
            ->update(['status' => Service::STATUS_INACTIVE]);
    }
}