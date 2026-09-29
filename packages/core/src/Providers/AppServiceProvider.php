<?php

namespace Baracod\Larastarterkit\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\AuthAccessToken;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(AuthAccessToken::class);
        Str::macro('smartPlural', function (string $word): string {
            return in_array(strtolower($word), ['cursus', 'status', 'syllabus'], true) ? $word : Str::plural($word);
        });
    }
}
