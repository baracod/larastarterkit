<?php

namespace Baracod\Larastarterkit\Http\Controllers;

use Baracod\Larastarterkit\Middleware\ConvertRequestToSnakeCase;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    public function __construct()
    {
        $this->middleware(ConvertRequestToSnakeCase::class);
        app()->setLocale('fr');
    }
}
