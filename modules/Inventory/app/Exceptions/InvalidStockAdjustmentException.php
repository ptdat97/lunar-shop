<?php

namespace Modules\Inventory\Exceptions;

use Modules\Core\Support\ApiErrorResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Thrown when a manual stock adjustment would drive a variant's stock below
 * zero. Unlike a sale (backorder/always variants may go negative on purpose), a
 * manual correction to a negative on-hand count is always a mistake, so we
 * refuse it and leave the stock untouched.
 *
 * A 422 (client error) via the shared api/v1 envelope ({@see ApiErrorResponse}).
 */
class InvalidStockAdjustmentException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(
        public readonly int $variantId,
        public readonly int $current,
        public readonly int $delta,
    ) {
        parent::__construct(
            "Stock adjustment would make variant #{$variantId} negative: {$current} {$delta}."
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
