<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Commands;

use Baracod\Larastarterkit\Generator\Console\MainGeneratorMenu;
use Illuminate\Console\Command;

/**
 * Point d'entrée du générateur : menu interactif (modules, modèles, génération, IA).
 * Les autres commandes du générateur sont regroupées sous larastarterkit:* (php artisan list larastarterkit).
 */
final class LarastarterkitCommand extends Command
{
    protected $signature = 'larastarterkit {--module= : Ouvre directement la gestion de ce module}';

    /** Anciens noms, conservés pour les habitudes et scripts existants. */
    protected $aliases = ['larastarterkit:builder', 'larastarterkit:builder-module', 'larastarterkit:make'];

    protected $description = 'Générateur CRUD STARTER : menu interactif (voir aussi larastarterkit:module|definition|crud|ai)';

    public function handle(): int
    {
        (new MainGeneratorMenu($this->option('module')))->start();

        return self::SUCCESS;
    }
}
