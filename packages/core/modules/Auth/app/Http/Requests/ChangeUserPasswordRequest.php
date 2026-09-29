<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangeUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email_locale' => 'sometimes|string|in:fr,en',
            'current_password' => 'required|string',
            'new_password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Le mot de passe actuel est requis.',
            'new_password.required' => 'Le nouveau mot de passe est requis.',
            'new_password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ];
    }
}
