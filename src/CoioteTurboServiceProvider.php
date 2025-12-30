<?php
namespace Cabanga\CoioteTurbo;

use Cabanga\CoioteTurbo\Commands\CheckIdealServerCommand;
use Cabanga\CoioteTurbo\Commands\ScheduleCommand;
use Cabanga\CoioteTurbo\Commands\StartCommand;
use Cabanga\CoioteTurbo\Commands\StatusCommand;
use Cabanga\CoioteTurbo\Commands\StopCommand;
use Cabanga\CoioteTurbo\Commands\WorkCommand;
use Illuminate\Support\ServiceProvider;

class CoioteTurboServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Make the configuration file publishable
        $this->publishes([
            __DIR__.'/../config/coioteTurbo.php' => config_path('coioteTurbo.php'),
        ], 'config');

        // Register the commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                StartCommand::class,
                StopCommand::class,
                StatusCommand::class,
                CheckIdealServerCommand::class,
                WorkCommand::class,
                ScheduleCommand::class
            ]);
        }
    }

    public function register(): void
    {
        // Merge the user's config with the package's default config
        $this->mergeConfigFrom(
            __DIR__.'/../config/coioteTurbo.php', 'coioteTurbo'
        );
    }
}
