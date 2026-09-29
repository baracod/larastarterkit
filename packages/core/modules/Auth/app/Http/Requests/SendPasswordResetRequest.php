<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Models\User;

class SendPasswordResetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('forcePasswordReset', User::class);
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:auth_users,id',
            'email_locale' => 'sometimes|string|in:fr,en',
            'new_password' => 'prohibited',
            'new_password_confirmation' => 'prohibited',
        ];
    }
}
