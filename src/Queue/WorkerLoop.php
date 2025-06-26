<?php

namespace Cabanga\CoioteTurbo\Queue;

use Cabanga\CoioteTurbo\Core\AppFlusher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\WorkerOptions;


class WorkerLoop
{
    /**
     * The Laravel application instance for this worker process.
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected Application $app;

    /**
     * The command-line options for the worker.
     * @var array
     */
    protected array $options;

    /**
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @param array $options
     */
    public function __construct(Application $app, array $options)
    {
        $this->app = $app;
        $this->options = $options;
    }

    /**
     * Run the main worker loop. This method will run indefinitely.
     */
    public function run(): void
    {

        $worker = $this->app->make(TurboWorker::class);

        $connectionName = $this->options['connection']
            ?: $this->app['config']['queue.default'];

        $queue = $this->options['queue']
            ?: $this->app['config']['queue.connections.'.$connectionName.'.queue'];

        // Configure the callbacks for our custom worker.
        $this->configureWorkerCallbacks($worker);

        // Start the main processing loop using our custom worker logic.
        $worker->runNextJobLoop($connectionName, $queue, $this->gatherWorkerOptions());
    }

    /**
     * Configure the essential callbacks on the worker instance.
     */
    protected function configureWorkerCallbacks(TurboWorker $worker): void
    {
        // After every job, flush the application state to prevent memory leaks.
        $worker->afterJobProcessedCallback = function () {
            AppFlusher::flush($this->app);
        };

        // Before processing the next job, check memory usage.
        // If the limit is exceeded, this will cause the loop to terminate.
        $worker->shouldStopCallback = function () {
            if (memory_get_usage(true) / 1024 / 1024 >= (int) $this->options['memory']) {
                // Log that we are stopping due to memory limit.
                // The Process Pool will automatically restart this worker.
                $this->app['log']->info('Worker stopping due to memory limit exceeded.');
                return true;
            }
            return false;
        };
    }

    /**
     * Gather the options for the Illuminate\Queue\Worker.
     * @return \Illuminate\Queue\WorkerOptions
     */
    protected function gatherWorkerOptions(): WorkerOptions
    {
        return new WorkerOptions(
            0, // delay
            (int) $this->options['memory'],
            (int) $this->options['timeout'],
            (int) $this->options['sleep'],
            (int) $this->options['tries'],
            false
        );
    }
}