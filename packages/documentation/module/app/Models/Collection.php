<?php

namespace Modules\Documentation\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $table = 'documentation_collections';

    protected $guarded = ['id'];

    protected $casts = ['published_at' => 'datetime', 'archived_at' => 'datetime'];

    public function editions()
    {
        return $this->hasMany(Edition::class);
    }
}
