<?php

namespace Baracod\Larastarterkit\Core\Providers;

use Illuminate\Support\ServiceProvider;

class DocumentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\Baracod\Larastarterkit\Core\Documents\Services\DocumentRegistry::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/documents.php');
        $this->loadViewsFrom(__DIR__.'/../../views/documents', 'documents');
    }
}
