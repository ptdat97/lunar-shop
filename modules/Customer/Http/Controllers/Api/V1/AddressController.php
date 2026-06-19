<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Lunar\Models\Address;
use Modules\Customer\Http\Resources\AddressResource;
use Modules\Customer\Services\CustomerResolver;

/**
 * Customer address book (CRUD). Addresses belong to the user's Lunar customer.
 */
class AddressController extends Controller
{
    public function __construct(
        protected CustomerResolver $customers,
    ) {}

    /**
     * GET /api/v1/customer/addresses
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $customer = $this->customers->forUser($request->user());

        return AddressResource::collection(
            $customer->addresses()->latest()->get()
        );
    }

    /**
     * POST /api/v1/customer/addresses
     */
    public function store(Request $request): AddressResource
    {
        $customer = $this->customers->forUser($request->user());
        $data = $this->validated($request);

        $address = $customer->addresses()->create($data);
        $this->syncDefaults($customer, $address, $data);

        return new AddressResource($address->refresh());
    }

    /**
     * PATCH /api/v1/customer/addresses/{address}
     */
    public function update(Request $request, Address $address): AddressResource
    {
        $customer = $this->customers->forUser($request->user());
        $model = $this->ownedAddress($customer, $address->getKey());
        $data = $this->validated($request);

        $model->update($data);
        $this->syncDefaults($customer, $model, $data);

        return new AddressResource($model->refresh());
    }

    /**
     * DELETE /api/v1/customer/addresses/{address}
     */
    public function destroy(Request $request, Address $address): JsonResponse
    {
        $customer = $this->customers->forUser($request->user());
        $this->ownedAddress($customer, $address->getKey())->delete();

        return response()->json(['data' => ['status' => 'deleted']]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'line_one' => ['required', 'string', 'max:255'],
            'line_two' => ['nullable', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],   // Tỉnh/Thành
            'city' => ['required', 'string', 'max:255'],    // Phường/Xã
            'postcode' => ['nullable', 'string', 'max:32'],
            'country_id' => ['required', 'integer', 'exists:lunar_countries,id'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'shipping_default' => ['nullable', 'boolean'],
            'billing_default' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Resolve an address that belongs to the customer (404 otherwise).
     */
    protected function ownedAddress($customer, int $id): Address
    {
        return $customer->addresses()->findOrFail($id);
    }

    /**
     * Ensure only one shipping/billing default per customer.
     *
     * @param  array<string, mixed>  $data
     */
    protected function syncDefaults($customer, Address $address, array $data): void
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
