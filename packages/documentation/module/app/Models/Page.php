<?php

namespace Modules\Documentation\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $table = 'documentation_pages';

    protected $guarded = ['id'];

    protected $casts = ['archived_at' => 'datetime'];

    public function edition()
    {
        return $this->belongsTo(Edition::class);
    }

    public function revision()
    {
        return $this->belongsTo(Revision::class, 'current_revision_id');
    }

    public function revisions()
    {
        return $this->hasMany(Revision::class);
    }
}
