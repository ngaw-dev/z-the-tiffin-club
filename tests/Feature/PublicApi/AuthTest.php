<?php

namespace Tests\Feature\PublicApi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_customer_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Amol Tester',
            'email' => 'amol@example.test',
            'phone' => '0211234567',
            'password' => 'super-secret-1',
            'password_confirmation' => 'super-secret-1',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email']]]);

        $this->assertDatabaseHas('users', [
            'email' => 'amol@example.test',
            'role' => 'customer',
        ]);
    }

    public function test_register_validates_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dupe@example.test']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Dupe',
            'email' => 'dupe@example.test',
            'password' => 'super-secret-1',
            'password_confirmation' => 'super-secret-1',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'super-secret-1']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'super-secret-1',
        ]);

        $response->assertOk()->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // The token row is revoked, so the bearer token can no longer authenticate.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
