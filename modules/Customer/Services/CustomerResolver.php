<?php

namespace Modules\Customer\Services;

use App\Models\User;
use Lunar\Models\Customer;

/**
 * Resolves the Lunar customer record for an authenticated user, creating and
 * linking one on first use. Lunar links users ↔ customers many-to-many; the
 * storefront treats the first linked customer as "the" customer.
 */
class CustomerResolver
{
    /**
     * The user's customer, creating + linking one if none exists yet.
     */
    public function forUser(User $user): Customer
    {
        $customer = $user->customers()->first();

        if ($customer) {
            return $customer;
        }

        [$first, $last] = $this->splitName($user->name);

        $customer = Customer::create([
            'first_name' => $first,
            'last_name' => $last,
        ]);

        $user->customers()->attach($customer->id);

        return $customer;
    }

    /**
     * The user's customer without creating one (null if not linked yet).
     */
    public function existingForUser(User $user): ?Customer
    {
        return $user->customers()->first();
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function splitName(?string $name): array
    {
        $parts = preg_split('/\s+/', trim((string) $name), 2) ?: [];

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }
}
