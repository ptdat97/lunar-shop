<?php

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Product;

class LookbookItem extends Model
{
    protected $fillable = [
        'lookbook_id',
        'product_id',
        'caption',
        'sort',
        'pos_x',
        'pos_y',
        'image_id',
    ];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'pos_x' => 'float',
            'pos_y' => 'float',
        ];
    }

    /**
     * Whether this item is placed as a hotspot pin on a photo.
     */
    public function isHotspot(): bool
    {
        return $this->pos_x !== null && $this->pos_y !== null;
    }

    public function lookbook(): BelongsTo
    {
        return $this->belongsTo(Lookbook::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(LookbookImage::class);
    }
}