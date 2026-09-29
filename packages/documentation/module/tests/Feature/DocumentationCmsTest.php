<?php

namespace Modules\Documentation\Tests\Feature;

use Baracod\Larastarterkit\Core\Support\ModuleRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Documentation\Jobs\BuildDocumentation;
use Modules\Documentation\Models\Collection;
use Modules\Documentation\Models\Edition;
use Modules\Documentation\Models\Page;
use Modules\Documentation\Models\Publication;
use Modules\Documentation\Models\Revision;
use Modules\Documentation\Services\EditorService;
use Modules\Documentation\Services\MarkdownService;
use Modules\Documentation\Services\PortalBuilder;
use Modules\Documentation\Services\PublicationService;
use Tests\TestCase;

class DocumentationCmsTest extends TestCase
{
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = sys_get_temp_dir().'/documentation-test-'.bin2hex(random_bytes(6));
        config(['documentation.storage' => $this->storage, 'documentation.public_link' => $this->storage.'/public/docs']);
        Queue::fake();
    }

    protected function tearDown(): void
    {
        // Do not traverse the node_modules symlink when deleting build fixtures.
        foreach (glob($this->storage.'/builds/*/node_modules') ?: [] as $link) {
            unlink($link);
        }
        if (is_link($this->storage.'/public/docs')) {
            unlink($this->storage.'/public/docs');
        }
        if (is_link($this->storage.'/site/current')) {
            unlink($this->storage.'/site/current');
        }
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    private function edition(): Edition
    {
        $collection = Collection::create(['title' => 'Guide', 'slug' => 'guide-'.bin2hex(random_bytes(3))]);

        return $collection->editions()->create(['title' => 'Version 1', 'slug' => 'v1']);
    }

    private function page(Edition $edition, string $markdown = 'Initial'): Page
    {
        return app(EditorService::class)->save($edition, null, ['title' => 'Getting started', 'path' => 'start', 'markdown' => $markdown, 'position' => 0], null);
    }

    private function body(Page $page, array $overrides = []): array
    {
        return [...$page->revision->only(['title', 'path', 'markdown', 'parent_id', 'position']), 'expected_revision_id' => $page->current_revision_id, ...$overrides];
    }

    private function builder(bool $fail = false, bool $activationFail = false): PortalBuilder
    {
        return new class($fail, $activationFail) extends PortalBuilder
        {
            public function __construct(private bool $fail, private bool $activationFail) {}

            protected function recordSuccess(Publication $publication, array $portal): void
            {
                if ($this->activationFail) {
                    throw new \RuntimeException('Simulated status recording failure');
                }
                parent::recordSuccess($publication, $portal);
            }

            protected function compile(string $workspace, Publication $publication): void
            {
                if ($this->fail) {
                    throw new \RuntimeException('Simulated build failure');
                }
                File::ensureDirectoryExists($workspace.'/.vitepress/dist');
                File::put($workspace.'/.vitepress/dist/index.html', 'release '.$publication->id);
                foreach (File::allFiles($workspace) as $file) {
                    if ($file->getExtension() === 'md') {
                        $target = $workspace.'/.vitepress/dist/'.substr($file->getRelativePathname(), 0, -3).'.html';
                        File::ensureDirectoryExists(dirname($target));
                        if ($file->getRelativePathname() !== 'index.md') {
                            File::put($target, 'page');
                        }
                    }
                }
            }
        };
    }

    public function test_cms_and_images_are_private_and_permissions_are_separate(): void
    {
        $this->getJson('/api/v1/documentation/collections')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/documentation/collections')->assertForbidden();
        $role = Role::create(['name' => 'docs-reader', 'display_name' => 'Reader']);
        $role->permissions()->attach(Permission::where('key', 'browse_documentation')->value('id'));
        $user = User::factory()->create();
        $user->roles()->attach($role);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/documentation/collections')->assertOk();
        $this->postJson('/api/v1/documentation/collections', [])->assertForbidden();
        $edition = $this->edition();
        $this->postJson('/api/v1/documentation/editions/'.$edition->id.'/publish')->assertForbidden();
        $this->postJson('/api/v1/documentation/collections/'.$edition->collection_id.'/images')->assertForbidden();
        $this->actingAsAdministrator();
        $this->getJson('/api/v1/documentation/collections')->assertOk();
    }

    public function test_collection_edition_and_page_creation_and_duplication(): void
    {
        $this->actingAsAdministrator();
        $collection = $this->postJson('/api/v1/documentation/collections', ['title' => 'API Guide', 'slug' => 'api-guide'])->assertSuccessful()->json();
        $edition = $this->postJson('/api/v1/documentation/collections/'.$collection['id'].'/editions', ['title' => 'v1', 'slug' => 'v1'])->assertSuccessful()->json();
        $page = $this->postJson('/api/v1/documentation/editions/'.$edition['id'].'/pages', ['title' => 'Start', 'path' => 'start', 'markdown' => 'Hello', 'position' => 0])->assertSuccessful()->json();
        $child = app(EditorService::class)->save(Edition::find($edition['id']), null, ['title' => 'Child', 'path' => 'child', 'markdown' => 'Child', 'parent_id' => $page['id']], null);
        $copy = $this->postJson('/api/v1/documentation/collections/'.$collection['id'].'/editions', ['title' => 'v2', 'slug' => 'v2', 'source_id' => $edition['id']])->assertSuccessful()->json();
        $copies = Page::where('edition_id', $copy['id'])->with('revision')->get();
        $this->assertCount(2, $copies);
        $copiedParent = $copies->first(fn ($item) => $item->revision->path === 'start');
        $copiedChild = $copies->first(fn ($item) => $item->revision->path === 'child');
        $this->assertSame($copiedParent->id, $copiedChild->revision->parent_id);
        $this->assertNotSame($child->current_revision_id, $copiedChild->current_revision_id);
    }

    public function test_edits_and_restores_create_revisions_and_stale_saves_fail(): void
    {
        $this->actingAsAdministrator();
        $edition = $this->edition();
        $page = $this->page($edition);
        $old = $page->current_revision_id;
        $url = '/api/v1/documentation/editions/'.$edition->id.'/pages/'.$page->id;
        $this->putJson($url, $this->body($page, ['markdown' => 'Updated']))->assertOk();
        $this->putJson($url, $this->body($page, ['markdown' => 'Stale']))->assertConflict();
        $page->refresh();
        $this->postJson('/api/v1/documentation/pages/'.$page->id.'/revisions/'.$old.'/restore', ['expected_revision_id' => $page->current_revision_id])->assertOk()->assertJsonPath('revision.markdown', 'Initial');
        $this->assertSame(3, $page->revisions()->count());
        $this->assertNotSame($old, $page->fresh()->current_revision_id);
        $this->assertSame('Initial', Revision::find($old)->markdown);
    }

    public function test_cross_edition_pages_parents_revisions_and_paths_are_rejected(): void
    {
        $this->actingAsAdministrator();
        $edition = $this->edition();
        $page = $this->page($edition);
        $other = $this->page($this->edition());
        $url = '/api/v1/documentation/editions/'.$edition->id.'/pages/'.$page->id;
        $this->putJson('/api/v1/documentation/editions/'.$edition->id.'/pages/'.$other->id, $this->body($other))->assertNotFound();
        $this->putJson($url, $this->body($page, ['parent_id' => $other->id]))->assertUnprocessable();
        $this->putJson($url, $this->body($page, ['parent_id' => $page->id]))->assertUnprocessable();
        $this->putJson($url, $this->body($page, ['path' => '../escape']))->assertUnprocessable();
        $this->putJson($url, $this->body($page, ['path' => 'index']))->assertUnprocessable();
        $this->postJson('/api/v1/documentation/pages/'.$page->id.'/revisions/'.$other->current_revision_id.'/restore', ['expected_revision_id' => $page->current_revision_id])->assertNotFound();
        $this->postJson('/api/v1/documentation/editions/'.$edition->id.'/pages', $this->body($page))->assertUnprocessable();
    }

    public function test_archive_keeps_history_and_omits_page_from_snapshot(): void
    {
        $this->actingAsAdministrator();
        $edition = $this->edition();
        $page = $this->page($edition);
        app(EditorService::class)->save($edition, null, ['title' => 'Public', 'path' => 'public', 'markdown' => 'Public'], null);
        $this->patchJson('/api/v1/documentation/pages/'.$page->id.'/archive', ['archived' => true])->assertOk();
        $publication = app(PublicationService::class)->request($edition, null)->fresh();
        $this->assertCount(1, $publication->snapshot['pages']);
        $this->assertSame('public', $publication->snapshot['pages'][0]['path']);
        $this->assertSame(1, $page->revisions()->count());
        $this->patchJson('/api/v1/documentation/pages/'.$page->id.'/archive', ['archived' => false])->assertOk();
        $this->assertSame(2, $page->revisions()->count());
    }

    public function test_snapshot_is_frozen_and_urls_are_reserved(): void
    {
        $this->actingAsAdministrator();
        $edition = $this->edition();
        $page = $this->page($edition);
        $publication = app(PublicationService::class)->request($edition, null)->fresh();
        app(EditorService::class)->save($edition, $page, $this->body($page, ['markdown' => 'Later draft']), null);
        $this->assertSame('Initial', $publication->snapshot['pages'][0]['markdown']);
        Queue::assertPushed(BuildDocumentation::class, fn ($job) => $job->queue === 'documentation');
        $this->putJson('/api/v1/documentation/collections/'.$edition->collection_id, ['title' => 'Renamed', 'slug' => 'different'])->assertConflict();
        $this->putJson('/api/v1/documentation/collections/'.$edition->collection_id.'/editions/'.$edition->id, ['title' => 'v1', 'slug' => 'different'])->assertConflict();
    }

    public function test_private_images_validate_type_scope_and_only_referenced_files_are_exported(): void
    {
        $this->actingAsAdministrator();
        $edition = $this->edition();
        $url = '/api/v1/documentation/collections/'.$edition->collection_id.'/images';
        $image = $this->post($url, ['image' => UploadedFile::fake()->image('screen.png', 20, 20)], ['Accept' => 'application/json'])->assertSuccessful()->json();
        $this->post($url, ['image' => UploadedFile::fake()->create('code.svg', 1, 'image/svg+xml')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post($url, ['image' => UploadedFile::fake()->image('unused.png')], ['Accept' => 'application/json'])->assertSuccessful();
        $this->page($edition, '![Screen](doc-image:'.$image['id'].')');
        $publication = app(PublicationService::class)->request($edition, null)->fresh();
        $this->assertCount(1, $publication->snapshot['images']);
        $other = $this->edition();
        $this->postJson('/api/v1/documentation/collections/'.$other->collection_id.'/preview', ['markdown' => '![Bad](doc-image:'.$image['id'].')'])->assertUnprocessable();
        $this->get('/api/v1/documentation/images/'.$image['id'].'/file')->assertOk()->assertHeader('Content-Type', 'image/png');
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/documentation/images/'.$image['id'].'/file')->assertForbidden();
    }

    public function test_markdown_is_inert_and_export_contains_no_editorial_vue_code(): void
    {
        $renderer = app(MarkdownService::class);
        $html = $renderer->render('<script>alert(1)</script>'."\n\n".'[bad](javascript:alert(1))'."\n\n{{ window.alert(1) }}\n\n```js\n<script>example</script>\n```", []);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringContainsString('&lt;script&gt;example&lt;/script&gt;', $html);
        $edition = $this->edition();
        $this->page($edition, '{{ window.alert(1) }}');
        $publication = app(PublicationService::class)->request($edition, null);
        $this->builder()->build($publication);
        $file = $this->storage.'/builds/'.$publication->id.'/'.$edition->collection->slug.'/v1/start.md';
        $this->assertStringContainsString('<div v-pre>', File::get($file));
    }

    public function test_build_failure_preserves_active_site_and_rollback_preserves_drafts(): void
    {
        $edition = $this->edition();
        $page = $this->page($edition);
        $service = app(PublicationService::class);
        $first = $service->request($edition, null);
        $this->builder()->build($first);
        $link = $this->storage.'/site/current';
        $this->assertSame('release '.$first->id, File::get($link.'/index.html'));
        $failed = $service->request($edition, null);
        try {
            $this->builder(true)->build($failed);
        } catch (\RuntimeException) {
        }
        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertSame('release '.$first->id, File::get($link.'/index.html'));
        app(EditorService::class)->save($edition, $page, $this->body($page, ['markdown' => 'Second']), null);
        $second = $service->request($edition, null);
        $this->builder()->build($second);
        $restore = $service->restore($first->fresh(), null);
        $this->builder()->build($restore);
        $this->assertSame('Initial', $restore->fresh()->portal[$edition->id]['pages'][0]['markdown']);
        $this->assertSame('Second', $page->fresh('revision')->revision->markdown);
    }

    public function test_publication_keeps_other_editions_and_serializes_requests(): void
    {
        $a = $this->edition();
        $b = $this->edition();
        $this->page($a);
        $this->page($b);
        $service = app(PublicationService::class);
        $first = $service->request($a, null);
        $second = $service->request($b, null);
        (new BuildDocumentation($second->id))->handle($this->builder());
        $this->assertSame('queued', $second->fresh()->status);
        (new BuildDocumentation($first->id))->handle($this->builder());
        (new BuildDocumentation($second->id))->handle($this->builder());
        $this->assertCount(2, $second->fresh()->portal);
        $root = $this->storage.'/site';
        $lock = fopen($root.'/build.lock', 'c');
        flock($lock, LOCK_EX);
        $third = $service->request($a, null);
        $this->assertFalse($this->builder()->build($third));
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    public function test_disabled_module_blocks_cms_and_builds_without_touching_public_site(): void
    {
        $this->actingAsAdministrator();
        $edition = $this->edition();
        $this->page($edition);
        $publication = app(PublicationService::class)->request($edition, null);
        $this->builder()->build($publication);
        $queued = app(PublicationService::class)->request($edition, null);
        $this->mock(ModuleRegistry::class, fn ($mock) => $mock->shouldReceive('enabled')->with('Documentation')->andReturnFalse());
        $this->getJson('/api/v1/documentation/collections')->assertNotFound();
        (new BuildDocumentation($queued->id))->handle($this->builder());
        $this->assertSame('failed', $queued->fresh()->status);
        $this->assertSame('release '.$publication->id, File::get($this->storage.'/site/current/index.html'));
    }

    public function test_import_is_idempotent_and_does_not_overwrite_edits_or_archives(): void
    {
        $this->artisan('documentation:import', ['--no-interaction' => true])->assertSuccessful();
        $count = Page::count();
        $this->assertGreaterThan(0, $count);
        $page = Page::with('revision')->first();
        app(EditorService::class)->save($page->edition, $page, $this->body($page, ['markdown' => 'Editorial content', 'path' => 'changed-path']), null);
        $page->update(['archived_at' => now()]);
        $this->artisan('documentation:import', ['--no-interaction' => true])->assertSuccessful();
        $this->assertSame($count, Page::count());
        $this->assertSame('Editorial content', $page->fresh('revision')->revision->markdown);
        $this->assertSame(0, Publication::count());
    }

    public function test_site_settings_require_edit_permission_validate_links_and_reject_stale_updates(): void
    {
        $this->getJson('/api/v1/documentation/site-settings')->assertUnauthorized();
        $role = Role::create(['name' => 'site-reader', 'display_name' => 'Reader']);
        $role->permissions()->attach(Permission::where('key', 'browse_documentation')->value('id'));
        $user = User::factory()->create();
        $user->roles()->attach($role);
        Sanctum::actingAs($user);
        $settings = $this->getJson('/api/v1/documentation/site-settings')->assertOk()->json();
        $this->putJson('/api/v1/documentation/site-settings', $settings)->assertForbidden();
        $this->actingAsAdministrator();
        foreach (['javascript:alert(1)', '//evil.example', '/\\evil.example', 'data:text/html,test'] as $link) {
            $invalid = $settings;
            $invalid['content']['actions'] = [['text' => 'Unsafe', 'link' => $link, 'theme' => 'brand']];
            $this->putJson('/api/v1/documentation/site-settings', $invalid)->assertUnprocessable();
        }
        $settings['content']['hero_text'] = 'Nouvel accueil';
        $this->putJson('/api/v1/documentation/site-settings', $settings)->assertOk()->assertJsonPath('version', 2);
        $this->putJson('/api/v1/documentation/site-settings', $settings)->assertConflict();
        $this->assertSame('Nouvel accueil', \Modules\Documentation\Models\SiteSetting::current()->content['hero_text']);
    }

    public function test_home_settings_and_selected_images_are_snapshotted_and_restored_with_the_portal(): void
    {
        $this->actingAsAdministrator();
        $edition = $this->edition();
        $this->page($edition);
        $image = $this->postJson('/api/v1/documentation/collections/'.$edition->collection_id.'/images', ['image' => UploadedFile::fake()->image('hero.png')])->assertSuccessful()->json();
        $settings = \Modules\Documentation\Models\SiteSetting::current();
        $first = [...$settings->content, 'hero_text' => '<script>alert(1)</script>', 'hero_image_id' => $image['id']];
        $settings->update(['content' => $first]);
        $publication = app(PublicationService::class)->request($edition, null);
        $settings->update(['content' => [...$first, 'hero_text' => 'Later draft']]);
        $this->builder()->build($publication);
        $this->assertSame('<script>alert(1)</script>', $publication->fresh()->portal[$edition->id]['site']['hero_text']);
        $source = File::get($this->storage.'/builds/'.$publication->id.'/index.md');
        $this->assertStringContainsString('&lt;script&gt;', $source);
        $this->assertStringNotContainsString('Later draft', $source);
        $manifest = json_decode(File::get($this->storage.'/builds/'.$publication->id.'/.vitepress/portal.json'), true);
        $this->assertFileExists($this->storage.'/builds/'.$publication->id.'/public'.$manifest['site']['hero_image']);
        $second = app(PublicationService::class)->request($edition, null);
        $this->builder()->build($second);
        $restore = app(PublicationService::class)->restore($publication->fresh(), null);
        $this->builder()->build($restore);
        $this->assertSame($first['hero_text'], $restore->fresh()->portal[$edition->id]['site']['hero_text']);
        $this->assertSame('Later draft', $settings->fresh()->content['hero_text']);
    }

    public function test_withdrawal_keeps_global_home_and_images_and_can_be_restored_without_editions(): void
    {
        $this->actingAsAdministrator();
        $edition = $this->edition();
        $this->page($edition);
        $image = $this->postJson('/api/v1/documentation/collections/'.$edition->collection_id.'/images', ['image' => UploadedFile::fake()->image('brand.png')])->assertSuccessful()->json();
        $settings = \Modules\Documentation\Models\SiteSetting::current();
        $content = [...$settings->content, 'hero_name' => 'Original brand', 'hero_image_id' => $image['id'], 'logo_image_id' => $image['id']];
        $settings->update(['content' => $content]);
        $service = app(PublicationService::class);
        $first = $service->request($edition, null);
        $this->builder()->build($first);

        $edition->update(['archived_at' => now()]);
        $settings->update(['content' => [...$content, 'hero_name' => 'Withdrawal brand']]);
        $withdrawal = $service->request($edition, null);
        $this->assertArrayNotHasKey('site_snapshot', $withdrawal->toArray());
        $settings->update(['content' => [...$content, 'hero_name' => 'Unpublished changes']]);
        $this->builder()->build($withdrawal);
        $this->assertSame([], $withdrawal->fresh()->portal);

        $restoreFirst = $service->restore($first->fresh(), null);
        $this->builder()->build($restoreFirst);
        $restoreEmpty = $service->restore($withdrawal->fresh(), null);
        $this->builder()->build($restoreEmpty);
        $restoreAgain = $service->restore($restoreEmpty->fresh(), null);
        $this->builder()->build($restoreAgain);
        foreach ([$withdrawal, $restoreEmpty, $restoreAgain] as $publication) {
            $workspace = $this->storage.'/builds/'.$publication->id;
            $manifest = json_decode(File::get($workspace.'/.vitepress/portal.json'), true);
            $this->assertSame([], $manifest['editions']);
            $this->assertSame('Withdrawal brand', $manifest['site']['hero_name']);
            $this->assertFileExists($workspace.'/public'.$manifest['site']['hero_image']);
            $this->assertSame($manifest['site']['hero_image'], $manifest['site']['logo_image']);
            $this->assertSame('Withdrawal brand', $publication->fresh()->siteSnapshot()['site']['hero_name']);
        }
        $this->assertSame('Unpublished changes', $settings->fresh()->content['hero_name']);
    }

    public function test_legacy_publications_keep_their_home_when_restored(): void
    {
        $edition = $this->edition();
        $this->page($edition);
        $settings = \Modules\Documentation\Models\SiteSetting::current();
        $settings->update(['content' => [...$settings->content, 'hero_name' => 'Legacy brand']]);
        $service = app(PublicationService::class);
        $first = $service->request($edition, null);
        $first->update(['site_snapshot' => null]);
        $this->builder()->build($first);
        $restored = $service->restore($first->fresh(), null);
        $restored->update(['site_snapshot' => null]);
        $this->builder()->build($restored);
        $again = $service->restore($restored->fresh(), null);
        $this->builder()->build($again);
        $this->assertSame('Legacy brand', $again->fresh()->siteSnapshot()['site']['hero_name']);
        $manifest = json_decode(File::get($this->storage.'/builds/'.$again->id.'/.vitepress/portal.json'), true);
        $this->assertSame('Legacy brand', $manifest['site']['hero_name']);
    }

    public function test_real_vitepress_build_exports_inert_content_and_scoped_navigation(): void
    {
        $edition = $this->edition();
        $this->page($edition, 'Visible snapshot {{ 7 * 7 }}'."\n\n```mermaid\nflowchart LR\nA-->B\n```");
        $publication = app(PublicationService::class)->request($edition, null);
        app(PortalBuilder::class)->build($publication);
        $this->assertSame('succeeded', $publication->fresh()->status);
        $html = File::get($this->storage.'/site/current/'.$edition->collection->slug.'/v1/start.html');
        $this->assertStringContainsString('Visible snapshot {{ 7 * 7 }}', $html);
        $this->assertStringContainsString('Rechercher dans la documentation', $html);
        $this->assertStringContainsString('language-mermaid', $html);
        $this->assertStringNotContainsString('Visible snapshot 49', $html);
        $home = File::get($this->storage.'/site/current/index.html');
        $this->assertStringContainsString('VPHero', $home);
        $this->assertStringContainsString('VPFeatures', $home);
        $this->assertStringContainsString('Guides pratiques', $home);
        $this->assertStringContainsString('src="/docs/documentation.svg"', $home);
        $this->assertStringNotContainsString('/docs/docs/', $home);
    }

    public function test_markdown_examples_are_not_treated_as_image_references(): void
    {
        $renderer = app(MarkdownService::class);
        $markdown = "`![Example](doc-image:123)`\n\n```md\n![Example](doc-image:456)\n```\n\n![Real](doc-image:7)";
        $this->assertSame([7], $renderer->imageIds($markdown));
        $html = $renderer->render($markdown, [7 => '/media/screen.png']);
        $this->assertStringContainsString('doc-image:123', $html);
        $this->assertStringContainsString('src="/media/screen.png"', $html);
        $this->assertStringContainsString('id="heading"', $renderer->render('## Heading', []));
    }

    public function test_publishing_an_archive_removes_it_but_keeps_restorable_history(): void
    {
        $edition = $this->edition();
        $this->page($edition);
        $service = app(PublicationService::class);
        $first = $service->request($edition, null);
        $this->builder()->build($first);
        $edition->update(['archived_at' => now()]);
        $withdrawal = $service->request($edition, null);
        $this->builder()->build($withdrawal);
        $this->assertSame([], $withdrawal->fresh()->portal);
        $restore = $service->restore($first->fresh(), null);
        $this->builder()->build($restore);
        $this->assertCount(1, $restore->fresh()->portal);
        $this->assertNotNull($edition->fresh()->archived_at);
        $this->assertSame(1, Revision::count());
    }

    public function test_recovery_requeues_orphans_and_repeated_delivery_preserves_release(): void
    {
        $edition = $this->edition();
        $this->page($edition);
        $publication = app(PublicationService::class)->request($edition, null);
        $publication->update(['status' => 'building', 'updated_at' => now()->subMinutes(11)]);
        $this->artisan('documentation:recover', ['--no-interaction' => true])->assertSuccessful();
        $this->assertSame('queued', $publication->fresh()->status);
        Queue::assertPushed(BuildDocumentation::class, 2);
        $this->builder()->build($publication);
        $this->builder(true)->build($publication);
        $this->assertSame('succeeded', $publication->fresh()->status);
        $this->assertSame('release '.$publication->id, File::get($this->storage.'/site/current/index.html'));
    }

    public function test_empty_drafts_are_valid_and_oversized_images_are_refused(): void
    {
        $this->actingAsAdministrator();
        $edition = $this->edition();
        $this->postJson('/api/v1/documentation/editions/'.$edition->id.'/pages', ['title' => 'Empty', 'path' => 'empty', 'markdown' => '', 'position' => 0])->assertSuccessful()->assertJsonPath('revision.markdown', '');
        config(['documentation.image_max_kb' => 1]);
        $this->post('/api/v1/documentation/collections/'.$edition->collection_id.'/images', ['image' => UploadedFile::fake()->image('large.png')->size(10)], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_activation_failure_restores_previous_site_and_initial_failure_unlocks_slugs(): void
    {
        $this->actingAsAdministrator();
        $edition = $this->edition();
        $this->page($edition);
        $service = app(PublicationService::class);
        $failed = $service->request($edition, null);
        try {
            $this->builder(true)->build($failed);
        } catch (\RuntimeException) {
        }
        $this->putJson('/api/v1/documentation/collections/'.$edition->collection_id, ['title' => 'Guide', 'slug' => 'renamed-guide'])->assertOk();
        $first = $service->request($edition, null);
        $this->builder()->build($first);
        $second = $service->request($edition, null);
        try {
            $this->builder(false, true)->build($second);
        } catch (\RuntimeException) {
        }
        $this->assertSame('failed', $second->fresh()->status);
        $this->assertSame('release '.$first->id, File::get($this->storage.'/site/current/index.html'));
        $this->assertNotNull($edition->fresh()->published_at);
    }
}
