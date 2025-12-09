<?php

namespace Baracod\Larastarterkit\Generator\Backend\Http;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RouteGen
{
    private string $filePath;

    public function __construct(?string $filePath = null)
    {
        $this->filePath = $filePath ?? base_path('routes/api.php');
    }

    /**
     * Ajoute une route apiResource avant le marqueur //{{ next-route }}
     *
     * @return array <statut:'added'|'already_exists'|'marker_not_found', apiRoute:string>
     */
    // public function _addApiResource(string $name, string $controller, ?string $module = null): array
    // {
    //     if (! File::exists($this->filePath)) {
    //         throw new \RuntimeException("Fichier api.php introuvable.");
    //     }

    //     $content = File::get($this->filePath);

    //     // Namespace du contrôleur
    //     $controllerNamespace = $module
    //         ? "Modules\\{$module}\\Http\\Controllers\\{$controller}"
    //         : "App\\Http\\Controllers\\{$controller}";

    //     $routeLine = "    Route::apiResource('{$name}', \\{$controllerNamespace}::class)->names('{$name}');";

    //     // Éviter les doublons
    //     if (str_contains($content, $routeLine)) {
    //         return 'already_exists';
    //     }

    //     // Vérifier si le marqueur existe
    //     if (! str_contains($content, '//{{ next-route }}')) {
    //         return 'marker_not_found';
    //     }

    //     // Injection avant le marqueur
    //     $updated = str_replace(
    //         '//{{ next-route }}',
    //         $routeLine . PHP_EOL . '    //{{ next-route }} la',
    //         $content
    //     );

    //     File::put($this->filePath, $updated);

    //     return 'added';
    // }

    /**
     * Ajoute une route apiResource avant le marqueur //{{ next-route }}
     *
     * @return array{statut:'added'|'already_exists'|'marker_not_found', apiRoute:string}
     */
    public function addApiResource(string $name, string $controller, ?string $module = null): array
    {
        if (! File::exists($this->filePath)) {
            throw new \RuntimeException('Fichier api.php introuvable.');
        }

        $content = File::get($this->filePath);
        $module = Str::studly($module);
        $route = 'api/'.$module.'/'.$name;

        // Namespace contrôleur
        $controllerNamespace = Str::camel($module)
            ? "Modules\\{$module}\\Http\\Controllers\\{$controller}"
            : "App\\Http\\Controllers\\{$controller}";

        // Ligne à injecter (sans indentation — on la rajoute au moment de l'injection)
        $routeLine = "Route::apiResource('{$name}', \\{$controllerNamespace}::class)->names('{$name}');";

        // Détection de doublon tolérante aux espaces/retours à la ligne
        $dupPattern = "/Route::apiResource\\(\\s*['\"]".preg_quote($name, '/')."['\"]\\s*,/m";
        if (preg_match($dupPattern, $content) === 1) {
            return ['statut' => 'already_exists', 'apiRoute' => $route];
        }

        // Cherche le marqueur et capture son indentation
        if (! preg_match('/^(\s*)\/\/\{\{\s*next-route\s*\}\}/m', $content, $m)) {
            return ['statut' => 'marker_not_found', 'apiRoute' => $route];
        }
        $indent = $m[1] ?? '    '; // indentation par défaut si non trouvée

        // Injection AVANT le marqueur, en préservant le marqueur tel quel (sans le modifier)
        $injection = $indent.$routeLine.PHP_EOL.$m[0];
        $updated = preg_replace('/^(\s*)\/\/\{\{\s*next-route\s*\}\}/m', $injection, $content, 1);

        File::put($this->filePath, $updated);

        return ['statut' => 'added', 'apiRoute' => $route];
    }
}
