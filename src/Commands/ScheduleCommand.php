<?php

namespace Cabanga\CoioteTurbo\Commands;

use Cabanga\CoioteTurbo\Scheduler\SchedulerLoop;
use Illuminate\Console\Command;

class ScheduleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coiote:schedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Starts the Coiote Turbo scheduler to run scheduled tasks.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Starting Coiote Turbo Scheduler...');

        // Instantiate and run the scheduler loop.
        // This command will run indefinitely until stopped.
        (new SchedulerLoop($this->laravel))->run();

        $this->info('Scheduler has been stopped.');
    }
}