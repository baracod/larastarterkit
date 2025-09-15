<?php

namespace Baracod\Larastarterkit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Baracod\Larastarterkit\Larastarterkit
 */
class Larastarterkit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Baracod\Larastarterkit\Larastarterkit::class;
    }
}
