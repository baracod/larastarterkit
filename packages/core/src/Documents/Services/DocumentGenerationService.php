<?php

namespace Baracod\Larastarterkit\Core\Documents\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;

class DocumentGenerationService
{
    public function definition(string $step, string $key): array
    {
        $spec = app(DocumentRegistry::class)->get($step)->documents()[$key] ?? null;
        abort_unless(is_array($spec), 422, 'Unknown document template.');
        $fields = [];
        foreach ($spec['fields'] as $name => [$type, $required]) {
            $fields[] = ['key' => $name, 'type' => $type, 'required' => $required];
        }

        return ['title' => $spec['title'], 'view' => $this->validateView($spec['view']),
            'template_version' => $spec['version'], 'fields' => $fields, 'signatories' => $spec['signatories']];
    }

    public function context(string $step, Model $record): array
    {
        return ['operation_type' => $step, 'operation_id' => $record->getKey()]
            + app(DocumentRegistry::class)->get($step)->context($record);
    }

    public function prepare(string $step, Model $record, array $data): array
    {
        app(DocumentWorkflowService::class)->assertAllowed($step, $data['document_key'], true);
        $definition = $this->definition($step, $data['document_key']);
        $rules = [];
        foreach ($definition['fields'] as $field) {
            $rules['fields.'.$field['key']] = [$field['required'] ? 'required' : 'nullable', ...match ($field['type']) {
                'number' => ['numeric', 'min:0', 'max:999999999999'],
                'integer' => ['integer', 'min:0', 'max:1000000000'],
                'date' => ['date_format:Y-m-d'],
                default => ['string', 'max:5000'],
            }];
        }
        $keys = array_column($definition['fields'], 'key');
        $rules['fields'] = ['array:'.implode(',', $keys)];
        $attributes = [];
        foreach ($keys as $key) {
            $attributes['fields.'.$key] = __('document_generation.fields.'.$key);
        }
        $validated = Validator::make(['fields' => $data['fields'] ?? []], $rules, [], $attributes)->validate();
        if (($data['issued_at'] ?? null) !== today()->toDateString()) {
            throw ValidationException::withMessages(['issued_at' => 'Un document généré porte la date du jour.']);
        }
        $snapshot = $this->context($step, $record) + [
            'document_key' => $data['document_key'], 'definition' => $definition,
            'fields' => $validated['fields'], 'issuer' => $data['issuer'] ?? config('app.name'), 'issued_at' => $data['issued_at'],
            'expires_at' => $data['expires_at'] ?? null, 'generated_by' => auth()->id(), 'generated_at' => now()->toIso8601String(),
            'signatures' => $this->signatures($record),
        ];

        return ['reference' => $snapshot['reference'], 'issuer' => $snapshot['issuer'], 'template_version' => $definition['template_version'], 'generation_snapshot' => $snapshot];
    }

    private function signatures(Model $record): array
    {
        $signatures = [];
        foreach ($record->getAttributes() as $key => $path) {
            if (! str_ends_with($key, '_signature_path') || ! is_string($path) || ! str_starts_with($path, 'signatures/') || str_contains($path, '..')) {
                continue;
            }
            $storage = Storage::disk('public');
            if ($storage->exists($path) && $storage->size($path) <= 1048576) {
                $contents = $storage->get($path);
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
                if (in_array($mime, ['image/png', 'image/jpeg'], true)) {
                    $signatures[] = ['role' => str_replace('_signature_path', '', $key), 'image' => 'data:'.$mime.';base64,'.base64_encode($contents)];
                }
            }
        }

        return $signatures;
    }

    public function preview(array $snapshot): array
    {
        $html = view($this->snapshotView($snapshot), ['snapshot' => $snapshot])->render();
        $html = str_replace('</head>', '<style>body { padding: 16px; } .pdf-footer { position: static; margin-top: 24px; } .watermark { display: none; }</style></head>', $html);

        return ['html' => $html, 'pdf' => base64_encode($this->render($snapshot)), 'preview_token' => $this->previewToken($snapshot)];
    }

    public function previewToken(array $snapshot): string
    {
        return Crypt::encryptString(json_encode([
            'fingerprint' => $this->previewFingerprint($snapshot),
            'generated_at' => $snapshot['generated_at'],
            'expires_at' => now()->addMinutes(30)->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    public function confirmPreview(array $metadata, ?string $token): array
    {
        try {
            $preview = json_decode(Crypt::decryptString($token ?? ''), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $exception) {
            $preview = null;
        }
        if (! is_array($preview) || ! is_string($preview['fingerprint'] ?? null)
            || ! is_string($preview['generated_at'] ?? null) || ! is_int($preview['expires_at'] ?? null)
            || $preview['expires_at'] <= now()->timestamp
            || ! hash_equals($preview['fingerprint'], $this->previewFingerprint($metadata['generation_snapshot']))) {
            throw ValidationException::withMessages(['preview_token' => __('document_generation.preview_changed')]);
        }
        $metadata['generation_snapshot']['generated_at'] = $preview['generated_at'];

        return $metadata;
    }

    private function previewFingerprint(array $snapshot): string
    {
        unset($snapshot['generated_at']);
        $paths = array_unique([
            View::getFinder()->find($this->snapshotView($snapshot)),
            ...glob(__DIR__.'/../../../views/documents/*.blade.php'),
            ...glob(resource_path('views/documents/*.blade.php')),
        ]);
        $templates = array_map(fn (string $path): string => hash_file('sha256', $path), $paths);

        return hash('sha256', json_encode([$this->canonicalData($snapshot), $templates], JSON_THROW_ON_ERROR));
    }

    private function canonicalData(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = $this->canonicalData($value);
            }
        }

        return $data;
    }

    public function render(array $snapshot): string
    {
        return Pdf::loadView($this->snapshotView($snapshot), ['snapshot' => $snapshot])
            ->setPaper('a4')->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false])
            ->output();
    }

    private function snapshotView(array $snapshot): string
    {
        return $this->validateView($snapshot['definition']['view'] ?? null);
    }

    private function validateView(mixed $view): string
    {
        $allowed = [];
        foreach (app(DocumentRegistry::class)->all() as $context) {
            foreach ($context->documents() as $definition) {
                $allowed[] = $definition['view'];
            }
        }
        abort_unless(is_string($view) && in_array($view, $allowed, true) && View::exists($view), 422, 'Invalid document template.');

        return $view;
    }
}
