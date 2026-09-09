<?php

namespace Modules\Assets\Http\Controllers\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Assets\Services\MediaLibraryService;

/**
 * The media library behind the panel's image fields.
 *
 * Lunar's panel manages media that belongs to a record — a product's gallery, a
 * collection's thumbnail. This shop also has images that belong to nothing in
 * particular: a banner's artwork, a page's hero, a lookbook photo, all stored
 * as a path on a row. Those need a library to pick from, which is what this is.
 *
 * JSON rather than Inertia: it answers a picker inside another page, not a page
 * of its own.
 */
class MediaBrowserController extends Controller
{
    public function __construct(protected MediaLibraryService $library) {}

    public function index(Request $request): JsonResponse
    {
        $assets = $this->library
            ->browse([
                'type' => $request->query('type', 'image'),
                'folder' => $request->query('folder'),
                'search' => $request->query('search'),
            ])
            ->paginate(24);

        return response()->json([
            // previewOf(), not preview(): browse() already eager-loaded the
            // file, and preview() would re-query one asset at a time.
            'items' => collect($assets->items())
                ->map(fn ($asset) => $this->library->previewOf($asset->id, $asset->file))
                ->values()
                ->all(),
            'folders' => $this->library->folders(),
            'page' => $assets->currentPage(),
            'lastPage' => $assets->lastPage(),
        ]);
    }

    /**
     * One asset's preview.
     *
     * The picker asks for this itself rather than having every payload carry
     * resolved previews: an image field can sit at any depth (a hero slide, a
     * lookbook photo, a menu banner), and plumbing previews down through nested
     * repeaters would mean every controller knowing about media.
     */
    public function show(int $assetId): JsonResponse
    {
        $preview = $this->library->preview($assetId);

        return $preview ? response()->json($preview) : response()->json(null, 404);
    }

    /**
     * Upload straight from the picker. Without it, setting a banner image means
     * leaving the form, uploading elsewhere, and coming back — which is how the
     * old admin worked and why its help text had to say so.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'image', 'max:10240'],
            'folder' => ['nullable', 'string', 'max:255'],
        ]);

        $asset = $this->library->store($request->file('file'), $request->input('folder'));

        return response()->json($this->library->preview($asset->id), 201);
    }
}
