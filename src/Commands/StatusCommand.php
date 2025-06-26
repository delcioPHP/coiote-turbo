<?php

namespace Cabanga\CoioteTurbo\Commands;

use Illuminate\Console\Command;

class StatusCommand extends Command
{
    /**
     * The Artisan command signature.
     *
     * @var string
     */
    protected $signature = 'coiote:status';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'Checks the status of the Coiote Turbo server';

    /**
     * Executes the command logic.
     *
     * @return int
     */
    public function handle(): int
    {
        // Use config helper to get the path to the PID file
        $pidFile = config('coioteTurbo.pid_file');

        if (! file_exists($pidFile)) {
            $this->warn('Coiote Turbo server is not running.');
            return self::SUCCESS;
        }

        $pid = trim(file_get_contents($pidFile));

        if (! ctype_digit($pid)) {
            $this->error("Invalid PID found in file: $pid");
            $this->info("You may need to manually remove the PID file: $pidFile");
            return self::FAILURE;
        }

        // Main check: posix_kill with signal 0
        if (posix_kill((int) $pid, 0)) {
            $this->info("Coiote Turbo server is active. (PID: $pid)");
        } else {
            // Handles stale PID files
            $this->warn("Process with PID $pid is not active, but the PID file exists.");
            unlink($pidFile);
            $this->info("Stale PID file has been removed: $pidFile");
        }

        return self::SUCCESS;
    }
}
