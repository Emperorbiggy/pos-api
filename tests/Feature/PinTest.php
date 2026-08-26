<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

final class PinTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_does_not_ask_for_a_pin(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'ECG POS Admin',
            'email' => 'admin@example.com',
            'terminal_id' => '204401PG',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.has_pin', false);

        $this->assertNull(User::query()->sole()->pin);
    }

    public function test_a_merchant_creates_their_pin_after_registering(): void
    {
        $merchant = User::factory()->withoutPin()->create();

        $this->createPin($merchant, '4821')
            ->assertCreated()
            ->assertExactJson(['data' => ['has_pin' => true]]);

        $this->verifyPin($merchant->refresh(), '4821')->assertOk();
    }

    public function test_creating_a_pin_twice_is_refused(): void
    {
        $merchant = User::factory()->create(['pin' => '4821']);

        $this->createPin($merchant, '9999')
            ->assertStatus(409)
            ->assertJsonPath('message', 'A pin is already set on this account. Use PUT /api/v1/auth/pin to change it.');

        // The original PIN stands.
        $this->verifyPin($merchant->refresh(), '4821')->assertOk();
    }

    public function test_a_pin_must_be_four_to_six_digits(): void
    {
        $merchant = User::factory()->withoutPin()->create();

        foreach (['123', '1234567', 'abcd', '12a4', ''] as $bad) {
            $this->createPin($merchant, $bad)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('pin');
        }

        $this->assertNull($merchant->refresh()->pin);
    }

    public function test_a_mismatched_confirmation_is_refused(): void
    {
        $merchant = User::factory()->withoutPin()->create();

        $this->asMerchant($merchant)
            ->postJson('/api/v1/auth/pin', ['pin' => '4821', 'pin_confirmation' => '1111'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pin');

        $this->assertNull($merchant->refresh()->pin);
    }

    public function test_a_correct_pin_is_accepted(): void
    {
        $merchant = User::factory()->create(['pin' => '4821']);

        $this->verifyPin($merchant, '4821')
            ->assertOk()
            ->assertExactJson(['data' => ['valid' => true]]);
    }

    public function test_an_incorrect_pin_is_rejected(): void
    {
        $merchant = User::factory()->create(['pin' => '4821']);

        $this->verifyPin($merchant, '0000')
            ->assertUnprocessable()
            ->assertJsonPath('errors.pin.0', 'The pin is incorrect.');
    }

    public function test_the_pin_is_stored_hashed_and_never_returned(): void
    {
        $merchant = User::factory()->create(['pin' => '4821']);

        $this->assertNotSame('4821', $merchant->pin);
        $this->assertTrue(Hash::check('4821', $merchant->pin));

        $body = $this->asMerchant($merchant)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.has_pin', true)
            ->getContent();

        $this->assertStringNotContainsString('4821', $body);
    }

    public function test_a_pin_with_a_leading_zero_survives(): void
    {
        $merchant = User::factory()->withoutPin()->create();

        $this->createPin($merchant, '0042')->assertCreated();
        $merchant->refresh();

        $this->verifyPin($merchant, '0042')->assertOk();
        $this->verifyPin($merchant, '42')->assertUnprocessable();
    }

    public function test_verifying_without_a_pin_set_reports_that_clearly(): void
    {
        $merchant = User::factory()->withoutPin()->create();

        $this->verifyPin($merchant, '1234')
            ->assertStatus(409)
            ->assertJsonPath('message', 'No pin has been set on this account yet. Set one from your profile first.');
    }

    public function test_changing_a_pin_that_does_not_exist_is_refused(): void
    {
        $merchant = User::factory()->withoutPin()->create();

        $this->updatePin($merchant, ['pin' => '9999'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'No pin has been set on this account yet. Use POST /api/v1/auth/pin to create one.');
    }

    public function test_changing_a_pin_requires_the_current_one(): void
    {
        $merchant = User::factory()->create(['pin' => '4821']);

        $this->updatePin($merchant, ['pin' => '9999'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_pin');

        $this->verifyPin($merchant->refresh(), '4821')->assertOk();
    }

    public function test_a_wrong_current_pin_is_rejected(): void
    {
        $merchant = User::factory()->create(['pin' => '4821']);

        $this->updatePin($merchant, ['pin' => '9999', 'current_pin' => '0000'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.current_pin.0', 'Your current pin is incorrect.');

        $this->verifyPin($merchant->refresh(), '4821')->assertOk();
    }

    public function test_a_pin_can_be_changed_with_the_current_one(): void
    {
        $merchant = User::factory()->create(['pin' => '4821']);

        $this->updatePin($merchant, ['pin' => '9999', 'current_pin' => '4821'])
            ->assertOk()
            ->assertExactJson(['data' => ['has_pin' => true]]);

        $merchant->refresh();

        $this->verifyPin($merchant, '9999')->assertOk();
        $this->verifyPin($merchant, '4821')->assertUnprocessable();
    }

    public function test_the_profile_endpoint_cannot_touch_the_pin(): void
    {
        $merchant = User::factory()->create(['pin' => '4821', 'name' => 'Before']);

        $this->asMerchant($merchant)
            ->patchJson('/api/v1/auth/profile', ['name' => 'After', 'pin' => '9999'])
            ->assertOk()
            ->assertJsonPath('data.name', 'After');

        // The name changed; the PIN did not.
        $this->verifyPin($merchant->refresh(), '4821')->assertOk();
    }

    public function test_guessing_is_rate_limited(): void
    {
        $merchant = User::factory()->create(['pin' => '4821']);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->verifyPin($merchant, '0000')->assertUnprocessable();
        }

        // A 4 digit PIN is 10,000 guesses; without a cap it falls in seconds.
        $this->verifyPin($merchant, '0000')->assertStatus(429);
    }

    public function test_the_pin_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/auth/pin', ['pin' => '1234'])->assertUnauthorized();
        $this->putJson('/api/v1/auth/pin', ['pin' => '1234'])->assertUnauthorized();
        $this->postJson('/api/v1/auth/verify-pin', ['pin' => '1234'])->assertUnauthorized();
    }

    private function createPin(User $merchant, string $pin): TestResponse
    {
        return $this->asMerchant($merchant)->postJson('/api/v1/auth/pin', ['pin' => $pin]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updatePin(User $merchant, array $payload): TestResponse
    {
        return $this->asMerchant($merchant)->putJson('/api/v1/auth/pin', $payload);
    }

    private function verifyPin(User $merchant, string $pin): TestResponse
    {
        return $this->asMerchant($merchant)->postJson('/api/v1/auth/verify-pin', ['pin' => $pin]);
    }

    private function asMerchant(User $merchant): self
    {
        $token = JWTAuth::fromUser($merchant);

        $this->isolateNextRequest();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
