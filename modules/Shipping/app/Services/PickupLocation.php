<?php

namespace Modules\Shipping\Services;

use Modules\Core\Support\Settings;

/**
 * The shop's own counter, offered at checkout as a zero-cost shipping option.
 *
 * This is the one delivery capability that needs no carrier contract, which is
 * why it lands before the rest of P0.5 (roadmap §13): inner-city customers stop
 * paying for shipping today, without waiting on a GHN/GHTK agreement.
 *
 * Lunar writes a shipping address row on every order, so pickup does not skip
 * the address — it fills it with the store's own while keeping the customer's
 * name and phone, so the counter knows who is collecting. See
 * {@see asShippingAddress()}.
 */
class PickupLocation
{
    /**
     * Settings keys this owns, all inside the `shipping` group.
     *
     * ShippingSettingsPage::save() must write every one of them on every save:
     * Settings::put() replaces the whole group, so omitting a key nulls it.
     *
     * @var list<string>
     */
    public const KEYS = [
        'pickup_enabled',
        'pickup_name',
        'pickup_line_one',
        'pickup_city',
        'pickup_state',
        'pickup_hours',
        'pickup_instructions',
    ];

    public const IDENTIFIER = 'pickup';

    public function __construct(private readonly Settings $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('shipping.pickup_enabled', false);
    }

    /**
     * Offered only when switched on AND carrying an address worth showing. A
     * pickup option that cannot say where to go is worse than no option at all.
     */
    public function isAvailable(): bool
    {
        return $this->enabled() && filled($this->lineOne()) && filled($this->city());
    }

    public function name(): string
    {
        $name = trim((string) $this->settings->get('shipping.pickup_name', ''));

        return $name !== '' ? $name : (string) config('app.name');
    }

    public function lineOne(): string
    {
        return trim((string) $this->settings->get('shipping.pickup_line_one', ''));
    }

    /** Ward — this shop uses the VN 2-tier address model, where city = ward. */
    public function city(): string
    {
        return trim((string) $this->settings->get('shipping.pickup_city', ''));
    }

    /** Province. */
    public function state(): string
    {
        return trim((string) $this->settings->get('shipping.pickup_state', ''));
    }

    public function hours(): string
    {
        return trim((string) $this->settings->get('shipping.pickup_hours', ''));
    }

    public function instructions(): string
    {
        return trim((string) $this->settings->get('shipping.pickup_instructions', ''));
    }

    /** One line, for checkout, mail and the invoice. */
    public function addressLine(): string
    {
        return collect([$this->lineOne(), $this->city(), $this->state()])
            ->filter()
            ->implode(', ');
    }

    /**
     * What the storefront needs to render the choice. Null when pickup is not on
     * offer, so callers have exactly one thing to check.
     *
     * @return array<string, string>|null
     */
    public function toArray(): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        return [
            'name' => $this->name(),
            'address' => $this->addressLine(),
            'hours' => $this->hours(),
            'instructions' => $this->instructions(),
        ];
    }

    /**
     * The store's address wearing the customer's contact details.
     *
     * The order still needs a destination row, and for a collected order that
     * destination is the counter — but the shop has to know who is coming for
     * it, so name, phone and email stay the customer's.
     *
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    public function asShippingAddress(array $contact): array
    {
        return array_merge($contact, [
            'line_one' => $this->lineOne(),
            'line_two' => null,
            'city' => $this->city(),
            'state' => $this->state(),
        ]);
    }
}
