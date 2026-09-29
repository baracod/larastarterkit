<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserNotificationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|in:all,read,unread',
            'sort' => 'nullable|in:newest,oldest',
            'per_page' => 'nullable|integer|min:5|max:100',
            'page' => 'nullable|integer|min:1',
        ];
    }
}
