<?php

namespace Baracod\Larastarterkit\Core\Support;

use Baracod\Larastarterkit\Core\Enums\WeightUnit;

final class WeightFormatter
{
    public static function format(float|int|string|null $weight, string|WeightUnit $unit = 'g'): string
    {
        $symbol = $unit instanceof WeightUnit ? $unit->value : $unit;

        return number_format((float) $weight, 3, ',', ' ').' '.$symbol;
    }
}
