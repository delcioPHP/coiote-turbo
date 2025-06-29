<?php
namespace Cabanga\CoioteTurbo\Queue;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Queue\QueueManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Throwable;

class WorkerLoop
{
    protected Application $app;
    protected array $options;
    protected bool $shouldStop = false;

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
        try {
            // Register signal handlers for graceful worker shutdown
            $this->registerWorkerSignalHandlers();

//            /** @var \Cabanga\CoioteTurbo\Queue\TurboWorker $worker */
//            $worker = $this->app->make(TurboWorker::class);


//            $worker = new \Cabanga\CoioteTurbo\Queue\TurboWorker(
//                $this->app->make(QueueManager::class),
//                $this->app->make(Dispatcher::class),
//                fn () => $this->app->isDownForMaintenance()
//            );

            $worker = new \Cabanga\CoioteTurbo\Queue\TurboWorker(
                $this->app->make(QueueManager::class),
                $this->app->make(Dispatcher::class),
                $this->app->make(ExceptionHandler::class),
                fn () => $this->app->isDownForMaintenance()
            );
            $worker->container = $this->app;
            $connectionName = $this->options['connection'] ?? $this->app['config']['queue.default'];
            $queue = $this->options['queue'] ?? $this->app['config']['queue.connections.'.$connectionName.'.queue'] ?? 'default';

            $this->configureWorkerCallbacks($worker);

            $this->app['log']->info("Worker starting with connection: {$connectionName}, queue: {$queue}");

            // Start the main processing loop.
            $worker->runNextJobLoop($connectionName, $queue, $this->gatherWorkerOptions());

        } catch (Throwable $e) {
            $this->app['log']->error('Worker loop crashed: ' . $e->getMessage(), [
                'exception' => $e,
                'pid' => getmypid()
            ]);
            throw $e;
        }
    }

    /**
     * Configure the essential callbacks on the worker instance.
     * @param \Cabanga\CoioteTurbo\Queue\TurboWorker $worker
     */
    protected function configureWorkerCallbacks(TurboWorker $worker): void
    {
        // After every job, flush the application state to prevent memory leaks.
        $worker->afterJobProcessedCallback = function () {
            try {
                \Cabanga\CoioteTurbo\Core\AppFlusher::flush($this->app);
            } catch (Throwable $e) {
                $this->app['log']->warning('Failed to flush app state: ' . $e->getMessage());
            }
        };

        // Before processing the next job, check memory usage and shutdown signals.
        $worker->shouldStopCallback = function () {
            if ($this->shouldStop) {
                $this->app['log']->info('Queue worker stopping due to shutdown signal.');
                return true;
            }

            $memoryUsage = memory_get_usage(true) / 1024 / 1024;
            $memoryLimit = (int) $this->options['memory'];

            if ($memoryUsage >= $memoryLimit) {
                $this->app['log']->info("Queue worker stopping due to memory limit exceeded: {$memoryUsage}MB >= {$memoryLimit}MB");
                return true;
            }

            return false;
        };
    }

    /**
     * Register signal handlers for worker processes
     */
    protected function registerWorkerSignalHandlers(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        $shutdown = function (int $signal) {
            $this->shouldStop = true;
            $this->app['log']->info("Worker received shutdown signal: {$signal}");
        };

        pcntl_signal(SIGTERM, $shutdown);
        pcntl_signal(SIGINT, $shutdown);
        pcntl_signal(SIGQUIT, $shutdown);
    }

    /**
     * Gather the options for the Illuminate\Queue\Worker.
     * @return \Illuminate\Queue\WorkerOptions
     */
    protected function gatherWorkerOptions(): WorkerOptions
    {
        return new WorkerOptions(
            'default',
            0,
            (int) $this->options['memory'],
            (int) $this->options['timeout'],
            (int) $this->options['sleep'],
            (int) $this->options['tries'],
            false
        );
    }
}
