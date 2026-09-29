<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Console;

use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition as DFModel;
use Baracod\Larastarterkit\Generator\GenerationMode;
use Baracod\Larastarterkit\Generator\GenerationOrchestrator;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

/**
 * Menu interactif pour la génération groupée (Backend/Frontend/Fullstack).
 *
 * Offre une UI améliorée avec :
 * - Sélection intelligente des modes (cocher/décocher)
 * - Propositions automatiques basées sur ce qui existe déjà
 * - Génération orchestrée avec dépendances respectées
 * - Feedback détaillé sur chaque génération
 */
final class GenerationMenuBuilder
{
    private GenerationOrchestrator $orchestrator;

    private DFModel $model;

    public function __construct(GenerationOrchestrator $orchestrator, DFModel $model)
    {
        $this->orchestrator = $orchestrator;
        $this->model = $model;
    }

    /**
     * Affiche le menu interactif et exécute les générations sélectionnées.
     *
     * @return array<string,bool> Résultats des générations
     */
    public function show(): array
    {
        while (true) {
            $strategy = select(
                label: 'Que veux-tu générer ?',
                options: [
                    'backend_only' => '🔧 Backend complet (Migration + Model + Request + Controller + Routes + Permissions + Factory + Test)',
                    'frontend_only' => '🎨 Frontend complet (Types + API + Index + AddOrEdit + Menu + Traductions)',
                    'fullstack' => '📦 Stack complet (Backend + Frontend)',
                    'custom' => '⚙️ Mode personnalisé (cocher/décocher individuellement)',
                    'back' => '« Retour',
                ],
                default: $this->suggestStrategy()
            );

            if ($strategy === 'back') {
                return [];
            }

            if ($strategy === 'custom') {
                $modes = $this->customModeSelector();
                if (empty($modes)) {
                    continue;
                }

                return $this->executeGeneration($modes);
            }

            if (in_array($strategy, ['backend_only', 'frontend_only', 'fullstack'])) {
                $strategyMap = [
                    'backend_only' => 'backend',
                    'frontend_only' => 'frontend',
                    'fullstack' => 'fullstack',
                ];

                $confirmed = confirm(
                    "Générer « {$strategy} » ?",
                    default: true
                );

                if ($confirmed) {
                    return $this->executeGeneration(
                        GenerationMode::forStrategy($strategyMap[$strategy])
                    );
                }
            }
        }
    }

    /**
     * Suggère la stratégie par défaut basée sur l'état du modèle.
     */
    private function suggestStrategy(): string
    {
        $backend = $this->model->backend();
        $frontend = $this->model->frontend();

        $hasBackend = $backend->hasModel || $backend->hasController || $backend->hasRoute;
        $hasFrontend = ($frontend->hasType ?? false) || ($frontend->hasApi ?? false);

        if (! $hasBackend && ! $hasFrontend) {
            return 'fullstack'; // Nouveau modèle
        }

        if ($hasBackend && ! $hasFrontend) {
            return 'frontend_only'; // Générer le frontend manquant
        }

        if (! $hasBackend && $hasFrontend) {
            return 'backend_only'; // Générer le backend manquant
        }

        return 'custom'; // Déjà partiellement généré
    }

    /**
     * Menu pour sélectionner individuellement les modes.
     *
     * @return array<GenerationMode>
     */
    private function customModeSelector(): array
    {
        $backend = $this->model->backend();
        $frontend = $this->model->frontend();
        $done = [
            GenerationMode::BACKEND_MIGRATION->value => $backend->hasMigration,
            GenerationMode::BACKEND_MODEL->value => $backend->hasModel,
            GenerationMode::BACKEND_REQUEST->value => $backend->hasRequest,
            GenerationMode::BACKEND_CONTROLLER->value => $backend->hasController,
            GenerationMode::BACKEND_ROUTE->value => $backend->hasRoute,
            GenerationMode::BACKEND_PERMISSIONS->value => $backend->hasPermission,
            GenerationMode::BACKEND_FACTORY->value => $backend->hasFactory,
            GenerationMode::BACKEND_TEST->value => $backend->hasTest,
            GenerationMode::FRONTEND_TYPES->value => $frontend->hasType,
            GenerationMode::FRONTEND_API->value => $frontend->hasApi,
            GenerationMode::FRONTEND_INDEX->value => $frontend->hasIndex,
            GenerationMode::FRONTEND_ADDOREDIT->value => $frontend->hasAddOrEditComponent,
            GenerationMode::FRONTEND_MENU->value => $frontend->hasMenu,
            GenerationMode::FRONTEND_I18N->value => $frontend->hasLang,
        ];

        $options = [];
        foreach (GenerationMode::expandFullstack() as $mode) {
            $options[$mode->value] = $mode->label().(($done[$mode->value] ?? false) ? '  ✓ existe' : '');
        }

        $selected = multiselect(
            label: 'Sélectionne les éléments à générer',
            options: $options,
            default: array_keys(array_filter($done, static fn (bool $d) => ! $d)),
            scroll: 14,
            required: false,
        );

        return array_map(static fn (string $value) => GenerationMode::from($value), $selected);
    }

    /**
     * Exécute la génération avec l'orchestrateur.
     *
     * @param  array<GenerationMode>  $modes
     * @return array<string,bool>
     */
    private function executeGeneration(array $modes): array
    {
        info("Génération en cours…\n");

        $results = $this->orchestrator->generate($modes);
        ReportPrinter::print($this->orchestrator->report(), app(\Baracod\Larastarterkit\Generator\Ai\GeneratorAi::class));

        return $results;
    }
}
