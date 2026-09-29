<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ForceChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email_locale' => 'sometimes|string|in:fr,en',
            'user_id' => 'required|integer|exists:auth_users,id',
            'new_password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'must_change_password' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'L\'identifiant utilisateur est requis.',
            'user_id.exists' => 'Cet utilisateur n\'existe pas.',
            'new_password.required' => 'Le nouveau mot de passe est requis.',
            'new_password.confirmed' => 'La confirmation ne correspond pas.',
        ];
    }
}
