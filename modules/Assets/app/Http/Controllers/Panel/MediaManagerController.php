<?php

namespace Modules\Assets\Http\Controllers\Panel;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Assets\Services\MediaLibraryService;

/**
 * The file manager: the one place images enter the shop's library.
 *
 * It renders twice, from the same Vue component:
 *
 * - `index` — a page of its own in the sidebar, for uploading, sorting into
 *   folders, editing alt text and deleting.
 * - `picker` — the same manager without the panel chrome, loaded in an iframe
 *   by every image field. The field gets back the file the admin chose (via
 *   postMessage) and never uploads or lists anything itself.
 *
 * One manager instead of a browse dialog per field is the point: upload rules,
 * folders and metadata exist once, and a file uploaded while filling in a
 * banner lands in the same library as one uploaded from this page.
 */
class MediaManagerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('shop/media/Index', $this->props());
    }

    /**
     * Query: `type` (image|video|document|all, default image), `multiple`
     * (0/1), `folder` (slug to open on), `upload_folder` (where uploads go
     * while no folder is open — the opener's context, e.g. `products`),
     * `channel` (echoed back in the postMessage so the opener can tell its
     * picker from another).
     */
    public function picker(Request $request): Response
    {
        // Query strings only. `?folder[]=x` arrives as an array, and handing
        // that to a string helper is a 500 instead of an ignored parameter.
        $query = fn (string $key): string => is_string($value = $request->query($key)) ? $value : '';
        $type = $query('type');

        return Inertia::render('shop/media/Picker', [
            ...$this->props(),
            'pick' => [
                'type' => in_array($type, [...MediaLibraryService::TYPES, 'all'], true) ? $type : 'image',
                'multiple' => $request->boolean('multiple'),
                'folder' => $query('folder'),
                'uploadFolder' => (string) app(MediaLibraryService::class)->folderSlug($query('upload_folder')),
                'channel' => $query('channel'),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    protected function props(): array
    {
        return [
            'labels' => __('admin.file_manager'),
            // One base URL; the client appends `/files`, `/files/{id}` and
            // `/folders/{name}`. AssetsSection declares that layout, and the
            // route names stay the contract on the PHP side.
            'base' => route('panel.shop.media.index'),
            'maxUploadKb' => (int) config('lunar.media.max_upload_kb', 8192),
            'unfiled' => MediaLibraryService::UNFILED,
        ];
    }
}
