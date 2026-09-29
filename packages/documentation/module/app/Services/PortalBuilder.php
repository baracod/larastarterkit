<?php

namespace Modules\Documentation\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\Documentation\Models\Publication;
use Symfony\Component\Process\Process;
use Throwable;

class PortalBuilder
{
    public function build(Publication $publication): bool
    {
        $root = config('documentation.storage').'/site';
        File::ensureDirectoryExists($root.'/releases');
        $lock = fopen($root.'/build.lock', 'c');
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return false;
        }
        $activated = false;
        $previousTarget = null;
        try {
            $publication->refresh();
            if ($publication->status === 'succeeded') {
                return true;
            }
            $publication->update(['status' => 'building', 'log' => null]);
            $marker = $root.'/current/publication.json';
            if (is_file($marker) && (int) json_decode(File::get($marker), true)['id'] === $publication->id) {
                $this->recordSuccess($publication, $publication->portal ?? []);

                return true;
            }
            $siteSnapshot = $publication->siteSnapshot();
            $previous = $this->activePortal($root);
            $portal = $publication->kind === 'restore' ? $publication->snapshot : $previous;
            if ($publication->kind !== 'restore') {
                foreach ($publication->snapshot['withdrawn_editions'] ?? [] as $id) {
                    unset($portal[$id]);
                }
                if (! ($publication->snapshot['withdrawn'] ?? false)) {
                    $portal[(string) $publication->edition_id] = $publication->snapshot;
                    foreach ($portal as &$snapshot) {
                        if ($snapshot['collection']['id'] === $publication->snapshot['collection']['id']) {
                            $snapshot['collection'] = $publication->snapshot['collection'];
                        }
                    }
                    unset($snapshot);
                }
                foreach ($portal as &$snapshot) {
                    $snapshot['site'] = $siteSnapshot['site'];
                    $snapshot['site_images'] = $siteSnapshot['site_images'];
                }
                unset($snapshot);
            }
            $workspace = config('documentation.storage').'/builds/'.$publication->id;
            File::deleteDirectory($workspace);
            File::ensureDirectoryExists($workspace.'/.vitepress');
            $this->export($portal, $workspace, $siteSnapshot);
            $this->compile($workspace, $publication);
            $dist = $workspace.'/.vitepress/dist';
            if (! is_file($dist.'/index.html')) {
                throw new \RuntimeException('Le site construit ne contient pas de page d’accueil.');
            }
            foreach ($portal as $snapshot) {
                $prefix = $snapshot['collection']['slug'].'/'.$snapshot['edition']['slug'];
                foreach ($snapshot['pages'] as $page) {
                    if (! is_file($dist.'/'.$prefix.'/'.$page['path'].'.html')) {
                        throw new \RuntimeException('Une page est absente du site construit.');
                    }
                }
            }
            File::put($dist.'/publication.json', json_encode(['id' => $publication->id], JSON_THROW_ON_ERROR));
            $release = $root.'/releases/'.$publication->id;
            File::deleteDirectory($release);
            if (! rename($dist, $release)) {
                throw new \RuntimeException('Impossible de préparer la publication.');
            }
            // Persist the manifest before switching; current/publication.json is the activation authority.
            $publication->update(['portal' => $portal]);
            if (! app(\Baracod\Larastarterkit\Core\Support\ModuleRegistry::class)->enabled('Documentation')) {
                throw new \RuntimeException('Le module Documentation est désactivé.');
            }
            $this->preparePublicLink($root);
            $previousTarget = is_link($root.'/current') ? readlink($root.'/current') : null;
            $temporary = $root.'/current-'.bin2hex(random_bytes(6));
            symlink('releases/'.$publication->id, $temporary);
            if (! rename($temporary, $root.'/current')) {
                @unlink($temporary);
                throw new \RuntimeException('Impossible d’activer la publication.');
            }
            $activated = true;
            $this->recordSuccess($publication, $portal);

            return true;
        } catch (Throwable $exception) {
            if ($activated) {
                if ($previousTarget !== null) {
                    $rollback = $root.'/rollback-'.bin2hex(random_bytes(6));
                    symlink($previousTarget, $rollback);
                    rename($rollback, $root.'/current');
                } else {
                    unlink($root.'/current');
                }
            }
            $publication->update(['status' => 'failed', 'log' => mb_substr(($publication->fresh()->log ?? '')."\n".$exception->getMessage(), -20000), 'finished_at' => now()]);
            throw $exception;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    protected function recordSuccess(Publication $publication, array $portal): void
    {
        DB::transaction(function () use ($publication, $portal) {
            foreach ($portal as $snapshot) {
                \Modules\Documentation\Models\Collection::whereKey($snapshot['collection']['id'])->whereNull('published_at')->update(['published_at' => now()]);
                \Modules\Documentation\Models\Edition::whereKey($snapshot['edition']['id'])->whereNull('published_at')->update(['published_at' => now()]);
            }
            $publication->update(['status' => 'succeeded', 'finished_at' => now()]);
        });
    }

    private function activePortal(string $root): array
    {
        $file = $root.'/current/publication.json';
        if (! is_file($file)) {
            return [];
        }
        $id = json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR)['id'];

        return Publication::findOrFail($id)->portal ?? [];
    }

    protected function compile(string $workspace, Publication $publication): void
    {
        $module = config('documentation.frontend_path');
        // Explicitly remove inherited secrets from the Node subprocess environment.
        $environment = array_fill_keys(array_keys(array_merge($_SERVER, $_ENV, getenv())), false);
        $environment = array_merge($environment, ['PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin', 'NODE_ENV' => 'production', 'HOME' => $workspace, 'TMPDIR' => sys_get_temp_dir()]);
        $process = new Process([config('documentation.node'), base_path('node_modules').'/vitepress/bin/vitepress.js', 'build', $workspace], $module, $environment);
        $process->setTimeout(config('documentation.build_timeout'));
        $process->run();
        $publication->update(['log' => mb_substr($process->getOutput().$process->getErrorOutput(), -20000)]);
        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Échec de la construction VitePress. Consultez le journal.');
        }
    }

    private function preparePublicLink(string $root): void
    {
        $link = config('documentation.public_link');
        if (! $link) {
            return; // A separate static server reads site/current on the shared volume.
        }
        if (is_link($link)) {
            if (readlink($link) !== $root.'/current') {
                throw new \RuntimeException('Le lien public pointe vers un autre emplacement.');
            }

            return;
        }
        if (file_exists($link)) {
            // Preserve the former static build before replacing its directory with a symlink.
            $legacy = $root.'/releases/legacy-'.bin2hex(random_bytes(4));
            if (! rename($link, $legacy)) {
                throw new \RuntimeException('Impossible de conserver le site précédent.');
            }
            if (! file_exists($root.'/current')) {
                symlink($legacy, $root.'/current');
            }
        }
        File::ensureDirectoryExists(dirname($link));
        if (! symlink($root.'/current', $link)) {
            throw new \RuntimeException('Impossible de créer le lien public.');
        }
    }

    public function export(array $portal, string $workspace, ?array $siteSnapshot = null): void
    {
        $module = config('documentation.frontend_path');
        File::copyDirectory($module.'/publishing/.vitepress', $workspace.'/.vitepress');
        File::copyDirectory($module.'/publishing/public', $workspace.'/public');
        File::put($workspace.'/package.json', '{"type":"module"}');
        File::put($workspace.'/tsconfig.json', '{"compilerOptions":{"target":"ES2022","module":"ESNext","moduleResolution":"Bundler"}}');
        // Only dependency code is shared with the isolated build workspace.
        symlink(base_path('node_modules'), $workspace.'/node_modules');
        $renderer = app(MarkdownService::class);
        $base = config('documentation.base');
        $siteSnapshot ??= reset($portal) ?: [];
        $site = \Modules\Documentation\Models\SiteSetting::normalize($siteSnapshot['site'] ?? []);
        $manifest = ['site' => $site, 'base' => $base, 'editions' => [], 'search' => []];
        $siteImages = $this->copyImages($siteSnapshot['site_images'] ?? [], $workspace);
        foreach (['logo', 'hero'] as $role) {
            if (isset($siteImages[$site[$role.'_image_id'] ?? 0])) {
                $manifest['site'][$role.'_image'] = $siteImages[$site[$role.'_image_id']];
            }
        }
        $collections = [];
        foreach ($portal as $snapshot) {
            $collection = $snapshot['collection'];
            $edition = $snapshot['edition'];
            $prefix = $collection['slug'].'/'.$edition['slug'];
            $images = $this->copyImages($snapshot['images'], $workspace);
            $pages = $snapshot['pages'];
            usort($pages, fn ($a, $b) => [$a['position'], $a['page_id']] <=> [$b['position'], $b['page_id']]);
            $navigation = $this->navigation($pages, null, $prefix);
            $editionMeta = ['id' => $edition['id'], 'collection' => $collection['slug'], 'collectionTitle' => $collection['title'], 'title' => $edition['title'], 'prefix' => $prefix, 'navigation' => $navigation];
            $manifest['editions'][] = $editionMeta;
            $collections[$collection['slug']]['data'] = $collection;
            $collections[$collection['slug']]['editions'][] = $editionMeta;
            foreach ($pages as $page) {
                $html = $renderer->render($page['markdown'], $images, $site['locale']);
                $file = $workspace.'/'.$prefix.'/'.$page['path'].'.md';
                $this->writePage($file, $page['title'], $html);
                $manifest['search'][] = ['scope' => $prefix, 'title' => $page['title'], 'url' => $base.$prefix.'/'.$page['path'].'.html', 'text' => html_entity_decode(strip_tags($html))];
            }
            $this->writePage($workspace.'/'.$prefix.'/index.md', $collection['title'].' — '.$edition['title'], $this->editionIndex($collection, $pages, $base.$prefix));
        }
        $home = '';
        foreach ($collections as $entry) {
            $collection = $entry['data'];
            $editions = $entry['editions'];
            usort($editions, fn ($a, $b) => ((int) ($b['id'] === $collection['default_edition_id'])) <=> ((int) ($a['id'] === $collection['default_edition_id'])));
            $home .= '<section><span class="cms-catalogue-icon" aria-hidden="true">📘</span><h2>'.e($collection['title']).'</h2><p>'.e($collection['description']).'</p><ul>';
            foreach ($editions as $edition) {
                $home .= '<li><a href="'.e($base.$edition['prefix'].'/index.html').'">'.e($edition['title']).'</a></li>';
            }
            $home .= '</ul></section>';
        }
        // JSON is valid YAML; escape every editorial string before VitePress's home v-html slots.
        $safe = fn ($text) => e($text ?? '');
        $frontmatter = [
            'layout' => 'home', 'title' => $site['title'],
            'hero' => [
                'name' => $safe($site['hero_name']), 'text' => $safe($site['hero_text']),
                'tagline' => $safe($site['tagline']),
                'actions' => $site['actions'] ?: [['theme' => 'brand', 'text' => $site['locale'] === 'en' ? 'Explore the guides' : 'Explorer les guides', 'link' => '#catalogue']],
            ],
            'features' => array_map(fn ($feature) => [...$feature, 'icon' => $safe($feature['icon'] ?? ''), 'title' => $safe($feature['title']), 'details' => $safe($feature['details'])], $site['features']),
        ];
        $frontmatter['hero']['image'] = ['src' => $manifest['site']['hero_image'] ?? '/documentation.svg', 'alt' => $site['title']];
        $manifest['site']['logo_image'] ??= '/documentation.svg';
        File::put($workspace.'/index.md', "---\n".json_encode($frontmatter, JSON_THROW_ON_ERROR | JSON_HEX_TAG)."\n---\n\n<div v-pre class=\"cms-catalogue\" id=\"catalogue\">\n".$home."\n</div>\n");
        File::put($workspace.'/.vitepress/portal.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP));
    }

    /** Landing page of an edition: description and a card per top-level chapter with its sub-pages. */
    private function editionIndex(array $collection, array $pages, string $prefix): string
    {
        $html = $collection['description'] ? '<p class="cms-edition-lead">'.e($collection['description']).'</p>' : '';
        $html .= '<div class="cms-chapters">';
        foreach (array_filter($pages, fn ($page) => $page['parent_id'] === null) as $chapter) {
            $children = array_filter($pages, fn ($page) => $page['parent_id'] === $chapter['page_id']);
            $html .= '<section class="cms-chapter"><a class="cms-chapter-title" href="'.e($prefix.'/'.$chapter['path'].'.html').'">'.e($chapter['title']).'</a>';
            if ($children) {
                $html .= '<ul>'.implode('', array_map(fn ($page) => '<li><a href="'.e($prefix.'/'.$page['path'].'.html').'">'.e($page['title']).'</a></li>', $children)).'</ul>';
            }
            $html .= '</section>';
        }

        return $html.'</div>';
    }

    private function copyImages(array $images, string $workspace): array
    {
        $urls = [];
        foreach ($images as $image) {
            File::ensureDirectoryExists($workspace.'/public/media');
            $source = config('documentation.storage').'/images/'.$image['file'];
            if (! is_file($source)) {
                throw new \RuntimeException('Une image de la publication est introuvable.');
            }
            File::copy($source, $workspace.'/public/media/'.$image['file']);
            // Public-folder path without the base: VitePress resolves it and prefixes the base itself.
            $urls[$image['id']] = '/media/'.$image['file'];
        }

        return $urls;
    }

    private function writePage(string $file, string $title, string $html): void
    {
        File::ensureDirectoryExists(dirname($file));
        $heading = preg_match('/^<h1[ >]/', ltrim($html)) ? '' : '<h1>'.e($title).'</h1>';
        File::put($file, "---\ntitle: ".json_encode($title, JSON_THROW_ON_ERROR)."\n---\n\n<div v-pre>\n".$heading."\n".$html."\n</div>\n");
    }

    private function navigation(array $pages, ?int $parent, string $prefix): array
    {
        return array_values(array_map(function ($page) use ($pages, $prefix) {
            $item = ['text' => $page['title'], 'link' => '/'.$prefix.'/'.$page['path'].'.html'];
            $children = $this->navigation($pages, $page['page_id'], $prefix);
            if ($children) {
                $item['items'] = $children;
                $item['collapsed'] = false;
            }

            return $item;
        }, array_filter($pages, fn ($page) => $page['parent_id'] === $parent)));
    }
}
