<?php

namespace Modules\Assets\Http\Controllers\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Lunar\Core\Models\Asset;
use Modules\Assets\Services\LibraryLinks;
use Modules\Assets\Services\MediaLibraryService;

/**
 * The JSON API behind the file manager — the one place the panel uploads,
 * edits and deletes library files.
 *
 * JSON rather than Inertia: the manager is a single page that keeps its
 * selection, scroll and upload queue while it lists, uploads and edits.
 * Round-tripping every action through an Inertia visit would reset all three.
 *
 * Image fields no longer talk to this directly. They open the file manager
 * (MediaManagerController::picker) and get back the file the admin chose — so
 * listing, uploading and folder rules live here once, not once per picker.
 */
class MediaLibraryController extends Controller
{
    /** A multiple of the 2-to-6-column grid, so the last row is full. */
    public const PER_PAGE = 48;

    public function __construct(
        protected MediaLibraryService $library,
        protected LibraryLinks $links,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in([...MediaLibraryService::TYPES, 'all'])],
            'folder' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(MediaLibraryService::SORTS)],
        ]);

        $assets = $this->library->browse($filters)->paginate(self::PER_PAGE);

        // How many gallery images each file backs — one query for the page.
        // Deleting a file takes it out of those galleries, so the manager
        // shows the number before anyone presses Delete.
        $used = $this->links->usageCounts(collect($assets->items())->pluck('id')->all());

        return response()->json([
            // previewOf(), not preview(): browse() already eager-loaded the
            // file, and preview() would re-query one asset at a time.
            'items' => collect($assets->items())
                ->map(fn (Asset $asset) => [
                    ...$this->library->previewOf($asset->id, $asset->file),
                    'used' => $used[$asset->id] ?? 0,
                ])
                ->values()
                ->all(),
            // A list, not a name → count map: an empty PHP array encodes as
            // `[]` and a filled one as `{}`, and the client would have to cope
            // with both.
            'folders' => collect($this->library->folderCounts())
                ->map(fn (int $count, string $name) => ['name' => $name, 'count' => $count])
                ->values()
                ->all(),
            'total' => $assets->total(),
            'page' => $assets->currentPage(),
            'lastPage' => $assets->lastPage(),
        ]);
    }

    /**
     * One file's preview, by Asset id.
     *
     * An image field asks for this itself rather than having every payload
     * carry resolved previews: the field can sit at any depth (a hero slide, a
     * lookbook photo, a menu banner), and plumbing previews down through nested
     * repeaters would mean every controller knowing about media.
     *
     * `{assetId}`, not `{asset}`: Lunar binds an Asset model to that name, and
     * this has to answer 404 for an id whose file is gone — a row can still
     * hold one.
     */
    public function show(int $assetId): JsonResponse
    {
        $preview = $this->library->preview($assetId);

        if (! $preview) {
            return response()->json(null, 404);
        }

        $usages = $this->links->usages(Asset::findOrFail($assetId));

        return response()->json([...$preview, 'used' => count($usages), 'usages' => $usages]);
    }

    /**
     * One file per request. The manager uploads a dropped batch as parallel
     * requests, so each file gets its own progress bar and one bad file fails
     * alone instead of taking the batch down with it.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => $this->fileRules(),
            'folder' => ['nullable', 'string', 'max:255'],
        ]);

        $asset = $this->library->store($request->file('file'), $request->input('folder'));

        return response()->json($this->library->preview($asset->id), 201);
    }

    public function update(Request $request, int $assetId): JsonResponse
    {
        $asset = $this->asset($assetId);

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'folder' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $asset = $this->library->update($asset, $data);

        return response()->json([
            ...$this->library->previewOf($asset->id, $asset->file),
            'used' => $this->links->usageCounts([$asset->id])[$asset->id] ?? 0,
        ]);
    }

    /**
     * Swap the file behind an asset. The id stays, so every banner, page and
     * menu item that points at it shows the new picture without being edited.
     */
    public function replace(Request $request, int $assetId): JsonResponse
    {
        $asset = $this->asset($assetId);

        $request->validate(['file' => $this->fileRules()]);

        $asset = $this->library->replace($asset, $request->file('file'));

        return response()->json([
            ...$this->library->preview($asset->id),
            'used' => $this->links->usageCounts([$asset->id])[$asset->id] ?? 0,
        ]);
    }

    public function move(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
            'folder' => ['nullable', 'string', 'max:255'],
        ]);

        $moved = $this->library->move($data['ids'], $data['folder'] ?? null);

        return response()->json(['moved' => $moved]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        // Counted before: deleting takes each file out of its galleries first.
        $unlinked = array_sum($this->links->usageCounts($data['ids']));

        return response()->json([
            'deleted' => $this->library->deleteMany($data['ids']),
            'unlinked' => $unlinked,
        ]);
    }

    public function renameFolder(Request $request, string $folder): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $slug = $this->library->renameFolder($folder, $data['name']);

        // "!!!" passes `required` but slugs to nothing — which would quietly
        // move every file in the folder out of all folders.
        if ($slug === null) {
            $message = __('admin.file_manager.folder_invalid');

            return response()->json(['message' => $message, 'errors' => ['name' => [$message]]], 422);
        }

        return response()->json(['folder' => $slug]);
    }

    /**
     * Images only, capped at Lunar's own upload ceiling — the number the
     * panel's product gallery enforces too, so the two cannot drift apart.
     *
     * Laravel's `image` rule no longer admits SVG, which is the point: an SVG
     * served from the shop's own origin is a script.
     *
     * @return array<int, string>
     */
    protected function fileRules(): array
    {
        return ['required', 'file', 'image', 'max:'.(int) config('lunar.media.max_upload_kb', 8192)];
    }

    protected function asset(int $assetId): Asset
    {
        return Asset::query()->whereHas('file')->with('file')->findOrFail($assetId);
    }
}
