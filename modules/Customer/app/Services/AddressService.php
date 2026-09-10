<?php

namespace Modules\Customer\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Core\Contracts\Actions\Customers\CreatesCustomerAddress;
use Lunar\Core\Contracts\Actions\Customers\DeletesCustomerAddress;
use Lunar\Core\Contracts\Actions\Customers\UpdatesCustomerAddress;
use Lunar\Core\Models\Address;
use Lunar\Core\Models\Customer;

/**
 * Customer address book — the single write path for addresses (web + API),
 * including ownership checks and the one-default-per-type invariant
 * (standards §4 — business rules live in services, not controllers).
 *
 * The writes themselves go through Lunar's own actions rather than touching
 * the model: each one logs an `address-created`/`-updated`/`-deleted` activity
 * entry against the customer, which is what the panel's customer timeline
 * reads. Writing `$customer->addresses()->create()` here saved nothing and
 * left every storefront address change invisible to staff.
 *
 * What stays ours is what Lunar does not do: the ownership check (a customer
 * may only touch their own address) and the one-default-per-type invariant.
 */
class AddressService
{
    /**
     * The customer's addresses, newest first.
     *
     * @return Collection<int, Address>
     */
    public function list(Customer $customer): Collection
    {
        return $customer->addresses()->latest()->get();
    }

    /**
     * Create an address for the customer.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Customer $customer, array $data): Address
    {
        // Two writes upholding one invariant (exactly one default per type):
        // insert the address, then clear the flag on its siblings. Without a
        // transaction a failure between them leaves the customer with two
        // defaults, and checkout then picks one arbitrarily.
        return DB::transaction(function () use ($customer, $data) {
            $address = app(CreatesCustomerAddress::class)->execute($customer, $data);
            $this->syncDefaults($customer, $address, $data);

            return $address->refresh();
        });
    }

    /**
     * Update one of the customer's addresses (404 when not theirs).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, int $addressId, array $data): Address
    {
        return DB::transaction(function () use ($customer, $addressId, $data) {
            $address = $this->owned($customer, $addressId);
            app(UpdatesCustomerAddress::class)->execute($address, $data);
            $this->syncDefaults($customer, $address, $data);

            return $address->refresh();
        });
    }

    /**
     * Delete one of the customer's addresses (404 when not theirs).
     */
    public function delete(Customer $customer, int $addressId): void
    {
        app(DeletesCustomerAddress::class)->execute($this->owned($customer, $addressId));
    }

    /**
     * Resolve an address that belongs to the customer (404 otherwise).
     */
    protected function owned(Customer $customer, int $id): Address
    {
        return $customer->addresses()->findOrFail($id);
    }

    /**
     * Ensure only one shipping/billing default per customer.
     *
     * @param  array<string, mixed>  $data
     */
    protected function syncDefaults(Customer $customer, Address $address, array $data): void
    {
        foreach (['shipping_default', 'billing_default'] as $flag) {
            if (! empty($data[$flag])) {
                $customer->addresses()
                    ->where('id', '!=', $address->id)
                    ->update([$flag => false]);
            }
        }
    }
}
