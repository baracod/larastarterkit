<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Console;

use Baracod\Larastarterkit\Generator\Ai\GeneratorAi;
use Baracod\Larastarterkit\Generator\GenerationOrchestrator;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * Affiche le détail d'une génération : statut par élément, fichiers, et provenance IA/règles.
 */
final class ReportPrinter
{
    /**
     * @param  list<array{mode: \Baracod\Larastarterkit\Generator\GenerationMode, status: string, message: string, files: list<string>}>  $report
     */
    public static function print(array $report, ?GeneratorAi $ai = null): void
    {
        $icons = [GenerationOrchestrator::GENERATED => '✓ généré', GenerationOrchestrator::SKIPPED => '• ignoré', GenerationOrchestrator::FAILED => '✗ échec'];
        table(
            ['Élément', 'Statut', 'Détail'],
            array_map(static fn (array $row) => [$row['mode']->label(), $icons[$row['status']] ?? $row['status'], $row['message']], $report),
        );

        $files = array_values(array_unique(array_merge(...array_map(static fn (array $row) => $row['files'], $report ?: [['files' => []]]))));
        if ($files !== []) {
            note("Fichiers :\n".implode("\n", array_map(static fn (string $f) => '  '.str_replace(base_path().'/', '', $f), $files)));
        }

        if ($ai !== null) {
            foreach ($ai->journal() as $entry) {
                $entry['fromAi']
                    ? info("IA « {$entry['feature']} » : utilisée.")
                    : note("IA « {$entry['feature']} » : règles déterministes ({$entry['reason']}).");
            }
        }

        $failed = count(array_filter($report, static fn (array $row) => $row['status'] === GenerationOrchestrator::FAILED));
        $failed > 0
            ? warning("{$failed} élément(s) en échec.")
            : info('Génération terminée. Pensez à : php artisan migrate --no-interaction · pnpm run build (ou dev).');
    }
}
