<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', fn ($user, $id): bool => (string) $user->id === (string) $id);

Broadcast::channel('dashboard', fn ($user): bool => (bool) $user->active && ! $user->must_change_password && ($user->hasRole('administrator') || $user->can('access', 'dashboard')));
