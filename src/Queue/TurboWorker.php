<?php
namespace Cabanga\CoioteTurbo\Queue;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Throwable;

class TurboWorker extends Worker
{
    /**
     * A callback to be executed after each job is processed.
     * @var callable|null
     */
    public $afterJobProcessedCallback = null;

    /**
     * A callback to check if the worker should stop.
     * @var callable|null
     */
    public $shouldStopCallback = null;

    /**
     * Track consecutive empty queue cycles to prevent CPU hammering
     * @var int
     */
    protected int $emptyQueueCycles = 0;

    /**
     * Maximum empty cycles before extending sleep time
     * @var int
     */
    protected int $maxEmptyQueueCycles = 10;

    public function __construct(
        QueueManager $manager,
        Dispatcher $events,
        ExceptionHandler $exceptions,
        callable $isDownForMaintenance
    ) {
        parent::__construct($manager, $events, $exceptions, $isDownForMaintenance);
    }

    /**
     * Process the next job on the given queue connection in a continuous loop.
     */
    public function runNextJobLoop(string $connectionName, string $queue, WorkerOptions $options): void
    {
        $this->container['log']->info("Starting job processing loop for queue: {$queue}");

        while (true) {
            try {
                if ($this->shouldStop()) {
                    $this->container['log']->info('Worker stopping: returned true');
                    break;
                }

                if (extension_loaded('pcntl')) {
                    pcntl_signal_dispatch();
                }

                $job = $this->getNextJob(
                    $this->manager->connection($connectionName), $queue
                );

                if ($job) {
                    $this->emptyQueueCycles = 0;

                    try {
                        $this->runJob($job, $connectionName, $options);
                    } catch (Throwable $e) {
                        $this->exceptions->report($e);
                        $this->container['log']->error('Job processing failed: ' . $e->getMessage(), [
                            'job_id' => $job->getJobId(),
                            'exception' => $e
                        ]);
                    }
                } else {
                    $this->handleEmptyQueue($options);
                }

                if ($this->afterJobProcessedCallback) {
                    try {
                        call_user_func($this->afterJobProcessedCallback);
                    } catch (Throwable $e) {
                        $this->container['log']->warning('After job callback failed: ' . $e->getMessage());
                    }
                }

            } catch (Throwable $e) {
                $this->container['log']->error('Worker loop error: ' . $e->getMessage(), [
                    'exception' => $e,
                    'connection' => $connectionName,
                    'queue' => $queue
                ]);

                $this->sleep(min($options->sleep * 2, 30));
            }
        }

        $this->container['log']->info('Worker loop ended');
    }

    /**
     * Handle empty queue with intelligent sleeping
     */
    protected function handleEmptyQueue(WorkerOptions $options): void
    {
        $this->emptyQueueCycles++;

        $sleepTime = $this->emptyQueueCycles > $this->maxEmptyQueueCycles
            ? min($options->sleep * 3, 30)
            : $options->sleep;

        $this->sleep($sleepTime);
    }

    /**
     * Determine if the worker should stop.
     */
    public function shouldStop(): bool
    {
//        if ($this->shouldStopCallback && call_user_func($this->shouldStopCallback)) {
//            return true;
//        }
        // Check custom callback first
        if ($this->shouldStopCallback && call_user_func($this->shouldStopCallback)) {
            return true;
        }
        return false;
    }

    /**
     * Sleep for the given number of seconds with signal handling
     */
    public function sleep($seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $microseconds = $seconds * 1000000;
        $sleepInterval = 100000;

        $slept = 0;
        while ($slept < $microseconds) {
            if ($this->shouldStop()) {
                break;
            }

            if (extension_loaded('pcntl')) {
                pcntl_signal_dispatch();
            }

            $currentSleep = min($sleepInterval, $microseconds - $slept);
            usleep($currentSleep);
            $slept += $currentSleep;
        }
    }
}
