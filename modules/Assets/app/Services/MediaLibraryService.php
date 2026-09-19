<?php

namespace Modules\Assets\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lunar\Core\Models\Asset;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Media Library logic built on Lunar's Asset model + Spatie MediaLibrary
 * (the media stack that ships with Lunar — see docs/architecture/overview.md).
 *
 * Each library file is a standalone Lunar Asset with a single attached Media
 * item (collection "images"). Conversions (thumb/webp/large/zoom…) are produced
 * by the configured FashionMediaDefinitions for the "asset" type. Logical
 * grouping (folder) and accessibility metadata (alt/title) live in the media's
 * custom_properties.
 */
class MediaLibraryService
{
    /** Accepted MIME prefixes/extensions grouped by logical type. */
    protected const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif', 'image/svg+xml'];

    protected const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];

    /**
     * What the library takes in: exactly Laravel's `image` rule (no SVG — served
     * from the shop's origin it is a script; no AVIF — the rule does not know
     * it). A gallery upload outside this list stays on the record, unlinked.
     */
    public const ACCEPTED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];

    /** Logical type buckets a browse can be narrowed to. */
    public const TYPES = ['image', 'video', 'document'];

    /** Orders the file manager offers. The first one is the default. */
    public const SORTS = ['newest', 'oldest', 'name'];

    /**
     * Folder filter meaning "files in no folder". A slug can never be this —
     * Str::slug('-') is empty — so it cannot collide with a real folder.
     */
    public const UNFILED = '-';

    /**
     * Spatie collection name used for library files (defaults to Lunar's).
     */
    public function collection(): string
    {
        return config('lunar.media.collection', 'images');
    }

    /**
     * Store an uploaded file as a new Asset + attached Media item.
     */
    public function store(UploadedFile $file, ?string $folder = null): Asset
    {
        $asset = Asset::create([]);

        $asset->addMedia($file->getRealPath())
            ->preservingOriginal()
            ->usingFileName($this->safeFileName($file))
            ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            ->withCustomProperties($this->properties($folder, $file->getMimeType(), sha1_file($file->getRealPath()) ?: null))
            ->toMediaCollection($this->collection());

        return $asset->fresh();
    }

    /**
     * Store an upload — unless the library already holds these exact bytes,
     * in which case that file is returned instead of a second copy.
     *
     * This is what keeps a gallery upload from duplicating the library: the
     * file manager hands a picked library file to Lunar's uploader as bytes,
     * and those bytes come back here and find themselves.
     */
    public function storeOrReuse(UploadedFile $file, ?string $folder = null): Asset
    {
        $hash = sha1_file($file->getRealPath());

        return ($hash ? $this->findByContent($hash, (int) $file->getSize()) : null)
            ?? $this->store($file, $folder);
    }

    /**
     * The library file with exactly this content, if any.
     *
     * Narrowed by size first (a column), then compared by SHA-1. Files stored
     * before hashes were recorded are hashed on first comparison and — unless
     * $remember is false (a dry run) — the result kept, so each is read once.
     */
    public function findByContent(string $sha1, int $size, bool $remember = true): ?Asset
    {
        $match = $this->libraryMedia()
            ->where('size', $size)
            ->get()
            ->first(function (Media $media) use ($sha1, $remember): bool {
                $known = $this->contentHash($media, $remember);

                return $known !== null && hash_equals($known, $sha1);
            });

        return $match ? Asset::with('file')->find($match->model_id) : null;
    }

    /**
     * SHA-1 of a media item's original — recorded on the item after the first
     * read unless $remember is false. Null when the file is not on disk.
     */
    public function contentHash(Media $media, bool $remember = true): ?string
    {
        if ($known = $media->getCustomProperty('sha1')) {
            return $known;
        }

        $path = $media->getPathRelativeToRoot();
        $disk = Storage::disk($media->disk);

        if (! $disk->exists($path)) {
            return null;
        }

        $hash = sha1((string) $disk->get($path));

        if ($remember) {
            $media->setCustomProperty('sha1', $hash);
            $media->saveQuietly();
        }

        return $hash;
    }

    public function accepts(UploadedFile $file): bool
    {
        return $this->acceptsMime($file->getMimeType());
    }

    public function acceptsMime(?string $mime): bool
    {
        return in_array($mime, self::ACCEPTED_MIMES, true);
    }

    /**
     * Store a file from an absolute filesystem path (e.g. a theme/seed image)
     * as a new Asset + attached Media item. Optional alt/title metadata.
     *
     * @param  array{alt?:string,title?:string,name?:string}  $meta
     */
    public function storeFromPath(string $path, ?string $folder = null, array $meta = []): Asset
    {
        $mime = mime_content_type($path) ?: null;
        $name = $meta['name'] ?? pathinfo($path, PATHINFO_FILENAME);

        $props = $this->properties($folder, $mime, sha1_file($path) ?: null);
        foreach (['alt', 'title'] as $key) {
            if (! empty($meta[$key])) {
                $props[$key] = $meta[$key];
            }
        }

        $asset = Asset::create([]);

        $asset->addMedia($path)
            ->preservingOriginal()
            ->usingFileName(Str::slug(pathinfo($path, PATHINFO_FILENAME)).'.'.pathinfo($path, PATHINFO_EXTENSION))
            ->usingName($name)
            ->withCustomProperties($props)
            ->toMediaCollection($this->collection());

        return $asset->fresh();
    }

    /**
     * Replace the binary of an existing asset's media with a new upload.
     * The old media item is removed and a new one attached, keeping the same
     * Asset id — so picker references (which store the Asset id) stay valid,
     * and every gallery linking to it is re-pointed at the new file.
     */
    public function replace(Asset $asset, UploadedFile $file): Asset
    {
        $media = $asset->file;
        $props = $media ? $media->custom_properties : [];

        // Preserve folder/alt/title, refresh the type and hash from the new file.
        $props['type'] = $this->classify($file->getMimeType());
        $props['sha1'] = sha1_file($file->getRealPath()) ?: null;

        $asset->clearMediaCollection($this->collection());

        $asset->addMedia($file->getRealPath())
            ->preservingOriginal()
            ->usingFileName($this->safeFileName($file))
            ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            ->withCustomProperties($props)
            ->toMediaCollection($this->collection());

        app(LibraryLinks::class)->resync($asset);

        return $asset->fresh();
    }

    /**
     * Delete the asset (its media + files cascade via Spatie) — after taking it
     * out of every gallery that shows it, so no product is left pointing at a
     * file that is gone.
     */
    public function delete(Asset $asset): void
    {
        app(LibraryLinks::class)->unlinkAll($asset);

        $asset->delete();
    }

    /**
     * Delete several library assets at once. Ids that are not library files —
     * gone already, or an Asset with no media — are skipped rather than failing
     * the batch: a second tab deleting the same file is not an error.
     *
     * @param  array<int, int|string>  $assetIds
     * @return int how many were deleted
     */
    public function deleteMany(array $assetIds): int
    {
        $assets = Asset::query()->whereHas('file')->whereIn('id', $assetIds)->get();

        $assets->each(fn (Asset $asset) => $this->delete($asset));

        return $assets->count();
    }

    /**
     * Edit a library file's metadata: display name, alt/title, folder.
     *
     * Only the keys present are touched, so the details panel can save one
     * field without resending the rest. A blank alt/title removes the property
     * instead of storing an empty string the storefront would render as alt="".
     *
     * @param  array{name?:string|null,alt?:string|null,title?:string|null,folder?:string|null}  $attributes
     */
    public function update(Asset $asset, array $attributes): Asset
    {
        $media = $asset->file;

        if (! $media) {
            return $asset;
        }

        if (filled($attributes['name'] ?? null)) {
            $media->name = trim((string) $attributes['name']);
        }

        foreach (['alt', 'title'] as $key) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $value = trim((string) $attributes[$key]);

            $value === ''
                ? $media->forgetCustomProperty($key)
                : $media->setCustomProperty($key, $value);
        }

        if (array_key_exists('folder', $attributes)) {
            $media->setCustomProperty('folder', $this->folderSlug($attributes['folder']));
        }

        $media->save();

        return $asset->fresh('file');
    }

    /**
     * Move library files into a folder (null → out of every folder).
     *
     * @param  array<int, int|string>  $assetIds
     * @return int how many were moved
     */
    public function move(array $assetIds, ?string $folder): int
    {
        $slug = $this->folderSlug($folder);

        return $this->libraryMedia()
            ->whereIn('model_id', $assetIds)
            ->get()
            ->each(function (Media $media) use ($slug): void {
                $media->setCustomProperty('folder', $slug);
                $media->save();
            })
            ->count();
    }

    /**
     * Rename a folder by re-labelling every file in it.
     *
     * Folders are not rows — a folder is the `folder` property its files share
     * — so renaming onto an existing folder simply merges the two.
     *
     * @return string|null the new slug, or null when the name slugs to nothing
     */
    public function renameFolder(string $from, string $to): ?string
    {
        $slug = $this->folderSlug($to);

        if ($slug === null) {
            return null;
        }

        $this->libraryMedia()
            ->where('custom_properties->folder', $from)
            ->get()
            ->each(function (Media $media) use ($slug): void {
                $media->setCustomProperty('folder', $slug);
                $media->save();
            });

        return $slug;
    }

    /**
     * Normalise a folder name the way every write does, so the name an admin
     * types, the one stored and the one filtered on are always the same slug.
     */
    public function folderSlug(?string $folder): ?string
    {
        $slug = Str::slug((string) $folder);

        return $slug === '' ? null : $slug;
    }

    /**
     * Media items that are library files: attached to a Lunar Asset, in the
     * library collection. Product galleries and review photos share the media
     * table and must never be counted, moved or listed as library files.
     *
     * @return Builder<Media>
     */
    protected function libraryMedia(): Builder
    {
        return Media::query()
            ->where('model_type', (new Asset)->getMorphClass())
            ->where('collection_name', $this->collection());
    }

    /**
     * Resolve a picker value (Asset id) to a public conversion URL, optionally
     * for a named conversion (e.g. 'thumb', 'webp', 'large'). Returns null if
     * the asset/media or conversion is missing. Use this anywhere a MediaPicker
     * value is rendered (storefront, API resources).
     *
     * Always returns a conversion URL — never the original full-size image,
     * which would lag the storefront. When no conversion is requested, defaults
     * to 'large' (the standard storefront display size).
     */
    public function url(int|string|null $assetId, ?string $conversion = null): ?string
    {
        if (! $assetId) {
            return null;
        }

        $media = Asset::with('file')->find($assetId)?->file;

        if (! $media) {
            return null;
        }

        // Default to 'large' when no conversion is specified — never serve
        // the original full-size image, which would lag the storefront.
        $conversion = $conversion ?: 'large';

        return app(MediaUrl::class)->conversion($media, $conversion);
    }

    /**
     * Browse the library: assets that have a media file, with optional
     * type/folder/name filters and an order. The file manager page and its
     * embedded picker both list through this, so they list the same thing the
     * same way.
     *
     * `folder` takes a slug, or {@see UNFILED} for files in no folder. An
     * unknown `type` or `sort` falls back to "any" / newest.
     *
     * @param  array{type?:string|null,folder?:string|null,search?:string|null,sort?:string|null}  $filters
     */
    public function browse(array $filters = []): Builder
    {
        $type = in_array($filters['type'] ?? null, self::TYPES, true) ? $filters['type'] : null;
        $folder = $filters['folder'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));

        $query = Asset::query()
            ->whereHas('file')
            ->with('file')
            ->when($type, fn ($q) => $q->whereHas(
                'file',
                fn ($m) => $m->where('custom_properties->type', $type),
            ))
            ->when($folder === self::UNFILED, fn ($q) => $q->whereHas(
                'file',
                fn ($m) => $m->whereNull('custom_properties->folder'),
            ))
            ->when($folder && $folder !== self::UNFILED, fn ($q) => $q->whereHas(
                'file',
                fn ($m) => $m->where('custom_properties->folder', $folder),
            ))
            ->when($search !== '', fn ($q) => $q->whereHas(
                'file',
                fn ($m) => $m->where('name', 'like', '%'.$search.'%'),
            ));

        return match ($filters['sort'] ?? null) {
            'oldest' => $query->oldest('id'),
            // Name lives on the media row, so order by a subquery rather than a
            // join that would duplicate the eager-loaded `file` columns.
            'name' => $query->orderBy(
                Media::query()->select('name')
                    ->whereColumn('model_id', (new Asset)->getTable().'.id')
                    ->where('model_type', (new Asset)->getMorphClass())
                    ->limit(1),
            )->orderBy('id'),
            default => $query->latest('id'),
        };
    }

    /**
     * Distinct folder names in the library, for filter dropdowns.
     *
     * @return array<string, string>
     */
    public function folders(): array
    {
        return collect($this->folderCounts())
            ->mapWithKeys(fn (int $count, string $folder) => [$folder => $folder])
            ->all();
    }

    /**
     * Every folder in the library with how many files it holds, by name.
     *
     * Counted in PHP from the one JSON column rather than with a
     * JSON_EXTRACT GROUP BY: a single-store library is hundreds of rows, and
     * this stays portable across the database drivers the tests run on.
     *
     * @return array<string, int>
     */
    public function folderCounts(): array
    {
        return $this->libraryMedia()
            ->whereNotNull('custom_properties->folder')
            ->pluck('custom_properties')
            ->map(fn ($p) => $p['folder'] ?? null)
            ->filter()
            ->countBy()
            ->sortKeys()
            ->all();
    }

    /**
     * Presentation payload for one library asset id: everything a picker
     * thumbnail needs (type, name, size, preview URL) or null when the id no
     * longer resolves to a library file.
     *
     * @return array<string, mixed>|null same shape as {@see previewOf()}
     */
    public function preview(int|string|null $assetId): ?array
    {
        if (! $assetId) {
            return null;
        }

        $asset = Asset::with('file')->find($assetId);
        $media = $asset?->file;

        if (! $asset || ! $media) {
            return null;
        }

        return $this->previewOf($asset->id, $media);
    }

    /**
     * Presentation payload for an already-loaded asset + media item — same shape
     * as {@see preview()} without the lookup, for lists that eager-loaded `file`.
     *
     * `url` is the original upload — for "open original" and nothing else.
     * `large` is what anything that embeds the file should use (an <img> in
     * page content): the storefront never serves the full-size original.
     *
     * @return array{id:int,name:string,file_name:string,type:string,mime:?string,size:string,bytes:int,url:?string,thumb:?string,large:?string,alt:?string,title:?string,folder:?string,created_at:?string}
     */
    public function previewOf(int $assetId, Media $media): array
    {
        $type = $media->getCustomProperty('type') ?? $this->typeOf($media);
        $url = $media->getUrl();
        $urls = app(MediaUrl::class);

        return [
            'id' => $assetId,
            'name' => (string) $media->name,
            'file_name' => (string) $media->file_name,
            'type' => $type,
            'mime' => $media->mime_type,
            'size' => (string) $media->humanReadableSize,
            'bytes' => (int) $media->size,
            'url' => $url,
            'thumb' => $type === 'image' ? ($urls->conversion($media, 'small') ?: $url) : null,
            'large' => $type === 'image' ? ($urls->conversion($media, 'large') ?: $url) : $url,
            'alt' => $media->getCustomProperty('alt'),
            'title' => $media->getCustomProperty('title'),
            'folder' => $media->getCustomProperty('folder'),
            'created_at' => $media->created_at?->toIso8601String(),
        ];
    }

    /**
     * Logical type bucket for a media item, from its mime type.
     */
    public function typeOf(?Media $media): string
    {
        return $this->classify($media?->mime_type);
    }

    /**
     * Map a MIME type to a logical type bucket.
     */
    public function classify(?string $mime): string
    {
        if ($mime && in_array($mime, self::IMAGE_MIMES, true)) {
            return 'image';
        }

        if ($mime && (in_array($mime, self::VIDEO_MIMES, true) || str_starts_with($mime, 'video/'))) {
            return 'video';
        }

        if ($mime && str_starts_with($mime, 'image/')) {
            return 'image';
        }

        return 'document';
    }

    /**
     * Custom properties stored on the media item.
     *
     * @return array<string, mixed>
     */
    protected function properties(?string $folder, ?string $mime, ?string $sha1 = null): array
    {
        return array_filter([
            'folder' => $this->folderSlug($folder),
            'type' => $this->classify($mime),
            // Lets a later upload of the same bytes find this file instead of
            // becoming a second copy (storeOrReuse()).
            'sha1' => $sha1,
        ], fn ($value) => $value !== null);
    }

    /**
     * Slugged, extension-preserving file name.
     */
    protected function safeFileName(UploadedFile $file): string
    {
        $ext = $file->getClientOriginalExtension();
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';

        return $base.($ext ? '.'.$ext : '');
    }

    /**
     * Ingest a file that already lives on the public `media` disk (e.g. a
     * Filament temp upload, a seed image) into a model's media collection —
     * the shared "path → Spatie media" step used across modules instead of each
     * re-writing `addMedia(Storage::disk('media')->path(...))->toMediaCollection(...)`.
     *
     * By default the source is MOVED (temp upload cleanup); pass
     * $preserveOriginal to copy it instead (seeds / migrations that keep the
     * source). Returns null when the file is missing.
     */
    public function ingestFromDisk(
        HasMedia $model,
        string $relativePath,
        string $collection,
        bool $preserveOriginal = false,
    ): ?Media {
        $relativePath = ltrim($relativePath, '/');

        if (! Storage::disk('media')->exists($relativePath)) {
            return null;
        }

        $adder = $model->addMedia(Storage::disk('media')->path($relativePath));

        if ($preserveOriginal) {
            $adder->preservingOriginal();
        }

        return $adder->toMediaCollection($collection);
    }
}
