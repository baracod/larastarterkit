<?php

namespace Modules\Admin\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Admin\Database\Seeders\Concerns\LoadsJsonDataFiles;

/**
 * Référentiels mondiaux (pays, états, villes, fuseaux, devises, langues)
 * requis par le paquet nnjeim/world et par les listes déroulantes de l'application.
 */
class WorldDataSeeder extends Seeder
{
    use LoadsJsonDataFiles;

    private const FILES = [
        'data/world/countries.json',
        'data/world/states.json',
        'data/world/cities.json',
        'data/world/timezones.json',
        'data/world/currencies.json',
        'data/world/languages.json',
    ];

    public function run(): void
    {
        $this->loadJsonDataFiles(self::FILES);
    }
}
