<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Core\Models\Product;

/**
 * @property int $product_id
 * @property string $author
 * @property int $rating
 * @property ?string $body
 * @property bool $approved
 */
class Review extends Model
{
    protected $table = 'product_reviews';

    protected $fillable = ['product_id', 'author', 'rating', 'body', 'approved'];

    protected $casts = [
        'rating' => 'int',
        'approved' => 'bool',
    ];

    /**
     * The product being reviewed.
     *
     * The column was always here; the relation was not, because nothing but the
     * storefront's own product-scoped queries ever needed it. The moderation
     * queue does: it lists reviews across every product and has to name each
     * one without a query per row.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
