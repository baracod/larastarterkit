<?php

namespace Modules\Documentation\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Documentation\Models\Collection;
use Modules\Documentation\Models\Edition;
use Modules\Documentation\Models\Image;
use Modules\Documentation\Models\Page;
use Modules\Documentation\Models\SiteSetting;
use Modules\Documentation\Services\EditorService;
use Modules\Documentation\Services\MarkdownService;
use Modules\Documentation\Services\PortalBuilder;
use Modules\Documentation\Services\PublicationService;
use Tests\TestCase;

class DocumentationEditorialTest extends TestCase
{
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = sys_get_temp_dir().'/documentation-editorial-'.bin2hex(random_bytes(6));
        config(['documentation.storage' => $this->storage, 'documentation.public_link' => false]);
        Queue::fake();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storage.'/builds/*/node_modules') ?: [] as $link) {
            unlink($link);
        }
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    private function edition(): Edition
    {
        $collection = Collection::create(['title' => 'Guide', 'slug' => 'guide-'.bin2hex(random_bytes(3)), 'description' => 'Tout le guide']);

        return $collection->editions()->create(['title' => 'Version 1', 'slug' => 'v1']);
    }

    private function page(Edition $edition, string $path, ?int $parent = null, int $position = 0, string $markdown = 'Texte'): Page
    {
        return app(EditorService::class)->save($edition, null, ['title' => ucfirst($path), 'path' => $path, 'markdown' => $markdown, 'parent_id' => $parent, 'position' => $position], null);
    }

    private function actingWith(array $permissions): User
    {
        $role = Role::create(['name' => 'docs-'.bin2hex(random_bytes(3)), 'display_name' => 'Docs']);
        $role->permissions()->attach(Permission::whereIn('key', array_map(fn ($name) => $name.'_documentation', $permissions))->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_callouts_code_titles_and_highlighted_lines_are_rendered_safely(): void
    {
        $renderer = app(MarkdownService::class);
        $html = $renderer->render("> [!TIP]\n> Utilisez **pnpm**.\n\n> [!WARNING] Sauvegarde **requise** <b>x</b>\n> Faites une copie.\n\n> Simple citation\n\n```php [app/Models/User.php] {2,4-5}\n<?php\necho 1;\n```", []);

        $this->assertStringContainsString('<blockquote class="cms-callout cms-callout-tip">', $html);
        $this->assertStringContainsString('<p class="cms-callout-title">Astuce</p>', $html);
        $this->assertStringContainsString('Utilisez <strong>pnpm</strong>.', $html);
        $this->assertStringNotContainsString('[!TIP]', $html);
        // The whole first line forms the title; raw HTML is stripped like everywhere else.
        $this->assertStringContainsString('<p class="cms-callout-title">Sauvegarde <strong>requise</strong> x</p>', $html);
        $this->assertStringContainsString('<p>Faites une copie.</p>', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString("<blockquote>\n<p>Simple citation</p>", $html);
        $this->assertStringContainsString('data-title="app/Models/User.php"', $html);
        $this->assertStringContainsString('data-lines="2,4-5"', $html);
        $this->assertStringContainsString('class="language-php"', $html);
        $this->assertStringContainsString('<p class="cms-callout-title">Tip</p>', $renderer->render("> [!TIP]\n> x", [], 'en'));
    }

    public function test_page_tree_reorder_creates_revisions_and_rejects_cycles_and_foreign_pages(): void
    {
        $edition = $this->edition();
        $first = $this->page($edition, 'first');
        $second = $this->page($edition, 'second', null, 1);
        $child = $this->page($edition, 'child', $first->id);
        $url = '/api/v1/documentation/editions/'.$edition->id.'/pages/reorder';

        $this->actingWith(['browse']);
        $this->postJson($url, ['pages' => [['id' => $second->id, 'parent_id' => null, 'position' => 0]]])->assertForbidden();

        $user = $this->actingWith(['browse', 'edit']);
        $this->postJson($url, ['pages' => [['id' => $second->id, 'parent_id' => null, 'position' => 0], ['id' => $first->id, 'parent_id' => null, 'position' => 1]]])
            ->assertOk()->assertJsonCount(3);
        $second->refresh()->load('revision');
        $this->assertSame(0, (int) $second->revision->position);
        $this->assertSame($user->id, $second->revision->author_id);
        $this->assertSame(2, $second->revisions()->count());
        // An unchanged page receives no new revision.
        $this->assertSame(1, $child->revisions()->count());

        $this->postJson($url, ['pages' => [['id' => $first->id, 'parent_id' => $child->id, 'position' => 0]]])->assertUnprocessable()->assertJsonValidationErrors('pages');
        $other = $this->page($this->edition(), 'other');
        $this->postJson($url, ['pages' => [['id' => $other->id, 'parent_id' => null, 'position' => 0]]])->assertUnprocessable();
        // Rejected moves leave no partial revision behind.
        $this->assertSame(2, $first->revisions()->count());
    }

    public function test_revision_notes_and_authors_expose_only_public_identity(): void
    {
        $edition = $this->edition();
        $page = $this->page($edition, 'start');
        $user = $this->actingWith(['browse', 'edit']);
        $this->putJson('/api/v1/documentation/editions/'.$edition->id.'/pages/'.$page->id, [
            'title' => 'Start', 'path' => 'start', 'markdown' => 'Nouveau', 'position' => 0,
            'expected_revision_id' => $page->current_revision_id, 'note' => 'Correction des commandes',
        ])->assertOk();

        $revisions = $this->getJson('/api/v1/documentation/pages/'.$page->id.'/revisions')->assertOk()->json();
        $this->assertSame('Correction des commandes', $revisions[0]['note']);
        $this->assertSame(['id' => $user->id, 'name' => $user->name], $revisions[0]['author']);
        $this->assertNull($revisions[1]['author']);

        app(PublicationService::class)->request($edition, $user->id);
        $publication = $this->getJson('/api/v1/documentation/publications')->assertOk()->json(0);
        $this->assertSame(['id' => $user->id, 'name' => $user->name], $publication['author']);
        $this->assertSame('Version 1', $publication['edition']['title']);
        $this->assertSame('Guide', $publication['edition']['collection']['title']);
    }

    public function test_media_metadata_usage_and_gif_uploads(): void
    {
        $edition = $this->edition();
        $this->actingWith(['browse', 'edit']);
        $collectionUrl = '/api/v1/documentation/collections/'.$edition->collection_id.'/images';
        $this->postJson($collectionUrl, ['image' => UploadedFile::fake()->image('capture.png', 20, 10)])->assertForbidden();

        $this->actingWith(['browse', 'edit', 'media']);
        $image = $this->postJson($collectionUrl, ['image' => UploadedFile::fake()->image('capture.png', 20, 10), 'alt' => 'Écran de connexion'])->assertCreated()->json();
        $this->assertSame('Écran de connexion', $image['alt']);
        $this->assertArrayNotHasKey('file', $image);
        $this->postJson($collectionUrl, ['image' => UploadedFile::fake()->image('anim.gif', 8, 8)])->assertCreated();
        $this->postJson($collectionUrl, ['image' => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml')])->assertUnprocessable();

        $this->page($edition, 'login', null, 0, "![Écran](doc-image:{$image['id']})");
        $listed = collect($this->getJson($collectionUrl)->assertOk()->json())->keyBy('id');
        $this->assertSame('Login', $listed[$image['id']]['usage'][0]['title']);
        $this->assertSame([], $listed->firstWhere('name', 'anim.gif')['usage']);

        $this->patchJson('/api/v1/documentation/images/'.$image['id'], ['name' => 'connexion.png', 'alt' => 'Formulaire'])->assertOk()->assertJsonPath('alt', 'Formulaire');
        $this->patchJson('/api/v1/documentation/images/'.$image['id'], ['name' => ''])->assertUnprocessable();
    }

    public function test_theme_settings_are_validated_merged_and_legacy_rows_are_normalized(): void
    {
        SiteSetting::create(['id' => 1, 'content' => ['title' => 'Ancien portail', 'hero_name' => 'Ancien', 'hero_text' => 'Texte', 'tagline' => null, 'logo_image_id' => null, 'hero_image_id' => null, 'actions' => [], 'features' => [], 'nav' => [], 'github_url' => null, 'footer' => ''], 'version' => 1]);
        $this->actingAsAdministrator();
        $settings = $this->getJson('/api/v1/documentation/site-settings')->assertOk()->json();
        $this->assertSame('Ancien portail', $settings['content']['title']);
        $this->assertSame('auto', $settings['content']['theme']['appearance']);
        $this->assertSame('fr', $settings['content']['locale']);

        foreach ([['brand_color' => 'red'], ['brand_color' => '#12345'], ['appearance' => 'sepia'], ['code_theme' => 'neon'], ['unknown' => 'x']] as $theme) {
            $invalid = $settings;
            $invalid['content']['theme'] = $theme;
            $this->putJson('/api/v1/documentation/site-settings', $invalid)->assertUnprocessable();
        }
        $invalid = $settings;
        $invalid['content']['locale'] = 'de';
        $this->putJson('/api/v1/documentation/site-settings', $invalid)->assertUnprocessable();

        $settings['content']['theme'] = ['brand_color' => '#0EA5E9', 'appearance' => 'force-dark'];
        $settings['content']['locale'] = 'en';
        $saved = $this->putJson('/api/v1/documentation/site-settings', $settings)->assertOk()->json('content');
        $this->assertSame('#0EA5E9', $saved['theme']['brand_color']);
        $this->assertSame('force-dark', $saved['theme']['appearance']);
        // Omitted theme keys keep their stored value.
        $this->assertSame('#50bdff', $saved['theme']['hero_from']);
        $this->assertSame('en', SiteSetting::current()->content['locale']);
    }

    public function test_export_uses_site_locale_theme_and_builds_an_edition_landing_page(): void
    {
        $settings = SiteSetting::current();
        $settings->update(['content' => SiteSetting::normalize([...$settings->content, 'locale' => 'en', 'theme' => ['brand_color' => '#16a34a', 'code_theme' => 'light']])]);
        $edition = $this->edition();
        $parent = $this->page($edition, 'install', null, 0, "> [!NOTE]\n> Read me");
        $this->page($edition, 'install/docker', $parent->id);
        $publication = app(PublicationService::class)->request($edition, null);
        $workspace = $this->storage.'/export';
        File::ensureDirectoryExists($workspace);

        app(PortalBuilder::class)->export([(string) $edition->id => $publication->snapshot], $workspace, $publication->siteSnapshot());

        $manifest = json_decode(File::get($workspace.'/.vitepress/portal.json'), true);
        $this->assertSame('en', $manifest['site']['locale']);
        $this->assertSame('#16a34a', $manifest['site']['theme']['brand_color']);
        $this->assertSame('light', $manifest['site']['theme']['code_theme']);
        $prefix = $edition->collection->slug.'/v1';
        $this->assertStringContainsString('<p class="cms-callout-title">Note</p>', File::get($workspace.'/'.$prefix.'/install.md'));
        $landing = File::get($workspace.'/'.$prefix.'/index.md');
        $this->assertStringContainsString('class="cms-chapters"', $landing);
        $this->assertStringContainsString('Tout le guide', $landing);
        $this->assertStringContainsString('/docs/'.$prefix.'/install/docker.html', $landing);
        $this->assertStringContainsString('Explore the guides', File::get($workspace.'/index.md'));
    }

    public function test_real_build_publishes_pages_with_images_callouts_and_theme(): void
    {
        $edition = $this->edition();
        File::ensureDirectoryExists($this->storage.'/images');
        imagepng(imagecreatetruecolor(4, 4), $this->storage.'/images/shot.png');
        $image = Image::create(['collection_id' => $edition->collection_id, 'name' => 'shot.png', 'file' => 'shot.png', 'mime' => 'image/png', 'width' => 4, 'height' => 4, 'size' => 100]);
        $this->page($edition, 'start', null, 0, "> [!TIP]\n> Astuce\n\n![Capture](doc-image:{$image->id})\n\n```ts [main.ts] {1}\nconst a = 1\n```");
        $publication = app(PublicationService::class)->request($edition, null);

        app(PortalBuilder::class)->build($publication);

        $this->assertSame('succeeded', $publication->fresh()->status, (string) $publication->fresh()->log);
        $root = $this->storage.'/site/current';
        $html = File::get($root.'/'.$edition->collection->slug.'/v1/start.html');
        $this->assertMatchesRegularExpression('#<img src="/docs/media/shot\.png" alt="Capture"#', $html);
        $this->assertFileExists($root.'/media/shot.png');
        $this->assertStringContainsString('cms-callout-tip', $html);
        $this->assertStringContainsString('data-title="main.ts"', $html);
        $this->assertStringContainsString('--vp-c-brand-2:#696cff', $html);
    }
}
