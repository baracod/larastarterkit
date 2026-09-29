<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Commands;

use Baracod\Larastarterkit\Generator\Ai\Assistant;
use Baracod\Larastarterkit\Generator\Ai\GeneratorAi;
use Baracod\Larastarterkit\Generator\Console\ReportPrinter;
use Baracod\Larastarterkit\Generator\DefinitionFile\FieldDefinition;
use Baracod\Larastarterkit\Generator\GenerationMode;
use Baracod\Larastarterkit\Generator\GenerationOrchestrator;
use Baracod\Larastarterkit\Generator\Support\DefinitionBuilder;
use Baracod\Larastarterkit\Generator\Support\TableImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

/**
 * Crée la définition d'un modèle dans module.json, sans passer par une table existante.
 *
 *   php artisan larastarterkit:definition Shop Product --fields="name:string:120, price:float, status:enum(draft|published)"
 *   php artisan larastarterkit:definition Shop --describe="Produits du catalogue avec prix, stock et catégorie"   (IA)
 *   php artisan larastarterkit:definition Shop Supplier --from-table=shop_suppliers
 */
final class DefinitionCommand extends Command
{
    protected $signature = 'larastarterkit:definition
        {module : Module existant}
        {model? : Nom du modèle (StudlyCase, singulier)}
        {--fields= : Champs "nom:type:modificateurs" séparés par des virgules}
        {--describe= : Description métier (conçue par l\'IA, ou syntaxe de champs sans IA)}
        {--from-table= : Importe une table existante}
        {--table= : Nom de table (défaut : <module>_<pluriel>)}
        {--soft-deletes : Suppression logique (deleted_at)}
        {--force : Remplace une définition existante}
        {--no-ai : N\'utilise pas l\'IA}
        {--generate= : Enchaîne la génération (backend, frontend ou fullstack)}';

    /** Ancien nom, conservé pour les scripts existants. */
    protected $aliases = ['generate:definition'];

    protected $description = 'Crée ou remplace la définition d\'un modèle (champs, types, contraintes, relations)';

    public function handle(GeneratorAi $ai, Assistant $assistant): int
    {
        if ($this->option('no-ai')) {
            $ai->disable();
        }
        $module = Str::studly((string) $this->argument('module'));
        if (! File::exists(base_path("Modules/{$module}/module.json"))) {
            $this->error("Module « {$module} » introuvable. Créez-le d'abord : php artisan larastarterkit");

            return self::FAILURE;
        }
        $interactive = $this->input->isInteractive() && ! $this->option('no-interaction');
        $model = $this->argument('model') ? Str::studly((string) $this->argument('model')) : null;

        try {
            $design = $this->design($module, $model, $assistant, $ai, $interactive);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        if ($design === null) {
            return self::FAILURE;
        }

        $table = (string) ($this->option('table') ?: $design['tableName']);
        $this->preview($design['modelName'], $table, $design['fields'], $design['relations'], $design['source']);
        if ($interactive && ! confirm('Enregistrer cette définition ?')) {
            $this->info('Annulé.');

            return self::SUCCESS;
        }

        try {
            $options = array_filter([
                'softDeletes' => $this->option('soft-deletes') || ($design['softDeletes'] ?? false) ?: null,
                'timestamps' => ($design['timestamps'] ?? true) ? null : false,
            ], static fn ($v) => $v !== null);
            $result = DefinitionBuilder::write($module, $design['modelName'], $table, $design['fields'], $design['relations'], $options, (bool) $this->option('force'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $key = $result['model']->key();
        $this->info("Définition « {$key} » enregistrée dans Modules/{$module}/module.json.");
        foreach ($result['skippedRelations'] as $skipped) {
            $this->warn("Relation ignorée (aucun modèle ne porte la table) : {$skipped}");
        }

        $strategy = $this->option('generate');
        if (! $strategy && $interactive) {
            $strategy = select('Générer maintenant ?', ['fullstack' => 'Stack complet', 'backend' => 'Backend', 'frontend' => 'Frontend', '' => 'Plus tard'], 'fullstack');
        }
        if (! $strategy) {
            $this->line("Pour générer : php artisan larastarterkit:crud {$module} {$key}");

            return self::SUCCESS;
        }
        $modes = GenerationMode::forStrategy((string) $strategy);
        if ($modes === []) {
            $this->error("Stratégie inconnue « {$strategy} ».");

            return self::FAILURE;
        }
        $orchestrator = GenerationOrchestrator::for($module, $key);
        $results = $orchestrator->generate($modes);
        ReportPrinter::print($orchestrator->report(), $ai);

        return in_array(false, $results, true) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{modelName:string,tableName:string,fields:list<FieldDefinition>,relations:list<array{foreignKey:string,table:string}>,source:string,softDeletes?:bool,timestamps?:bool}|null
     */
    private function design(string $module, ?string $model, Assistant $assistant, GeneratorAi $ai, bool $interactive): ?array
    {
        $fields = $this->option('fields');
        $describe = $this->option('describe');
        $fromTable = $this->option('from-table');

        if (! $fields && ! $describe && ! $fromTable) {
            if (! $interactive) {
                $this->error('Indiquez --fields, --describe ou --from-table.');

                return null;
            }
            $choice = select('Source de la définition', array_filter([
                'describe' => $ai->enabled('design') ? 'Décrire l’entité en langage naturel (IA)' : null,
                'fields' => 'Lister les champs (nom:type:modificateurs)',
                'table' => 'Importer une table existante',
            ]));
            match ($choice) {
                'describe' => $describe = textarea('Décrivez l’entité', placeholder: 'Ex. Fournisseurs avec raison sociale, e-mail unique, pays et délai de livraison en jours.', required: true),
                'fields' => $fields = text('Champs', placeholder: 'name:string:120, email:string:unique, country:string, delay_days:integer:nullable', required: true, hint: 'Types : string, text, integer, float, boolean, date, datetime, json, uuid, enum(a|b), fk(table)'),
                default => $fromTable = select('Table', $this->candidateTables($module)),
            };
            $model ??= $choice === 'describe' && $ai->enabled('design') ? null : text('Nom du modèle', required: true);
        }

        if ($fromTable) {
            $imported = TableImporter::import((string) $fromTable);
            $name = $model ?? Str::studly(Str::singular(Str::after((string) $fromTable, Str::snake($module).'_')));

            return [...$imported, 'modelName' => $name, 'tableName' => (string) $fromTable, 'source' => 'table '.$fromTable];
        }

        $source = (string) ($fields ?: $describe);
        if ($fields && ! $model) {
            $this->error('Le nom du modèle est requis avec --fields.');

            return null;
        }
        $outcome = $assistant->designEntity($module, $source, $model, Schema::getTableListing());

        return [...$outcome->value, 'source' => $outcome->fromAi ? 'IA ('.$ai->provider().')' : 'syntaxe de champs'];
    }

    /**
     * @param  list<FieldDefinition>  $fields
     * @param  list<array{foreignKey:string,table:string}>  $relations
     */
    private function preview(string $model, string $table, array $fields, array $relations, string $source): void
    {
        $this->info("{$model} → table {$table} (source : {$source})");
        table(['Champ', 'Type', 'Requis', 'Unique', 'Détail'], array_map(static fn (FieldDefinition $f) => [
            $f->name,
            $f->type->value.($f->length ? "({$f->length})" : ''),
            $f->isNullable() ? 'non' : 'oui',
            $f->unique ? 'oui' : '',
            trim(($f->enumValues ? implode('|', $f->enumValues).' ' : '').($f->label ?? '')),
        ], $fields));
        foreach ($relations as $relation) {
            $this->line("  ↳ {$relation['foreignKey']} → {$relation['table']}");
        }
    }

    /** @return array<string, string> */
    private function candidateTables(string $module): array
    {
        $tables = Schema::getTableListing();
        $prefixed = array_values(array_filter($tables, static fn (string $t) => str_starts_with($t, Str::snake($module).'_') || str_starts_with($t, Str::lower($module).'_')));

        return array_combine($prefixed ?: $tables, $prefixed ?: $tables);
    }
}
