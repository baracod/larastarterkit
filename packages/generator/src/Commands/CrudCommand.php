<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Commands;

use Baracod\Larastarterkit\Generator\Ai\GeneratorAi;
use Baracod\Larastarterkit\Generator\Console\ReportPrinter;
use Baracod\Larastarterkit\Generator\DefinitionFile\DefinitionStore;
use Baracod\Larastarterkit\Generator\GenerationMode;
use Baracod\Larastarterkit\Generator\GenerationOrchestrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

/**
 * Génère un CRUD (backend et/ou frontend) à partir de la définition d'un modèle (module.json).
 *
 * - Interactif : php artisan larastarterkit:crud
 * - Stratégie : php artisan larastarterkit:crud Shop product --strategy=backend
 * - Modes : php artisan larastarterkit:crud Shop product --modes=migration,model,request,controller,route
 * - Sans IA : php artisan larastarterkit:crud Shop product --no-ai
 */
final class CrudCommand extends Command
{
    protected $signature = 'larastarterkit:crud
        {module? : Nom du module}
        {modelKey? : Clé du modèle dans module.json (kebab-case)}
        {--strategy=fullstack : backend, frontend ou fullstack}
        {--modes= : Modes précis, séparés par des virgules (migration,model,request,controller,route,permissions,factory,test,types,api,index,form,menu,i18n)}
        {--force : Régénère aussi les éléments existants (les migrations ne sont jamais réécrites)}
        {--no-ai : N\'utilise pas l\'IA (règles déterministes uniquement)}
        {--batch : Sans aucune question}';

    /** Ancien nom, conservé pour les scripts existants. */
    protected $aliases = ['generate:crud'];

    protected $description = 'Génère un CRUD complet (Backend + Frontend) à partir de la définition du modèle';

    public function handle(GeneratorAi $ai): int
    {
        if ($this->option('no-ai')) {
            $ai->disable();
        }
        $interactive = ! $this->option('batch') && ! $this->option('no-interaction') && $this->input->isInteractive();

        $module = $this->argument('module');
        $modelKey = $this->argument('modelKey');
        if (! $module || ! $modelKey) {
            if (! $interactive) {
                $this->error('Sans interaction, le module et la clé du modèle sont requis.');

                return self::FAILURE;
            }
            [$module, $modelKey] = $this->pickModuleAndModel($module ? Str::studly($module) : null);
            if ($module === null) {
                return self::FAILURE;
            }
        }
        $module = Str::studly($module);
        $modelKey = Str::kebab($modelKey);

        if ($this->option('modes')) {
            $parsed = GenerationMode::parseAliases((string) $this->option('modes'));
            if ($parsed['unknown'] !== []) {
                $this->error('Modes inconnus : '.implode(', ', $parsed['unknown']).'. Valeurs : '.implode(', ', array_keys(GenerationMode::ALIASES)));

                return self::FAILURE;
            }
            $modes = $parsed['modes'];
        } else {
            $modes = GenerationMode::forStrategy((string) $this->option('strategy'));
        }
        if ($modes === []) {
            $this->error('Aucun mode de génération : --strategy=backend|frontend|fullstack ou --modes=…');

            return self::FAILURE;
        }

        try {
            $orchestrator = GenerationOrchestrator::for($module, $modelKey)->force((bool) $this->option('force'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($interactive) {
            $this->line('');
            $this->info("{$module} · {$orchestrator->model()->name()} — ".($ai->enabled() ? 'IA : '.$ai->provider() : 'IA inactive : '.$ai->unavailableReason()));
            foreach ($modes as $mode) {
                $this->line("  • {$mode->label()}");
            }
            if (! confirm('Confirmer la génération ?')) {
                $this->info('Annulé.');

                return self::SUCCESS;
            }
        }

        $results = $orchestrator->generate($modes);
        ReportPrinter::print($orchestrator->report(), $ai);

        return in_array(false, $results, true) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function pickModuleAndModel(?string $module): array
    {
        $modules = [];
        foreach (glob(base_path('Modules/*/module.json')) ?: [] as $file) {
            $count = count((array) (json_decode((string) File::get($file), true)['models'] ?? []));
            if ($count > 0) {
                $modules[basename(dirname($file))] = basename(dirname($file))." ({$count} modèle(s))";
            }
        }
        if ($modules === []) {
            $this->warn('Aucun modèle défini. Créez-en un : php artisan larastarterkit:definition <Module> <Modèle> --fields="name:string, …"');

            return [null, null];
        }

        $module ??= select('Module', $modules);
        $path = base_path("Modules/{$module}/module.json");
        if (! File::exists($path)) {
            $this->error("Module « {$module} » introuvable.");

            return [null, null];
        }
        $models = [];
        foreach (DefinitionStore::fromFile($path)->module()->all() as $key => $model) {
            $models[$key] = "{$model->name()} ({$model->tableName()})";
        }

        return $models === [] ? [null, null] : [$module, select('Modèle', $models)];
    }
}
