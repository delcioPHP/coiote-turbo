<?php

namespace Cabanga\CoioteTurbo\Commands;

use Illuminate\Console\Command;

class StopCommand extends Command
{
    /**
     * The Artisan command signature.
     *
     * @var string
     */
    protected $signature = 'coiote:stop';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'Stops the Coiote Turbo server';

    /**
     * Executes the command logic.
     *
     * @return int
     */
    public function handle(): int
    {
        $pidFile = config('coioteTurbo.pid_file');

        if (! file_exists($pidFile)) {
            $this->info('Coiote Turbo server is not currently running.');
            return self::SUCCESS;
        }

        $pid = trim(file_get_contents($pidFile));

        if (! ctype_digit($pid)) {
            $this->error("Invalid PID found: $pid. Please remove the file manually: $pidFile");
            return self::FAILURE;
        }

        $this->info("Stopping Coiote Turbo server (PID: $pid)...");

        // Send graceful termination signal (SIGTERM)
        posix_kill((int) $pid, SIGTERM);

        // Wait and confirm the process has stopped (MUCH MORE ROBUST)
        $this->output->write('Waiting for the process to stop ');

        $timeout = 10;
        for ($i = 0; $i < ($timeout * 2); $i++) {
            if (! posix_kill((int) $pid, 0)) {
                // Process no longer exists — success!
                unlink($pidFile);
                $this->output->writeln(''); // Newline after dots
                $this->info("Server (PID: $pid) stopped successfully.");
                return self::SUCCESS;
            }
            $this->output->write('.');
            usleep(500000);
        }

        // If the process is still alive after the timeout
        $this->output->writeln('');
        $this->error("Unable to gracefully stop the server (PID: $pid).");
        $this->warn("You may need to force it with: kill -9 $pid");

        return self::FAILURE;
    }
}
