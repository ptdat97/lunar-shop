<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Product;

/**
 * Fabric / care information for a product (shown alongside the size chart).
 *
 * @property int $id
 * @property int $product_id
 * @property ?string $material
 * @property ?string $composition
 * @property ?string $care_instruction
 * @property ?string $fabric_weight
 * @property ?string $stretch
 * @property ?string $transparency
 * @property ?string $lining
 */
class ProductMaterial extends Model
{
    protected $table = 'product_materials';

    protected $fillable = [
        'product_id',
        'material',
        'composition',
        'care_instruction',
        'fabric_weight',
        'stretch',
        'transparency',
        'lining',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
