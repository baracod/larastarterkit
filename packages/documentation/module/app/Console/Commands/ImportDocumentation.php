<?php

namespace Modules\Documentation\Console\Commands;

use Baracod\Larastarterkit\Core\Support\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\Documentation\Models\Collection;
use Modules\Documentation\Services\EditorService;

class ImportDocumentation extends Command
{
    protected $signature = 'documentation:import
        {--collection=sneat-starter : Identifiant d\'URL de la documentation cible (créée si absente)}
        {--title=Sneat Starter : Titre d\'une documentation créée}
        {--description=Documentation du starter : Description d\'une documentation créée}
        {--edition=v1 : Édition cible (créée si absente)}';

    protected $description = 'Importer les fichiers Markdown du starter sans écraser le CMS';

    public function handle(EditorService $editor): int
    {
        if (! app(ModuleRegistry::class)->enabled('Documentation')) {
            $this->error('Le module Documentation est désactivé.');

            return self::FAILURE;
        }
        $slug = Str::slug((string) $this->option('collection'));
        $editionSlug = Str::slug((string) $this->option('edition'));
        if ($slug === '' || $editionSlug === '') {
            $this->error('La documentation et l\'édition doivent avoir un identifiant valide.');

            return self::FAILURE;
        }
        $docs = config('documentation.frontend_path').'/docs';
        $files = collect(File::glob($docs.'/*.md'))->keyBy(fn ($file) => $this->pagePath($file));
        $groups = $this->sidebarGroups($docs.'/.vitepress/config.mts');

        $count = DB::transaction(function () use ($editor, $slug, $editionSlug, $files, $groups) {
            $collection = Collection::firstOrCreate(['slug' => $slug], ['title' => (string) $this->option('title'), 'description' => (string) $this->option('description')]);
            $collection = Collection::whereKey($collection->id)->lockForUpdate()->firstOrFail();
            $edition = $collection->editions()->firstOrCreate(['slug' => $editionSlug], ['title' => $editionSlug]);
            if ($collection->archived_at || $edition->archived_at) {
                return 0;
            }
            $known = $edition->pages()->with('revisions')->get()->flatMap(fn ($page) => $page->revisions->pluck('path'))->all();
            $pages = $edition->pages()->whereNull('archived_at')->with('revision')->get()->keyBy(fn ($page) => $page->revision?->path);
            $count = 0;
            $position = 0;
            $save = function (string $path, string $markdown, ?int $parent) use ($editor, $edition, &$known, &$pages, &$count, &$position) {
                if (in_array($path, $known)) {
                    return $pages->get($path)?->id;
                }
                preg_match('/^#\s+(.+)$/m', $markdown, $match);
                $markdown = preg_replace('/\]\(\.\/index(?:\.md)?\)/', '](./introduction.html)', $markdown);
                $markdown = preg_replace('/\]\(\.\/([a-z0-9-]+)(?:\.md)?\)/', ']($1.html)', $markdown);
                $page = $editor->save($edition, null, ['title' => $match[1] ?? $path, 'path' => $path, 'markdown' => $markdown, 'position' => $position++, 'parent_id' => $parent], null);
                $known[] = $path;
                $pages->put($path, $page);
                $count++;

                return $page->id;
            };

            // Pages outside the sidebar groups (home page first), then each group as a section page with its pages.
            $grouped = collect($groups)->flatMap(fn ($group) => $group['links'])->all();
            foreach ($files->sortBy(fn ($file, $path) => $path === 'introduction' ? '' : $path) as $path => $file) {
                if (! in_array($path, $grouped)) {
                    $save($path, File::get($file), null);
                }
            }
            foreach ($groups as $group) {
                $links = array_values(array_filter($group['links'], fn ($path) => $files->has($path)));
                $index = collect($links)->map(fn ($path) => '- ['.$this->title($files[$path], $path).'](./'.$path.')')->implode("\n");
                $section = $save(Str::slug($group['text']), "# {$group['text']}\n\n{$index}\n", null);
                foreach ($links as $path) {
                    $save($path, File::get($files[$path]), $section);
                }
            }
            if (! $collection->default_edition_id) {
                $collection->update(['default_edition_id' => $edition->id]);
            }

            return $count;
        });
        $this->info($count.' page(s) importée(s). Aucune publication automatique.');

        return self::SUCCESS;
    }

    private function pagePath(string $file): string
    {
        $path = pathinfo($file, PATHINFO_FILENAME);

        return $path === 'index' ? 'introduction' : $path;
    }

    private function title(string $file, string $path): string
    {
        return preg_match('/^#\s+(.+)$/m', File::get($file), $match) ? trim($match[1]) : $path;
    }

    /**
     * Sidebar groups of the VitePress configuration: [['text' => 'Utiliser', 'links' => ['concepts', …]], …].
     * A flat sidebar (no groups) keeps the historical flat import.
     *
     * @return list<array{text: string, links: list<string>}>
     */
    private function sidebarGroups(string $config): array
    {
        if (! is_file($config)) {
            return [];
        }
        preg_match_all("/\\{\\s*text:\\s*'((?:[^'\\\\]|\\\\.)*)',\\s*items:\\s*\\[(.*?)\\]\\s*,?\\s*\\}/s", File::get($config), $matches, PREG_SET_ORDER);

        return array_map(function ($match) {
            preg_match_all("/link:\\s*'\\/([a-z0-9-]+)'/", $match[2], $links);

            return ['text' => stripslashes($match[1]), 'links' => array_map(fn ($path) => $path === 'index' ? 'introduction' : $path, $links[1])];
        }, $matches);
    }
}
