<?php

namespace Baracod\Larastarterkit\Core\Documents\Services;

use Modules\Admin\Models\Setting;

class DocumentWorkflowService
{
    public const SETTING_KEY = 'document_workflow';

    public const MODES = ['upload', 'generate', 'both'];

    public function definitions(): array
    {
        $steps = [];
        foreach (app(DocumentRegistry::class)->all() as $type => $context) {
            $steps[$type] = array_map(fn (array $definition): string => $definition['title'], $context->documents());
        }

        return $steps;
    }

    public function settings(): array
    {
        $stored = Setting::getValue(self::SETTING_KEY, default: []);

        return is_array($stored) ? $stored : [];
    }

    /**
     * Mode configuré pour une étape, avant application des exceptions par document.
     */
    public function stepMode(string $step, ?array $settings = null): string
    {
        $settings ??= $this->settings();
        $defaults = config('documents.steps.'.$step, []);

        return $this->normalizeMode($settings[$step]['mode'] ?? $defaults['mode'] ?? 'upload');
    }

    public function policy(string $step, string $key, ?array $settings = null): array
    {
        $settings ??= $this->settings();
        $defaults = config('documents.steps.'.$step, []);
        $mode = $this->normalizeMode(
            $settings[$step]['documents'][$key] ?? $settings[$step]['mode']
            ?? $defaults['documents'][$key] ?? $defaults['mode'] ?? 'upload'
        );

        return $this->policyFor($mode);
    }

    /**
     * Politique effective de chaque étape et de chaque document, telle qu'appliquée
     * aux créations, aux aperçus et aux anciennes routes d'impression.
     *
     * @return array<string, array{mode: string, can_upload: bool, can_generate: bool, documents: array<string, array{mode: string, can_upload: bool, can_generate: bool}>}>
     */
    public function policies(): array
    {
        $settings = $this->settings();
        $steps = [];

        foreach ($this->definitions() as $step => $documents) {
            $rules = [];

            foreach (array_keys($documents) as $key) {
                $policy = $this->policy($step, (string) $key, $settings);
                $rules[(string) $key] = ['mode' => $policy['mode'], 'can_upload' => $policy['can_upload'], 'can_generate' => $policy['generation_enabled']];
            }

            $stepPolicy = $this->policyFor($this->stepMode($step, $settings));
            $steps[$step] = ['mode' => $stepPolicy['mode'], 'can_upload' => $stepPolicy['can_upload'], 'can_generate' => $stepPolicy['generation_enabled'], 'documents' => $rules];
        }

        return $steps;
    }

    /**
     * @return array{mode: string, can_upload: bool, generation_enabled: bool}
     */
    private function policyFor(string $mode): array
    {
        return ['mode' => $mode, 'can_upload' => in_array($mode, ['upload', 'both'], true), 'generation_enabled' => in_array($mode, ['generate', 'both'], true)];
    }

    private function normalizeMode(mixed $mode): string
    {
        return is_string($mode) && in_array($mode, self::MODES, true) ? $mode : 'upload';
    }

    public function assertAllowed(string $step, string $key, bool $generate): void
    {
        abort_unless(isset($this->definitions()[$step][$key]), 422, 'Type de document inconnu.');
        $policy = $this->policy($step, $key);
        abort_unless($policy[$generate ? 'generation_enabled' : 'can_upload'], 403,
            $generate ? 'La génération est désactivée dans la configuration de cette étape.' : 'L’import est désactivé dans la configuration de cette étape.');
    }

    public function configuration(): array
    {
        $stored = $this->settings();
        $steps = [];
        foreach ($this->definitions() as $step => $documents) {
            $default = config('documents.steps.'.$step.'.mode', 'upload');
            $rules = [];
            foreach ($documents as $key => $title) {
                $rules[] = ['key' => $key, 'title' => $title, 'override' => $stored[$step]['documents'][$key] ?? null,
                    'default_mode' => $this->policy($step, $key, [])['mode'],
                ] + $this->policy($step, $key, $stored);
            }
            $steps[] = ['key' => $step, 'mode' => $stored[$step]['mode'] ?? $default,
                'override' => $stored[$step]['mode'] ?? null, 'default_mode' => $default, 'documents' => $rules];
        }

        return ['steps' => $steps];
    }
}
