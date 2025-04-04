<?php

namespace Modules\Kiosk\App\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'command:name';

    /**
     * The console command description.
     */
    protected $description = 'Command description.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $events = [
            [
                'message' => 'first',
                'dateTime' => '2025-02-20 10:20:00',
                'type' => 'session_start',
            ],
            [
                'message' => 'second',
                'dateTime' => '2025-02-20 10:21:00',
                'type' => 'pageview',
            ],
            [
                'message' => 'end',
                'dateTime' => '2025-02-20 10:22:00',
                'type' => 'pageview',
            ],
        ];

        $col = collect($events);
        $col = $col->sortBy('dateTime');

        $first = $col->first();
        $last = $col->last();

        $diff = Carbon::parse($last['dateTime'])->diffInSeconds(Carbon::parse($first['dateTime']));
        $isCompleted = $col->contains('type', '=', 'session_timeout') || $col->contains('type', '=', 'session_end');

        dd($isCompleted);
    }
}
