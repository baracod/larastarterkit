<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Frontend;

use Baracod\Larastarterkit\Generator\Ai\Assistant;
use Baracod\Larastarterkit\Generator\Backend\ModuleGen;
use Baracod\Larastarterkit\Generator\DefinitionFile\DefinitionStore;
use Baracod\Larastarterkit\Generator\DefinitionFile\Enums\FieldType;
use Baracod\Larastarterkit\Generator\DefinitionFile\FieldDefinition as DField;
use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition as DFModel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;
use RuntimeException;

/**
 * Générateur frontend (TypeScript + Vue + i18n + menu) piloté par la définition du modèle.
 *
 * Chaque étape est indépendante (types, api, index, form, menu, translations) ; generate() les enchaîne.
 */
final class TypeScriptGeneratorFromJson
{
    private DFModel $dfModel;

    private ModuleGen $module;

    private string $moduleName;

    private string $modelName;

    private string $tableName;

    private string $baseUrl;

    /** Provenance des libellés ("IA" ou "règles"), renseignée par generateTranslations(). */
    public string $translationSource = '';

    public function __construct(private string $modelKey, string $moduleName)
    {
        $this->moduleName = Str::studly($moduleName);
        $this->dfModel = DefinitionStore::fromFile(Module::getModulePath($this->moduleName).'module.json')->module()->model($this->modelKey);
        $this->modelName = $this->dfModel->name();
        $this->tableName = $this->dfModel->tableName() ?: Str::snake(Str::pluralStudly($this->modelName));
        $this->module = new ModuleGen($this->moduleName);

        // Le client $api préfixe déjà /api/v1 : baseUrl ne contient que la suite du chemin.
        $apiRoute = (string) ($this->dfModel->backend()->apiRoute ?? '');
        $base = $apiRoute !== '' ? $apiRoute : Str::lower($this->moduleName).'/'.$this->resourceName();
        $this->baseUrl = (string) preg_replace('#^(api/)?(v1/)?#', '', $base);
    }

    /** Point d’entrée : tout le frontend de l'entité. */
    public function generate(): bool
    {
        $this->generateTypes();
        $this->generateApi();
        $this->generateIndexPage();
        $this->generateForm();
        $this->generateMenu();
        $this->generateTranslations();

        return true;
    }

    /* ---------------------------------------------------------------------
     |  Chemins et noms
     * --------------------------------------------------------------------*/

    public function resourceName(): string
    {
        return Str::kebab(Str::smartPlural($this->modelKey));
    }

    public function pageDirectory(): string
    {
        return Str::kebab(Str::smartPlural($this->modelName));
    }

    /** Nom de route produit par unplugin-vue-router pour la page liste : {module}-{dossier}. */
    public function routeName(): string
    {
        return Str::lower($this->moduleName).'-'.$this->pageDirectory();
    }

    public function entityKey(): string
    {
        return Str::camel($this->modelName);
    }

    /* ---------------------------------------------------------------------
     |  Types
     * --------------------------------------------------------------------*/

    public function generateTypes(): string
    {
        $path = $this->module->getPath('resources/ts/types/entities.d.ts');
        $interface = $this->renderInterface();
        File::ensureDirectoryExists(dirname($path));

        $content = File::exists($path) ? (string) File::get($path) : '';
        $pattern = "/export interface I{$this->modelName} \\{.*?\\n\\}/s";
        $content = preg_match($pattern, $content)
            ? (string) preg_replace_callback($pattern, static fn () => $interface, $content, 1)
            : trim($content.PHP_EOL.PHP_EOL.$interface);

        File::put($path, trim($content).PHP_EOL);

        return $path;
    }

    public function renderInterface(): string
    {
        $lines = ['  id?: number'];
        foreach ($this->fields() as $field) {
            $optional = $field->isNullable() ? '?' : '';
            $lines[] = "  {$field->name}{$optional}: ".$this->tsType($field).($field->isNullable() ? ' | null' : '');
        }
        if ($this->dfModel->usesTimestamps()) {
            $lines[] = '  created_at?: string';
            $lines[] = '  updated_at?: string';
        }
        if ($this->dfModel->usesSoftDeletes()) {
            $lines[] = '  deleted_at?: string | null';
        }
        foreach ($this->belongsTo() as $relation) {
            $lines[] = "  {$relation['name']}?: { id: number; [key: string]: unknown } | null";
        }

        return "export interface I{$this->modelName} {\n".implode("\n", $lines)."\n}";
    }

    private function tsType(DField $field): string
    {
        return match ($field->type) {
            FieldType::Integer, FieldType::Float => 'number',
            FieldType::Boolean => 'boolean',
            FieldType::Json => 'Record<string, unknown>',
            FieldType::Enum => $field->enumValues === []
                ? 'string'
                : implode(' | ', array_map(static fn (string $v) => "'".addslashes($v)."'", $field->enumValues)),
            default => 'string',
        };
    }

    /* ---------------------------------------------------------------------
     |  API
     * --------------------------------------------------------------------*/

    public function generateApi(): string
    {
        $path = $this->module->getPath("resources/ts/api/{$this->modelName}.ts");
        File::ensureDirectoryExists(dirname($path));
        File::put($path, strtr($this->stub('api.stub'), [
            '{{ modelName }}' => $this->modelName,
            '{{ baseUrl }}' => $this->baseUrl,
        ]));

        return $path;
    }

    /* ---------------------------------------------------------------------
     |  Page liste
     * --------------------------------------------------------------------*/

    public function generateIndexPage(): string
    {
        $headers = [];
        $slots = '';
        foreach ($this->fields() as $field) {
            if ($field->type === FieldType::Json || $field->type === FieldType::Text) {
                continue;
            }
            $headers[] = "{ title: '{$field->name}', key: '{$field->name}' },";
            $slots .= match (true) {
                $field->type === FieldType::Boolean => $this->slot($field->name, "<VChip :color=\"item.{$field->name} ? 'success' : 'secondary'\" size=\"small\">\n          {{ item.{$field->name} ? t('action.yes') : t('action.no') }}\n        </VChip>"),
                $field->type === FieldType::Date => $this->slot($field->name, "{{ formatDate(item.{$field->name}) }}"),
                $field->type === FieldType::DateTime => $this->slot($field->name, "{{ formatDate(item.{$field->name}, true) }}"),
                default => '',
            };
        }
        foreach ($this->belongsTo() as $relation) {
            $display = $relation['display'];
            $slots .= $this->slot($relation['foreignKey'], "{{ item.{$relation['name']}?.{$display} ?? item.{$relation['foreignKey']} ?? '—' }}");
        }

        $hasDates = str_contains($slots, 'formatDate(');
        $dateHelper = $hasDates
            ? "const formatDate = (value?: string | null, withTime = false) => value\n  ? new Date(value).toLocaleString(locale.value, withTime ? { dateStyle: 'short', timeStyle: 'short' } : { dateStyle: 'short' })\n  : '—'\n"
            : '';

        $path = $this->module->getPath("resources/ts/pages/{$this->pageDirectory()}/index.vue");
        $this->write($path, strtr($this->stub('index.vue.stub'), [
            '{{ dateHelper }}' => $dateHelper,
            '{{ i18nImports }}' => $hasDates ? 't, locale' : 't',
            '{{ modelName }}' => $this->modelName,
            '{{ moduleName }}' => $this->moduleName,
            '{{ entityKey }}' => $this->entityKey(),
            '{{ permissionsSubject }}' => $this->tableName,
            '{{ headers }}' => implode("\n    ", $headers),
            '{{ customDisplayColumn }}' => trim($slots),
        ]));

        return $path;
    }

    private function slot(string $key, string $content): string
    {
        return "<template #item.{$key}=\"{ item }\">\n          {$content}\n        </template>\n\n        ";
    }

    /* ---------------------------------------------------------------------
     |  Formulaire
     * --------------------------------------------------------------------*/

    public function generateForm(): string
    {
        $entity = $this->entityKey();
        $belongsTo = [];
        foreach ($this->belongsTo() as $relation) {
            $belongsTo[$relation['foreignKey']] = $relation;
        }

        $fields = [];
        $defaults = [];
        $imports = [];
        $lists = [];
        $loaders = [];
        foreach ($this->fields() as $field) {
            $label = "t('{$this->moduleName}.{$entity}.field.".Str::camel($field->name)."')";
            $common = "v-model=\"form.{$field->name}\"\n        :label=\"{$label}\"\n        :readonly=\"viewOnly\"\n        :error-messages=\"errorMessage.{$field->name}\"";
            $required = $field->isNullable() ? '' : "\n        required";
            $defaults[] = "{$field->name}: ".$this->defaultValue($field);

            $relation = $belongsTo[$field->name] ?? null;
            if ($relation !== null && $relation['api'] !== null) {
                $list = Str::camel($relation['model']).'Options';
                $imports[] = "import { {$relation['model']}API } from '{$relation['api']}'";
                $imports[] = "import type { I{$relation['model']} } from '".str_replace('/api/'.$relation['model'], '/types/entities', $relation['api'])."'";
                $lists[] = "const {$list} = ref<I{$relation['model']}[]>([])";
                $loaders[] = "{$list}.value = await {$relation['model']}API.getAll()";
                $fields[] = "<CoreAutocomplete\n        {$common}\n        :items=\"{$list}\"\n        item-value=\"id\"\n        item-title=\"{$relation['display']}\"{$required}\n        clearable\n      />";

                continue;
            }

            $fields[] = match ($field->type) {
                FieldType::Boolean => "<VCol\n        cols=\"12\"\n        md=\"6\"\n      >\n        <VSwitch\n          v-model=\"form.{$field->name}\"\n          :label=\"{$label}\"\n          :readonly=\"viewOnly\"\n          :error-messages=\"errorMessage.{$field->name}\"\n          color=\"primary\"\n        />\n      </VCol>",
                FieldType::Enum => "<CoreAutocomplete\n        {$common}\n        :items=\"[".implode(', ', array_map(static fn (string $v) => "'".addslashes($v)."'", $field->enumValues))."]\"{$required}\n      />",
                FieldType::Text => "<CoreTextarea\n        {$common}{$required}\n        md=\"12\"\n        rows=\"3\"\n        auto-grow\n      />",
                FieldType::Json => "<CoreTextarea\n        :model-value=\"JSON.stringify(form.{$field->name} ?? {}, null, 2)\"\n        :label=\"{$label}\"\n        :readonly=\"viewOnly\"\n        :error-messages=\"errorMessage.{$field->name}\"\n        md=\"12\"\n        rows=\"4\"\n        @update:model-value=\"(value: string) => { try { form.{$field->name} = JSON.parse(value) } catch { /* JSON incomplet pendant la saisie */ } }\"\n      />",
                FieldType::Integer, FieldType::Float => "<CoreTextField\n        v-model.number=\"form.{$field->name}\"\n        type=\"number\"\n        :label=\"{$label}\"\n        :readonly=\"viewOnly\"\n        :error-messages=\"errorMessage.{$field->name}\"{$required}\n      />",
                FieldType::Date => "<CoreTextField\n        {$common}\n        type=\"date\"{$required}\n      />",
                FieldType::DateTime => "<CoreTextField\n        {$common}\n        type=\"datetime-local\"{$required}\n      />",
                default => "<CoreTextField\n        {$common}".($field->length ? "\n        :counter=\"{$field->length}\"" : '').$required."\n      />",
            };
        }

        $path = $this->module->getPath("resources/ts/components/{$this->moduleName}{$this->modelName}AddOrEdit.vue");
        $this->write($path, strtr($this->stub('addOrEdit.vue.stub'), [
            '{{ modelName }}' => $this->modelName,
            '{{ moduleName }}' => $this->moduleName,
            '{{ modelNameCamelCase }}' => $entity,
            // Les composants Core* sont eux-mêmes des colonnes responsives (md=6 par défaut).
            '{{ formFields }}' => implode("\n            ", array_map(static fn (string $f) => str_replace("\n", "\n      ", $f), $fields)),
            '{{ defaultValues }}' => '{ '.implode(', ', $defaults).' }',
            '{{ imports }}' => implode("\n", array_unique($imports)),
            '{{ relationLists }}' => $lists === [] ? '' : implode("\n", $lists)."\n",
            '{{ onMounted }}' => $loaders === [] ? '' : "\nonMounted(async () => {\n  ".implode("\n  ", $loaders)."\n})\n",
        ]));

        return $path;
    }

    private function defaultValue(DField $field): string
    {
        $default = $field->effectiveDefault();
        if ($field->type === FieldType::Boolean) {
            return $default !== null && filter_var($default, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }
        if ($default !== null && $default !== '' && $field->type !== FieldType::Json) {
            return in_array($field->type, [FieldType::Integer, FieldType::Float], true)
                ? (string) (0 + $default)
                : "'".addslashes((string) $default)."'";
        }

        return match (true) {
            $field->type === FieldType::Json => '{}',
            $field->isNullable() || in_array($field->type, [FieldType::Integer, FieldType::Float], true) => 'null',
            default => "''",
        };
    }

    /* ---------------------------------------------------------------------
     |  Menu
     * --------------------------------------------------------------------*/

    public function generateMenu(): string
    {
        $path = $this->module->getPath('resources/ts/menuItems.json');
        $items = File::exists($path) ? $this->decode((string) File::get($path)) : [];
        $item = [
            'title' => "{$this->moduleName}.{$this->entityKey()}.menuTitle",
            'to' => ['name' => $this->routeName()],
            'icon' => ['icon' => 'bx-file-blank'],
            'action' => 'browse',
            'subject' => $this->tableName,
        ];

        $replaced = false;
        foreach ($items as $index => $existing) {
            if (($existing['title'] ?? null) === $item['title']) {
                // Conserve l'icône personnalisée, corrige la route et la permission (anciens menus en "access").
                $items[$index] = array_replace($item, array_intersect_key($existing, ['icon' => true]));
                $replaced = true;
            }
        }
        if (! $replaced) {
            $items[] = $item;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);

        return $path;
    }

    /* ---------------------------------------------------------------------
     |  i18n
     * --------------------------------------------------------------------*/

    /** @return list<string> Fichiers écrits */
    public function generateTranslations(): array
    {
        $names = array_map(static fn (DField $f) => $f->name, $this->fields());
        $outcome = app(Assistant::class)->translations($this->modelName, $this->moduleName, $names);
        $this->translationSource = $outcome->source();

        // Les libellés saisis dans la définition priment sur les propositions (en français).
        $translations = $outcome->value;
        foreach ($this->fields() as $field) {
            if ($field->label !== null && isset($translations['fr'])) {
                $translations['fr']['field'][Str::camel($field->name)] = $field->label;
            }
        }

        $written = [];
        $basePath = $this->module->getPath('resources/ts/locales');
        File::ensureDirectoryExists($basePath);
        foreach ($translations as $locale => $payload) {
            $file = "{$basePath}/{$locale}.json";
            $existing = File::exists($file) ? $this->decode((string) File::get($file)) : [];
            $existing[$this->entityKey()] = $payload;
            File::put($file, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
            $written[] = $file;
        }

        return $written;
    }

    /* ---------------------------------------------------------------------
     |  Lecture de la définition
     * --------------------------------------------------------------------*/

    /** @return list<DField> Champs éditables (hors colonnes techniques) */
    private function fields(): array
    {
        $technical = (array) config('generator.technical_columns', ['id', 'created_at', 'updated_at', 'deleted_at']);

        return array_values(array_filter($this->dfModel->fields(), static fn (DField $f) => ! in_array($f->name, $technical, true)));
    }

    /**
     * Relations belongsTo exploitables côté interface.
     *
     * @return list<array{name:string, foreignKey:string, model:string, display:string, api:?string}>
     */
    private function belongsTo(): array
    {
        $relations = [];
        foreach ($this->dfModel->relations() as $relation) {
            if (($relation['type'] ?? '') !== 'belongsTo' || empty($relation['foreignKey'])) {
                continue;
            }
            $model = (string) ($relation['model']['name'] ?? Str::studly(Str::singular((string) ($relation['table'] ?? ''))));
            $module = (string) ($relation['moduleName'] ?? $this->moduleName);
            $apiFile = base_path("Modules/{$module}/resources/ts/api/{$model}.ts");
            $relations[] = [
                'name' => (string) ($relation['name'] ?? Str::camel(Str::beforeLast((string) $relation['foreignKey'], '_id'))),
                'foreignKey' => (string) $relation['foreignKey'],
                'model' => $model,
                'display' => $this->displayField($relation),
                // Liste déroulante seulement si le client API de l'entité liée existe (sinon saisie de l'identifiant).
                'api' => File::exists($apiFile) ? ($module === $this->moduleName ? "../api/{$model}" : '@'.Str::lower($module)."/api/{$model}") : null,
            ];
        }

        return $relations;
    }

    /** Champ lisible de l'entité liée : name, title, label ou code s'ils existent dans sa définition. */
    private function displayField(array $relation): string
    {
        $module = (string) ($relation['moduleName'] ?? $this->moduleName);
        $path = base_path("Modules/{$module}/module.json");
        $table = (string) ($relation['table'] ?? '');
        if (File::exists($path)) {
            $json = $this->decode((string) File::get($path));
            foreach ((array) ($json['models'] ?? []) as $model) {
                if (($model['tableName'] ?? null) !== $table) {
                    continue;
                }
                $names = array_column((array) ($model['fillable'] ?? []), 'name');
                foreach (['name', 'title', 'label', 'code', 'reference', 'email'] as $candidate) {
                    if (in_array($candidate, $names, true)) {
                        return $candidate;
                    }
                }
            }
        }

        return 'id';
    }

    /** Écrit un fichier généré sans les lignes vides en série laissées par les sections absentes. */
    private function write(string $path, string $content): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, (string) preg_replace("/\n{3,}/", "\n\n", $content));
    }

    private function stub(string $name): string
    {
        $path = dirname(__DIR__, 2)."/stubs/entity-generator/frontend/{$name}";
        if (! File::exists($path)) {
            throw new RuntimeException("Stub introuvable : {$path}");
        }

        return (string) File::get($path);
    }

    /** @return array<mixed> */
    private function decode(string $json): array
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new RuntimeException('JSON invalide : '.json_last_error_msg());
        }

        return $data;
    }
}
