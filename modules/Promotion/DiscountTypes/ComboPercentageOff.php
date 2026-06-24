<?php

namespace Modules\Promotion\DiscountTypes;

use Illuminate\Support\Collection;
use Lunar\DiscountTypes\AbstractDiscountType;
use Lunar\Models\Contracts\Cart as CartContract;
use Lunar\Base\ValueObjects\Cart\DiscountBreakdown;
use Lunar\Base\ValueObjects\Cart\DiscountBreakdownLine;
use Lunar\DataTypes\Price;

/**
 * "Buy across these groups together, get X% off" — an AUTOMATIC bundle/combo
 * discount (no coupon). Models offers like "Buy a shirt + a trousers, get 15%
 * off both".
 *
 * The cart must contain at least one line from EACH required collection group.
 * Only then is the percentage taken off the matched lines (one cheapest line
 * per group, so a shirt + trousers combo discounts one shirt and one trousers).
 *
 * Lunar has no cross-group "buy A and B together" discount, so this is built
 * new (Principle #1). Configuration lives on `data`:
 *   - `data.combo_collections` — array of Lunar collection ids, one per group
 *   - `data.percentage`        — percentage off the matched lines (default 15)
 */
class ComboPercentageOff extends AbstractDiscountType
{
    public function getName(): string
    {
        return 'Combo Discount (Buy across groups, get X% off)';
    }

    public function apply(CartContract $cart): CartContract
    {
        if (! $this->checkDiscountConditions($cart)) {
            return $cart;
        }

        $data = $this->discount->data ?? [];
        $groups = collect($data['combo_collections'] ?? [])->filter()->values();
        $percentage = (float) ($data['percentage'] ?? 0);

        if ($groups->count() < 2 || $percentage <= 0) {
            return $cart;
        }

        // For each required collection group, pick the cheapest eligible line.
        // If any group is unrepresented, the combo isn't complete — bail.
        $matchedLines = collect();

        foreach ($groups as $collectionId) {
            $linesInGroup = $cart->lines->filter(
                fn ($line) => $line->purchasable->product->collections
                    ->pluck('id')->contains((int) $collectionId)
            );

            if ($linesInGroup->isEmpty()) {
                return $cart; // combo not satisfied
            }

            // Cheapest unit so the discount is the conservative "one of each".
            $cheapest = $linesInGroup->sortBy(fn ($line) => $line->unitPrice->value)->first();

            // Avoid double-counting a line that satisfies two groups.
            if (! $matchedLines->contains(fn ($l) => $l->id === $cheapest->id)) {
                $matchedLines->push($cheapest);
            }
        }

        $affectedLines = collect();
        $totalDiscount = 0;

        foreach ($matchedLines as $line) {
            // Discount a single unit of each matched line (the combo item).
            $unit = $line->unitPrice->value;
            $existing = $line->discountTotal?->value ?: 0;
            $amount = (int) round($unit * ($percentage / 100));

            if ($existing > $amount) {
                continue;
            }

            $totalDiscount += $amount;

            $subTotal = $line->subTotalDiscounted?->value ?: $line->subTotal->value;
            $line->discountTotal = new Price($existing + $amount, $cart->currency, 1);
            $line->subTotalDiscounted = new Price(max(0, $subTotal - $amount), $cart->currency, 1);

            $affectedLines->push(new DiscountBreakdownLine(
                line: $line,
                quantity: 1,
            ));
        }

        if ($totalDiscount <= 0) {
            return $cart;
        }

        if (! $cart->discounts) {
            $cart->discounts = collect();
        }
        $cart->discounts->push($this);

        $this->addDiscountBreakdown($cart, new DiscountBreakdown(
            price: new Price($totalDiscount, $cart->currency, 1),
            lines: $affectedLines,
            discount: $this->discount,
        ));

        return $cart;
    }
}
