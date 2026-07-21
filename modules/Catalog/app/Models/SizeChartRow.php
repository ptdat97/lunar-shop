<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One size row within a SizeChart (e.g. "M" with its measurements). Drives the
 * storefront chart and the size recommender.
 *
 * @property int $id
 * @property int $size_chart_id
 * @property string $size
 * @property ?string $fit
 * @property ?string $bust
 * @property ?string $waist
 * @property ?string $hip
 * @property ?string $shoulder
 * @property ?string $length
 * @property ?string $inseam
 * @property int $sort
 */
class SizeChartRow extends Model
{
    protected $table = 'size_chart_rows';

    protected $fillable = [
        'size_chart_id', 'size', 'fit',
        'bust', 'waist', 'hip', 'shoulder', 'length', 'inseam', 'sort',
    ];

    /** Measurement keys used by the chart / recommender. */
    public const MEASUREMENTS = ['bust', 'waist', 'hip', 'shoulder', 'length', 'inseam'];

    public function sizeChart(): BelongsTo
    {
        return $this->belongsTo(SizeChart::class);
    }

    /**
     * Numeric value of a measurement (mid-point if a "min-max" range).
     */
    public function numeric(string $key): ?float
    {
        $raw = $this->{$key} ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        preg_match_all('/\d+(?:\.\d+)?/', (string) $raw, $m);

        if (empty($m[0])) {
            return null;
        }

        $nums = array_map('floatval', $m[0]);

        return array_sum($nums) / count($nums);
    }
}
