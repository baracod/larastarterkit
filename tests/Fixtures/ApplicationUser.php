<?php

namespace Tests\Fixtures;

use Modules\Auth\Models\User;

/** An application model that extends the core user, as configured through AUTH_MODEL. */
class ApplicationUser extends User
{
    protected $appends = ['application_marker'];

    public function getApplicationMarkerAttribute(): string
    {
        return 'from-application';
    }
}
