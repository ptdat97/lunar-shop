<?php

namespace Modules\Inventory\Exceptions;

use Modules\Core\Support\ApiErrorResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Thrown when an order would oversell a variant (requested quantity exceeds
 * available inventory and the variant isn't set to backorder/always).
 *
 * A 422, not a 500: someone else took the last units while this shopper's cart
 * sat there, which is their problem to solve, not a server fault. Implementing
 * HttpExceptionInterface is enough for the shared `api/v1` error envelope
 * ({@see ApiErrorResponse}) to carry the message through
 * with the right status.
 */
class InsufficientStockException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(
        public readonly int $variantId,
        public readonly int $requested,
        public readonly int $available,
        public readonly string $description = '',
    ) {
        $label = $description !== '' ? $description : "variant #{$variantId}";

        parent::__construct(
            "Insufficient stock for {$label}: requested {$requested}, only {$available} available."
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
