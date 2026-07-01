<?php

namespace Modules\Assets\Services;

use Illuminate\Http\UploadedFile;
use Lunar\Models\Asset;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Media Library logic built on Lunar's Asset model + Spatie MediaLibrary
 * (the media stack that ships with Lunar — see lunarphp_sme_fashion_plan.md).
 *
 * Each library file is a standalone Lunar Asset with a single attached Media
 * item (collection "images"). Conversions (thumb/webp/large/zoom…) are produced
 * by the configured FashionMediaDefinitions for the "asset" type. Logical
 * grouping (folder) and accessibility metadata (alt/title) live in the media's
 * custom_properties.
 */
class FileManager
{
    /** Accepted MIME prefixes/extensions grouped by logical type. */
    protected const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif', 'image/svg+xml'];

    protected const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];

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
            ->withCustomProperties($this->properties($folder, $file->getMimeType()))
            ->toMediaCollection($this->collection());

        return $asset->fresh();
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

        $props = $this->properties($folder, $mime);
        foreach (['alt', 'title'] as $key) {
            if (! empty($meta[$key])) {
                $props[$key] = $meta[$key];
            }
        }

        $asset = Asset::create([]);

        $asset->addMedia($path)
            ->preservingOriginal()
            ->usingFileName(\Illuminate\Support\Str::slug(pathinfo($path, PATHINFO_FILENAME)) . '.' . pathinfo($path, PATHINFO_EXTENSION))
            ->usingName($name)
            ->withCustomProperties($props)
            ->toMediaCollection($this->collection());

        return $asset->fresh();
    }

    /**
     * Replace the binary of an existing asset's media with a new upload.
     * The old media item is removed and a new one attached, keeping the same
     * Asset id — so picker references (which store the Asset id) stay valid.
     */
    public function replace(Asset $asset, UploadedFile $file): Asset
    {
        $media = $asset->file;
        $props = $media ? $media->custom_properties : [];

        // Preserve folder/alt/title, refresh the type from the new mime.
        $props['type'] = $this->classify($file->getMimeType());

        $asset->clearMediaCollection($this->collection());

        $asset->addMedia($file->getRealPath())
            ->preservingOriginal()
            ->usingFileName($this->safeFileName($file))
            ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            ->withCustomProperties($props)
            ->toMediaCollection($this->collection());

        return $asset->fresh();
    }

    /**
     * Update metadata (name, alt, title, folder) on the attached media.
     */
    public function updateMeta(Asset $asset, array $data): Asset
    {
        $media = $asset->file;

        if (! $media) {
            return $asset;
        }

        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $media->name = $data['name'];
        }

        foreach (['alt', 'title', 'folder'] as $key) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key] !== null && $data[$key] !== '' ? $data[$key] : null;
                $media->setCustomProperty($key, $value);
            }
        }

        $media->save();

        return $asset->fresh();
    }

    /**
     * Delete the asset (its media + files cascade via Spatie).
     */
    public function delete(Asset $asset): void
    {
        $asset->delete();
    }

    /**
     * Resolve a picker value (Asset id) to a public URL, optionally for a
     * named conversion (e.g. 'thumb', 'webp', 'large'). Returns null if the
     * asset/media or conversion is missing. Use this anywhere a MediaPicker
     * value is rendered (storefront, API resources).
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

        if ($conversion && in_array($conversion, array_keys($media->generated_conversions ?? []), true)) {
            return $media->getUrl($conversion);
        }

        return $media->getUrl();
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
    protected function properties(?string $folder, ?string $mime): array
    {
        return [
            'folder' => $folder ? \Illuminate\Support\Str::slug($folder) : null,
            'type' => $this->classify($mime),
        ];
    }

    /**
     * Slugged, extension-preserving file name.
     */
    protected function safeFileName(UploadedFile $file): string
    {
        $ext = $file->getClientOriginalExtension();
        $base = \Illuminate\Support\Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';

        return $base . ($ext ? '.' . $ext : '');
    }
}
