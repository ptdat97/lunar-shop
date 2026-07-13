<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Intentionally empty. Every API endpoint is versioned and owned by the module
| that implements it: `modules/<Name>/Routes/api.php`, each self-prefixing
| `api/v1`. Loaded by that module's service provider.
|
| Laravel's stock `GET /api/user` stub used to live here — unversioned, outside
| the `{data}` envelope, and returning the raw User model. `GET /api/v1/customer`
| replaces it.
|
|--------------------------------------------------------------------------
| FROZEN for headless (2026-07-13) — read before adding a route
|--------------------------------------------------------------------------
|
| The Next.js storefront is on hold; Blade SSR is the only storefront. But
| `/api/v1` is NOT "the headless API" — it is the backbone of Blade SSR itself:
| 14 theme JS files call it (cart, coupon, search + suggest, notify-me,
| recommend-size, locations, membership, auth). It stays, and it stays healthy.
|
| What is frozen is the SURFACE, not the code. The rule is: KEEP, DON'T EXTEND.
|
|   - Adding an endpoint/shape because Blade SSR needs it        → fine, as always.
|   - Adding one "for the future app / for when headless returns" → NO. That is
|     building for a consumer that does not exist. The project already decided
|     this once (audit § Phần 4 deferred /home-feed for exactly this reason).
|
| These endpoints have NO Blade consumer today — they exist for a headless/mobile
| client that is currently on hold. Keep them working; do not grow them:
|
|   /home-feed · /devices · /notifications (+ read, read-all)
|   /orders/{id}/timeline · /auth/token/* (PAT issue/refresh/revoke)
|   /banners · /pages · /collections/{slug} · /wishlist · /orders
|   /products/{product}/reviews · /checkout/* · /health (infra probe)
|
| Note /auth/token/* also carries token expiry + abilities — a guard with a
| mutation-check behind it (increment #4). Do not remove it to "clean up": it
| costs nothing to keep and removing it deletes a proven security layer.
|
| Full rationale + the trigger for un-freezing: todo.md § 11, plan.md § Quyết
| định có chủ đích.
|
*/
