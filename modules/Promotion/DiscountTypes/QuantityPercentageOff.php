<?php

namespace Modules\Promotion\DiscountTypes;

use Illuminate\Support\Collection;
use Lunar\DiscountTypes\AbstractDiscountType;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Contracts\Cart as CartContract;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Base\ValueObjects\Cart\DiscountBreakdown;
use Lunar\Base\ValueObjects\Cart\DiscountBreakdownLine;
use Lunar\DataTypes\Price;

/**
 * "Buy N or more, get X% off" — an AUTOMATIC quantity-threshold percentage
 * discount (no coupon). Models offers like "Buy 2 get 10% off".
 *
 * Lunar ships BuyXGetY (which discounts the *reward* lines fully) and AmountOff
 * (percentage off, but with no minimum-quantity gate). Neither expresses
 * "once the eligible quantity reaches N, take X% off all eligible lines", so
 * this type fills that gap. It reuses Lunar's eligibility model:
 *   - `data.min_qty`     — quantity threshold across eligible lines (default 2)
 *   - `data.percentage`  — percentage to take off eligible lines (default 10)
 * Eligibility is scoped via the discount's `discountableConditions` (products,
 * variants or collections). With no conditions, every line is eligible.
 *
 * @see \Lunar\DiscountTypes\AmountOff  for the percentage-application pattern
 * @see \Lunar\DiscountTypes\BuyXGetY   for the conditions matching pattern
 */
class QuantityPercentageOff extends AbstractDiscountType
{
    public function getName(): string
    {
        return 'Quantity Discount (Buy N get X% off)';
    }

    public function apply(CartContract $cart): CartContract
    {
        // Honour customer-group / coupon / min-spend / usage conditions just
        // like the native types do.
        if (! $this->checkDiscountConditions($cart)) {
            return $cart;
        }

        $data = $this->discount->data ?? [];
        $minQty = (int) ($data['min_qty'] ?? 2);
        $percentage = (float) ($data['percentage'] ?? 0);

        if ($percentage <= 0 || $minQty < 1) {
            return $cart;
        }

        $eligible = $this->eligibleLines($cart);

        if ($eligible->sum('quantity') < $minQty) {
            return $cart;
        }

        $affectedLines = collect();
        $totalDiscount = 0;

        foreach ($eligible as $line) {
            $subTotal = $line->subTotalDiscounted?->value ?: $line->subTotal->value;
            $existing = $line->discountTotal?->value ?: 0;

            $amount = (int) round($subTotal * ($percentage / 100));

            // Don't downgrade a line that already has a better deal.
            if ($existing > $amount) {
                continue;
            }

            $totalDiscount += $amount;

            $line->discountTotal = new Price($existing + $amount, $cart->currency, 1);
            $line->subTotalDiscounted = new Price($subTotal - $amount, $cart->currency, 1);

            $affectedLines->push(new DiscountBreakdownLine(
                line: $line,
                quantity: $line->quantity,
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

    /**
     * Cart lines that match the discount's conditions. With no conditions set,
     * all lines qualify (mirrors Lunar's "no limitation = everything").
     *
     * @return Collection<int, \Lunar\Models\CartLine>
     */
    protected function eligibleLines(CartContract $cart): Collection
    {
        $conditions = $this->discount->discountableConditions;

        if ($conditions->isEmpty()) {
            return $cart->lines;
        }

        return $cart->lines->filter(
            fn ($line) => $this->lineMatchesConditions($line, $conditions)
        )->values();
    }

    protected function lineMatchesConditions($line, Collection $conditions): bool
    {
        return $conditions->contains(function ($item) use ($line) {
            if ($item->discountable_type == Product::morphName()
                && $item->discountable_id == $line->purchasable->product->id) {
                return true;
            }

            if ($item->discountable_type == ProductVariant::morphName()
                && $item->discountable_id == $line->purchasable->id) {
                return true;
            }

            if ($item->discountable_type == LunarCollection::morphName()
                && $line->purchasable->product->collections->pluck('id')->contains($item->discountable_id)) {
                return true;
            }

            return false;
        });
    }
}
