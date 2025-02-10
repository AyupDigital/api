<?php

namespace Modules\Kiosk\App\Console;

use Illuminate\Console\Command;
use Modules\Kiosk\App\Services\ClickSendService;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

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
        $clickSendService = new ClickSendService();
        $clickSendService->sendSms('07858418751', 'Test message');
    }
}
