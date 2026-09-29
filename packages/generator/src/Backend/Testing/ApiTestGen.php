<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Backend\Testing;

use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Test de fonctionnalité de l'API générée : authentification, permissions, CRUD, validation, suppression groupée.
 */
final class ApiTestGen
{
    public function __construct(private readonly ModelDefinition $model) {}

    public function path(): string
    {
        return base_path("Modules/{$this->model->moduleName()}/tests/Feature/{$this->model->name()}ApiTest.php");
    }

    /**
     * @return array{status:'generated'|'skipped', path:?string, message:string}
     */
    public function generate(bool $force = false): array
    {
        if (! $force && File::exists($this->path())) {
            return ['status' => 'skipped', 'path' => $this->path(), 'message' => 'Test déjà présent (--force pour le régénérer).'];
        }
        $apiRoute = (string) ($this->model->backend()->apiRoute ?? '');
        if ($apiRoute === '') {
            return ['status' => 'skipped', 'path' => null, 'message' => 'Générez d’abord les routes : le chemin de l’API est inconnu.'];
        }

        $related = [];
        foreach ($this->model->relations() as $relation) {
            if (($relation['type'] ?? null) !== 'belongsTo' || empty($relation['model']['fqcn'])) {
                continue;
            }
            $field = $this->model->fields()[$relation['foreignKey']] ?? null;
            if ($field === null || $field->isNullable()) {
                continue;
            }
            if (! self::hasFactory((string) $relation['model']['fqcn'], (string) ($relation['moduleName'] ?? ''))) {
                return ['status' => 'skipped', 'path' => null, 'message' => "« {$relation['model']['name']} » (requis par {$relation['foreignKey']}) n'a pas de factory : test non généré."];
            }
            $related[$relation['foreignKey']] = '\\'.ltrim((string) $relation['model']['fqcn'], '\\').'::factory()->create()->'.($relation['ownerKey'] ?? 'id');
        }

        File::ensureDirectoryExists(dirname($this->path()));
        File::put($this->path(), $this->render('/api/v1/'.preg_replace('#^(api/)?(v1/)?#', '', $apiRoute), $related));

        return ['status' => 'generated', 'path' => $this->path(), 'message' => 'Test de l’API créé : '.basename($this->path())];
    }

    /**
     * @param  array<string, string>  $related  clé étrangère requise => expression créant l'enregistrement lié
     */
    public function render(string $endpoint, array $related): string
    {
        $module = $this->model->moduleName();
        $name = $this->model->name();
        $table = $this->model->tableName();
        $requiresInput = array_filter($this->model->fields(), static fn ($f) => ! $f->isNullable()) !== [];
        $overrides = implode('', array_map(static fn (string $fk, string $value) => "\n            '{$fk}' => {$value},", array_keys($related), $related));
        $validationTest = $requiresInput ? <<<'PHP'

            public function test_invalid_payload_is_rejected_with_field_errors(): void
            {
                $this->actingWith(['add']);

                $this->postJson(self::ENDPOINT, [])->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
            }

        PHP : '';

        return <<<PHP
        <?php

        namespace Modules\\{$module}\\Tests\\Feature;

        use Laravel\\Sanctum\\Sanctum;
        use Modules\\Auth\\Models\\Permission;
        use Modules\\Auth\\Models\\Role;
        use Modules\\Auth\\Models\\User;
        use Modules\\{$module}\\Database\\Seeders\\GeneratedPermissionSeeder;
        use Modules\\{$module}\\Models\\{$name};
        use Tests\\TestCase;

        /**
         * Généré par le générateur CRUD : à compléter avec les règles métier de l'entité.
         */
        class {$name}ApiTest extends TestCase
        {
            private const ENDPOINT = '{$endpoint}';

            protected function setUp(): void
            {
                parent::setUp();
                \$this->seed(GeneratedPermissionSeeder::class);
            }

            /** @param list<string> \$actions */
            private function actingWith(array \$actions): User
            {
                \$role = Role::create(['name' => 'tester-'.uniqid(), 'display_name' => 'Tester']);
                \$role->permissions()->sync(Permission::where('subject', '{$table}')->whereIn('action', \$actions)->pluck('id'));
                \$user = User::factory()->create();
                \$user->roles()->attach(\$role);
                Sanctum::actingAs(\$user);

                return \$user;
            }

            /** @return array<string, mixed> */
            private function payload(): array
            {
                return [...{$name}::factory()->raw(),{$overrides}
                ];
            }

            public function test_guests_and_users_without_permission_are_rejected(): void
            {
                \$this->getJson(self::ENDPOINT)->assertUnauthorized();
                \$this->actingWith([]);
                \$this->getJson(self::ENDPOINT)->assertForbidden();
                \$this->postJson(self::ENDPOINT, \$this->payload())->assertForbidden();
            }

            public function test_records_can_be_listed_created_updated_and_deleted(): void
            {
                \$this->actingWith(['browse', 'add', 'edit', 'delete']);

                \$created = \$this->postJson(self::ENDPOINT, \$this->payload())->assertCreated()->json();
                \$this->getJson(self::ENDPOINT)->assertOk()->assertJsonFragment(['id' => \$created['id']]);
                \$this->getJson(self::ENDPOINT.'?per_page=5')->assertOk()->assertJsonStructure(['data', 'total']);
                \$this->getJson(self::ENDPOINT.'/'.\$created['id'])->assertOk();
                \$this->putJson(self::ENDPOINT.'/'.\$created['id'], \$this->payload())->assertOk()->assertJsonPath('id', \$created['id']);
                \$this->deleteJson(self::ENDPOINT.'/'.\$created['id'])->assertOk();
                \$this->getJson(self::ENDPOINT.'/'.\$created['id'])->assertNotFound();
            }
        {$validationTest}
            public function test_bulk_delete_requires_the_delete_permission(): void
            {
                \$ids = collect([\$this->payload(), \$this->payload()])->map(fn (array \$data) => {$name}::create(\$data)->id)->all();

                \$this->actingWith(['browse']);
                \$this->postJson(self::ENDPOINT.'/bulk-delete', ['ids' => \$ids])->assertForbidden();

                \$this->actingWith(['delete']);
                \$this->postJson(self::ENDPOINT.'/bulk-delete', ['ids' => \$ids])->assertOk()->assertJsonPath('deleted', 2);
            }
        }

        PHP;
    }

    private static function hasFactory(string $fqcn, string $module): bool
    {
        $name = Str::afterLast($fqcn, '\\');

        return File::exists(base_path("Modules/{$module}/database/factories/{$name}Factory.php"))
            || (class_exists($fqcn) && method_exists($fqcn, 'factory') && File::exists(database_path("factories/{$name}Factory.php")));
    }
}
