<?php

namespace Modules\Documentation\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Models\User;

class Revision extends Model
{
    protected $table = 'documentation_revisions';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['created_at' => 'datetime'];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id')->select(['id', 'name']);
    }
}
