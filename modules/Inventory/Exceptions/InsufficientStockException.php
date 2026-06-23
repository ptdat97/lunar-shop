<?php

namespace Modules\Inventory\Exceptions;

use RuntimeException;

/**
 * Thrown when an order would oversell a variant (requested quantity exceeds
 * available inventory and the variant isn't set to backorder/always).
 */
class InsufficientStockException extends RuntimeException
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
}
