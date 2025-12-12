<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class AuthAccessToken extends SanctumPersonalAccessToken
{
    use HasFactory;
    protected $table = 'auth_access_tokens';
}
