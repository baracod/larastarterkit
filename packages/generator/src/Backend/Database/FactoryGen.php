<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Backend\Database;

use Baracod\Larastarterkit\Generator\Ai\Assistant;
use Baracod\Larastarterkit\Generator\Ai\Fallback\FakeData;
use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition;
use Illuminate\Support\Facades\File;

/**
 * Factory et seeder de démonstration d'un modèle.
 * Les valeurs sont produites par des formateurs Faker issus d'une liste fermée (choisis par l'IA ou par règles).
 */
final class FactoryGen
{
    public function __construct(
        private readonly ModelDefinition $model,
        private readonly Assistant $assistant,
    ) {}

    public function factoryPath(): string
    {
        return base_path("Modules/{$this->model->moduleName()}/database/factories/{$this->model->name()}Factory.php");
    }

    public function seederPath(): string
    {
        return base_path("Modules/{$this->model->moduleName()}/database/seeders/{$this->model->name()}Seeder.php");
    }

    public function factoryClass(): string
    {
        return "Modules\\{$this->model->moduleName()}\\Database\\Factories\\{$this->model->name()}Factory";
    }

    /**
     * @return array{status:'generated'|'skipped', paths:list<string>, message:string, source:string}
     */
    public function generate(bool $force = false): array
    {
        if (! $force && File::exists($this->factoryPath())) {
            return ['status' => 'skipped', 'paths' => [$this->factoryPath()], 'message' => 'Factory déjà présente (--force pour la régénérer).', 'source' => '—'];
        }

        $outcome = $this->assistant->fakeFormatters($this->model);
        File::ensureDirectoryExists(dirname($this->factoryPath()));
        File::put($this->factoryPath(), $this->renderFactory($outcome->value));
        $paths = [$this->factoryPath()];
        if ($force || ! File::exists($this->seederPath())) {
            File::ensureDirectoryExists(dirname($this->seederPath()));
            File::put($this->seederPath(), $this->renderSeeder());
            $paths[] = $this->seederPath();
        }

        return ['status' => 'generated', 'paths' => $paths, 'message' => 'Factory et seeder de démonstration créés.', 'source' => $outcome->source()];
    }

    /**
     * @param  array<string, string>  $formatters
     */
    public function renderFactory(array $formatters): string
    {
        $module = $this->model->moduleName();
        $name = $this->model->name();
        $foreign = [];
        foreach ($this->model->relations() as $relation) {
            if (($relation['type'] ?? null) === 'belongsTo' && ! empty($relation['model']['fqcn'])) {
                $foreign[$relation['foreignKey']] = $relation;
            }
        }

        $lines = [];
        $needsResolver = false;
        foreach ($this->model->fields() as $field) {
            if (in_array($field->name, ['id', 'created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }
            if (isset($foreign[$field->name])) {
                $relation = $foreign[$field->name];
                $related = '\\'.ltrim((string) $relation['model']['fqcn'], '\\');
                $ownerKey = (string) ($relation['ownerKey'] ?? 'id');
                if ($field->isNullable()) {
                    $lines[] = "'{$field->name}' => fn () => {$related}::query()->inRandomOrder()->value('{$ownerKey}'),";
                } else {
                    $needsResolver = true;
                    $lines[] = "'{$field->name}' => fn () => \$this->requiredRelation({$related}::class, '{$ownerKey}', '{$field->name}'),";
                }

                continue;
            }
            $lines[] = "'{$field->name}' => ".FakeData::expression($field, $formatters[$field->name] ?? null).',';
        }
        $body = implode("\n            ", $lines);
        $resolver = $needsResolver ? <<<'PHP'

            /** Resolve at runtime: the related factory may be generated after this one. */
            private function requiredRelation(string $model, string $column, string $field): mixed
            {
                $value = $model::query()->whereNotNull($column)->inRandomOrder()->value($column);
                if ($value !== null) {
                    return $value;
                }

                $message = "Relation obligatoire {$field} : aucun enregistrement disponible dans {$model} pour {$column}. "
                    ."Créez un enregistrement lié, fournissez explicitement {$field}, ou ajoutez une factory utilisable au modèle lié.";
                if (! method_exists($model, 'factory')) {
                    throw new \RuntimeException($message);
                }

                try {
                    $record = $model::factory()->create();
                    $value = $record->getAttribute($column);
                } catch (\Throwable $exception) {
                    throw new \RuntimeException($message, 0, $exception);
                }
                if ($value === null) {
                    throw new \RuntimeException($message);
                }

                return $value;
            }
        PHP : '';

        return <<<PHP
        <?php

        namespace Modules\\{$module}\\Database\\Factories;

        use Illuminate\\Database\\Eloquent\\Factories\\Factory;
        use Modules\\{$module}\\Models\\{$name};

        /**
         * @extends Factory<{$name}>
         */
        class {$name}Factory extends Factory
        {
            protected \$model = {$name}::class;

            /**
             * @return array<string, mixed>
             */
            public function definition(): array
            {
                return [
                    {$body}
                ];
            }
        {$resolver}
        }

        PHP;
    }

    public function renderSeeder(): string
    {
        $module = $this->model->moduleName();
        $name = $this->model->name();
        $count = max(1, (int) config('generator.seed_count', 10));

        return <<<PHP
        <?php

        namespace Modules\\{$module}\\Database\\Seeders;

        use Illuminate\\Database\\Seeder;
        use Modules\\{$module}\\Models\\{$name};

        /**
         * Données de démonstration : à appeler explicitement, jamais en production.
         * php artisan db:seed --class="Modules\\\\{$module}\\\\Database\\\\Seeders\\\\{$name}Seeder"
         */
        class {$name}Seeder extends Seeder
        {
            public function run(): void
            {
                {$name}::factory()->count({$count})->create();
            }
        }

        PHP;
    }
}
