<?php

namespace App\Generator\Console;

namespace App\Generator;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use App\Generator\Backend\Model\ModelGen;
use App\Generator\ModuleGenerator;

use function Laravel\Prompts\{
    info,
    text,
    error,
    warning,
    select,
    confirm,
    multiselect
};

class Model {}
