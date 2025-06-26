<?php

namespace Cabanga\CoioteTurbo\Commands;

use Cabanga\CoioteTurbo\Queue\WorkerLoop;
use Illuminate\Console\Command;
use Illuminate\Queue\WorkerOptions;
use Swoole\Process\Pool;

class WorkCommand extends Command
{
    protected $signature = 'coiote:work
                            {connection? : The database connection to process}
                            {--workers=auto : The number of worker processes to start}
                            {--queue= : The names of the queues to work}
                            {--memory=128 : The memory limit in megabytes}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=1 : Number of times to attempt a job before logging it failed}
                            {--sleep=3 : Number of seconds to sleep when no job is available}';

    protected $description = 'Starts Coiote Turbo queue workers using a Process Pool.';

    /**
     * The Swoole process pool.
     * @var \Swoole\Process\Pool
     */
    protected Pool $pool;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Check for required extensions.
        if (!extension_loaded('swoole') || !extension_loaded('pcntl')) {
            $this->error('The "swoole" and "pcntl" extensions are required to run the worker pool.');
            return;
        }

        $workerCount = $this->getWorkerCount();
        $this->info("Starting process pool with {$workerCount} workers...");

        // Create a new process pool.
        $this->pool = new Pool($workerCount);

        // Set the event callback for when a worker process starts.
        // This is the core of our multi-process architecture.
        $this->pool->on('WorkerStart', function (Pool $pool, int $workerId) {
            $this->runWorker($workerId);
        });

        // Set the event callback for when a worker process stops.
        $this->pool->on('WorkerStop', function (Pool $pool, int $workerId) {
            $this->info("Worker #{$workerId} has stopped. It will be restarted if necessary.");
        });

        // Register signal handlers for graceful shutdown of the pool.
        $this->registerSignalHandlers();

        // Start the pool. This is a blocking call.
        $this->pool->start();

        $this->info('Worker pool has been shut down.');
    }

    /**
     * The logic to be executed by each worker process.
     * @param int $workerId
     */
    protected function runWorker(int $workerId): void
    {
        // It's a good practice to reset the app instance in each child process
        // to avoid issues with shared resources like database connections.
        $this->laravel->rebound('app');
        $freshApp = $this->laravel->make('app');

        // Pass all command line options to the worker loop.
        $options = [
            'connection' => $this->argument('connection'),
            'queue' => $this->option('queue'),
            'memory' => $this->option('memory'),
            'timeout' => $this->option('timeout'),
            'tries' => $this->option('tries'),
            'sleep' => $this->option('sleep'),
        ];

        // Instantiate and run the dedicated worker loop.
        (new WorkerLoop($freshApp, $options))->run();
    }

    /**
     * Determine the number of workers to start.
     * @return int
     */
    protected function getWorkerCount(): int
    {
        $workers = $this->option('workers');

        if ($workers === 'auto') {
            return swoole_cpu_num();
        }

        return max(1, (int) $workers);
    }

    /**
     * Register signal handlers for graceful termination.
     */
    protected function registerSignalHandlers(): void
    {
        pcntl_async_signals(true);

        $stopPool = function () {
            $this->warn('Shutdown signal received. Shutting down worker pool...');
            $this->pool->shutdown();
        };

        pcntl_signal(SIGTERM, $stopPool);
        pcntl_signal(SIGINT, $stopPool);
    }
}