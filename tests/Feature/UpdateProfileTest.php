<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

final class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_merchant_can_change_their_name(): void
    {
        $merchant = User::factory()->create(['name' => 'Old Name', 'terminal_id' => '204401PG']);

        $this->updateProfile($merchant, ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.terminal_id', '204401PG');

        $this->assertSame('New Name', $merchant->refresh()->name);
    }

    public function test_a_merchant_can_change_their_terminal_id(): void
    {
        $merchant = User::factory()->create(['name' => 'Same Name', 'terminal_id' => '204401PG']);

        $this->updateProfile($merchant, ['terminal_id' => 'NEW999XX'])
            ->assertOk()
            ->assertJsonPath('data.terminal_id', 'NEW999XX')
            ->assertJsonPath('data.name', 'Same Name');

        $this->assertSame('NEW999XX', $merchant->refresh()->terminal_id);
    }

    public function test_both_fields_can_be_changed_at_once(): void
    {
        $merchant = User::factory()->create(['name' => 'Old', 'terminal_id' => 'OLD111']);

        $this->updateProfile($merchant, ['name' => 'New', 'terminal_id' => 'NEW222'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New')
            ->assertJsonPath('data.terminal_id', 'NEW222');
    }

    public function test_a_terminal_id_belonging_to_another_merchant_is_rejected(): void
    {
        User::factory()->create(['terminal_id' => 'TAKEN01']);
        $merchant = User::factory()->create(['terminal_id' => '204401PG']);

        $this->updateProfile($merchant, ['terminal_id' => 'TAKEN01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('terminal_id');

        $this->assertSame('204401PG', $merchant->refresh()->terminal_id);
    }

    public function test_keeping_your_own_terminal_id_is_not_treated_as_a_clash(): void
    {
        $merchant = User::factory()->create(['terminal_id' => '204401PG']);

        $this->updateProfile($merchant, ['name' => 'Renamed', 'terminal_id' => '204401PG'])
            ->assertOk()
            ->assertJsonPath('data.terminal_id', '204401PG');
    }

    public function test_blank_values_are_rejected(): void
    {
        $merchant = User::factory()->create(['name' => 'Keep Me', 'terminal_id' => '204401PG']);

        $this->updateProfile($merchant, ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->updateProfile($merchant, ['terminal_id' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('terminal_id');

        $this->assertSame('Keep Me', $merchant->refresh()->name);
    }

    public function test_email_and_password_cannot_be_changed_here(): void
    {
        $merchant = User::factory()->create(['email' => 'original@example.com']);
        $originalPassword = $merchant->password;

        $this->updateProfile($merchant, [
            'name' => 'Renamed',
            'email' => 'attacker@example.com',
            'password' => 'hunter2hunter2',
        ])->assertOk();

        $merchant->refresh();

        $this->assertSame('original@example.com', $merchant->email);
        $this->assertSame($originalPassword, $merchant->password);
    }

    public function test_changing_the_terminal_id_leaves_recorded_payments_untouched(): void
    {
        $merchant = User::factory()->create(['terminal_id' => '204401PG']);
        Payment::factory()->for($merchant)->create(['terminal_id' => '204401PG']);

        $this->updateProfile($merchant, ['terminal_id' => 'NEW999XX'])->assertOk();

        // The payment was collected on the old terminal and must still say so.
        $this->assertSame('204401PG', $merchant->payments()->sole()->terminal_id);
    }

    public function test_the_endpoint_requires_authentication(): void
    {
        $this->patchJson('/api/v1/auth/profile', ['name' => 'Nobody'])
            ->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateProfile(User $merchant, array $payload): TestResponse
    {
        $token = JWTAuth::fromUser($merchant);

        $this->isolateNextRequest();

        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/auth/profile', $payload);
    }
}
