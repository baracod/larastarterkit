<?php

namespace Modules\Documentation\Console\Commands;

use Baracod\Larastarterkit\Core\Support\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\Documentation\Models\Collection;
use Modules\Documentation\Services\EditorService;

class ImportDocumentation extends Command
{
    protected $signature = 'documentation:import';

    protected $description = 'Importer les fichiers Markdown du starter sans écraser le CMS';

    public function handle(EditorService $editor): int
    {
        if (! app(ModuleRegistry::class)->enabled('Documentation')) {
            $this->error('Le module Documentation est désactivé.');

            return self::FAILURE;
        }
        $count = DB::transaction(function () use ($editor) {
            $collection = Collection::firstOrCreate(['slug' => 'sneat-starter'], ['title' => 'Sneat Starter', 'description' => 'Documentation du starter']);
            $collection = Collection::whereKey($collection->id)->lockForUpdate()->firstOrFail();
            $edition = $collection->editions()->firstOrCreate(['slug' => 'v1'], ['title' => 'v1']);
            if ($collection->archived_at || $edition->archived_at) {
                return 0;
            }
            $known = $edition->pages()->with('revisions')->get()->flatMap(fn ($page) => $page->revisions->pluck('path'))->all();
            $count = 0;
            foreach (File::glob(config('documentation.frontend_path').'/docs/*.md') as $file) {
                $path = pathinfo($file, PATHINFO_FILENAME);
                $path = $path === 'index' ? 'introduction' : $path;
                if (in_array($path, $known)) {
                    continue;
                }
                $markdown = File::get($file);
                preg_match('/^#\s+(.+)$/m', $markdown, $match);
                $title = $match[1] ?? $path;
                $markdown = preg_replace('/\]\(\.\/index(?:\.md)?\)/', '](./introduction.html)', $markdown);
                $markdown = preg_replace('/\]\(\.\/([a-z0-9-]+)(?:\.md)?\)/', ']($1.html)', $markdown);
                $editor->save($edition, null, ['title' => $title, 'path' => $path, 'markdown' => $markdown, 'position' => $count++], null);
            }
            if (! $collection->default_edition_id) {
                $collection->update(['default_edition_id' => $edition->id]);
            }

            return $count;
        });
        $this->info($count.' page(s) importée(s). Aucune publication automatique.');

        return self::SUCCESS;
    }
}
