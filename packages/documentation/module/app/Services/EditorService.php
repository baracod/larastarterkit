<?php

namespace Modules\Documentation\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Documentation\Models\Edition;
use Modules\Documentation\Models\Page;
use Modules\Documentation\Models\Revision;

class EditorService
{
    public function save(Edition $edition, ?Page $page, array $data, ?int $author): Page
    {
        return DB::transaction(function () use ($edition, $page, $data, $author) {
            $edition = Edition::whereKey($edition->id)->lockForUpdate()->firstOrFail();
            abort_if($edition->archived_at || $edition->collection->archived_at, 409, 'Cette édition est archivée.');
            if ($page) {
                $page = Page::where('edition_id', $edition->id)->whereKey($page->id)->lockForUpdate()->firstOrFail();
                abort_if($page->archived_at, 409, 'Cette page est archivée.');
                abort_unless((int) ($data['expected_revision_id'] ?? 0) === (int) $page->current_revision_id, 409, 'La page a été modifiée. Rechargez-la avant de sauvegarder.');
            }
            $pages = $edition->pages()->whereNull('archived_at')->with('revision')->get();
            foreach ($pages as $other) {
                if ($other->id !== $page?->id && $other->revision?->path === $data['path']) {
                    throw ValidationException::withMessages(['path' => 'Ce chemin existe déjà dans cette édition.']);
                }
            }
            $parent = $data['parent_id'] ?? null;
            $visited = [];
            while ($parent) {
                $ancestor = $pages->firstWhere('id', (int) $parent);
                if (! $ancestor || $ancestor->id === $page?->id || isset($visited[$parent])) {
                    throw ValidationException::withMessages(['parent_id' => 'Parent invalide ou arborescence circulaire.']);
                }
                $visited[$parent] = true;
                $parent = $ancestor->revision?->parent_id;
            }
            app(MarkdownService::class)->images($data['markdown'], $edition->collection_id);
            $page ??= Page::create(['edition_id' => $edition->id]);
            $revision = Revision::create([
                'page_id' => $page->id, 'title' => $data['title'], 'path' => $data['path'],
                'markdown' => $data['markdown'], 'parent_id' => $data['parent_id'] ?? null,
                'position' => $data['position'] ?? 0, 'author_id' => $author, 'created_at' => now(),
                'note' => $data['note'] ?? null,
            ]);
            $page->update(['current_revision_id' => $revision->id]);

            return $page->fresh('revision');
        });
    }

    /**
     * Moves several pages at once; the whole resulting tree is validated before any revision is written.
     *
     * @param  array<int, array{id: int, parent_id: ?int, position: int}>  $items
     */
    public function reorder(Edition $edition, array $items, ?int $author): int
    {
        return DB::transaction(function () use ($edition, $items, $author) {
            $edition = Edition::whereKey($edition->id)->lockForUpdate()->firstOrFail();
            abort_if($edition->archived_at || $edition->collection->archived_at, 409, 'Cette édition est archivée.');
            $pages = $edition->pages()->whereNull('archived_at')->with('revision')->lockForUpdate()->get()->keyBy('id');
            $tree = $pages->map(fn (Page $page) => ['parent_id' => $page->revision->parent_id ? (int) $page->revision->parent_id : null, 'position' => (int) $page->revision->position])->all();
            foreach ($items as $item) {
                if (! isset($tree[$item['id']])) {
                    throw ValidationException::withMessages(['pages' => 'Une page est introuvable ou archivée.']);
                }
                $tree[$item['id']] = ['parent_id' => empty($item['parent_id']) ? null : (int) $item['parent_id'], 'position' => (int) $item['position']];
            }
            foreach (array_keys($tree) as $id) {
                $visited = [];
                for ($parent = $tree[$id]['parent_id']; $parent; $parent = $tree[$parent]['parent_id']) {
                    if (! isset($tree[$parent]) || $parent === $id || isset($visited[$parent])) {
                        throw ValidationException::withMessages(['pages' => 'Parent invalide ou arborescence circulaire.']);
                    }
                    $visited[$parent] = true;
                }
            }
            $changed = 0;
            foreach ($tree as $id => $node) {
                $page = $pages[$id];
                if ($node['parent_id'] === ($page->revision->parent_id ? (int) $page->revision->parent_id : null) && $node['position'] === (int) $page->revision->position) {
                    continue;
                }
                $revision = Revision::create([
                    ...$page->revision->only(['title', 'path', 'markdown']),
                    'page_id' => $page->id, 'parent_id' => $node['parent_id'], 'position' => $node['position'],
                    'author_id' => $author, 'created_at' => now(), 'note' => 'Réorganisation du sommaire',
                ]);
                $page->update(['current_revision_id' => $revision->id]);
                $changed++;
            }

            return $changed;
        });
    }

    public function duplicate(Edition $source, Edition $target, ?int $author): void
    {
        $map = [];
        $pages = $source->pages()->whereNull('archived_at')->with('revision')->get();
        foreach ($pages as $page) {
            $map[$page->id] = Page::create(['edition_id' => $target->id]);
        }
        foreach ($pages as $page) {
            $revision = Revision::create([
                ...$page->revision->only(['title', 'path', 'markdown', 'position']),
                'page_id' => $map[$page->id]->id,
                'parent_id' => $map[$page->revision->parent_id]->id ?? null,
                'author_id' => $author, 'created_at' => now(),
            ]);
            $map[$page->id]->update(['current_revision_id' => $revision->id]);
        }
    }
}
