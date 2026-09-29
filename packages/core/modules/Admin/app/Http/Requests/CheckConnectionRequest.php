<?php

namespace Modules\Admin\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckConnectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('administrator') === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service' => ['required', Rule::in(['database', 'storage', 'mail', 'push', 'horizon', 'scheduler'])],
            'disk' => ['required_if:service,storage', 'nullable', 'string', Rule::in(array_keys(config('filesystems.disks', [])))],
        ];
    }
}
