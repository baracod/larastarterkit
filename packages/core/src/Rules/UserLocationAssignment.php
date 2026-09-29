<?php

namespace Baracod\Larastarterkit\Core\Rules;

use Baracod\Larastarterkit\Core\Services\UserLocationScopeService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UserLocationAssignment implements ValidationRule
{
    public function __construct(private readonly string $reference) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = auth()->user();

        if ($user === null) {
            $fail('Vous devez etre authentifie pour selectionner cette valeur.');

            return;
        }

        if (! app(UserLocationScopeService::class)->isAllowed($user, $this->reference, is_numeric($value) ? (int) $value : null)) {
            $fail('La valeur selectionnee est hors de votre perimetre geographique.');
        }
    }
}
