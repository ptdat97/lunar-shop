<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Lunar\Models\Address;
use Modules\Customer\Http\Resources\AddressResource;
use Modules\Customer\Services\AddressService;
use Modules\Customer\Services\CustomerResolver;

/**
 * Customer address book (CRUD). Addresses belong to the user's Lunar customer;
 * ownership + default-flag rules live in AddressService.
 */
class AddressController extends Controller
{
    public function __construct(
        protected CustomerResolver $customers,
        protected AddressService $addresses,
    ) {}

    /**
     * GET /api/v1/customer/addresses
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $customer = $this->customers->forUser($request->user());

        return AddressResource::collection($this->addresses->list($customer));
    }

    /**
     * POST /api/v1/customer/addresses
     */
    public function store(Request $request): AddressResource
    {
        $customer = $this->customers->forUser($request->user());

        return new AddressResource(
            $this->addresses->create($customer, $this->validated($request))
        );
    }

    /**
     * PATCH /api/v1/customer/addresses/{address}
     */
    public function update(Request $request, Address $address): AddressResource
    {
        $customer = $this->customers->forUser($request->user());

        return new AddressResource(
            $this->addresses->update($customer, $address->getKey(), $this->validated($request))
        );
    }

    /**
     * DELETE /api/v1/customer/addresses/{address}
     */
    public function destroy(Request $request, Address $address): JsonResponse
    {
        $customer = $this->customers->forUser($request->user());
        $this->addresses->delete($customer, $address->getKey());

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
}
