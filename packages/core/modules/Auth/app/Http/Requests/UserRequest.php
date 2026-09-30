<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Auth\Models\User;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'id.integer' => 'Le champ « identifiant » doit être un entier.',
            'name.required' => 'Le champ « nom » est obligatoire.',
            'name.string' => 'Le champ « nom » doit être une chaîne de caractères.',
            'name.max' => 'Le champ « nom » dépasse la longueur maximale autorisée.',
            'username.string' => 'Le champ « nom d\'utilisateur » doit être une chaîne de caractères.',
            'username.max' => 'Le champ « nom d\'utilisateur » dépasse la longueur maximale autorisée.',
            'email.required' => 'Le champ « adresse e-mail » est obligatoire.',
            'email.string' => 'Le champ « adresse e-mail » doit être une chaîne de caractères.',
            'email.email' => 'Le champ « adresse e-mail » doit avoir un format valide.',
            'email.max' => 'Le champ « adresse e-mail » dépasse la longueur maximale autorisée.',
            'additional_info.string' => 'Le champ « informations supplémentaires » doit être une chaîne de caractères.',
            'additional_info.max' => 'Le champ « informations supplémentaires » dépasse la longueur maximale autorisée.',
            'avatar.string' => 'Le champ « avatar » doit être une chaîne de caractères.',
            'avatar.max' => 'Le champ « avatar » dépasse la longueur maximale autorisée.',
            'email_verified_at.date_format' => 'Le champ « date de vérification de l\'e-mail » doit respecter le format requis.',
            'email_verified_at.before' => 'Le champ « date de vérification de l\'e-mail » doit être une date antérieure.',
            'password.required' => 'Le champ « mot de passe » est obligatoire.',
            'password.string' => 'Le champ « mot de passe » doit être une chaîne de caractères.',
            'password.max' => 'Le champ « mot de passe » dépasse la longueur maximale autorisée.',
            'remember_token.string' => 'Le champ « jeton de session » doit être une chaîne de caractères.',
            'remember_token.max' => 'Le champ « jeton de session » dépasse la longueur maximale autorisée.',
            'active.boolean' => 'Le champ « actif » doit être vrai ou faux.',
            'created_at.date_format' => 'Le champ « date de création » doit respecter le format requis.',
            'created_at.before' => 'Le champ « date de création » doit être une date antérieure.',
            'updated_at.date_format' => 'Le champ « date de modification » doit respecter le format requis.',
            'updated_at.before' => 'Le champ « date de modification » doit être une date antérieure.',
        ];
    }

    public function rules(): array
    {
        if ($this->is('api/v1/auth/users/update-profile/*')) {
            $userId = (int) $this->route('id');

            return [
                'name' => ['sometimes', 'string', 'max:255'],
                'username' => ['sometimes', 'string', 'max:255', Rule::unique('auth_users', 'username')->ignore($userId)],
                'email' => ['sometimes', 'email', 'max:255', Rule::unique('auth_users', 'email')->ignore($userId)],
                'additional_info' => ['sometimes', 'nullable', 'string', 'max:65535'],
                'avatar_file' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            ];
        }

        $routeUser = $this->route('user');
        $userId = $routeUser instanceof User ? $routeUser->id : $routeUser;
        $passwordRule = Password::min(8)->mixedCase()->numbers()->symbols();

        $rules = [
            'email_locale' => 'sometimes|string|in:fr,en',
            'id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:255', Rule::unique('auth_users', 'username')->ignore($userId)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('auth_users', 'email')->ignore($userId)],
            'additional_info' => 'nullable|string|max:65535',
            'avatar_file' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'active' => 'nullable|boolean',
            'password' => $this->isMethod('post') ? ['required', $passwordRule] : ['prohibited'],
        ];

        return $rules;
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = [];

        foreach ($validator->errors()->getMessages() as $field => $messages) {
            $first = $messages[0] ?? 'Invalid.';
            $ruleKey = null;

            foreach ($validator->failed()[$field] ?? [] as $rule => $params) {
                $ruleKey = strtolower($rule); // ex: required, integer, etc.
                break;
            }

            $errors[$field] = $ruleKey
                ? ['key' => $ruleKey, 'message' => $first]
                : ['message' => $first];
        }

        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed',
            'errors' => $errors,
        ], 422));
    }
}
