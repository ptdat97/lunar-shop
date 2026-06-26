<?php

namespace Acme\Reviews\Models;

use Illuminate\Database\Eloquent\Model;

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
}
