<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class RestoreDatabaseCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'starter:restore-database
        {--database= : Nom de la connexion Laravel a restaurer}
        {--yes : Confirme explicitement l ecrasement si la base contient deja des tables}';

    /**
     * @var string
     */
    protected $description = 'Restaure la base STARTER (migrations + seeders selon environnement)';

    public function handle(): int
    {
        $connectionName = $this->connectionName();

        try {
            $tables = Schema::connection($connectionName)->getTableListing();
            $databaseName = $this->databaseName($connectionName);
        } catch (Throwable $throwable) {
            $this->error('Impossible de lire la base cible: '.$throwable->getMessage());

            return self::FAILURE;
        }

        if ($tables !== [] && ! $this->confirmOverwrite($connectionName, $databaseName, $tables)) {
            return self::FAILURE;
        }

        $this->info('Restauration de la base ['.$databaseName.'] via migrations + seeders du profil ['.app()->environment().']...');

        return $this->runRestore($connectionName);
    }

    private function connectionName(): string
    {
        $connectionName = $this->option('database');

        if (is_string($connectionName) && $connectionName !== '') {
            return $connectionName;
        }

        return (string) config('database.default');
    }

    private function databaseName(string $connectionName): string
    {
        $databaseName = config('database.connections.'.$connectionName.'.database');

        return is_string($databaseName) && $databaseName !== '' ? $databaseName : $connectionName;
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function confirmOverwrite(string $connectionName, string $databaseName, array $tables): bool
    {
        $rowCount = $this->countRows($connectionName, $tables);

        $this->warn('La base ['.$databaseName.'] contient deja '.count($tables).' table(s) et '.$rowCount.' ligne(s).');
        $this->warn('La restauration va executer migrate:fresh --seed et ecraser toutes les donnees existantes.');

        if ((bool) $this->option('yes')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Restauration annulee. Relancez avec --yes pour confirmer l ecrasement en mode non interactif.');

            return false;
        }

        return $this->confirm('Voulez-vous continuer ?', false);
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function countRows(string $connectionName, array $tables): int
    {
        $rowCount = 0;

        foreach ($tables as $table) {
            try {
                $rowCount += (int) DB::connection($connectionName)->table($table)->count();
            } catch (Throwable) {
                continue;
            }
        }

        return $rowCount;
    }

    private function runRestore(string $connectionName): int
    {
        $exitCode = Artisan::call('migrate:fresh', [
            '--database' => $connectionName,
            '--seed' => true,
            '--force' => true,
        ]);

        $output = trim(Artisan::output());

        if ($output !== '') {
            $this->line($output);
        }

        if ($exitCode !== self::SUCCESS) {
            $this->error('La restauration a echoue.');

            return self::FAILURE;
        }

        $this->info('Base restauree avec succes.');

        return self::SUCCESS;
    }
}
