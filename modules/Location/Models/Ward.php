<?php

namespace Modules\Location\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ward extends Model
{
    protected $table = 'vn_wards';

    protected $fillable = ['province_id', 'code', 'name'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
