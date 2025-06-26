<?php

namespace Cabanga\CoioteTurbo\Commands;

use Cabanga\CoioteTurbo\Http\SwooleServer;
use Illuminate\Console\Command;

class StartCommand extends Command
{
    // Command signature in Artisan
    protected $signature = 'coiote:start';

    // Command description
    protected $description = 'Starts the Coiote Turbo server with Swoole';

    public function handle(): int
    {
        // CRITICAL validation of the Swoole extension
        if (!extension_loaded('swoole')) {
            $this->error('The Swoole extension is not installed or enabled.');
            $this->info('Please install it to use Coiote Turbo: pecl install swoole');
            return self::FAILURE;
        }

        // Load the configuration (make sure the key is lowercase)
        $config = config('coioteTurbo');

        // Logic to check if it's already running (using the pid_file from config)
        if (file_exists($config['pid_file'])) {
            $pid = file_get_contents($config['pid_file']);
            if ($pid && posix_kill((int)$pid, 0)) {
                $this->warn("Coiote Turbo already seems to be running with PID: $pid. Use 'coiote:stop' to stop it.");
                return self::SUCCESS;
            }
            unlink($config['pid_file']);
        }

        $this->info('Starting Coiote Turbo server...');

        // If we are not running in daemon mode (i.e., foreground for development),
        // override the log file path to send logs directly to the console's standard output.
        if (empty($config['daemonize'])) {
            $this->line(" <fg=yellow>Running in foreground mode. Tailing logs. Press Ctrl+C to stop.</>");
            // This is a standard Unix stream that points to the console.
            $config['log_file'] = '/dev/stdout';
            $this->info(
                "Coiote Turbo server started successfully. Listening at http://{$config['host']}:{$config['port']}"
            );
        }

        // Instantiate and start the server.
        // The SwooleServer class will now receive the correct log_file path.
        $server = new SwooleServer($this->laravel, $config);

        // This call will either block (foreground) or exit after forking (daemon).
        $server->start();

        return self::SUCCESS;
    }
}