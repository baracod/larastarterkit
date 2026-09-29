<?php

namespace Modules\Admin\Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

trait LoadsJsonDataFiles
{
    /**
     * Charge des jeux JSON canoniques par lots. Chaque fichier contient le nom
     * de table, la clé d'identité et les lignes à insérer ou mettre à jour.
     *
     * @param  array<int, string>  $relativePaths
     */
    protected function loadJsonDataFiles(array $relativePaths): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        DB::beginTransaction();

        try {
            foreach ($relativePaths as $relativePath) {
                $dataset = $this->readJsonDataset($relativePath);
                $table = $dataset['table'];
                $uniqueBy = $dataset['unique_by'];

                foreach (array_chunk($dataset['rows'], 500) as $rows) {
                    $columns = array_keys($rows[0]);
                    $updateColumns = array_values(array_diff($columns, $uniqueBy));

                    DB::table($table)->upsert($rows, $uniqueBy, $updateColumns);
                }
            }
            DB::commit();
        } catch (\Throwable $throwable) {
            DB::rollBack();

            throw $throwable;
        } finally {
            \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * @return array{table: string, unique_by: array<int, string>, rows: array<int, array<string, mixed>>}
     */
    private function readJsonDataset(string $relativePath): array
    {
        $path = dirname(__DIR__).'/'.$relativePath;

        if (! is_file($path)) {
            throw new RuntimeException("Seed file [{$relativePath}] is missing.");
        }

        try {
            $dataset = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Seed file [{$relativePath}] contains invalid JSON.", previous: $exception);
        }

        if (! is_array($dataset)
            || ! is_string($dataset['table'] ?? null)
            || ! is_array($dataset['unique_by'] ?? null)
            || ! is_array($dataset['rows'] ?? null)) {
            throw new RuntimeException("Seed file [{$relativePath}] has an invalid dataset structure.");
        }

        foreach ($dataset['rows'] as $row) {
            if (! is_array($row) || array_diff($dataset['unique_by'], array_keys($row)) !== []) {
                throw new RuntimeException("Seed file [{$relativePath}] contains an invalid row.");
            }
        }

        return $dataset;
    }
}
