# The Tiffin Box — Backend API

Laravel 12 API-only backend for The Tiffin Box, a daily tiffin-meal ordering service.
**This repository is the API only** — all responses are JSON; the customer/owner frontend
will live in a separate repository added later. (The Phase 0 Laravel scaffold still ships
default Blade/Vite boilerplate like `resources/views` and `vite.config.js`; these are
unused by the API and marked for later cleanup.)

## Stack

- PHP 8.3 / Laravel 12 (API-only)
- MySQL 8
- DDEV for local development (includes Mailpit for email capture)
- Laravel Sanctum (auth), Scribe (OpenAPI docs), Pint + Larastan + PHPUnit (quality)

## DDEV Quickstart

```bash
ddev start                 # bring up the project (https://the-tiffin-box.ddev.site)
ddev composer install      # install dependencies (first run only)
ddev exec cp .env.example .env && ddev artisan key:generate
ddev artisan migrate       # run migrations
ddev artisan db:seed       # seed dev data (today's meal, extras, owner user, sample orders)
ddev artisan test          # run the test suite
```

Useful commands:

| Command | What it does |
| --- | --- |
| `ddev describe` | Show service URLs/ports (web, DB, Mailpit) |
| `ddev launch` | Open the app in your browser |
| `ddev artisan scribe:generate` | Regenerate API docs (see below) |
| `ddev php -v` | Verify PHP 8.3 |

Mailpit is available at the `mailpit` HTTPS port shown by `ddev describe` — order
confirmation and status-update emails (Phase 5) land there locally.

> If you run DDEV alongside other DDEV projects from sibling worktrees and hit a
> project-name/port conflict, rename the project locally (uncommitted edit of `name:`
> in `.ddev/config.yaml`) or use `ddev config --ports` — never stop another worker's project.

## API Overview

All endpoints live under `/api/v1` and return a uniform JSON envelope:

- Success: `{ "message": ..., "data": ..., "status": 200 }`
- Error:   `{ "message": ..., "errors": {...}|null, "status": 4xx }`

| Method | Endpoint | Phase | Status |
| --- | --- | --- | --- |
| GET | `/api/v1/menu/today` | 3 — Storefront | Merged |
| POST | `/api/v1/orders` | 3 — Storefront | Merged |
| GET | `/api/v1/orders/{orderNumber}` | 3 — Storefront | Merged |
| POST | `/api/v1/auth/register` | 3 — Storefront | Merged |
| POST | `/api/v1/auth/login` | 3 — Storefront | Merged |
| POST | `/api/v1/auth/logout` | 3 — Storefront | Merged |
| GET | `/api/v1/account/orders` | 3 — Storefront | Merged |
| POST | `/api/v1/admin/meals` | 4 — Admin | In flight (PR #6) |
| PUT/DELETE | `/api/v1/admin/meals/{date}` | 4 — Admin | In flight (PR #6) |
| GET/PUT | `/api/v1/admin/settings` | 4 — Admin | In flight (PR #6) |
| GET | `/api/v1/admin/orders` | 4 — Admin | In flight (PR #6) |
| PATCH | `/api/v1/admin/orders/{id}/status` | 4 — Admin | In flight (PR #6) |

Phases 0–3 and 5 (scaffold, domain model, business rules, storefront, emails) are
merged; Phase 4 (admin) is in flight on PR #6. Order confirmation and status-update
emails are queued on order placement / status change and land in Mailpit locally.
See `docs/` for the ERD.

## Settings Defaults

Owner-configurable via the `settings` table (Phase 4 `PUT /api/v1/admin/settings`):

| Key | Default | Meaning |
| --- | --- | --- |
| `delivery_cutoff` | `16:00` | Last time to place a delivery order |
| `pickup_cutoff` | `20:00` | Last time to place a pickup order |
| `pickup_window_start` | `18:00` | Pickup window opens |
| `pickup_window_end` | `20:00` | Pickup window closes |
| `ordering_open` | `1` | Manual open/close override |
| `restaurant_address` | `123 Cuba Street, Wellington` | Pickup address |
| `delivery_city` | `Wellington` | Only city eligible for delivery |

## CORS

The future frontend origin is configurable, not hard-coded: set `FRONTEND_URL`
(defaults to `http://localhost:5173` for dev) and optionally `CORS_ALLOWED_ORIGINS`
(comma-separated) in `.env`. See `config/cors.php`.

## API Documentation (Scribe)

Scribe is configured (`config/scribe.php`) to document `api/*` routes. Storefront
and email flows are merged, but full generation waits until the remaining phase PRs
(Phase 4 admin, PR #6) merge so the docs cover the complete API in one pass. After
merge:

```bash
ddev artisan scribe:generate   # writes static docs + OpenAPI spec
```

## Testing & CI

```bash
ddev artisan test              # full suite (phpunit.xml uses in-memory SQLite)
ddev bin pint --test           # code style
ddev bin phpstan analyse       # static analysis (Larastan, level 5)
```

GitHub Actions (`.github/workflows/ci.yml`) runs Pint, PHPStan and the full test
suite on PHP 8.3 against a MySQL 8 service on every push and PR.

Note: PHPStan currently analyses `app/`, `bootstrap/` and `config/`; widening it to
`database/` and `tests/` needs `@property` docblocks on the Phase 1 models — tracked
as follow-up.
