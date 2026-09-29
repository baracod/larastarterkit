<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Commands;

use Baracod\Larastarterkit\Generator\Backend\ModuleGen;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Crée un module prêt à recevoir des entités : structure nwidart, autoload Composer,
 * routes protégées, permission d'accès (seeder + base) et entrée de menu.
 */
final class ModuleCommand extends Command
{
    protected $signature = 'larastarterkit:module
        {name : Nom du module (StudlyCase, ex. Inventory)}
        {--description= : Description affichée dans le menu des modules}
        {--icon= : Icône du menu (ex. mdi-warehouse)}';

    protected $description = 'Crée un module prêt pour le générateur CRUD';

    public function handle(): int
    {
        $name = Str::studly((string) $this->argument('name'));
        if (! preg_match('/^[A-Z][A-Za-z0-9]{1,63}$/', $name)) {
            $this->error("Nom de module invalide : « {$this->argument('name')} ».");

            return self::FAILURE;
        }

        $module = new ModuleGen($name, $this->option('icon'), null, $this->option('description'));
        if ($module->exists()) {
            $this->warn("Le module « {$name} » existe déjà.");

            return self::SUCCESS;
        }

        try {
            $module->generate(refreshCaches: true);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Module « {$name} » créé.");
        $this->line("Étape suivante : php artisan larastarterkit:definition {$name} <Modèle> --fields=\"name:string:120, …\" --generate=fullstack");
        $this->line('Puis : pnpm run build, et redémarrez les workers (php artisan queue:restart --no-interaction).');

        return self::SUCCESS;
    }
}
