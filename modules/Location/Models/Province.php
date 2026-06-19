<?php

namespace Modules\Location\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $table = 'vn_provinces';

    protected $fillable = ['code', 'name'];

    public function wards(): HasMany
    {
        return $this->hasMany(Ward::class)->orderBy('name');
    }
}
