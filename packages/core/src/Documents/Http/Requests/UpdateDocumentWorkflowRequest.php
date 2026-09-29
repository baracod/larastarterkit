<?php

namespace Baracod\Larastarterkit\Core\Documents\Http\Requests;

use Baracod\Larastarterkit\Core\Documents\Services\DocumentWorkflowService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('administrator') ?? false;
    }

    public function rules(): array
    {
        $definitions = app(DocumentWorkflowService::class)->definitions();
        $rules = ['steps' => ['present', 'array:'.implode(',', array_keys($definitions))]];
        foreach ($definitions as $step => $documents) {
            $rules['steps.'.$step] = ['sometimes', 'array:mode,documents'];
            $rules['steps.'.$step.'.mode'] = ['present_with:steps.'.$step, 'nullable', Rule::in(DocumentWorkflowService::MODES)];
            $rules['steps.'.$step.'.documents'] = ['sometimes', 'array:'.implode(',', array_keys($documents))];
            foreach (array_keys($documents) as $key) {
                $rules['steps.'.$step.'.documents.'.$key] = ['sometimes', 'nullable', Rule::in(DocumentWorkflowService::MODES)];
            }
        }

        return $rules;
    }
}
