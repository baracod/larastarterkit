<?php

namespace Modules\Admin\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Le champ « type » doit etre system, module ou user.',
            'key.required' => 'Le champ « clé » est obligatoire.',
            'key.string' => 'Le champ « clé » doit etre une chaine de caracteres.',
            'module.required_if' => 'Le champ « module » est obligatoire pour un setting de type module.',
            'user_id.required_if' => 'Le champ « utilisateur » est obligatoire pour un setting de type user.',
            'user_id.exists' => 'Le champ « utilisateur » doit correspondre a un utilisateur existant.',
            'value_type.in' => 'Le champ « type de valeur » est invalide.',
        ];
    }

    public function rules(): array
    {
        return [
            'type' => 'sometimes|in:system,module,user',
            'key' => 'sometimes|required|string|max:255',
            'value' => 'sometimes|required',
            'module' => 'sometimes|nullable|string|max:100|required_if:type,module',
            'user_id' => 'sometimes|nullable|integer|exists:auth_users,id|required_if:type,user',
            'value_type' => 'sometimes|nullable|in:string,boolean,integer,float,array,json',
            'label' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'input_type' => 'sometimes|nullable|string|max:50',
            'options' => 'sometimes|nullable|array',
            'default_value' => 'sometimes|nullable',
            'is_public' => 'sometimes|nullable|boolean',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = [];

        foreach ($validator->errors()->getMessages() as $field => $messages) {
            $first = $messages[0] ?? 'Invalid.';
            $ruleKey = null;

            foreach ($validator->failed()[$field] ?? [] as $rule => $params) {
                $ruleKey = strtolower($rule);
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
