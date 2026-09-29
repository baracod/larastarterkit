<?php

namespace Baracod\Larastarterkit\Core\Documents\Models;

use Illuminate\Database\Eloquent\Model;

class ProcedureDocument extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['path', 'generation_snapshot'];

    protected function casts(): array
    {
        return ['generation_snapshot' => 'array'];
    }
}
