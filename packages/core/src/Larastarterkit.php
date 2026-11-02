<?php

namespace Baracod\Larastarterkit\Core;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use LogicException;
use Modules\Auth\Models\User;

/**
 * Public entry points of the core. Code of the core resolves the user model here instead of referencing it directly,
 * so that an application can use its own model through `auth.providers.users.model` (AUTH_MODEL).
 */
final class Larastarterkit
{
    /**
     * @return class-string<User>
     */
    public static function userModel(): string
    {
        $model = config('auth.providers.users.model') ?: User::class;

        if (! is_a($model, User::class, true)) {
            throw new LogicException("The user model [{$model}] must extend ".User::class.'.');
        }

        return $model;
    }

    /**
     * @return Builder<User>
     */
    public static function newUserQuery(): Builder
    {
        return self::userModel()::query();
    }

    /**
     * An application user model keeps the morph type already stored for users (notifications, activity log):
     * the core class name becomes an alias of the application model.
     */
    public static function registerUserMorphAlias(): void
    {
        $model = self::userModel();

        if ($model !== User::class && ! in_array($model, Relation::morphMap(), true)) {
            Relation::morphMap([User::class => $model]);
        }
    }

    public static function newUser(array $attributes = []): User
    {
        $model = self::userModel();

        return new $model($attributes);
    }
}
