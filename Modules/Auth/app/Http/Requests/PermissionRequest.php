<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PermissionRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'id.integer' => 'Le champ id doit être un entier.',
            'key.required' => 'Le champ key est obligatoire.',
            'key.string' => 'Le champ key doit être une chaîne de caractères.',
            'key.max' => 'Le champ key dépasse la longueur maximale autorisée.',
            'action.string' => 'Le champ action doit être une chaîne de caractères.',
            'action.max' => 'Le champ action dépasse la longueur maximale autorisée.',
            'subject.string' => 'Le champ subject doit être une chaîne de caractères.',
            'subject.max' => 'Le champ subject dépasse la longueur maximale autorisée.',
            'description.string' => 'Le champ description doit être une chaîne de caractères.',
            'description.max' => 'Le champ description dépasse la longueur maximale autorisée.',
            'table_name.string' => 'Le champ table_name doit être une chaîne de caractères.',
            'table_name.max' => 'Le champ table_name dépasse la longueur maximale autorisée.',
            'always_allow.required' => 'Le champ always_allow est obligatoire.',
            'always_allow.boolean' => 'Le champ always_allow doit être vrai ou faux.',
            'is_public.required' => 'Le champ is_public est obligatoire.',
            'is_public.boolean' => 'Le champ is_public doit être vrai ou faux.',
            'created_at.date_format' => 'Le champ created_at doit respecter le format requis.',
            'created_at.before' => 'Le champ created_at doit être une date antérieure.',
            'updated_at.date_format' => 'Le champ updated_at doit respecter le format requis.',
            'updated_at.before' => 'Le champ updated_at doit être une date antérieure.',
        ];
    }

    /**
     * Récupère les règles de validation qui s'appliquent à la requête.
     */
    public function rules(): array
    {
        return [
            'id' => 'nullable|integer',
            'key' => 'required|string|max:255',
            'action' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'table_name' => 'nullable|string|max:255',
            'always_allow' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
            'created_at' => 'nullable|date_format:Y-m-d\TH:i:s.u\Z|before:now',
            'updated_at' => 'nullable|date_format:Y-m-d\TH:i:s.u\Z|before:now',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = [];

        foreach ($validator->errors()->getMessages() as $field => $messages) {
            $errors[$field] = collect($messages)
                ->map(function ($message) use ($field, $validator) {

                    // Extraire la règle depuis les messages en anglais
                    foreach ($validator->failed()[$field] ?? [] as $rule => $params) {
                        return [
                            'key' => strtolower($rule),
                            'message' => $message,
                        ]; // "Required", "Integer", etc.
                    }

                    return $message;
                })[0];
        }

        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed',
            'errors' => $errors,
        ], 422));
    }
}
