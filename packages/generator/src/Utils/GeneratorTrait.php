<?php

namespace Baracod\Larastarterkit\Generator\Utils;

use Illuminate\Support\Str;

trait GeneratorTrait
{
    public function tableNameToModelName(string $tableName): string
    {
        $nameElements = explode('_', $tableName);
        if (count($nameElements) === 1) {
            return ucfirst(Str::singular($nameElements[0]));
        }
        unset($nameElements[0]);
        $tableName = implode(' ', $nameElements);
        $tableName = ucwords($tableName);
        $tableName = str_replace(' ', '', $tableName);

        return Str::singular($tableName);
    }
}
