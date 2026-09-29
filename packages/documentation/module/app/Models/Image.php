<?php

namespace Modules\Documentation\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $table = 'documentation_images';

    protected $guarded = ['id'];

    protected $hidden = ['file'];

    protected $casts = ['archived_at' => 'datetime'];
}
