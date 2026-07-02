<?php

namespace Modules\Promotion\Services;

use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Order;

/**
 * Spend-based loyalty tiers, layered on Lunar's native CustomerGroup system.
 *
 * A customer's lifetime *paid* spend places them in the highest tier they
 * qualify for; each tier maps to a Lunar CustomerGroup. Membership discounts
 * are ordinary Lunar Discounts scoped to those groups, so the cart pipeline
 * applies them automatically (Lunar's DiscountManager filters by the cart
 * customer's groups). We only own the tier <-> group sync here — Principle #1:
 * inherit Lunar, don't rebuild the discount engine.
 */
class MembershipService
{
    /** Tier config rows, ascending by min_spend (admin-configurable). */
    public function tiers(): array
    {
        return (array) app(\Modules\Core\Support\Settings::class)->get('promotion.membership.tiers', []);
    }

    public function enabled(): bool
    {
        return (bool) app(\Modules\Core\Support\Settings::class)->get('promotion.membership.enabled', false);
    }

    /**
     * Lifetime paid spend (minor units) for a customer across their orders.
     */
    public function lifetimeSpend(Customer $customer): int
    {
        $statuses = config('promotion.membership.paid_statuses', ['payment-received']);

        return (int) Order::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', $statuses)
            ->sum('total');
    }

    /**
     * The tier config a spend amount (minor units) qualifies for, or null.
     *
     * @return array<string, mixed>|null
     */
    public function tierForSpend(int $spendMinor): ?array
    {
        $factor = (int) (\Lunar\Models\Currency::getDefault()?->factor ?: 100);
        $spendMajor = $spendMinor / $factor;

        $matched = null;
        foreach ($this->tiers() as $tier) {
            if ($spendMajor >= (float) ($tier['min_spend'] ?? 0)) {
                $matched = $tier;
            }
        }

        return $matched;
    }

    /**
     * Resolve (creating if needed) the Lunar CustomerGroup for a tier handle.
     */
    public function groupForTier(array $tier): CustomerGroup
    {
        return CustomerGroup::firstOrCreate(
            ['handle' => $tier['handle']],
            ['name' => $tier['name'], 'default' => false],
        );
    }

    /** All CustomerGroups managed by membership tiers. */
    public function managedGroupIds(): \Illuminate\Support\Collection
    {
        $handles = collect($this->tiers())->pluck('handle');

        return CustomerGroup::whereIn('handle', $handles)->pluck('id');
    }

    /**
     * Sync a single customer into the group for their current spend tier,
     * removing them from other managed (tier) groups. Returns the tier they
     * now belong to, or null if they qualify for none.
     *
     * @return array<string, mixed>|null
     */
    public function syncCustomer(Customer $customer): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $tier = $this->tierForSpend($this->lifetimeSpend($customer));
        $managedIds = $this->managedGroupIds();

        // Detach any tier groups they no longer qualify for, keeping non-tier
        // groups (e.g. the default "retail" group) untouched.
        $keepId = null;
        if ($tier) {
            $keepId = $this->groupForTier($tier)->id;
        }

        $detach = $managedIds->reject(fn ($id) => $id === $keepId)->all();
        if ($detach) {
            $customer->customerGroups()->detach($detach);
        }

        if ($keepId && ! $customer->customerGroups()->where('customer_group_id', $keepId)->exists()) {
            $customer->customerGroups()->attach($keepId);
        }

        return $tier;
    }

    /**
     * The customer's current membership tier (the highest managed group they
     * are attached to), or null. Read-only — does not re-sync.
     *
     * @return array<string, mixed>|null
     */
    public function currentTier(Customer $customer): ?array
    {
        $groupIds = $customer->customerGroups()->pluck($customer->customerGroups()->getTable() . '.id');

        $current = null;
        foreach ($this->tiers() as $tier) {
            $group = CustomerGroup::where('handle', $tier['handle'])->first();
            if ($group && $groupIds->contains($group->id)) {
                $current = $tier; // tiers ascending → last match is highest
            }
        }

        return $current;
    }

    /**
     * Spend (minor units) still needed to reach the next tier, with that tier,
     * for progress display. Null when already at the top tier or disabled.
     *
     * @return array{tier: array<string,mixed>, remaining_minor: int}|null
     */
    public function nextTierProgress(Customer $customer): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $spend = $this->lifetimeSpend($customer);
        $factor = (int) (\Lunar\Models\Currency::getDefault()?->factor ?: 100);
        $spendMajor = $spend / $factor;

        foreach ($this->tiers() as $tier) {
            if ($spendMajor < (float) ($tier['min_spend'] ?? 0)) {
                return [
                    'tier' => $tier,
                    'remaining_minor' => (int) round(((float) $tier['min_spend'] * $factor) - $spend),
                ];
            }
        }

        return null;
    }
}
