<?php

namespace Lunar\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lunar\Base\BaseModel;
use Lunar\Base\Traits\HasMacros;
use Lunar\Base\Traits\HasMedia;
use Lunar\Base\Traits\HasTranslations;
use Lunar\Base\Traits\LogsActivity;
use Lunar\Base\Traits\Searchable;
use Lunar\Database\Factories\ProductOptionFactory;
use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;

/**
 * @property int $id
 * @property AsArrayObject $name
 * @property ?AsArrayObject $label
 * @property int $position
 * @property ?string $handle
 * @property bool $shared
 * @property ?AsArrayObject $meta
 * @property string $display_type
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
class ProductOption extends BaseModel implements Contracts\ProductOption, SpatieHasMedia
{
    use HasFactory;
    use HasMacros;
    use HasMedia;
    use HasTranslations;
    use LogsActivity;
    use Searchable;

    /**
     * How an option renders in the storefront picker and the admin variant
     * builder: plain text chips, a hex colour swatch (per-value colour picker
     * + optional swatch image), or an image swatch (per-value image only).
     * Configured per option on the Product Options admin page — nothing is
     * keyed to a hardcoded handle.
     */
    public const DISPLAY_TYPES = ['text', 'color', 'image'];

    /** Expose display_type on toArray() so Filament forms can fill it. */
    protected $appends = ['display_type'];

    /**
     * Define which attributes should be cast.
     *
     * @var array
     */
    protected $casts = [
        'name' => AsArrayObject::class,
        'label' => AsArrayObject::class,
        'shared' => 'boolean',
        'meta' => AsArrayObject::class,
    ];

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ProductOptionFactory::new();
    }

    /**
     * Define which attributes should be
     * protected from mass assignment.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Display type, stored in meta so no schema change is needed (mirrors
     * ProductOptionValue::swatch_color). Unknown/absent → 'text'.
     */
    public function getDisplayTypeAttribute(): string
    {
        $type = data_get($this->meta, 'display_type');

        return in_array($type, self::DISPLAY_TYPES, true) ? $type : 'text';
    }

    public function setDisplayTypeAttribute(?string $type): void
    {
        $meta = collect($this->meta ?? [])->toArray();
        $meta['display_type'] = in_array($type, self::DISPLAY_TYPES, true) ? $type : 'text';

        $this->meta = $meta;
    }

    public function scopeShared(Builder $builder): Builder
    {
        return $builder->where('shared', '=', true);
    }

    public function scopeExclusive(Builder $builder): Builder
    {
        return $builder->where('shared', '=', false);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductOptionValue::modelClass())->orderBy('position');
    }

    public function products(): BelongsToMany
    {
        $prefix = config('lunar.database.table_prefix');

        return $this->belongsToMany(
            Product::modelClass(),
            "{$prefix}product_product_option"
        )->withPivot(['position'])->orderByPivot('position');
    }
}
