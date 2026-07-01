<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\OrderLine;

/**
 * One line of a return request: how many of a given order line are being
 * returned.
 */
class ReturnRequestLine extends Model
{
    protected $table = 'return_request_lines';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_request_id');
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class, 'order_line_id');
    }
}
