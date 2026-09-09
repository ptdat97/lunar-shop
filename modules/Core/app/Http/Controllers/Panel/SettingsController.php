<?php

namespace Modules\Core\Http\Controllers\Panel;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Panel\Field;
use Modules\Core\Panel\InputNormaliser;
use Modules\Core\Panel\SettingsGroup;
use Modules\Core\Panel\SettingsRegistry;

/**
 * The shop's feature settings — eight former admin pages behind one screen.
 *
 * Every group declares its fields and its own storage; this only routes,
 * validates and hands the payload back. Secrets never travel outward and a
 * blank one is restored from what is stored, so saving a page you only came to
 * read cannot wipe a working credential.
 */
class SettingsController extends Controller
{
    public function __construct(protected SettingsRegistry $registry) {}

    public function edit(Request $request): Response
    {
        $settings = $this->group($request);

        return Inertia::render('shop/settings/Edit', [
            'group' => $this->descriptor($settings),
            'tabs' => $this->tabs($request),
            'fields' => array_map(fn (Field $f) => $f->toArray(), $settings->fields()),
            'values' => $settings->formValues(),
            'secretsPresent' => $settings->secretsPresent(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = $this->group($request);

        $data = InputNormaliser::apply(
            $settings->fields(),
            $request->validate($settings->validationRules()),
        );

        $settings->persist($settings->resolveSecrets($data));

        return back()->with('success', __('panel.saved', ['name' => $settings->label()]));
    }

    protected function group(Request $request): SettingsGroup
    {
        // The key rides on the route, the same way it does for resources: each
        // group has its own named route so the settings sidebar can build a URL
        // for it, and `can:` is already applied there.
        $key = (string) $request->route()->defaults['settingsKey'];

        return $this->registry->get($key) ?? abort(404);
    }

    /** @return array<string, mixed> */
    protected function descriptor(SettingsGroup $group): array
    {
        return [
            'key' => $group->key(),
            'label' => $group->label(),
            'description' => $group->description(),
            'icon' => $group->icon(),
            'saveLabel' => __('panel.save'),
            'secretKeptLabel' => __('panel.secret_kept'),
            'action' => route("panel.shop.settings.{$group->key()}.update"),
        ];
    }

    /**
     * Only the tabs this user may open — the sidebar and what the URL allows
     * stay in step.
     *
     * @return array<int, array<string, string>>
     */
    protected function tabs(Request $request): array
    {
        $user = $request->user(config('lunar.panel.guard', 'staff'));

        return collect($this->registry->all())
            ->filter(fn (SettingsGroup $group) => $user?->can($group->permission()))
            ->map(fn (SettingsGroup $group) => [
                'key' => $group->key(),
                'label' => $group->label(),
                'url' => route("panel.shop.settings.{$group->key()}.edit"),
            ])
            ->values()
            ->all();
    }
}
