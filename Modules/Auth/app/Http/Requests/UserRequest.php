<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'id.integer' => 'Le champ id doit être un entier.',
            'name.required' => 'Le champ name est obligatoire.',
            'name.string' => 'Le champ name doit être une chaîne de caractères.',
            'name.max' => 'Le champ name dépasse la longueur maximale autorisée.',
            'username.string' => 'Le champ username doit être une chaîne de caractères.',
            'username.max' => 'Le champ username dépasse la longueur maximale autorisée.',
            'email.required' => 'Le champ email est obligatoire.',
            'email.string' => 'Le champ email doit être une chaîne de caractères.',
            'email.email' => 'Le champ email doit avoir un format valide.',
            'email.max' => 'Le champ email dépasse la longueur maximale autorisée.',
            'additional_info.string' => 'Le champ additional_info doit être une chaîne de caractères.',
            'additional_info.max' => 'Le champ additional_info dépasse la longueur maximale autorisée.',
            'avatar.string' => 'Le champ avatar doit être une chaîne de caractères.',
            'avatar.max' => 'Le champ avatar dépasse la longueur maximale autorisée.',
            'email_verified_at.date_format' => 'Le champ email_verified_at doit respecter le format requis.',
            'email_verified_at.before' => 'Le champ email_verified_at doit être une date antérieure.',
            'password.required' => 'Le champ password est obligatoire.',
            'password.string' => 'Le champ password doit être une chaîne de caractères.',
            'password.max' => 'Le champ password dépasse la longueur maximale autorisée.',
            'remember_token.string' => 'Le champ remember_token doit être une chaîne de caractères.',
            'remember_token.max' => 'Le champ remember_token dépasse la longueur maximale autorisée.',
            'active.boolean' => 'Le champ active doit être vrai ou faux.',
            'created_at.date_format' => 'Le champ created_at doit respecter le format requis.',
            'created_at.before' => 'Le champ created_at doit être une date antérieure.',
            'updated_at.date_format' => 'Le champ updated_at doit respecter le format requis.',
            'updated_at.before' => 'Le champ updated_at doit être une date antérieure.',
        ];
    }

    public function rules(): array
    {
        // NOTE: supprimez tout dd() ici
        $rules = [
            'id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255',
            'additional_info' => 'nullable|string|max:65535',
            'avatar_file' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            // Attention: ce format exige microsecondes (.uuuuuu). Si vos dates sont en millisecondes, adaptez.
            'email_verified_at' => 'nullable|date_format:Y-m-d\TH:i:s.u\Z|before:now',
            'remember_token' => 'nullable|string|max:100',
            'active' => 'nullable|boolean',
            'password' => 'nullable',
            'created_at' => 'nullable|date_format:Y-m-d\TH:i:s.u\Z|before:now',
            'updated_at' => 'nullable|date_format:Y-m-d\TH:i:s.u\Z|before:now',
        ];

        // Si c’est la route update-profile => on ne valide que les champs envoyés
        if ($this->is('api/v1/auth/users/update-profile/*')) {
            $rules = array_intersect_key($rules, $this->all());
            // Optionnel: sur update-profile, évitez d’exiger le password si non envoyé
            if (! array_key_exists('password', $this->all())) {
                unset($rules['password']);
            }
        }

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
