<?php

namespace Baracod\Larastarterkit\Core\Documents\Http\Requests;

use Baracod\Larastarterkit\Core\Documents\Services\ProcedureDocumentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcedureDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $generating = $this->routeIs('procedure-documents.generate', 'procedure-documents.preview');

        return [
            'supersedes_id' => ['nullable', 'integer'],
            'fields' => [$generating ? 'sometimes' : 'prohibited', 'array'],
            'preview_token' => [$generating ? 'sometimes' : 'prohibited', 'nullable', 'string', 'max:8192'],
            'document_key' => ['required', Rule::in(array_keys(app(ProcedureDocumentService::class)->requirements((string) $this->route('type'))))],
            'reference' => [$generating ? 'nullable' : 'required', 'string', 'max:255'],
            'issuer' => [$generating ? 'nullable' : 'required', 'string', 'max:255'],
            'issued_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'file' => [$generating ? 'prohibited' : 'required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
        ];
    }
}
