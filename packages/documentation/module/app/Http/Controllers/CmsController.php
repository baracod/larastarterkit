<?php

namespace Modules\Documentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Documentation\Models\Collection;
use Modules\Documentation\Models\Edition;
use Modules\Documentation\Models\Image;
use Modules\Documentation\Models\Page;
use Modules\Documentation\Models\Publication;
use Modules\Documentation\Services\EditorService;
use Modules\Documentation\Services\MarkdownService;
use Modules\Documentation\Services\PublicationService;

class CmsController extends Controller
{
    public function collections()
    {
        return Collection::with('editions')->orderBy('title')->get();
    }

    public function collection(Request $request, ?Collection $collection = null)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200', 'description' => 'nullable|string|max:5000',
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('documentation_collections')->ignore($collection?->id)],
            'default_edition_id' => ['nullable', 'integer', Rule::exists('documentation_editions', 'id')->where('collection_id', $collection?->id ?? 0)->whereNull('archived_at')],
        ]);

        return DB::transaction(function () use ($collection, $data) {
            if ($collection?->exists) {
                $collection = Collection::whereKey($collection->id)->lockForUpdate()->firstOrFail();
                abort_if($collection->archived_at, 409);
                abort_if(($collection->published_at || Publication::whereIn('edition_id', $collection->editions()->select('id'))->whereIn('status', ['queued', 'building'])->exists()) && $collection->slug !== $data['slug'], 409, 'Identifiant déjà publié.');
                $collection->update($data);

                return $collection;
            }

            return Collection::create($data);
        });
    }

    public function edition(Request $request, Collection $collection, ?Edition $edition = null)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/', Rule::unique('documentation_editions')->where('collection_id', $collection->id)->ignore($edition?->id)],
            'source_id' => ['nullable', 'integer', Rule::exists('documentation_editions', 'id')->where('collection_id', $collection->id)->whereNull('archived_at')],
        ]);

        return DB::transaction(function () use ($collection, $edition, $data, $request) {
            $collection = Collection::whereKey($collection->id)->lockForUpdate()->firstOrFail();
            abort_if($collection->archived_at, 409);
            if ($edition?->exists) {
                abort_unless($edition->collection_id === $collection->id, 404);
                $edition = Edition::whereKey($edition->id)->lockForUpdate()->firstOrFail();
                abort_if($edition->archived_at, 409);
                abort_if(($edition->published_at || Publication::where('edition_id', $edition->id)->whereIn('status', ['queued', 'building'])->exists()) && $edition->slug !== $data['slug'], 409, 'Identifiant déjà publié.');
                $edition->update(collect($data)->only(['title', 'slug'])->all());
            } else {
                $edition = $collection->editions()->create(collect($data)->only(['title', 'slug'])->all());
                if (! empty($data['source_id'])) {
                    $source = Edition::whereKey($data['source_id'])->lockForUpdate()->firstOrFail();
                    app(EditorService::class)->duplicate($source, $edition, $request->user()->id);
                }
            }

            return $edition;
        });
    }

    public function pages(Edition $edition)
    {
        return $edition->pages()->with('revision')->get();
    }

    public function page(Request $request, Edition $edition, ?Page $page = null)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'path' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:[-\/][a-z0-9]+)*$/', Rule::notIn(['index', '404', 'assets'])],
            'markdown' => 'present|nullable|string|max:500000', 'parent_id' => 'nullable|integer',
            'position' => 'required|integer|min:0|max:100000', 'expected_revision_id' => 'nullable|integer',
            'note' => 'nullable|string|max:255',
        ]);

        $data['markdown'] ??= '';

        return app(EditorService::class)->save($edition, $page, $data, $request->user()->id);
    }

    public function reorder(Request $request, Edition $edition)
    {
        $data = $request->validate([
            'pages' => 'required|array|min:1|max:1000',
            'pages.*.id' => 'required|integer|distinct',
            'pages.*.parent_id' => 'nullable|integer',
            'pages.*.position' => 'required|integer|min:0|max:100000',
        ]);
        app(EditorService::class)->reorder($edition, $data['pages'], $request->user()->id);

        return $edition->pages()->with('revision')->get();
    }

    public function revisions(Page $page)
    {
        return $this->withAuthors($page->revisions()->with('author')->orderByDesc('id')->limit(config('documentation.revisions_limit'))->get());
    }

    /** Exposes only the author's id and name: no appended roles or avatar, and no query per row. */
    private function withAuthors($items)
    {
        return $items->each(fn ($item) => $item->getRelation('author')?->setAppends([])->setVisible(['id', 'name']));
    }

    public function restorePage(Request $request, Page $page, int $revision)
    {
        $request->validate(['expected_revision_id' => 'required|integer']);
        $source = $page->revisions()->findOrFail($revision);

        return app(EditorService::class)->save($page->edition, $page, [
            ...$source->only(['title', 'path', 'markdown', 'parent_id', 'position']),
            'expected_revision_id' => $request->integer('expected_revision_id'),
        ], $request->user()->id);
    }

    public function archive(Request $request, string $kind, int $id)
    {
        $class = ['collections' => Collection::class, 'editions' => Edition::class, 'pages' => Page::class][$kind] ?? null;
        abort_unless($class, 404);
        $request->validate(['archived' => 'required|boolean']);

        return DB::transaction(function () use ($class, $id, $request) {
            $model = $class::findOrFail($id);
            if ($model instanceof Page) {
                Edition::whereKey($model->edition_id)->lockForUpdate()->firstOrFail();
                // Unarchiving must pass the same path and parent checks as an edit.
                if (! $request->boolean('archived')) {
                    $model->update(['archived_at' => null]);
                    app(EditorService::class)->save($model->edition, $model, [
                        ...$model->revision->only(['title', 'path', 'markdown', 'parent_id', 'position']),
                        'expected_revision_id' => $model->current_revision_id,
                    ], $request->user()->id);
                } else {
                    $children = $model->edition->pages()->whereNull('archived_at')->whereHas('revision', fn ($q) => $q->where('parent_id', $model->id))->exists();
                    abort_if($children, 409, 'Archivez ou déplacez les pages enfants avant leur parent.');
                }
            }
            $model->update(['archived_at' => $request->boolean('archived') ? now() : null]);

            return $model;
        });
    }

    public function preview(Request $request, Collection $collection, MarkdownService $renderer)
    {
        $data = $request->validate(['markdown' => 'present|nullable|string|max:500000']);

        $locale = \Modules\Documentation\Models\SiteSetting::current()->content['locale'];

        return ['html' => $renderer->render($data['markdown'] ?? '', $renderer->images($data['markdown'] ?? '', $collection->id), $locale)];
    }

    public function images(Collection $collection, MarkdownService $renderer)
    {
        // Usage lets editors see which drafts would break before archiving a file.
        $usage = [];
        $pages = Page::whereIn('edition_id', $collection->editions()->select('id'))->whereNull('archived_at')->with('revision:id,title,markdown', 'edition:id,title')->get();
        foreach ($pages as $page) {
            foreach ($renderer->imageIds($page->revision?->markdown ?? '') as $id) {
                $usage[$id][] = ['page_id' => $page->id, 'edition_id' => $page->edition_id, 'title' => $page->revision->title, 'edition' => $page->edition?->title];
            }
        }

        return Image::where('collection_id', $collection->id)->orderByDesc('id')->get()->each(fn (Image $image) => $image->setAttribute('usage', $usage[$image->id] ?? []));
    }

    public function updateImage(Request $request, Image $image)
    {
        $data = $request->validate(['name' => 'required|string|max:240', 'alt' => 'nullable|string|max:240']);
        $image->update($data);

        return $image;
    }

    public function upload(Request $request, Collection $collection)
    {
        abort_if($collection->archived_at, 409);
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimetypes:'.implode(',', config('documentation.image_mimes')), 'max:'.config('documentation.image_max_kb')],
            'alt' => 'nullable|string|max:240',
        ]);
        $upload = $request->file('image');
        [$width, $height] = getimagesize($upload->getRealPath());
        $name = Str::uuid().'.'.$upload->extension();
        $directory = config('documentation.storage').'/images';
        File::ensureDirectoryExists($directory);
        $size = $upload->getSize();
        $mime = $upload->getMimeType();
        $upload->move($directory, $name);

        return Image::create(['collection_id' => $collection->id, 'name' => mb_substr($upload->getClientOriginalName(), 0, 240), 'file' => $name, 'mime' => $mime, 'width' => $width, 'height' => $height, 'size' => $size, 'alt' => $request->input('alt'), 'author_id' => $request->user()->id]);
    }

    public function imageFile(Image $image)
    {
        return response()->file(config('documentation.storage').'/images/'.$image->file, ['Content-Type' => $image->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function archiveImage(Request $request, Image $image)
    {
        $request->validate(['archived' => 'required|boolean']);
        $image->update(['archived_at' => $request->boolean('archived') ? now() : null]);

        return $image;
    }

    public function publications()
    {
        return Publication::select(['id', 'edition_id', 'kind', 'status', 'author_id', 'created_at', 'finished_at', 'log'])
            ->with(['edition:id,collection_id,title,slug', 'edition.collection:id,title,slug', 'author:id,name'])
            ->orderByDesc('id')->limit(config('documentation.publications_limit'))->get()
            ->pipe(fn ($items) => $this->withAuthors($items));
    }

    public function publish(Request $request, Edition $edition, PublicationService $service)
    {
        return response()->json($service->request($edition, $request->user()->id), 202);
    }

    public function restorePublication(Request $request, Publication $publication, PublicationService $service)
    {
        return response()->json($service->restore($publication, $request->user()->id), 202);
    }
}
