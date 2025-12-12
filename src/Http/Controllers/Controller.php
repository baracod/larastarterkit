<?php

namespace Baracod\Larastarterkit\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Baracod\Larastarterkit\Middleware\ConvertRequestToSnakeCase;

class Controller extends BaseController
{
    public function __construct()
    {
        $this->middleware(ConvertRequestToSnakeCase::class);
        app()->setLocale('fr');
    }
}
