<?php

namespace Modules\Checkout\Data;

/**
 * Outcome of a refund attempt.
 */
class RefundResult
{
    public function __construct(
        public bool $success = false,
        public string $message = '',
        public ?string $driver = null,
        public int $amount = 0,
    ) {}
}
