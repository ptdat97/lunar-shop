<?php

namespace Modules\Catalog\Services;

use Lunar\Models\Product;
use Modules\Catalog\Models\SizeChartRow;

/**
 * Fashion Size Intelligence: recommends the best-fitting size for a product
 * given a shopper's body measurements, using the product's assigned reusable
 * size chart. Lunar has no sizing logic, so this is a fashion-specific addition
 * (see lunarphp_sme_fashion_plan.md, module Product).
 *
 * Strategy: compare the shopper's body (plus a small ease allowance) against
 * each chart row's garment measurements and score the fit. Being smaller than
 * the body is penalised harder than being slightly roomy.
 */
class SizeRecommender
{
    /**
     * Ease (cm) added to the body before matching. Charts typically list the
     * recommended body measurements per size (the range a wearer should fall
     * in), so we match the body directly and keep ease at zero. Adjust per
     * brand if your charts store garment (flat) measurements instead.
     */
    protected const EASE = [
        'bust' => 0.0,
        'waist' => 0.0,
        'hip' => 0.0,
        'shoulder' => 0.0,
        'length' => 0.0,
        'inseam' => 0.0,
    ];

    public function __construct(
        protected SizeChartService $charts,
    ) {}

    /**
     * Recommend a size for a product from body measurements.
     *
     * @param  array<string, float|int|string|null>  $body  e.g. ['bust'=>90,'waist'=>72,'hip'=>96]
     * @return array{
     *     recommended: ?array{size:string,fit:?string,score:float,confidence:string},
     *     alternatives: array<int, array{size:string,score:float}>,
     *     measured: array<string, float>
     * }
     */
    public function recommend(Product $product, array $body): array
    {
        $body = $this->normaliseBody($body);

        $chart = $this->charts->chartFor($product);
        $rows = $chart ? $chart->rows : collect();

        $scored = [];

        foreach ($rows as $row) {
            $score = $this->scoreRow($row, $body);

            if ($score === null) {
                continue;
            }

            $scored[] = [
                'size' => $row->size,
                'fit' => $row->fit,
                'score' => round($score, 2),
            ];
        }

        usort($scored, fn ($a, $b) => $a['score'] <=> $b['score']);

        $best = $scored[0] ?? null;

        return [
            'recommended' => $best ? [...$best, 'confidence' => $this->confidence($best['score'])] : null,
            'alternatives' => array_map(
                fn ($s) => ['size' => $s['size'], 'score' => $s['score']],
                array_slice($scored, 1, 3),
            ),
            'measured' => $body,
        ];
    }

    /**
     * Fit score for one chart row (lower = closer). Null if nothing comparable.
     */
    protected function scoreRow(SizeChartRow $row, array $body): ?float
    {
        $total = 0.0;
        $compared = 0;

        foreach ($body as $key => $value) {
            $garment = $row->numeric($key);

            if ($garment === null) {
                continue;
            }

            $ideal = $value + (self::EASE[$key] ?? 0.0);
            $diff = $garment - $ideal;

            // Too tight (garment smaller than ideal) is penalised 1.6x.
            $total += $diff < 0 ? abs($diff) * 1.6 : $diff;
            $compared++;
        }

        return $compared > 0 ? $total / $compared : null;
    }

    protected function confidence(float $score): string
    {
        return match (true) {
            $score <= 2.0 => 'high',
            $score <= 5.0 => 'medium',
            default => 'low',
        };
    }

    /**
     * @return array<string, float>
     */
    protected function normaliseBody(array $body): array
    {
        $out = [];

        foreach (SizeChartRow::MEASUREMENTS as $key) {
            if (isset($body[$key]) && is_numeric($body[$key])) {
                $out[$key] = (float) $body[$key];
            }
        }

        return $out;
    }
}
