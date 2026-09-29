<?php

namespace Modules\Admin\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AdminSettingRequest extends FormRequest
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
            'type.required' => 'Le champ type est obligatoire.',
            'module.string' => 'Le champ module doit être une chaîne de caractères.',
            'user_id.integer' => 'Le champ user_id doit être un entier.',
            'key.string' => 'Le champ key doit être une chaîne de caractères.',
            'value.string' => 'Le champ value doit être une chaîne de caractères.',
            'value_type.required' => 'Le champ value_type est obligatoire.',
            'value_type.string' => 'Le champ value_type doit être une chaîne de caractères.',
            'label.string' => 'Le champ label doit être une chaîne de caractères.',
            'description.string' => 'Le champ description doit être une chaîne de caractères.',
            'input_type.required' => 'Le champ input_type est obligatoire.',
            'input_type.string' => 'Le champ input_type doit être une chaîne de caractères.',
            'options.string' => 'Le champ options doit être une chaîne de caractères.',
            'default_value.string' => 'Le champ default_value doit être une chaîne de caractères.',
            'is_public.required' => 'Le champ is_public est obligatoire.',
            'is_public.boolean' => 'Le champ is_public doit être vrai ou faux.',
        ];
    }

    /**
     * Récupère les règles de validation qui s'appliquent à la requête.
     */
    public function rules(): array
    {
        return [
            'type' => 'required|string|in:system,module,user',
            'module' => 'nullable|string',
            'user_id' => 'nullable|integer',
            'key' => 'nullable|string',
            'value' => 'nullable|string',
            'value_type' => 'required|string',
            'label' => 'nullable|string',
            'description' => 'nullable|string',
            'input_type' => 'required|string',
            'options' => 'nullable',
            'default_value' => 'nullable|string',
            'is_public' => 'required|boolean',
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
