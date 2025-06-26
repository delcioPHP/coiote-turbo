<?php

namespace Cabanga\CoioteTurbo\Scheduler;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Swoole\Timer;
use Throwable;

class SchedulerLoop
{
    /**
     * The Laravel application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected Application $app;

    /**
     * A flag to stop the loop gracefully.
     *
     * @var bool
     */
    protected bool $shouldStop = false;

    /**
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Run the scheduler loop.
     */
    public function run(): void
    {
        // Register signal handlers for graceful shutdown.
        $this->registerSignalHandlers();

        // Tick every 60 seconds (60000 ms) to run the scheduler.
        Timer::tick(60000, function () {
            if ($this->shouldStop) {
                Timer::clearAll();
                return;
            }
            $this->runDueTasks();
        });
    }

    /**
     * Find and run tasks that are due.
     */
    protected function runDueTasks(): void
    {
        try {
            /** @var \Illuminate\Console\Scheduling\Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            // Find all events that are due to run now.
            $dueEvents = array_filter(
                $schedule->events(),
                fn ($event) => $event->isDue($this->app)
            );

            if (empty($dueEvents)) {
                return;
            }

            Log::info('Scheduler: Found ' . count($dueEvents) . ' due tasks. Running...');

            // Run each due event.
            foreach ($dueEvents as $event) {
                // We wrap each event in its own try-catch to prevent
                // one failed job from stopping others.
                try {
                    $event->run($this->app);
                } catch (Throwable $e) {
                    Log::error(
                        'Scheduler: A scheduled task failed.',
                        ['exception' => $e]
                    );
                }
            }

        } catch (Throwable $e) {
            // Catch errors in the scheduler process itself.
            Log::critical(
                'Scheduler: The scheduler loop encountered a critical error.',
                ['exception' => $e]
            );
        }
    }

    /**
     * Register handlers for shutdown signals.
     */
    protected function registerSignalHandlers(): void
    {
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
        pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
    }
}