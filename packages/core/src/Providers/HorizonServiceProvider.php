<?php

namespace Baracod\Larastarterkit\Core\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Console\WorkCommand;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Symfony\Component\Console\Input\InputOption;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function register(): void
    {
        parent::register();

        // Laravel 12.60+ reads this option, absent from Horizon 5.44's signature.
        $this->app->afterResolving(WorkCommand::class, function (WorkCommand $command): void {
            if (! $command->getDefinition()->hasOption('stop-when-empty-for')) {
                $command->getDefinition()->addOption(new InputOption(
                    'stop-when-empty-for',
                    null,
                    InputOption::VALUE_OPTIONAL,
                    'Stop when no jobs have been processed for the given number of seconds',
                    0,
                ));
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return in_array(optional($user)->email, [
                //
            ]);
        });
    }
}
