<?php

namespace Modules\Order\Support;

/**
 * The return/RMA lifecycle statuses, published for other modules.
 *
 * Same reason {@see OrderStatus} exists: Catalog's fit history needs to know
 * which returns still count (a rejected one releases its claim), and reaching
 * for `Modules\Order\Models\ReturnRequest` to read a constant would couple it to
 * Order's persistence layer (§10 — cross-module traffic goes through the owning
 * module's service or Support, never its models).
 *
 * `ReturnRequest`'s own constants alias these, so the two cannot drift.
 */
class ReturnStatus
{
    /** Opened by the customer, awaiting staff review. */
    public const REQUESTED = 'requested';

    /** Staff accepted it; the goods are coming back (or already have). */
    public const APPROVED = 'approved';

    /** Staff refused it. A rejected request releases the units it claimed. */
    public const REJECTED = 'rejected';

    /** Money returned to the customer. */
    public const REFUNDED = 'refunded';

    public const COMPLETED = 'completed';
}
