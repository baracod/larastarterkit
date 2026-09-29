<?php

namespace Baracod\Larastarterkit\Core\Rules;

use Baracod\Larastarterkit\Core\Services\SignatureImageService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\ValidationException;

class SignatureImage implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('La signature doit être une image PNG ou JPEG valide.');

            return;
        }

        try {
            app(SignatureImageService::class)->decode($value);
        } catch (ValidationException $exception) {
            $fail($exception->validator->errors()->first());
        }
    }
}
