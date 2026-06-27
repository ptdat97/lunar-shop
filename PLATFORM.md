# Platform Core

> The minimal, business-free core of this Commerce Platform. **Lunar = commerce
> engine**, **Platform = extensibility**, **Plugin = business features**,
> **Theme = presentation**. Platform (`modules/Platform`, namespace
> `Modules\Platform`) ships **zero business logic** — it only provides the seams
> that modules and plugins extend. (Plugin authoring details: [PLUGIN_SDK.md](PLUGIN_SDK.md).)

Built incrementally, non-breaking, in the refactor roadmap
([lunarphp_sme_fashion_platform_refactor.md](lunarphp_sme_fashion_platform_refactor.md)).
**218 tests** cover it.

---

## What's in Core

| Capability | Where | Summary |
|---|---|---|
| **Hook framework** | `Services/HookManager`, `Facades/Hook`, `Support/Hooks` | action/filter bus by priority; `Hooks::*` registry of named extension points |
| **Event bridge** | `Events/EventBridge` | re-broadcast Laravel/Lunar events onto `Hooks::*` (Core owns the mechanism, modules declare the mapping) |
| **Plugin SDK** | `Plugin/*`, `Console/Plugin*`, `Models/PluginState` | contract + manager (discover/allow-list/topo-sort/semver/fail-soft) + lifecycle + CLI + `PluginConfig` tab + `PluginSettings` |
| **Decorator** | `Support/Decorator` | standardised, lazy, stackable wrapping of a container binding |
| **Rule engine** | `Rule/*` | `Operator` + `RuleRegistry` (fields) + `Rule` + `RuleSet` (all/any), pure + JSON-serialisable |
| **Workflow engine** | `Workflow/*`, `Models/Workflow` | Trigger (`Hooks::*`) → Conditions (`RuleSet`) → Action (queued); definitions stored as JSON |
| **Admin extension registry** | `Support/AdminPages` | modules/plugins contribute Filament pages/resources into Lunar's panel |
| **Contracts (versioning)** | `Support/PayloadContract`, `Workflow/WorkflowContract` | pin payload/definition shape; bump VERSION on breaking change |
| **Health check** | `Support/PlatformDoctor`, `Console/PlatformDoctorCommand` | drift between persisted config and live registries |

Core depends only on Laravel + Lunar + its own classes. A grep for any business
module (`Modules\Product`, `Modules\Cart`, …) inside `modules/Platform` is empty —
this is enforced as a refactor invariant.

---

## Extension points (how to extend without editing Core)

Pick the lightest seam that fits — in order of preference:

1. **Hook filter/action** (`Hooks::*`) — add/adjust a value or react to an event.
   Resource payloads (`product.resource`, `cart.resource`, …), `cart.totals`,
   `checkout.validate`, `price.display`, `product.related`, `section.render`,
   domain events (`order.*`, `customer.*`, `cart.*`, `inventory.*`, …).
2. **Registry extend** — `SearchManager::extend(driver)`,
   `RecommendManager::extend(strategy)`, `SectionRenderer::registerType(type)`,
   `RuleRegistry::field(...)`, `WorkflowRegistry::trigger/registerAction(...)`,
   `AdminPages::add/addResource(...)`.
3. **Decorator** — `Decorator::wrap(Contract::class, MyDecorator::class)` to layer
   behaviour over `PricingContract` / `CartContract` / `CheckoutContract` /
   `SearchEngine`.
4. **Plugin** — a self-contained package/folder gated by `config/plugins.php`.

> Rule of thumb: if Lunar already does it, extend Lunar. If it's a value-add,
> it's a plugin. Core only gains a new capability when it's **generic** (no
> business knowledge) — e.g. the rule/workflow engines.

---

## CLI

```
php artisan plugin:list                  # discovered plugins + state
php artisan plugin:install <id>          # migrate + activate (idempotent)
php artisan plugin:disable <id>          # deactivate, keep data
php artisan plugin:uninstall <id> --force
php artisan plugin:doctor                # plugin requirement check
php artisan platform:doctor              # workflow/registry drift check
```

---

## Versioning & health

- **`PayloadContract::VERSION`** pins the hookable payload shape (product/cart/
  order required keys). **`WorkflowContract::VERSION`** pins the workflow/rule
  definition shape. Bump deliberately on a breaking change so old data is
  detected, not silently broken.
- **`platform:doctor`** reports drift: a workflow whose trigger/action/rule-field
  is no longer registered (e.g. a plugin got disabled), or a structurally invalid
  definition. Read-only.

---

## Reference plugins (dogfood)

Seven first-party plugins prove the seams (see [PLUGIN_SDK.md](PLUGIN_SDK.md) §8):
`acme/reviews` (payload enrich), `acme/preorder` (commerce intervention),
`acme/scout-search` (driver), `acme/wishlist` · `acme/recommend` ·
`acme/analytics` (features extracted from modules), `acme/workflow` (automation
triggers/actions). Each runs with **zero core edits**.
