<?php

namespace Modules\Documentation\Models;

use Illuminate\Database\Eloquent\Model;

class Edition extends Model
{
    protected $table = 'documentation_editions';

    protected $guarded = ['id'];

    protected $casts = ['published_at' => 'datetime', 'archived_at' => 'datetime'];

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public function pages()
    {
        return $this->hasMany(Page::class);
    }
}
