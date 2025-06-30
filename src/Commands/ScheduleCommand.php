<?php

namespace Cabanga\CoioteTurbo\Commands;

use Cabanga\CoioteTurbo\Scheduler\SchedulerLoop;
use Illuminate\Console\Command;

class ScheduleCommand extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'coiote:schedule';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Starts the Coiote Turbo scheduler to run scheduled tasks.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Get scheduler-specific configuration.
        $schedulerConfig = config('coioteTurbo.scheduler', []);

        // Check if the scheduler is enabled in the config file.
        if (!($schedulerConfig['enabled'] ?? false)) {
            $this->warn('Coiote Turbo Scheduler is disabled in the configuration file.');
            $this->info('To enable it, set "enabled" to true in the "scheduler" section of coioteTurbo.php');
            return;
        }

        $this->info('Starting Coiote Turbo Scheduler...');
        $this->info('Press Ctrl+C to stop.');

        // Instantiate and run the scheduler loop, passing its specific config.
        (new SchedulerLoop($this->laravel, $schedulerConfig))->run();
    }
}