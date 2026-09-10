<?php

namespace Modules\Checkout\Data;

use Lunar\Core\Models\Transaction;

/**
 * Outcome of a refund attempt.
 *
 * `transaction` is the refund row the gateway path recorded. It travels back so
 * the payment type can hand it to Lunar's `RefundOrder`, which needs it to
 * attribute the money to order lines — without it Lunar treats the refund as
 * "recorded, but not attributable" and writes no line allocation at all.
 */
class RefundResult
{
    public function __construct(
        public bool $success = false,
        public string $message = '',
        public ?string $driver = null,
        public int $amount = 0,
        public ?Transaction $transaction = null,
    ) {}
}
