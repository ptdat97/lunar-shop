<?php

namespace Tests\Feature;

use Modules\Cart\Contracts\CartContract;
use Modules\Cart\Services\CartService;
use Modules\Checkout\Contracts\CheckoutContract;
use Modules\Checkout\Services\CheckoutService;
use Modules\Platform\Support\Decorator;
use Modules\Pricing\Contracts\PricingContract;
use Modules\Pricing\Services\PricingService;
use Tests\TestCase;

/**
 * D1 — the core services now sit behind a Contract, bound as a shared singleton
 * and aliased so concrete- and interface-typed callers get the SAME instance,
 * and the Platform Decorator can wrap them. No caller change; the seam is what's
 * new. (SearchEngine already had its contract — covered by SearchSizeTest.)
 */
class ServiceContractTest extends TestCase
{
    public function test_services_implement_their_contracts(): void
    {
        $this->assertInstanceOf(PricingContract::class, app(PricingService::class));
        $this->assertInstanceOf(CartContract::class, app(CartService::class));
        $this->assertInstanceOf(CheckoutContract::class, app(CheckoutService::class));
    }

    public function test_concrete_and_contract_resolve_the_same_singleton(): void
    {
        $this->assertSame(app(PricingService::class), app(PricingContract::class));
        $this->assertSame(app(CartService::class), app(CartContract::class));
        $this->assertSame(app(CheckoutService::class), app(CheckoutContract::class));
    }

    public function test_a_contract_can_be_decorated_and_reaches_concrete_callers(): void
    {
        Decorator::wrap(PricingContract::class, PricingSpyDecorator::class, $this->app);

        // A caller type-hinting the CONCRETE class still gets the decorated one.
        $resolved = app(PricingService::class);

        $this->assertInstanceOf(PricingSpyDecorator::class, $resolved);
        $this->assertStringEndsWith('-decorated', $resolved->defaultCurrencyCode());
    }
}

/** A test decorator that wraps PricingContract and tweaks one method. */
class PricingSpyDecorator implements PricingContract
{
    public function __construct(protected PricingContract $inner) {}

    public function defaultCurrencyCode(): string
    {
        return $this->inner->defaultCurrencyCode().'-decorated';
    }

    // Pass-through the rest.
    public function matchedPrice(\Lunar\Models\ProductVariant $variant): ?\Lunar\DataTypes\Price
    {
        return $this->inner->matchedPrice($variant);
    }

    public function displayPrice(\Lunar\Models\Product $product): ?string
    {
        return $this->inner->displayPrice($product);
    }

    public function lowestPriceAmount(\Lunar\Models\Product $product): ?float
    {
        return $this->inner->lowestPriceAmount($product);
    }

    public function variantPrice(int $variantId, ?int $currencyId = null): ?\Lunar\Models\Price
    {
        return $this->inner->variantPrice($variantId, $currencyId);
    }

    public function hasTieredPricing(int $variantId): bool
    {
        return $this->inner->hasTieredPricing($variantId);
    }

    public function customerGroupPrices(int $variantId, int $customerGroupId)
    {
        return $this->inner->customerGroupPrices($variantId, $customerGroupId);
    }
}
