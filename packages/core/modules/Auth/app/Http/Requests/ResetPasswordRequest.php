<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'email_locale' => 'sometimes|string|in:fr,en',
            'token' => 'required|string',
            'email' => 'required|string|email|max:255|exists:auth_users,email',
            'new_password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Le token de réinitialisation est obligatoire.',
            'email.required' => 'L\'email est obligatoire.',
            'email.email' => 'L\'email doit être une adresse email valide.',
            'email.exists' => 'Cet email n\'existe pas dans notre système.',
            'new_password.required' => 'Le nouveau mot de passe est obligatoire.',
            'new_password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'new_password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ];
    }
}
