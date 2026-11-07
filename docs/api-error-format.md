# API Error Format (T6.1)

All API failures are rendered at the exception-handler level (`bootstrap/app.php`)
via `App\Support\ApiResponse`, so every current and future endpoint — including
controllers owned by other phase PRs — returns the same JSON envelope with no
per-controller work:

```json
{
  "message": "The customer email field is required.",
  "errors": { "customer_email": ["The customer email field is required."] },
  "status": 422
}
```

- Success responses may use `ApiResponse::success(data:, message:, status:)` →
  `{ message, data, status }`.
- `errors` is `null` for non-validation failures.

## Mapping

| Exception | Status | Message |
| --- | --- | --- |
| `ValidationException` | 422 | First validator message; full `errors` bag |
| `AuthenticationException` | 401 | `Unauthenticated.` |
| `AuthorizationException` | 403 | Exception message |
| `NotFoundHttpException` (incl. `ModelNotFoundException`, unknown routes) | 404 | `Resource not found.` |
| Other `HttpExceptionInterface` (e.g. 429 throttle) | its status code | Exception message or status phrase |
| Any other `Throwable` | 500 | Generic message unless `APP_DEBUG` |

JSON rendering applies to `api/*` requests or any `Accept: application/json`
request; web routes keep Laravel's default rendering.

Covered by `tests/Feature/ApiErrorFormatTest.php`.

## OpenAPI / Scribe

Scribe is installed and configured (`config/scribe.php`) to document `api/*` routes.
Full generation intentionally **waits until the Phase 2–5 PRs merge** so we never
publish docs for endpoints that don't exist. After merge run:

```bash
ddev artisan scribe:generate
```
