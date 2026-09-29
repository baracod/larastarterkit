<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UserSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|max:255',
            'settings.*.value' => 'required',
            'settings.*.value_type' => 'nullable|in:string,boolean,integer,float,array,json',
            'settings.*.label' => 'nullable|string|max:255',
            'settings.*.description' => 'nullable|string|max:1000',
            'settings.*.input_type' => 'nullable|string|max:50',
            'settings.*.options' => 'nullable|array',
            'settings.*.default_value' => 'nullable',
            'settings.*.is_public' => 'nullable|boolean',
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
