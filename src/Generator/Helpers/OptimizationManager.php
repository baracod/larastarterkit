<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Helpers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process; // Laravel 10+
use Throwable;

final class OptimizationManager
{
    /**
     * Rafraîchit l'autoload Composer si nécessaire (création/suppression de classes).
     * - En dev: optionnel
     * - En prod: recommandé (autoload optimisé)
     */
    public static function composerDumpAutoload(bool $optimized = true): void
    {
        try {
            $args = ['composer', 'dump-autoload'];
            if ($optimized) {
                $args[] = '-o';
            }
            $result = Process::run($args);
            if ($result->failed()) {
                Log::warning('[Optimization] composer dump-autoload failed', [
                    'exitCode' => $result->exitCode(),
                    'error'    => $result->errorOutput(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('[Optimization] composer dump-autoload threw', ['ex' => $e->getMessage()]);
        }
    }

    /**
     * Vide proprement tous les caches (équivalent à "php artisan optimize:clear").
     */
    public static function clearAllCaches(): void
    {
        // optimize:clear englobe config/route/view/cache/clear-compiled
        Artisan::call('optimize:clear');
    }

    /**
     * Reconstruit les caches utiles en production.
     * Attention: route:cache échoue si des closures existent dans tes routes.
     */
    public static function rebuildCachesForProduction(): void
    {
        try {
            Artisan::call('config:cache');
        } catch (Throwable $e) {
            Log::warning('[Optimization] config:cache failed', ['ex' => $e->getMessage()]);
        }

        try {
            Artisan::call('route:cache'); // échoue s'il y a des closures
        } catch (Throwable $e) {
            Log::warning('[Optimization] route:cache failed (closures ?)', ['ex' => $e->getMessage()]);
        }

        try {
            Artisan::call('view:cache');
        } catch (Throwable $e) {
            Log::warning('[Optimization] view:cache failed', ['ex' => $e->getMessage()]);
        }

        // Optionnel si tu utilises l'Event Discovery
        try {
            Artisan::call('event:cache');
        } catch (Throwable $e) {
            Log::info('[Optimization] event:cache not applicable', ['ex' => $e->getMessage()]);
        }
    }

    /**
     * Pipeline complet et propre.
     * - $withComposer: lance un dump-autoload (prod conseillé)
     * - $rebuild: en prod, rebâtit les caches après clear
     * - $maintenance: en prod, fait down/up autour des opérations
     */
    public static function refreshAll(bool $withComposer = true, bool $rebuild = true, bool $maintenance = null): void
    {
        $isProd = App::environment('production');
        $maintenance = $maintenance ?? $isProd;

        try {
            if ($maintenance) {
                // Personnalise --render si tu as une vue maintenance
                Artisan::call('down', ['--render' => 'errors::503', '--retry' => 10, '--refresh' => 5]);
            }

            if ($withComposer) {
                self::composerDumpAutoload(optimized: $isProd);
            }

            self::clearAllCaches();

            if ($rebuild && $isProd) {
                self::rebuildCachesForProduction();
            }
        } finally {
            if ($maintenance) {
                Artisan::call('up');
            }
        }
    }
}
