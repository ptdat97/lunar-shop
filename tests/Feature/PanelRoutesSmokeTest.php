<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Lunar\Core\Models\Staff;
use Modules\Core\Panel\PanelResource;
use Modules\Core\Panel\ResourceRegistry;
use Modules\Core\Panel\SettingsRegistry;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Opens every panel screen and asserts it renders.
 *
 * The other tests cover behaviour; this covers *wiring*. An upgrade breaks
 * screens one at a time — a renamed prop here, a controller signature there —
 * and none of it surfaces until someone opens that page. The Filament v3 → v4
 * upgrade is how its predecessor came to exist, and the 1.5 → 2.0 one is why
 * this replacement does.
 *
 * It sweeps the *route table*, not a hand-written list, so a screen added
 * tomorrow is covered without anyone remembering to add it here.
 */
class PanelRoutesSmokeTest extends TestCase
{
    use CreatesStorefrontData;

    /**
     * Routes that are correct to skip, each with the reason. Kept short and
     * justified — every entry is a screen nothing checks.
     */
    private const SKIP = [
        // Guest-only: RedirectIfAuthenticated bounces an authenticated admin,
        // and they have their own coverage (PanelPasswordOnlyLoginTest).
        'panel.login',
        'panel.password.request',
        'panel.two-factor.challenge',

        // Not a screen — a JSON endpoint the picker calls, covered by
        // PanelMediaPickerTest.
        'panel.shop.media.index',

        // Search endpoints answer XHR, not navigation.
        'panel.search',
        'panel.catalog.products.search',
        'panel.catalog.collections.search',
        'panel.catalog.product-options.search',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // The reference data a real install always has (currency, language,
        // tax class, channel). Without it these screens fail on missing
        // defaults, which is a fixture problem, not a wiring one.
        $this->seedBaseData();

        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');
    }

    /** @return array<int, Route> */
    private function parameterlessGetRoutes(): array
    {
        return collect(Router::getRoutes())
            ->filter(fn (Route $route) => str_starts_with($route->uri(), 'panel')
                && in_array('GET', $route->methods(), true)
                && ! $route->parameterNames()
                && $route->getName()
                && ! in_array($route->getName(), self::SKIP, true))
            ->values()
            ->all();
    }

    /**
     * The sweep. Every panel screen reachable without a record must return 200
     * — first-party and this shop's alike.
     */
    public function test_every_parameterless_panel_screen_renders(): void
    {
        $routes = $this->parameterlessGetRoutes();

        $this->assertGreaterThan(40, count($routes), 'Bảng route co lại bất thường — panel có còn đăng ký không?');

        $failures = [];

        foreach ($routes as $route) {
            $name = $route->getName();
            $response = $this->get(route($name));
            $status = $response->baseResponse->getStatusCode();

            // A landing route that forwards somewhere (panel.settings opens the
            // first settings screen) is fine — as long as where it forwards to
            // renders. Following it is worth more than listing it as an
            // exception, because a redirect into a broken screen still looks
            // like a pass from here.
            if ($status === 302) {
                $target = $this->get($response->headers->get('Location'));

                if ($target->baseResponse->getStatusCode() !== 200) {
                    $failures[$name] = '302 → '.$target->baseResponse->getStatusCode();
                }

                continue;
            }

            // 404 is the right answer for a resource that refuses creation; the
            // dedicated assertion below checks those are the ones expected.
            if ($status !== 200 && ! ($status === 404 && $this->isDeliberate404($name))) {
                $failures[$name] = $status;
            }
        }

        $this->assertSame([], $failures, 'Màn hình panel không render: '.json_encode($failures, JSON_UNESCAPED_UNICODE));
    }

    /**
     * A create screen 404s only where the resource says it cannot be created —
     * a returns queue is filled by customers, a stock-alert queue by shoppers.
     */
    private function isDeliberate404(string $name): bool
    {
        if (! preg_match('/^panel\.shop\.([\w-]+)\.create$/', $name, $matches)) {
            return false;
        }

        return ! app(ResourceRegistry::class)->get($matches[1])?->canCreate();
    }

    /**
     * The shop's own resources, end to end: index, create and edit, each
     * against what the resource says it allows. These are the screens no
     * upstream test will ever cover.
     */
    public function test_every_shop_resource_screen_matches_what_it_declares(): void
    {
        $resources = app(ResourceRegistry::class)->all();

        $this->assertNotEmpty($resources);

        foreach ($resources as $key => $resource) {
            $this->get(route("panel.shop.{$key}.index"))
                ->assertOk("Danh sách [{$key}] không render.");

            $this->assertCreateScreen($key, $resource);
            $this->assertEditScreen($key, $resource);
        }
    }

    private function assertCreateScreen(string $key, PanelResource $resource): void
    {
        $response = $this->get(route("panel.shop.{$key}.create"));

        $resource->canCreate()
            ? $response->assertOk("Màn hình thêm [{$key}] không render.")
            : $response->assertNotFound("[{$key}] không cho tạo nhưng màn hình thêm vẫn mở.");
    }

    /**
     * The edit form is the half that renders per-record: a schema that reads a
     * column wrongly only shows up with a row present.
     */
    private function assertEditScreen(string $key, PanelResource $resource): void
    {
        $record = $resource->model()::query()->first();

        if (! $record) {
            $record = $this->seedRecordFor($resource);
        }

        if (! $record) {
            $this->markTestIncomplete("Không dựng được bản ghi mẫu cho [{$key}].");
        }

        $this->get(route("panel.shop.{$key}.edit", $record->getKey()))
            ->assertOk("Màn hình sửa [{$key}] không render.");
    }

    /**
     * One row per resource.
     *
     * A resource that refuses creation holds rows the domain puts there — a
     * return opened by a customer, a stock alert taken from a product page —
     * and those carry required columns its admin schema never mentions. Those
     * get a hand-built fixture; everything else is derived from its own schema,
     * which is what keeps this honest when a schema gains a column.
     */
    private function seedRecordFor(PanelResource $resource): ?object
    {
        if (! $resource->canCreate()) {
            return $this->seedDomainRecordFor($resource);
        }

        $attributes = [];

        foreach ($resource->fields() as $field) {
            $definition = $field->toArray();

            if (str_contains($field->name, '.') || in_array($definition['type'], ['repeater'], true)) {
                continue;
            }

            $value = $field->defaultValue();

            if ($definition['required'] && blank($value)) {
                $value = match ($definition['type']) {
                    'number' => 1,
                    'select' => array_key_first($definition['options']) ?? null,
                    default => 'smoke-'.$field->name,
                };
            }

            if ($value !== null) {
                $attributes[$field->name] = $value;
            }
        }

        return $resource->model()::query()->create($attributes);
    }

    /**
     * Fixtures for the queues customers fill. Each entry is a resource whose
     * rows the admin never creates, so its required columns come from the
     * domain rather than from the form.
     */
    private function seedDomainRecordFor(PanelResource $resource): ?object
    {
        return match ($resource->key()) {
            'stock-notifications' => $resource->model()::create([
                'product_variant_id' => $this->createProduct()->variants->first()->id,
                'email' => 'khach@example.com',
            ]),
            'returns' => $resource->model()::create([
                'order_id' => \Lunar\Core\Models\Order::factory()->create([
                    'channel_id' => \Lunar\Core\Models\Channel::getDefault()->id,
                    'currency_code' => \Lunar\Core\Models\Currency::getDefault()->code,
                ])->id,
                'reference' => 'RMA-SMOKE',
                'status' => \Modules\Order\Models\ReturnRequest::REQUESTED,
                'reason' => 'wrong-size',
            ]),
            default => null,
        };
    }

    /** Every settings tab must open — eight former pages behind one screen. */
    public function test_every_settings_tab_renders(): void
    {
        $groups = app(SettingsRegistry::class)->all();

        $this->assertNotEmpty($groups);

        foreach ($groups as $group) {
            $this->get(route("panel.shop.settings.{$group->key()}.edit"))
                ->assertOk("Tab cài đặt [{$group->key()}] không render.");
        }
    }

    /**
     * Every navigation entry has to point somewhere that exists. A nav item
     * whose route was renamed resolves to null and renders as a dead link —
     * visible to nobody running tests, obvious to whoever clicks it.
     */
    public function test_every_navigation_item_resolves_to_a_real_route(): void
    {
        $manager = app(\Lunar\Panel\PanelManager::class);
        $staff = auth('staff')->user();

        $broken = [];

        foreach ([$manager->navigation(), $manager->settingsNavigation()] as $registry) {
            foreach ($this->flattenNavigation($registry->toArray($staff)) as $item) {
                if (($item['url'] ?? null) === null) {
                    $broken[] = $item['key'] ?? '(không tên)';
                }
            }
        }

        $this->assertSame([], $broken, 'Mục điều hướng không có URL: '.implode(', ', $broken));
    }

    /**
     * @param  array<int|string, mixed>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function flattenNavigation(array $nodes): array
    {
        $items = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (array_key_exists('key', $node) && array_key_exists('url', $node)) {
                $items[] = $node;
            }

            foreach (['items', 'children'] as $childKey) {
                if (! empty($node[$childKey]) && is_array($node[$childKey])) {
                    $items = [...$items, ...$this->flattenNavigation($node[$childKey])];
                }
            }
        }

        return $items;
    }
}
