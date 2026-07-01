<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable size chart (e.g. "Women's Tops"). Defined once and assigned to
 * many products, so staff pick an existing chart instead of re-entering
 * measurements per product.
 *
 * @property int $id
 * @property string $name
 * @property ?string $category
 * @property bool $active
 */
class SizeChart extends Model
{
    protected $table = 'size_charts';

    protected $fillable = ['name', 'category', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function rows(): HasMany
    {
        return $this->hasMany(SizeChartRow::class)->orderBy('sort');
    }
}
