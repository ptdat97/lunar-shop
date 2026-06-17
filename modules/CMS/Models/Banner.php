<?php

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'button_text',
        'button_url',
        'image',
        'mobile_image',
        'position',
        'active',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('active', true)->orderBy('sort');
    }
}