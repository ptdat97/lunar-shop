<?php

namespace Modules\Payment\Data;

use Lunar\Models\Order;

/**
 * Outcome of reconciling a VNPay callback.
 */
class VNPayResult
{
    public function __construct(
        public bool $verified = false,
        public bool $paid = false,
        public ?Order $order = null,
        public bool $alreadyProcessed = false,
    ) {}
}
