<?php

namespace Cabanga\CoioteTurbo\Queue;

use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Throwable;

class TurboWorker extends Worker
{
    /**
     * A callback to be executed after each job is processed.
     *
     * @var callable|null
     */
    public $afterJobProcessedCallback;

    /**
     * A callback to check if the worker should stop.
     *
     * @var callable|null
     */
    public $shouldStopCallback;

    /**
     * Process the next job on the given queue connection.
     * We override the daemon method to create our own controlled loop.
     *
     * @param  string  $connectionName
     * @param  string  $queue
     * @param  \Illuminate\Queue\WorkerOptions  $options
     * @return void
     */
    public function runNextJobLoop(string $connectionName, string $queue, WorkerOptions $options): void
    {
        // This is our main worker loop.
        while (true) {
            // Check if we should stop for any reason (memory, signal, etc).
            if ($this->shouldStop()) {
                break;
            }

            // Get the next job from the queue. We pass a fresh instance of options
            // to ensure state isn't carried over inside the worker.
            $job = $this->getNextJob(
                $this->manager->connection($connectionName), $queue
            );

            // If we have a job, we will process it.
            if ($job) {
                try {
                    $this->runJob($job, $connectionName, $options);
                } catch (Throwable $e) {
                    // The parent runJob method already has exception handling,
                    // but we can add more logging here if needed.
                    $this->exceptions->report($e);
                }
            } else {
                // If no job is available, we will sleep to prevent hammering the CPU.
                $this->sleep($options->sleep);
            }

            // Execute the post-job callback, which will handle state flushing.
            if ($this->afterJobProcessedCallback) {
                call_user_func($this->afterJobProcessedCallback);
            }
        }
    }

    /**
     * Determine if the worker should stop.
     *
     * @return bool
     */
    protected function shouldStop(): bool
    {
        if ($this->shouldStopCallback) {
            return call_user_func($this->shouldStopCallback);
        }

        return parent::shouldStop();
    }
}