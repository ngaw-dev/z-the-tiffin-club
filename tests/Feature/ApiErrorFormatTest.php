<?php

namespace Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

/**
 * T6.1 — the uniform JSON error envelope must apply to every API route,
 * regardless of which controller threw. Routes are defined inline so this
 * test does not depend on sibling-owned route files.
 */
class ApiErrorFormatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api/v1')->group(function (): void {
            Route::get('/_test/validation', fn () => throw ValidationException::withMessages([
                'customer_email' => ['The customer email field is required.'],
            ]));

            Route::get('/_test/not-found', fn () => throw new ModelNotFoundException);

            Route::get('/_test/unauthenticated', fn () => throw new AuthenticationException);

            Route::get('/_test/forbidden', fn () => throw new AuthorizationException);

            Route::get('/_test/throttle', fn () => throw new TooManyRequestsHttpException);

            Route::get('/_test/server-error', fn () => throw new \RuntimeException('boom'));
        });
    }

    public function test_validation_errors_use_the_envelope(): void
    {
        $this->getJson('/api/v1/_test/validation')
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['customer_email'], 'status'])
            ->assertJsonPath('status', 422);
    }

    public function test_model_not_found_becomes_404_envelope(): void
    {
        $this->getJson('/api/v1/_test/not-found')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Resource not found.')
            ->assertJsonPath('status', 404)
            ->assertJsonPath('errors', null);
    }

    public function test_unauthenticated_becomes_401_envelope(): void
    {
        $this->getJson('/api/v1/_test/unauthenticated')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('status', 401);
    }

    public function test_forbidden_becomes_403_envelope(): void
    {
        $this->getJson('/api/v1/_test/forbidden')
            ->assertStatus(403)
            ->assertJsonPath('status', 403);
    }

    public function test_http_exception_status_is_preserved(): void
    {
        $this->getJson('/api/v1/_test/throttle')
            ->assertStatus(429)
            ->assertJsonPath('status', 429);
    }

    public function test_server_error_hides_details_but_keeps_envelope(): void
    {
        config(['app.debug' => false]);

        $this->getJson('/api/v1/_test/server-error')
            ->assertStatus(500)
            ->assertJsonPath('message', 'Server Error. Please try again later.')
            ->assertJsonPath('status', 500);
    }

    public function test_non_api_routes_keep_default_rendering(): void
    {
        // The renderer must not hijack web routes.
        $this->get('/up')->assertStatus(200);
    }
}
