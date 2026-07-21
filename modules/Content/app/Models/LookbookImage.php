<?php

namespace Modules\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LookbookImage extends Model
{
    protected $fillable = [
        'lookbook_id',
        'image',
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
