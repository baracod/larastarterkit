<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RoleRequest extends FormRequest
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
            'id.integer' => 'Le champ « identifiant » doit être un entier.',
            'name.required' => 'Le champ « nom » est obligatoire.',
            'name.string' => 'Le champ « nom » doit être une chaîne de caractères.',
            'name.max' => 'Le champ « nom » dépasse la longueur maximale autorisée.',
            'display_name.required' => 'Le champ « nom d\'affichage » est obligatoire.',
            'display_name.string' => 'Le champ « nom d\'affichage » doit être une chaîne de caractères.',
            'display_name.max' => 'Le champ « nom d\'affichage » dépasse la longueur maximale autorisée.',
            'description.string' => 'Le champ « description » doit être une chaîne de caractères.',
            'description.max' => 'Le champ « description » dépasse la longueur maximale autorisée.',
            'order.integer' => 'Le champ « ordre » doit être un entier.',
            'is_owner.boolean' => 'Le champ « propriétaire » doit être vrai ou faux.',
            'created_at.date_format' => 'Le champ « date de création » doit respecter le format requis.',
            'created_at.before' => 'Le champ « date de création » doit être une date antérieure.',
            'updated_at.date_format' => 'Le champ « date de modification » doit respecter le format requis.',
            'updated_at.before' => 'Le champ « date de modification » doit être une date antérieure.',
        ];
    }

    /**
     * Récupère les règles de validation qui s'appliquent à la requête.
     */
    public function rules(): array
    {
        return [
            'id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'order' => 'nullable|integer',
            'is_owner' => 'nullable|boolean',
            'created_at' => 'nullable|before:now|date',
            'updated_at' => 'nullable|before:now|date',
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
