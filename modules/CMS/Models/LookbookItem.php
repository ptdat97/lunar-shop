<?php

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LookbookItem extends Model
{
    protected $fillable = [
        'lookbook_id',
        'product_id',
        'caption',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }

    public function lookbook(): BelongsTo
    {
        return $this->belongsTo(Lookbook::class);
    }
}