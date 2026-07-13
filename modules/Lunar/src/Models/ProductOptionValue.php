<?php

namespace Lunar\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Lunar\Base\BaseModel;
use Lunar\Base\Traits\HasMacros;
use Lunar\Base\Traits\HasMedia;
use Lunar\Base\Traits\HasTranslations;
use Lunar\Database\Factories\ProductOptionValueFactory;
use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;

/**
 * @property int $id
 * @property int $product_option_id
 * @property AsArrayObject $name
 * @property int $position
 * @property ?AsArrayObject $meta
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class ProductOptionValue extends BaseModel implements Contracts\ProductOptionValue, SpatieHasMedia
{
    use HasFactory;
    use HasMacros;
    use HasMedia;
    use HasTranslations;

    /**
     * Media collection holding the single swatch image for this value
     * (registered in Base\OptionValueMediaDefinitions).
     */
    public const SWATCH_COLLECTION = 'swatch';

    /**
     * Define which attributes should be cast.
     *
     * @var array
     */
    protected $casts = [
        'name' => AsArrayObject::class,
        'meta' => AsArrayObject::class,
    ];

    /**
     * Define which attributes should be
     * protected from mass assignment.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ProductOptionValueFactory::new();
    }

    /**
     * Hex display colour for this value (e.g. '#1a1a1a'), stored in meta so
     * no schema change is needed. Null when the value has no colour swatch.
     */
    public function getSwatchColorAttribute(): ?string
    {
        return data_get($this->meta, 'swatch') ?: null;
    }

    public function setSwatchColorAttribute(?string $hex): void
    {
        $meta = collect($this->meta ?? [])->toArray();
        $meta['swatch'] = $hex ?: null;

        $this->meta = $meta;
    }

    /**
     * URL of the swatch image, if one was uploaded. Storefront pickers should
     * prefer this over swatch_color when both exist. When a conversion is
     * requested but not generated yet (queued), falls back to the original.
     */
    public function swatchImageUrl(string $conversion = ''): ?string
    {
        $media = $this->getFirstMedia(self::SWATCH_COLLECTION);

        if (! $media) {
            return null;
        }

        return $conversion
            ? $media->getAvailableUrl([$conversion])
            : $media->getUrl();
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::modelClass(), 'product_option_id');
    }

    public function variants(): BelongsToMany
    {
        $prefix = config('lunar.database.table_prefix');

        return $this->belongsToMany(
            ProductVariant::modelClass(),
            "{$prefix}product_option_value_product_variant",
            'value_id',
            'variant_id',
        );
    }
}
