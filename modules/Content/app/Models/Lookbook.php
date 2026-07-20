<?php

namespace Modules\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Lookbook extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'cover_image',
        'description',
        'published',
    ];

    protected function casts(): array
    {
        return [
            'published' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function items(): HasMany
    {
        return $this->hasMany(LookbookItem::class)->orderBy('sort');
    }

    public function images(): HasMany
    {
        return $this->hasMany(LookbookImage::class)->orderBy('sort');
    }

    public function scopePublished($query)
    {
        return $query->where('published', true);
    }

    protected static function booted(): void
    {
        static::creating(function (self $lookbook) {
            if (blank($lookbook->slug)) {
                $lookbook->slug = Str::slug($lookbook->title);
            }
        });
    }
}
