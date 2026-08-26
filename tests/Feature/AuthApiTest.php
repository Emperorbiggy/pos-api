<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

final class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_login_and_access_protected_profile(): void
    {
        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'ECG POS Admin',
            'email' => 'admin@example.com',
            'terminal_id' => '1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $registerResponse
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                    'user' => ['id', 'name', 'email', 'terminal_id', 'created_at'],
                ],
            ])
            ->assertJsonPath('data.user.terminal_id', '1234567890');

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('data.token_type', 'bearer')
            ->assertJsonPath('data.user.terminal_id', '1234567890');

        $token = $loginResponse->json('data.access_token');

        $this->isolateNextRequest();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@example.com');
    }

    public function test_terminal_id_cannot_be_shared_by_two_users(): void
    {
        User::factory()->create(['terminal_id' => '1234567890']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Second Cashier',
            'email' => 'second@example.com',
            'terminal_id' => '1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('terminal_id');
    }

    public function test_access_token_is_valid_for_twenty_four_hours(): void
    {
        $token = $this->tokenForNewUser();

        $this->travel(23)->hours();
        $this->isolateNextRequest();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $this->travel(2)->hours();
        $this->isolateNextRequest();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_expired_token_can_still_be_refreshed_within_the_refresh_window(): void
    {
        $token = $this->tokenForNewUser();

        // Past the 24h access lifetime, still inside the 7 day refresh window.
        $this->travel(3)->days();
        $this->isolateNextRequest();

        $refreshed = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/refresh')
            ->assertOk()
            ->assertJsonPath('data.token_type', 'bearer')
            ->assertJsonPath('data.expires_in', 86400)
            ->assertJsonPath('data.user.terminal_id', '1234567890')
            ->json('data.access_token');

        $this->assertNotSame($token, $refreshed);

        $this->isolateNextRequest();

        $this->withHeader('Authorization', 'Bearer '.$refreshed)
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_token_cannot_be_refreshed_after_the_refresh_window_closes(): void
    {
        $token = $this->tokenForNewUser();

        $this->travel(8)->days();
        $this->isolateNextRequest();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/refresh')
            ->assertUnauthorized();
    }

    public function test_a_token_cannot_be_replayed_once_it_has_been_refreshed(): void
    {
        $token = $this->tokenForNewUser();

        $this->isolateNextRequest();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/refresh')
            ->assertOk();

        // The old token is blacklisted on refresh: it can neither be exchanged
        // again nor used to reach a protected route.
        $this->isolateNextRequest();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/refresh')
            ->assertUnauthorized();

        $this->isolateNextRequest();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_refresh_rejects_a_token_whose_account_no_longer_exists(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        // Valid signature, but the subject is gone. This is what a token minted
        // against another database with the same JWT_SECRET looks like.
        $user->forceDelete();

        $this->isolateNextRequest();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/refresh')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'The account this token belongs to no longer exists. Please log in again.');
    }

    public function test_refresh_rejects_a_request_with_no_token(): void
    {
        $this->postJson('/api/v1/auth/refresh')->assertUnauthorized();
    }

    public function test_protected_oirs_routes_require_jwt_authentication(): void
    {
        $this->postJson('/api/v1/validate-ipn', [
            'ipn' => '931713074597',
            'terminal_id' => '1234567890',
        ])->assertUnauthorized();
    }

    /**
     * Register a fresh user and return the access token issued at login.
     */
    private function tokenForNewUser(): string
    {
        return $this->postJson('/api/v1/auth/register', [
            'name' => 'ECG POS Admin',
            'email' => 'admin@example.com',
            'terminal_id' => '1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()->json('data.access_token');
    }
}
