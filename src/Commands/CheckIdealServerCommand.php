<?php

namespace Cabanga\CoioteTurbo\Commands;

use Illuminate\Console\Command;

class CheckIdealServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coiote:doctor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks if the environment is optimally configured for Coiote Turbo.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Running Coiote Turbo environment checks...');
        $this->newLine();

        $this->checkPhpExtension('swoole');
        $this->checkPhpExtension('pcntl');
        $this->checkOpcacheConfiguration();

        $this->newLine();
        $this->info('Environment check complete.');
    }

    /**
     * Check if a required PHP extension is loaded.
     *
     * @param string $extension
     */
    protected function checkPhpExtension(string $extension): void
    {
        if (extension_loaded($extension)) {
            $this->line("<fg=green;options=bold>PHP Extension:</> <fg=white>$extension is loaded.</>");
        } else {
            $this->line("<fg=red;options=bold>PHP Extension:</> <fg=white>$extension is not loaded.</> <fg=yellow>This is required.</>");
        }
    }

    /**
     * Check for optimal OPcache settings for the CLI.
     */
    protected function checkOpcacheConfiguration(): void
    {
        $this->info('Checking OPcache configuration...');

        if (!ini_get('opcache.enable')) {
            $this->line("<fg=red;options=bold> OPcache:</> <fg=white>OPcache is disabled.</> <fg=yellow>It's highly recommended to enable it in your php.ini.</>");
            return;
        }

        // Check for opcache.enable_cli
        if (ini_get('opcache.enable_cli')) {
            $this->line("<fg=green;options=bold> OPcache CLI:</> <fg=white>Enabled.</>");
        } else {
            $this->line("<fg=yellow;options=bold> OPcache CLI:</> <fg=white>Disabled.</> <fg=gray>Set 'opcache.enable_cli=1' in php.ini for maximum performance.</>");
        }

        // Check for JIT
        if (ini_get('opcache.jit_buffer_size') && ini_get('opcache.jit') !== 'off') {
            $this->line("<fg=green;options=bold> OPcache JIT:</> <fg=white>Enabled.</>");
        } else {
            $this->line("<fg=yellow;options=bold> OPcache JIT:</> <fg=white>Disabled or not configured.</> <fg=gray>Consider enabling JIT in php.ini for CPU-intensive tasks.</>");
        }
    }
}