<?php

namespace Modules\Documentation\Models;

use Illuminate\Database\Eloquent\Model;

class Publication extends Model
{
    protected $table = 'documentation_publications';

    protected $guarded = ['id'];

    protected $casts = ['snapshot' => 'array', 'portal' => 'array', 'site_snapshot' => 'array', 'finished_at' => 'datetime'];

    protected $hidden = ['site_snapshot'];

    public function edition()
    {
        return $this->belongsTo(Edition::class);
    }

    public function author()
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'author_id');
    }

    public function siteSnapshot(): array
    {
        if ($this->site_snapshot !== null) {
            return $this->site_snapshot;
        }

        // Older publications stored site metadata alongside their edition snapshots.
        $source = $this->kind === 'restore' ? (array_values($this->portal ?? $this->snapshot ?? [])[0] ?? []) : ($this->snapshot ?? []);

        return ['site' => $source['site'] ?? [], 'site_images' => $source['site_images'] ?? []];
    }
}
