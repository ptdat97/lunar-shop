<?php

namespace Modules\Catalog\Http\Controllers\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Lunar\Core\Models\Asset;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductOptionValue;
use Modules\Assets\Services\LibraryLinks;
use Modules\Assets\Services\MediaUrl;
use Modules\Catalog\Services\VariantImages;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * "Photos by colour" on Lunar's own product editor.
 *
 * A slot in the content column, beside Lunar's gallery. Each colour of the
 * product gets one photo set — picked from the product's gallery or from the
 * shop's file manager — and every variant of that colour receives it through
 * Lunar's own variant-image pivot ({@see VariantImages}).
 *
 * JSON rather than Inertia redirects: the component saves one colour at a time
 * and keeps the rest of the product form — which may hold unsaved edits —
 * exactly as it is.
 */
class ProductColourImagesController extends Controller
{
    public function __construct(
        protected VariantImages $images,
        protected MediaUrl $urls,
    ) {}

    public function show(Product $product): JsonResponse
    {
        return response()->json($this->state($product));
    }

    public function update(Request $request, Product $product, ProductOptionValue $value): JsonResponse
    {
        $axis = $this->images->axis($product);

        abort_unless($axis && $value->product_option_id === $axis->id, 404);
        abort_if($this->images->variantsWith($product, $value)->isEmpty(), 404);

        $media = new Media;

        $request->validate([
            'items' => ['present', 'array', 'max:40'],
            'items.*.media_id' => [
                'required_without:items.*.asset_id', 'integer',
                // Only this product's own gallery — the same rule Lunar's
                // per-variant picker applies.
                Rule::exists($media->getTable(), 'id')
                    ->where('model_type', $product->getMorphClass())
                    ->where('model_id', $product->id)
                    ->where('collection_name', config('lunar.media.collection', 'images')),
            ],
            'items.*.asset_id' => ['required_without:items.*.media_id', 'integer', Rule::exists((new Asset)->getTable(), 'id')],
        ]);

        $this->images->assign($product, $value, $request->input('items', []));

        return response()->json($this->state($product->fresh()));
    }

    /**
     * Labels only — slot props are built on every boot, `migrate` included,
     * so nothing here may touch the database (see ProductSizingController).
     *
     * @return array<string, mixed>
     */
    public static function slotProps(): array
    {
        return [
            'labels' => __('admin.colour_images'),
        ];
    }

    /** @return array<string, mixed> */
    protected function state(Product $product): array
    {
        $axis = $this->images->axis($product);

        return [
            'axis' => $axis ? ['id' => $axis->id, 'name' => (string) $axis->translate('name')] : null,
            'groups' => collect($this->images->groups($product, $axis))
                ->map(fn (array $group) => [
                    'value_id' => $group['value']->id,
                    'name' => (string) $group['value']->translate('name'),
                    'colour' => data_get($group['value']->meta, 'colour'),
                    'variants' => $group['variants']->count(),
                    'mixed' => $group['mixed'],
                    'images' => $group['media']->map(fn (Media $media) => $this->image($media))->all(),
                ])
                ->all(),
            // The product's gallery, to pick from without opening the library.
            'gallery' => $product->getMedia(config('lunar.media.collection', 'images'))
                ->map(fn (Media $media) => $this->image($media))
                ->values()
                ->all(),
        ];
    }

    /** @return array{media_id: int, asset_id: ?int, name: string, thumb: ?string} */
    protected function image(Media $media): array
    {
        return [
            'media_id' => $media->id,
            'asset_id' => $media->getCustomProperty(LibraryLinks::ASSET),
            'name' => (string) $media->name,
            'thumb' => $this->urls->conversion($media, 'small') ?? $media->getUrl(),
        ];
    }
}
