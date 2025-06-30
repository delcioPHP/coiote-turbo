<?php

namespace Cabanga\CoioteTurbo\Scheduler;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Swoole\Process;
use Swoole\Timer;
use Throwable;

class SchedulerLoop
{
    /**
     * The Laravel application instance.
     * @var Application
     */
    protected Application $app;

    /**
     * Scheduler-specific configuration.
     * @var array
     */
    protected array $config;

    public function __construct(Application $app, array $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * Writes a message to the configured log file directly.
     * This method avoids using Laravel's logging system to prevent issues in forked processes.
     *
     * @param string $level   The log level (e.g., 'INFO', 'ERROR').
     * @param string $message The log message.
     * @param array  $context Additional context to be JSON encoded.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        // Only log if it's enabled in the configuration.
        if (!($this->config['log']['enabled'] ?? false)) {
            return;
        }

        $logPath = $this->config['log']['path'] ?? null;
        if (empty($logPath)) {
            return; // Do not log if path is not defined.
        }

        $directory = dirname($logPath);
        if (!@is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $formattedContext = empty($context) ? '' : json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $formattedMessage = sprintf(
            "[%s] scheduler.%s: %s %s" . PHP_EOL,
            $timestamp,
            strtoupper($level),
            $message,
            $formattedContext
        );

        // Append the message to the file with an exclusive lock to prevent corrupted writes.
        @file_put_contents($logPath, $formattedMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Run the scheduler loop.
     */
    public function run(): void
    {
        // Use Swoole's signal handler for proper integration with the event loop.
        Process::signal(SIGINT, function () {
            $this->log('info', 'Shutdown signal received, stopping scheduler timer...');
            Timer::clearAll();
        });
        Process::signal(SIGTERM, function () {
            $this->log('info', 'Shutdown signal received, stopping scheduler timer...');
            Timer::clearAll();
        });

        // Tick every 60 seconds to run the scheduler.
        // The process will keep running as long as there are active timers.
        Timer::tick(60000, fn () => $this->runDueTasks());
    }

    /**
     * Find and run tasks that are due.
     */
    protected function runDueTasks(): void
    {
        try {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            $dueEvents = array_filter(
                $schedule->events(),
                fn ($event) => $event->isDue($this->app)
            );

            if (empty($dueEvents)) {
                $this->log('debug', 'No scheduled tasks are due.');
                return;
            }

            $this->log('info', 'Found ' . count($dueEvents) . ' due tasks. Running...');

            foreach ($dueEvents as $event) {
                try {
                    $description = $event->description ?: 'Closure';
                    $this->log('info', "Running task: {$description}");
                    $event->run($this->app);
                } catch (Throwable $e) {
                    $this->log('error', 'A scheduled task failed.', ['description' => $event->description, 'exception' => $e->getMessage()]);
                }
            }
        } catch (Throwable $e) {
            $this->log('critical', 'The scheduler loop encountered a critical error.', ['exception' => $e->getMessage()]);
        }
    }
}