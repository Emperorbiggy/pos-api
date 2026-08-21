<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@example.com');
    }

    public function test_protected_oirs_routes_require_jwt_authentication(): void
    {
        $this->postJson('/api/v1/validate-ipn', [
            'ipn' => '931713074597',
            'terminal_id' => '1234567890',
        ])->assertUnauthorized();
    }
}
