<?php

namespace Modules\Documentation\Services;

use Illuminate\Support\Facades\DB;
use Modules\Documentation\Jobs\BuildDocumentation;
use Modules\Documentation\Models\Collection;
use Modules\Documentation\Models\Edition;
use Modules\Documentation\Models\Image;
use Modules\Documentation\Models\Publication;
use Throwable;

class PublicationService
{
    public function request(Edition $edition, ?int $author): Publication
    {
        $publication = DB::transaction(function () use ($edition, $author) {
            $collection = Collection::whereKey($edition->collection_id)->lockForUpdate()->firstOrFail();
            $edition = Edition::whereKey($edition->id)->lockForUpdate()->firstOrFail();
            $withdrawn = (bool) ($edition->archived_at || $collection->archived_at);
            $pages = $edition->pages()->whereNull('archived_at')->with('revision')->get();
            abort_if(! $withdrawn && $pages->isEmpty(), 422, 'Ajoutez au moins une page.');
            $imageIds = [];
            foreach ($pages as $page) {
                $imageIds = array_merge($imageIds, app(MarkdownService::class)->imageIds($page->revision->markdown));
            }
            $images = Image::where('collection_id', $collection->id)->whereIn('id', array_unique($imageIds))->get();
            abort_unless($images->count() === count(array_unique($imageIds)), 422, 'Une image est introuvable.');
            $site = \Modules\Documentation\Models\SiteSetting::current()->content;
            $siteImages = Image::whereIn('id', array_filter([$site['logo_image_id'], $site['hero_image_id']]))->get();
            $snapshot = [
                'site' => $site,
                'site_images' => $siteImages->map(fn ($image) => [...$image->toArray(), 'file' => $image->file])->all(),
                'withdrawn' => $withdrawn,
                'withdrawn_editions' => Edition::whereNotNull('archived_at')->orWhereHas('collection', fn ($q) => $q->whereNotNull('archived_at'))->pluck('id')->all(),
                'collection' => $collection->only(['id', 'title', 'slug', 'description', 'default_edition_id']),
                'edition' => $edition->only(['id', 'title', 'slug']),
                'pages' => $pages->map(fn ($page) => ['id' => $page->id, ...$page->revision->only(['id', 'page_id', 'parent_id', 'title', 'path', 'position', 'markdown'])])->all(),
                'images' => $images->map(fn ($image) => [...$image->toArray(), 'file' => $image->file])->all(),
            ];

            return Publication::create(['edition_id' => $edition->id, 'author_id' => $author, 'snapshot' => $snapshot, 'site_snapshot' => ['site' => $site, 'site_images' => $snapshot['site_images']]]);
        });

        return $this->dispatch($publication);
    }

    public function restore(Publication $source, ?int $author): Publication
    {
        abort_unless($source->status === 'succeeded' && $source->portal !== null, 409, 'Publication indisponible.');

        return $this->dispatch(Publication::create([
            'kind' => 'restore', 'author_id' => $author, 'snapshot' => $source->portal, 'site_snapshot' => $source->siteSnapshot(),
        ]));
    }

    private function dispatch(Publication $publication): Publication
    {
        try {
            BuildDocumentation::dispatch($publication->id)->onConnection(config('documentation.queue_connection'))->onQueue('documentation');
        } catch (Throwable $exception) {
            $publication->update(['status' => 'failed', 'log' => 'La tâche ne peut pas être mise en file.', 'finished_at' => now()]);
            throw $exception;
        }

        return $publication->makeHidden(['snapshot', 'portal']);
    }
}
