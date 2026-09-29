<?php

namespace Tests\Feature;

use Baracod\Larastarterkit\Core\Documents\Contracts\DocumentContext;
use Baracod\Larastarterkit\Core\Documents\Models\ProcedureDocument;
use Baracod\Larastarterkit\Core\Documents\Services\DocumentRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\User;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->owner = User::factory()->create();
        Sanctum::actingAs($this->owner);
        app(DocumentRegistry::class)->register('example', new class implements DocumentContext
        {
            public function resolve(int $id): Model
            {
                return User::query()->findOrFail($id);
            }

            public function authorize(User $user, Model $record, string $action): bool
            {
                return $user->id === $record->id;
            }

            public function documents(): array
            {
                return ['summary' => ['title' => 'Summary', 'view' => 'documents::generic', 'version' => '1', 'fields' => ['note' => ['string', false]], 'signatories' => []]];
            }

            public function context(Model $record): array
            {
                return ['reference' => 'EX-'.$record->id, 'sections' => [['title' => 'Details', 'rows' => [['key' => 'Name', 'value' => $record->name]]]]];
            }
        });
        config(['documents.steps.example.mode' => 'both']);
    }

    private function endpoint(): string
    {
        return '/api/v1/documents/example/'.$this->owner->id;
    }

    private function metadata(): array
    {
        return ['document_key' => 'summary', 'reference' => 'EX-1', 'issuer' => 'Example', 'issued_at' => today()->toDateString()];
    }

    public function test_upload_download_and_version_history(): void
    {
        $payload = $this->metadata() + ['file' => UploadedFile::fake()->create('summary.pdf', 10, 'application/pdf')];
        $first = $this->postJson($this->endpoint(), $payload)->assertCreated()->assertJsonPath('version', 1)->json('id');
        $second = $this->postJson($this->endpoint(), $payload + ['supersedes_id' => $first])->assertCreated()->assertJsonPath('version', 2)->json('id');
        $this->get($this->endpoint().'/'.$first)->assertOk();
        $this->assertSame(2, ProcedureDocument::query()->count());
        $this->postJson($this->endpoint(), $payload + ['supersedes_id' => $first])->assertUnprocessable();
        $this->getJson($this->endpoint())->assertOk()->assertJsonCount(2, 'documents');
        Storage::disk('local')->put(ProcedureDocument::findOrFail($second)->path, 'altered');
        $this->getJson($this->endpoint().'/'.$second)->assertStatus(409);
    }

    public function test_preview_confirm_generate_and_changed_context_rejection(): void
    {
        $payload = $this->metadata() + ['fields' => ['note' => 'Generic document']];
        $preview = $this->postJson($this->endpoint().'/preview?format=json', $payload)->assertOk();
        $this->assertStringStartsWith('%PDF', base64_decode($preview->json('pdf')));
        $token = $preview->json('preview_token');
        $this->postJson($this->endpoint().'/generate', $payload + ['preview_token' => $token])->assertCreated()->assertJsonPath('source', 'generated');
        $this->owner->update(['name' => 'Changed']);
        $this->postJson($this->endpoint().'/generate', $payload + ['preview_token' => $token])->assertUnprocessable();
        $this->postJson($this->endpoint().'/generate', $payload)->assertUnprocessable();
    }

    public function test_unauthorized_unknown_invalid_and_disabled_operations(): void
    {
        $this->getJson('/api/v1/documents/unknown/1')->assertNotFound();
        $this->postJson($this->endpoint(), $this->metadata() + ['file' => UploadedFile::fake()->create('script.php', 1, 'text/plain')])->assertUnprocessable();
        config(['documents.steps.example.mode' => 'upload']);
        $this->postJson($this->endpoint().'/preview', $this->metadata())->assertForbidden();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson($this->endpoint())->assertForbidden();
        $this->getJson('/api/v1/documents/settings')->assertForbidden();
    }
}
