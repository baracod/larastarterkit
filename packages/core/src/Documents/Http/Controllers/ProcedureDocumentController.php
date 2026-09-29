<?php

namespace Baracod\Larastarterkit\Core\Documents\Http\Controllers;

use Baracod\Larastarterkit\Core\Documents\Http\Requests\ProcedureDocumentRequest;
use Baracod\Larastarterkit\Core\Documents\Models\ProcedureDocument;
use Baracod\Larastarterkit\Core\Documents\Services\ProcedureDocumentService;
use Baracod\Larastarterkit\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProcedureDocumentController extends Controller
{
    public function __construct(private readonly ProcedureDocumentService $service) {}

    public function index(string $type, int $record): JsonResponse
    {
        $operation = $this->service->resolve($type, $record, 'browse');
        $workflow = app(\Baracod\Larastarterkit\Core\Documents\Services\DocumentWorkflowService::class);
        $generator = app(\Baracod\Larastarterkit\Core\Documents\Services\DocumentGenerationService::class);
        $settings = $workflow->settings();
        $requirements = [];
        foreach ($this->service->requirements($type) as $key => $title) {
            $policy = $workflow->policy($type, $key, $settings);
            $requirements[] = $policy + $generator->definition($type, $key) + [
                'key' => $key, 'can_generate' => $policy['generation_enabled'], 'issuer' => config('app.name'),
            ];
        }
        $documents = ProcedureDocument::where('operation_type', $type)->where('operation_id', $record)->latest('id')->get();

        return response()->json(['requirements' => $requirements, 'documents' => $documents,
            'context' => $generator->context($type, $operation),
            'can_edit' => $this->service->canEdit($type, $operation)]);
    }

    /**
     * Modes effectifs (import, génération ou les deux) de chaque étape et de chaque
     * document, pour que l'interface n'offre que les actions autorisées.
     */
    public function policies(): JsonResponse
    {
        return response()->json(['steps' => app(\Baracod\Larastarterkit\Core\Documents\Services\DocumentWorkflowService::class)->policies()], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function preview(ProcedureDocumentRequest $request, string $type, int $record): \Illuminate\Http\Response|JsonResponse
    {
        $operation = $this->service->resolve($type, $record, 'edit');
        $generator = app(\Baracod\Larastarterkit\Core\Documents\Services\DocumentGenerationService::class);
        $metadata = $generator->prepare($type, $operation, $request->validated());

        if ($request->query('format') === 'json') {
            return response()->json($generator->preview($metadata['generation_snapshot']))->header('Cache-Control', 'private, no-store');
        }

        return response($generator->render($metadata['generation_snapshot']), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="preview.pdf"', 'Cache-Control' => 'private, no-store',
            'X-Document-Preview-Token' => $generator->previewToken($metadata['generation_snapshot']),
        ]);
    }

    public function store(ProcedureDocumentRequest $request, string $type, int $record): JsonResponse
    {
        $operation = $this->service->resolve($type, $record, 'edit');
        $data = $request->validated();
        $generating = $request->routeIs('procedure-documents.generate');
        $path = null;
        try {
            $document = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $type, $record, $operation, $data, $generating, &$path) {
                $operation = $operation->newQuery()->lockForUpdate()->findOrFail($record);
                app(\Baracod\Larastarterkit\Core\Documents\Services\DocumentWorkflowService::class)->assertAllowed($type, $data['document_key'], $generating);
                $previous = ProcedureDocument::where('operation_type', $type)->where('operation_id', $record)
                    ->where('document_key', $data['document_key'])->latest('id')->first();
                if (array_key_exists('supersedes_id', $data) && (int) ($data['supersedes_id'] ?? 0) !== (int) ($previous?->id ?? 0)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['supersedes_id' => 'La version a changé ; rechargez le dossier.']);
                }
                $metadata = [];
                if ($generating) {
                    $generator = app(\Baracod\Larastarterkit\Core\Documents\Services\DocumentGenerationService::class);
                    $metadata = $generator->prepare($type, $operation, $data);
                    $metadata = $generator->confirmPreview($metadata, $data['preview_token'] ?? null);
                    $contents = $generator->render($metadata['generation_snapshot']);
                    $path = 'procedure-documents/'.Str::uuid().'.pdf';
                    abort_unless(Storage::disk('local')->put($path, $contents), 500);
                    $name = $data['document_key'].'-'.$record.'.pdf';
                } else {
                    $file = $request->file('file');
                    $path = $file->store('procedure-documents', 'local');
                    abort_unless($path, 500);
                    $name = $file->getClientOriginalName();
                }

                return ProcedureDocument::create($metadata + [
                    'operation_type' => $type, 'operation_id' => $record, 'document_key' => $data['document_key'],
                    'reference' => $data['reference'] ?? '', 'issuer' => $data['issuer'] ?? config('app.name'),
                    'issued_at' => $data['issued_at'], 'source' => $generating ? 'generated' : 'uploaded',
                    'path' => $path, 'original_name' => $name,
                    'supersedes_id' => $previous?->id, 'version' => ($previous?->version ?? 0) + 1,
                    'sha256' => hash_file('sha256', Storage::disk('local')->path($path)), 'created_by' => $request->user()->id,
                ]);
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return response()->json($document, 201);
    }

    public function download(string $type, int $record, int $document): BinaryFileResponse
    {
        $this->service->resolve($type, $record, 'browse');
        $file = ProcedureDocument::where('operation_type', $type)->where('operation_id', $record)->findOrFail($document);
        abort_unless(Storage::disk('local')->exists($file->path), 404);
        abort_unless(hash_equals($file->sha256, hash_file('sha256', Storage::disk('local')->path($file->path))), 409, 'Le fichier a été altéré.');

        return response()->download(Storage::disk('local')->path($file->path), $file->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
