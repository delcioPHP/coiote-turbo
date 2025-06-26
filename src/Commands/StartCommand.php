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

        $this->info('Starting Coiote Turbo...');

        // Load the configuration
        $config = config('coioteTurbo');

        // Logic to check if it's already running (using the pid_file from config)
        if (file_exists($config['pid_file'])) {
            $pid = file_get_contents($config['pid_file']);
            if (posix_kill((int)$pid, 0)) {
                $this->warn("Coiote Turbo already seems to be running with PID: $pid");
                return self::SUCCESS;
            }
            // If the process does not exist, remove the old pid file
            unlink($config['pid_file']);
        }

        // Instantiate and start the server
        $server = new SwooleServer($this->laravel, $config);
        $server->start();

        $this->info("Coiote Turbo started at http://{$config['host']}:{$config['port']}");
        return self::SUCCESS;
    }
}
