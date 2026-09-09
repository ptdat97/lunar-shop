<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Lunar\Core\Models\Staff;
use Modules\Core\Panel\SettingsGroup;
use Modules\Core\Panel\SettingsRegistry;
use Modules\Core\Support\Settings;
use Modules\Notification\Support\MailSettings;
use Modules\Theme\Services\ThemeSettings;
use Tests\TestCase;

/**
 * The shop's feature settings — eight former admin pages behind one screen.
 *
 * Each group declares its fields and keeps its own storage, so what is under
 * test is that a declared form still writes exactly what the services behind it
 * read: the same keys, the same shapes, the same fallbacks.
 *
 * The rule that matters most here is `Settings::put()` replacing a whole group.
 * A screen that forgets a key does not leave it alone — it erases it.
 */
class PanelSettingsTest extends TestCase
{
    private function actingAsAdmin(): static
    {
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');

        return $this;
    }

    /** Every declared group must render; a broken schema fails here, not in a browser. */
    public function test_every_group_renders(): void
    {
        $groups = app(SettingsRegistry::class)->all();

        $this->assertNotEmpty($groups);

        foreach ($groups as $group) {
            $this->actingAsAdmin()
                ->get(route("panel.shop.settings.{$group->key()}.edit"))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('shop/settings/Edit')
                    ->where('group.key', $group->key())
                    ->has('fields')
                    ->has('values'),
                );
        }
    }

    /** The tab strip and what the URLs allow are the same list. */
    public function test_tabs_list_every_group_the_user_may_open(): void
    {
        $expected = collect(app(SettingsRegistry::class)->all())->map->key()->all();

        $this->actingAsAdmin()
            ->get(route('panel.shop.settings.catalog.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tabs', fn ($tabs) => collect($tabs)->pluck('key')->all() === $expected),
            );
    }

    public function test_catalog_settings_write_the_three_groups_their_services_read(): void
    {
        $this->actingAsAdmin()
            ->put(route('panel.shop.settings.catalog.update'), [
                'recommend' => ['product_limit' => 12, 'cart_limit' => 4, 'cache_ttl' => 600],
                'review' => ['auto_approve' => false],
                'recently_viewed' => ['limit' => 10],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $settings = app(Settings::class);

        $this->assertSame(12, (int) $settings->get('recommend.product_limit'));
        $this->assertSame(600, (int) $settings->get('recommend.cache_ttl'));
        $this->assertFalse((bool) $settings->get('review.auto_approve'));
        $this->assertSame(10, (int) $settings->get('recently_viewed.limit'));
    }

    /**
     * `put()` replaces a whole group, so saving one field must still write the
     * others. This is the bug the old pages each had to guard against by hand.
     */
    public function test_saving_one_field_does_not_erase_its_neighbour(): void
    {
        $this->actingAsAdmin()
            ->put(route('panel.shop.settings.inventory.update'), [
                'low_stock_threshold' => 3,
                'hold_minutes' => 45,
            ])->assertSessionHasNoErrors();

        $this->actingAsAdmin()
            ->put(route('panel.shop.settings.inventory.update'), [
                'low_stock_threshold' => 7,
                'hold_minutes' => 45,
            ])->assertSessionHasNoErrors();

        $settings = app(Settings::class);

        $this->assertSame(7, (int) $settings->get('inventory.low_stock_threshold'));
        $this->assertSame(45, (int) $settings->get('inventory.hold_minutes'));
    }

    /**
     * A stored credential must never travel to a browser just because someone
     * opened the settings screen. The old payment page did exactly that.
     */
    public function test_secrets_are_never_sent_to_the_browser(): void
    {
        app(Settings::class)->put('payment', [
            'default' => 'vnpay',
            'vnpay' => ['tmn_code' => 'ABC', 'hash_secret' => 'SUPER-SECRET'],
            'momo' => [],
        ]);

        $this->actingAsAdmin()
            ->get(route('panel.shop.settings.payment.edit'))
            ->assertOk()
            ->assertDontSee('SUPER-SECRET')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('values.vnpay.hash_secret', '')
                // …but the form still says one is stored, so it does not read
                // as unconfigured. Keyed by field name, dots and all, so it is
                // checked as a whole rather than by dot-path.
                ->where('secretsPresent', fn ($present) => collect($present)->get('vnpay.hash_secret') === true)
                ->where('values.vnpay.tmn_code', 'ABC'),
            );
    }

    /** Blank means "keep it", not "clear it". */
    public function test_saving_with_a_blank_secret_keeps_the_stored_one(): void
    {
        app(Settings::class)->put('payment', [
            'default' => 'vnpay',
            'vnpay' => ['tmn_code' => 'ABC', 'hash_secret' => 'SUPER-SECRET'],
            'momo' => [],
        ]);

        $this->actingAsAdmin()
            ->put(route('panel.shop.settings.payment.update'), [
                'default' => 'vnpay',
                'vnpay' => ['tmn_code' => 'ABC', 'hash_secret' => ''],
                'momo' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('SUPER-SECRET', app(Settings::class)->get('payment.vnpay.hash_secret'));

        // And a new value does replace it.
        $this->actingAsAdmin()
            ->put(route('panel.shop.settings.payment.update'), [
                'default' => 'vnpay',
                'vnpay' => ['tmn_code' => 'ABC', 'hash_secret' => 'ROTATED'],
                'momo' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('ROTATED', app(Settings::class)->get('payment.vnpay.hash_secret'));
    }

    /**
     * The SMS gateway is stored under `sms_gateway`: `sms` is config's driver
     * map, which Settings::get() falls back to. Writing the wrong key would
     * hand SmsSettings::gateway() a nested driver list where it wants flat
     * credentials.
     */
    public function test_the_sms_gateway_is_stored_under_the_key_its_reader_uses(): void
    {
        $this->actingAsAdmin()
            ->put(route('panel.shop.settings.notification.update'), [
                'mail_override' => false,
                'sms_enabled' => true,
                'sms_events' => ['payment-received'],
                'sms' => ['endpoint' => 'https://sms.test/send', 'api_key' => 'KEY', 'auth' => 'bearer'],
                'push_enabled' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'https://sms.test/send',
            app(Settings::class)->get('notification.sms_gateway.endpoint'),
        );

        $gateway = \Modules\Notification\Support\SmsSettings::gateway();

        $this->assertSame('https://sms.test/send', $gateway['endpoint']);
        $this->assertSame('bearer', $gateway['auth']);
        // Unsubmitted keys keep their defaults rather than becoming empty.
        $this->assertSame('to', $gateway['to_field']);
    }

    /** The mail password is a secret too, and the wrapper must still read it. */
    public function test_mail_credentials_round_trip_through_their_wrapper(): void
    {
        $this->actingAsAdmin()
            ->put(route('panel.shop.settings.notification.update'), [
                'mail_override' => true,
                'mail' => [
                    'host' => 'smtp.test',
                    'port' => 587,
                    'username' => 'shop',
                    'password' => 'PW',
                    'encryption' => 'tls',
                    'from_address' => 'shop@example.com',
                    'from_name' => 'Shop',
                ],
                'sms_enabled' => false,
                'push_enabled' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(MailSettings::overrideEnabled());
        $this->assertSame('smtp.test', MailSettings::smtp()['host']);
        $this->assertSame('PW', MailSettings::smtp()['password']);
    }

    /**
     * Tiers are sorted ascending on save: MembershipService walks them in order
     * and takes the last one reached, so an unsorted list awards the wrong tier.
     */
    public function test_membership_tiers_are_sorted_by_spend(): void
    {
        $this->actingAsAdmin()
            ->put(route('panel.shop.settings.membership.update'), [
                'enabled' => true,
                'tiers' => [
                    ['handle' => 'gold', 'name' => 'Vàng', 'min_spend' => 5000000, 'discount_percentage' => 10],
                    ['handle' => 'silver', 'name' => 'Bạc', 'min_spend' => 1000000, 'discount_percentage' => 5],
                ],
            ])
            ->assertSessionHasNoErrors();

        $tiers = app(Settings::class)->get('promotion.membership.tiers');

        $this->assertSame(['silver', 'gold'], array_column($tiers, 'handle'));
    }

    /** The theme has its own table; a save must reach it, not app_settings. */
    public function test_theme_settings_write_to_the_theme_store(): void
    {
        $this->actingAsAdmin()
            ->put(route('panel.shop.settings.theme.update'), [
                'copyright' => '© 2026 Shop',
                'contact' => ['address' => '1 Phố Huế', 'email' => 'hi@example.com', 'phone' => '0900'],
                'topbar' => [['text' => 'Miễn phí ship trên 500k']],
                'payment' => 'visa.png, momo.png',
            ])
            ->assertSessionHasNoErrors();

        $store = app(ThemeSettings::class);

        $this->assertSame('© 2026 Shop', $store->get('copyright'));
        $this->assertSame('1 Phố Huế', $store->get('contact.address'));
        $this->assertSame('Miễn phí ship trên 500k', $store->get('topbar.0.text'));
        $this->assertSame(['visa.png', 'momo.png'], $store->get('payment'));
    }

    /** Validation comes from the declared rules. */
    public function test_declared_rules_are_enforced(): void
    {
        $this->actingAsAdmin()
            ->put(route('panel.shop.settings.catalog.update'), [
                'recommend' => ['product_limit' => 999, 'cart_limit' => 4, 'cache_ttl' => 600],
                'review' => ['auto_approve' => true],
                'recently_viewed' => ['limit' => 8],
            ])
            ->assertSessionHasErrors('recommend.product_limit');
    }

    /** Feature settings decide how the shop charges people — not a loose gate. */
    public function test_settings_require_the_core_settings_permission(): void
    {
        $staff = Staff::factory()->create(['admin' => false]);

        $this->actingAs($staff, 'staff')
            ->get(route('panel.shop.settings.payment.edit'))
            ->assertForbidden();

        $staff->givePermissionTo('settings:core');

        $this->actingAs($staff->fresh(), 'staff')
            ->get(route('panel.shop.settings.payment.edit'))
            ->assertOk();
    }

    /** Every group's icon must exist, or its sidebar entry renders blank. */
    public function test_every_group_icon_exists_in_the_panels_icon_set(): void
    {
        $source = file_get_contents(
            base_path('vendor/lunarphp/panel/resources/js/components/Icon.vue'),
        );

        preg_match_all("/^    '?([a-zA-Z0-9_-]+)'?: '/m", $source, $matches);

        foreach (app(SettingsRegistry::class)->all() as $group) {
            $this->assertContains(
                $group->icon(),
                $matches[1],
                "Nhóm [{$group->key()}] dùng icon [{$group->icon()}] mà panel không có.",
            );
        }
    }
}
