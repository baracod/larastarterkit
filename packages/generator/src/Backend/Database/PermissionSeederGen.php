<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Backend\Database;

use Baracod\Larastarterkit\Generator\DefinitionFile\DefinitionStore;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\Role;

/**
 * Permissions CRUD des entités générées.
 *
 * Elles sont écrites dans un seeder du module (GeneratedPermissionSeeder, régénéré depuis module.json)
 * pour qu'une nouvelle installation les obtienne, puis appliquées immédiatement à la base courante.
 */
final class PermissionSeederGen
{
    public const ACTIONS = ['browse', 'add', 'edit', 'delete'];

    public function __construct(private readonly string $module) {}

    public function seederPath(): string
    {
        return base_path("Modules/{$this->module}/database/seeders/GeneratedPermissionSeeder.php");
    }

    /**
     * @param  list<string>  $subjects  Sujets (tables) à inclure en plus de ceux déjà marqués dans module.json
     * @return array{path:string, subjects:list<string>, applied:int, wiredInto:?string}
     */
    public function generate(array $subjects = []): array
    {
        $all = array_values(array_unique([...$this->subjectsFromDefinition(), ...$subjects]));
        sort($all);
        File::ensureDirectoryExists(dirname($this->seederPath()));
        File::put($this->seederPath(), $this->render($all));

        return [
            'path' => $this->seederPath(),
            'subjects' => $all,
            'applied' => $this->apply($subjects),
            'wiredInto' => $this->wireIntoModuleSeeder(),
        ];
    }

    /**
     * Applique les permissions à la base actuelle (idempotent) et les accorde à l'administrateur.
     *
     * @param  list<string>  $subjects
     */
    public function apply(array $subjects): int
    {
        if (! Schema::hasTable('auth_permissions')) {
            return 0;
        }
        $access = Permission::query()->firstOrCreate(['key' => 'access_'.$this->moduleSubject()], [
            'action' => 'access', 'subject' => $this->moduleSubject(), 'description' => 'access '.$this->moduleSubject(),
            'is_public' => true, 'always_allow' => false,
        ]);
        $created = (int) $access->wasRecentlyCreated;
        $ids = [$access->id];
        foreach ($subjects as $subject) {
            foreach (self::ACTIONS as $action) {
                $permission = Permission::query()->firstOrCreate(['key' => "{$action}_{$subject}"], [
                    'action' => $action, 'subject' => $subject, 'description' => "{$action} {$subject}",
                    'is_public' => false, 'always_allow' => false,
                ]);
                $created += (int) $permission->wasRecentlyCreated;
                $ids[] = $permission->id;
            }
        }
        Role::query()->where('name', 'administrator')->first()?->permissions()->syncWithoutDetaching($ids);

        return $created;
    }

    /** Sujet d'accès au module, identique à celui du menu (Modules/modules.json). */
    public function moduleSubject(): string
    {
        return strtolower($this->module);
    }

    /** @return list<string> */
    private function subjectsFromDefinition(): array
    {
        $path = base_path("Modules/{$this->module}/module.json");
        if (! File::exists($path)) {
            return [];
        }
        $subjects = [];
        foreach (DefinitionStore::fromFile($path)->module()->all() as $model) {
            if ($model->backend()->hasPermission) {
                $subjects[] = $model->tableName();
            }
        }

        return $subjects;
    }

    /** @param list<string> $subjects */
    private function render(array $subjects): string
    {
        $actions = "['".implode("', '", self::ACTIONS)."']";
        $lines = implode("\n            ", array_map(static fn (string $s) => "'{$s}' => {$actions},", $subjects));
        $module = $this->moduleSubject();

        return <<<PHP
        <?php

        namespace Modules\\{$this->module}\\Database\\Seeders;

        use Illuminate\\Database\\Seeder;
        use Modules\\Auth\\Database\\Seeders\\Concerns\\SeedsPermissions;

        /**
         * Fichier géré par le générateur (régénéré à partir de module.json) : ne pas modifier à la main.
         */
        class GeneratedPermissionSeeder extends Seeder
        {
            use SeedsPermissions;

            public function run(): void
            {
                // Accès au module (menu), puis actions CRUD de chaque entité générée.
                \$this->seedPermissions(['{$module}' => ['access']], ['{$module}']);
                \$this->seedPermissions([
                    {$lines}
                ]);
            }
        }

        PHP;
    }

    /**
     * Ajoute l'appel au seeder généré dans le seeder principal du module s'il existe.
     */
    private function wireIntoModuleSeeder(): ?string
    {
        $main = base_path("Modules/{$this->module}/database/seeders/{$this->module}DatabaseSeeder.php");
        if (! File::exists($main)) {
            return null;
        }
        $code = (string) File::get($main);
        if (str_contains($code, 'GeneratedPermissionSeeder')) {
            return $main;
        }
        $patched = preg_replace('/(public function run\(\)\s*:\s*void\s*\{)/', "$1\n        \$this->call(GeneratedPermissionSeeder::class);", $code, 1, $count);
        if ($count === 1) {
            File::put($main, (string) $patched);

            return $main;
        }

        return null;
    }
}
