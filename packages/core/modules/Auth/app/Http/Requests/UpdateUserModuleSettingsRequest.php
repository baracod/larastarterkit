<?php

namespace Modules\Auth\Http\Requests;

use Baracod\Larastarterkit\Core\Support\ModuleRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserModuleSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('administrator');
    }

    public function rules(): array
    {
        return [
            'settings' => ['present', 'array', 'max:100'],
            'settings.*' => ['array:module,key,value'],
            'settings.*.module' => ['required', Rule::in(array_keys(app(ModuleRegistry::class)->statuses()))],
            'settings.*.key' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9_.-]+$/'],
            'settings.*.value' => ['present', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return ['settings.*.module.in' => __('validation.in'), 'settings.*.key.regex' => __('validation.regex')];
    }
}
