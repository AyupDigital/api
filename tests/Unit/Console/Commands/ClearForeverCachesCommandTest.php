<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Support\Facades\Cache;

class ClearForeverCachesCommandTest extends \Tests\TestCase
{
    public function testClearForeverCachesCommand()
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with('Role::serviceWorker')
            ->andReturn(true);
        Cache::shouldReceive('forget')
            ->once()
            ->with('Role::serviceAdmin')
            ->andReturn(true);
        Cache::shouldReceive('forget')
            ->once()
            ->with('Role::organisationAdmin')
            ->andReturn(true);
        Cache::shouldReceive('forget')
            ->once()
            ->with('Role::contentAdmin')
            ->andReturn(true);
        Cache::shouldReceive('forget')
            ->once()
            ->with('Role::globalAdmin')
            ->andReturn(true);
        Cache::shouldReceive('forget')
            ->once()
            ->with('Role::superAdmin')
            ->andReturn(true);
        $this->artisan('cache:clear-forever')
            ->expectsOutput('Clearing all caches...')
            ->expectsOutput('All caches cleared successfully.')
            ->assertExitCode(0);
    }
}