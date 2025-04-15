<?php

namespace Tests\Unit\Console\Commands;

use App\Models\Audit;
use App\Models\Service;

class CleanSoftDeletesCommandTest extends \Tests\TestCase
{
    public function test_it_cleans_up_soft_deleted_records(): void
    {
        $serviceToDelete = Service::factory()->create([
            'deleted_at' => now()->subMonths(13),
        ]);
        Service::factory()->create([
            'deleted_at' => now()->subMonths(11),
        ]);

        $this->artisan('clean:soft-deletes')
            ->expectsOutput('Soft-deleted records older than 12 months for model: App\Models\Service have been deleted.')
            ->assertExitCode(0);

        $this->assertDatabaseCount((new Service())->getTable(), 1);

        $this->assertDatabaseHas((new Audit())->getTable(), [
            'action' => Audit::ACTION_DELETE,
            'description' => 'Soft-deleted records older than 12 months for model: App\Models\Service, deleted: ' . $serviceToDelete->id,
        ]);
    }
}
