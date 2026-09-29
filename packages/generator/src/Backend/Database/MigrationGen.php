<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Backend\Database;

use Baracod\Larastarterkit\Generator\DefinitionFile\Enums\FieldType;
use Baracod\Larastarterkit\Generator\DefinitionFile\FieldDefinition;
use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Migration de création de table à partir de la définition du modèle.
 *
 * Non destructif : une migration existante pour la table n'est jamais réécrite,
 * et aucune migration n'est créée si la table existe déjà (modèle importé d'une base existante).
 */
final class MigrationGen
{
    public function __construct(private readonly ModelDefinition $model) {}

    public function directory(): string
    {
        return base_path("Modules/{$this->model->moduleName()}/database/migrations");
    }

    /**
     * Migration existante qui crée déjà cette table, s'il y en a une.
     */
    public function existing(): ?string
    {
        $table = preg_quote($this->model->tableName(), '/');
        foreach (File::glob($this->directory().'/*.php') as $file) {
            if (preg_match("/Schema::create\\(\\s*['\"]{$table}['\"]/", (string) File::get($file))) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @return array{status:'generated'|'skipped', path:?string, message:string}
     */
    public function generate(): array
    {
        if ($existing = $this->existing()) {
            return ['status' => 'skipped', 'path' => $existing, 'message' => 'Une migration crée déjà cette table : '.basename($existing).'. Ajoutez une migration de modification pour la faire évoluer.'];
        }
        if (Schema::hasTable($this->model->tableName())) {
            return ['status' => 'skipped', 'path' => null, 'message' => "La table « {$this->model->tableName()} » existe déjà en base : aucune migration de création n'est nécessaire."];
        }

        File::ensureDirectoryExists($this->directory());
        $path = $this->directory().'/'.date('Y_m_d_His').'_create_'.$this->model->tableName().'_table.php';
        File::put($path, $this->render());

        return ['status' => 'generated', 'path' => $path, 'message' => 'Migration créée : '.basename($path)];
    }

    public function render(): string
    {
        $lines = ['$table->id();'];
        $foreign = [];
        foreach ($this->model->relations() as $relation) {
            if (($relation['type'] ?? null) === 'belongsTo' && ! empty($relation['foreignKey']) && ! empty($relation['table'])) {
                $foreign[$relation['foreignKey']] = [$relation['table'], (string) ($relation['ownerKey'] ?? 'id')];
            }
        }
        $constraints = [];
        foreach ($this->model->fields() as $field) {
            if (in_array($field->name, ['id', 'created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }
            [$foreignTable, $ownerKey] = $foreign[$field->name] ?? [null, 'id'];
            if ($foreignTable !== null && $ownerKey !== 'id') {
                // Clé étrangère vers une autre colonne que "id" : colonne typée + contrainte explicite.
                $lines[] = $this->column($field, null);
                $constraints[] = "\$table->foreign('{$field->name}')->references('{$ownerKey}')->on('{$foreignTable}')".($field->isNullable() ? '->nullOnDelete()' : '->restrictOnDelete()').';';

                continue;
            }
            $lines[] = $this->column($field, $foreignTable);
        }
        if ($this->model->usesTimestamps()) {
            $lines[] = '$table->timestamps();';
        }
        if ($this->model->usesSoftDeletes()) {
            $lines[] = '$table->softDeletes();';
        }
        array_push($lines, ...$constraints);

        $table = $this->model->tableName();
        $body = implode("\n            ", $lines);

        return <<<PHP
        <?php

        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$table}', function (Blueprint \$table) {
                    {$body}
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$table}');
            }
        };

        PHP;
    }

    private function column(FieldDefinition $field, ?string $foreignTable): string
    {
        $name = $field->name;
        $column = $foreignTable !== null ? "\$table->foreignId('{$name}')" : match ($field->type) {
            FieldType::String => $field->length !== null && $field->length !== 255 ? "\$table->string('{$name}', {$field->length})" : "\$table->string('{$name}')",
            FieldType::Text => "\$table->text('{$name}')",
            FieldType::Integer => "\$table->integer('{$name}')",
            FieldType::Float => "\$table->decimal('{$name}', 15, 2)",
            FieldType::Boolean => "\$table->boolean('{$name}')",
            FieldType::Date => "\$table->date('{$name}')",
            FieldType::DateTime => "\$table->dateTime('{$name}')",
            FieldType::Json => "\$table->json('{$name}')",
            FieldType::Uuid => "\$table->uuid('{$name}')",
            FieldType::Enum => $field->enumValues !== []
                ? "\$table->enum('{$name}', ".$this->export($field->enumValues).')'
                : "\$table->string('{$name}')",
        };

        if ($field->isNullable()) {
            $column .= '->nullable()';
        }
        $default = $field->effectiveDefault();
        if ($default !== null && $default !== '' && $field->type !== FieldType::Json && $field->type !== FieldType::Text) {
            $column .= '->default('.$this->defaultLiteral($field, $default).')';
        } elseif ($field->type === FieldType::Boolean && ! $field->isNullable()) {
            $column .= '->default(false)';
        }
        if ($field->unique) {
            $column .= '->unique()';
        }

        if ($foreignTable !== null) {
            $column .= "->constrained('{$foreignTable}')".($field->isNullable() ? '->nullOnDelete()' : '->restrictOnDelete()');
        }

        return $column.';';
    }

    private function defaultLiteral(FieldDefinition $field, mixed $value): string
    {
        return match ($field->type) {
            FieldType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
            FieldType::Integer => (string) (int) $value,
            FieldType::Float => (string) (float) $value,
            default => $this->export((string) $value),
        };
    }

    private function export(mixed $value): string
    {
        return is_array($value)
            ? '['.implode(', ', array_map(fn ($v) => $this->export($v), $value)).']'
            : var_export($value, true);
    }
}
