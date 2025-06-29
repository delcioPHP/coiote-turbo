<?php

namespace Cabanga\CoioteTurbo\Commands;

use Cabanga\CoioteTurbo\Queue\WorkerLoop;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
//use Illuminate\Support\Facades\Queue;
//use Illuminate\Support\Facades\DB;
use Swoole\Process\Pool;
use Throwable;

class WorkCommand extends Command
{
    protected $signature = 'coiote:work
                            {connection? : The database connection to process}
                            {--workers=1 : The number of worker processes to start. Use auto to match the number of available CPU cores.}
                            {--queue= : The names of the queues to work}
                            {--memory=128 : The memory limit in megabytes}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=1 : Number of times to attempt a job before logging it failed}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--no-check : Skip queue connection verification}';

    protected $description = 'Starts Coiote Turbo queue workers using a Process Pool.';

    protected Pool $pool;
    protected string $basePath;
    protected bool $shouldShutdown = false;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if (!extension_loaded('swoole') || !extension_loaded('pcntl')) {
            $this->error('The "swoole" and "pcntl" extensions are required to run the worker pool.');
            return;
        }

        //$this->basePath = $this->laravel->basePath();

        //$this->laravel->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        // Check for queue driver connectivity before starting the workers (unless skipped)
        if (!$this->option('no-check') && !$this->checkQueueConnection()) {
            return;
        }

        // Store the base path before starting the pool
        $this->basePath = $this->laravel->basePath();

        $workerCount = $this->getWorkerCount();
        $this->info("Starting process pool with {$workerCount} workers for queues...");

        $this->pool = new Pool($workerCount);
        $this->pool->on('WorkerStart', fn (Pool $pool, int $workerId) => $this->runWorker($workerId));
        $this->pool->on('WorkerStop', fn (Pool $pool, int $workerId) => $this->laravel['log']->info("Queue worker #{$workerId} has stopped."));

        // Register signal handlers only in the master process.
        $this->registerSignalHandlers();

        try {
            $this->pool->start();
        } catch (Throwable $e) {
            $this->error("Pool failed to start: " . $e->getMessage());
        }

        $this->info('Queue worker pool has been shut down.');
    }

    /**
     * The logic to be executed by each worker process.
     * This method now creates a fully isolated Laravel sandbox.
     * @param int $workerId
     */

    protected function runWorker(int $workerId): void
    {
        try {
            // Reset signal handlers for the child process
            pcntl_signal(SIGTERM, SIG_DFL);
            pcntl_signal(SIGINT, SIG_DFL);
            pcntl_signal(SIGUSR1, SIG_DFL);
            pcntl_signal(SIGUSR2, SIG_DFL);

            // Clear container state and perform garbage collection
            Facade::clearResolvedInstances();
            Container::setInstance(null);
            unset($this->laravel);
            if (gc_enabled()) {
                gc_collect_cycles();
            }

            // Reload and bootstrap a fresh Laravel application instance
            $app = require $this->basePath . '/bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

            // Reassign application instance to the current context
            $this->laravel = $app;
            Container::setInstance($app);
            Facade::setFacadeApplication($app);

            // Explicitly forget key bindings to force clean rebind
            $app->forgetInstance('log');
            $app->forgetInstance('queue');
            $app->forgetInstance('db');

            // Log that the worker has started
            $app['log']->info("Queue worker #{$workerId} started.");

            // Build worker options from command arguments
            $options = [
                'connection' => $this->argument('connection'),
                'queue' => $this->option('queue'),
                'memory' => $this->option('memory'),
                'timeout' => $this->option('timeout'),
                'tries' => $this->option('tries'),
                'sleep' => $this->option('sleep'),
            ];

            // Start the actual worker loop
            (new WorkerLoop($app, $options))->run();

        } catch (Throwable $e) {
            if (isset($this->laravel['log'])) {
                $this->laravel['log']->error("Worker #{$workerId} crashed: " . $e->getMessage(), [
                    'exception' => $e,
                    'worker_id' => $workerId,
                ]);
            }
            exit(1);
        }
    }



    /**
     * Checks the queue connection before starting the workers.
     * @return bool
     */

    protected function checkQueueConnection(): bool
    {
        try {
            // Temporarily reduce the default socket timeout (important for Redis or external queue connections)
            $originalTimeout = ini_get('default_socket_timeout');
            ini_set('default_socket_timeout', 5);

            $this->info("Checking queue connection...");

            // Uses the default queue connection already resolved by Laravel
            $connection = $this->laravel->make('queue')->connection();

            // Uses the default queue name, or the one passed via --queue option
            $queueName = $this->option('queue') ?: 'default';

            $size = $connection->size($queueName);

            // Restore the original socket timeout
            ini_set('default_socket_timeout', $originalTimeout);

            // Log the connection class for debugging purposes
            $driver = get_class($connection);
            $this->info("Connected to queue driver [{$driver}], queue: {$queueName}, size: {$size}");

            return true;

        } catch (Throwable $e) {
            if (isset($originalTimeout)) {
                ini_set('default_socket_timeout', $originalTimeout);
            }

            $this->error("Could not connect to the queue.");
            $this->error("Error: " . $e->getMessage());
            $this->warn("Solutions:");
            $this->warn("Check your .env file for queue configuration");
            $this->warn("Ensure the queue service is running");
            $this->warn("Verify database/Redis connection");
            $this->warn("Use --no-check flag to skip this verification");

            return false;
        }
    }



    /**
     * Determine the number of workers to start.
     * @return int
     */
    protected function getWorkerCount(): int
    {
        $workers = $this->option('workers');
        //return $workers === 'auto' ? swoole_cpu_num() : max(1, (int) $workers);
        return $workers === 'auto' ? swoole_cpu_num() : (int) $workers;
    }

    /**
     * Register signal handlers for graceful termination.
     */
    protected function registerSignalHandlers(): void
    {
        pcntl_async_signals(true);

        $stopPool = function (int $signal) {
            if ($this->shouldShutdown) {
                // Force shutdown if already shutting down
                $this->getOutput()->writeln('<fg=red>Force shutdown initiated...</>');
                $this->pool->shutdown();
                exit(0);
            }

            $this->shouldShutdown = true;
            $signalName = $this->getSignalName($signal);

            $this->getOutput()->writeln('');
            $this->getOutput()->writeln("<fg=yellow>Received {$signalName}. Shutting down queue worker pool gracefully...</>");
            $this->getOutput()->writeln('<fg=cyan>Press Ctrl+C again to force shutdown.</>');

            // Graceful shutdown
            $this->pool->shutdown();
        };

        pcntl_signal(SIGTERM, $stopPool);
        pcntl_signal(SIGINT, $stopPool);
        pcntl_signal(SIGQUIT, $stopPool);
    }

    /**
     * Get human-readable signal name
     * @param int $signal
     * @return string
     */
    protected function getSignalName(int $signal): string
    {
        $signals = [
            SIGTERM => 'SIGTERM',
            SIGINT => 'SIGINT (Ctrl+C)',
            SIGQUIT => 'SIGQUIT',
        ];

        return $signals[$signal] ?? "Signal {$signal}";
    }
}
