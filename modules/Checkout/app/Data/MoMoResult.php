<?php

namespace Modules\Checkout\Data;

use Lunar\Models\Order;

/**
 * Outcome of reconciling a MoMo callback.
 */
class MoMoResult
{
    public function __construct(
        public bool $verified = false,
        public bool $paid = false,
        public ?Order $order = null,
        public bool $alreadyProcessed = false,
    ) {}
}
