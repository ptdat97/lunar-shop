<?php

namespace Acme\Preorder\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $product_id
 * @property bool $enabled
 * @property ?\Illuminate\Support\Carbon $expected_at
 */
class Preorder extends Model
{
    protected $table = 'product_preorders';

    protected $fillable = ['product_id', 'enabled', 'expected_at'];

    protected $casts = [
        'enabled' => 'bool',
        'expected_at' => 'date',
    ];
}
