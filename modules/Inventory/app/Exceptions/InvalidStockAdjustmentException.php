<?php

namespace Modules\Inventory\Exceptions;

use Modules\Core\Support\ApiErrorResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Thrown when a manual stock adjustment would leave the SKU in a state the shop
 * cannot honour:
 *
 *  - below zero — a negative on-hand count is always a mistake; and
 *  - below `committed` — the units are already sold and awaiting dispatch, so
 *    setting the shelf under that number describes goods that have been
 *    promised but cannot be shipped.
 *
 * Either way we refuse and leave the stock untouched. (A sale is different:
 * backorder/always SKUs may go negative on purpose, and that path does not come
 * through here.)
 *
 * A 422 (client error) via the shared api/v1 envelope ({@see ApiErrorResponse}).
 */
class InvalidStockAdjustmentException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(
        public readonly int $variantId,
        public readonly int $current,
        public readonly int $delta,
        public readonly ?int $committed = null,
    ) {
        $target = $current + $delta;

        parent::__construct(
            $committed !== null
                ? "Stock adjustment would leave variant #{$variantId} at {$target}, "
                    ."below the {$committed} unit(s) already committed to orders."
                : "Stock adjustment would make variant #{$variantId} negative: {$current} {$delta}."
        );
    }

    public function getStatusCode(): int
    {
        return 422;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return [];
    }
}
