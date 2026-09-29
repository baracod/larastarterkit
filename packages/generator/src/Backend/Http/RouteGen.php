<?php

namespace Baracod\Larastarterkit\Generator\Backend\Http;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Déclare les routes REST d'une entité dans routes/api.php d'un module, avant le marqueur //{{ next-route }}.
 */
class RouteGen
{
    /** Accepte "//{{ next-route }}" et "// {{ next-route }}" (forme produite par Pint). */
    private const MARKER = '/^(\s*)\/\/\s*\{\{\s*next-route\s*\}\}/m';

    private string $filePath;

    public function __construct(?string $filePath = null)
    {
        $this->filePath = $filePath ?? base_path('routes/api.php');
    }

    /**
     * Ajoute la ressource API et la route de suppression groupée.
     *
     * "apiRoute" est le chemin sans le préfixe /api/v1 du client frontend, précédé de "api/"
     * (ex. "api/stockmanagement/products"), déduit du préfixe réellement déclaré dans le fichier.
     *
     * @return array{statut:'added'|'already_exists'|'marker_not_found', apiRoute:string}
     */
    public function addApiResource(string $name, string $controller, ?string $module = null, ?string $subject = null): array
    {
        if (! File::exists($this->filePath)) {
            throw new \RuntimeException('Fichier api.php introuvable.');
        }

        $content = File::get($this->filePath);
        $module = Str::studly((string) $module);
        $route = 'api/'.trim($this->groupPrefix($content, $module).'/'.$name, '/');

        $controllerClass = $module !== ''
            ? "\\Modules\\{$module}\\Http\\Controllers\\{$controller}"
            : "\\App\\Http\\Controllers\\{$controller}";
        $subject ??= Str::snake(Str::pluralStudly(Str::beforeLast($controller, 'Controller')));
        $security = "'auth:sanctum', 'active', 'must_change_pass'";

        $lines = [
            "Route::post('{$name}/bulk-delete', [{$controllerClass}::class, 'destroyMany'])->name('{$name}.bulk-delete')"
                ."->middleware([{$security}, 'ability:delete,{$subject}']);",
            "Route::apiResource('{$name}', {$controllerClass}::class)->names('{$name}')"
                ."->middleware([{$security}])"
                ."->middlewareFor(['index', 'show'], 'ability:browse,{$subject}')"
                ."->middlewareFor('store', 'ability:add,{$subject}')"
                ."->middlewareFor('update', 'ability:edit,{$subject}')"
                ."->middlewareFor('destroy', 'ability:delete,{$subject}');",
        ];

        $hasResource = preg_match("/Route::apiResource\\(\\s*['\"]".preg_quote($name, '/')."['\"]\\s*,/m", $content) === 1;
        $hasBulk = str_contains($content, "'{$name}/bulk-delete'");
        if ($hasResource && $hasBulk) {
            return ['statut' => 'already_exists', 'apiRoute' => $route];
        }
        if (! preg_match(self::MARKER, $content, $marker)) {
            return ['statut' => 'marker_not_found', 'apiRoute' => $route];
        }

        $indent = $marker[1] !== '' ? ltrim($marker[1], "\r\n") : '    ';
        $toAdd = array_filter([$hasBulk ? null : $lines[0], $hasResource ? null : $lines[1]]);
        if ($hasResource) {
            // La route groupée doit précéder la ressource : insertion juste avant la déclaration existante.
            $content = preg_replace_callback(
                "/^([ \\t]*)(Route::apiResource\\(\\s*['\"]".preg_quote($name, '/')."['\"])/m",
                static fn (array $m) => $m[1].$lines[0].PHP_EOL.$m[1].$m[2],
                $content,
                1,
            );
        } else {
            $injection = implode(PHP_EOL, array_map(static fn (string $line) => $indent.$line, $toAdd)).PHP_EOL.$marker[0];
            $content = preg_replace_callback(self::MARKER, static fn () => $injection, $content, 1);
        }

        File::put($this->filePath, (string) $content);

        return ['statut' => 'added', 'apiRoute' => $route];
    }

    /**
     * Préfixe déclaré par le groupe de routes du module, sans "api/" ni "v1/" (les ajoute le client frontend).
     */
    private function groupPrefix(string $content, string $module): string
    {
        if (preg_match("/->prefix\\(\\s*['\"]([^'\"]+)['\"]\\s*\\)|Route::prefix\\(\\s*['\"]([^'\"]+)['\"]\\s*\\)/", $content, $m)) {
            $prefix = trim($m[1] !== '' ? $m[1] : $m[2], '/');
            $prefix = preg_replace('#^api/#', '', $prefix);

            return (string) preg_replace('#^v1(/|$)#', '', (string) $prefix);
        }

        return Str::lower($module);
    }
}
