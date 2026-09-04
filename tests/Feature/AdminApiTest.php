<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

final class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_ordinary_terminal_cannot_reach_any_admin_route(): void
    {
        $terminal = User::factory()->create(['is_admin' => false]);

        $this->as($terminal)->getJson('/api/v1/admin/terminals')->assertForbidden();
        $this->as($terminal)->getJson('/api/v1/admin/transactions')->assertForbidden();
        $this->as($terminal)->getJson('/api/v1/admin/transactions/summary')->assertForbidden();
        $this->as($terminal)->patchJson('/api/v1/admin/terminals/'.$terminal->id, ['name' => 'Hacked'])
            ->assertForbidden();

        $this->assertNotSame('Hacked', $terminal->refresh()->name);
    }

    public function test_admin_routes_reject_an_unauthenticated_caller(): void
    {
        $this->getJson('/api/v1/admin/terminals')->assertUnauthorized();
    }

    public function test_an_admin_sees_every_terminal_with_its_totals(): void
    {
        $admin = $this->admin();
        $busy = User::factory()->create(['name' => 'Ilesa Terminal', 'terminal_id' => '204401PG']);
        User::factory()->create(['name' => 'Quiet Terminal', 'terminal_id' => '999999XX']);

        Payment::factory()->count(2)->for($busy)->paid()->create(['amount_paid' => 5000]);

        $response = $this->as($admin)->getJson('/api/v1/admin/terminals')->assertOk();

        // Three terminals: the admin plus the two above, ordered by name.
        $response->assertJsonPath('meta.total', 3);

        $ilesa = collect($response->json('data'))->firstWhere('terminal_id', '204401PG');
        $this->assertSame(2, $ilesa['transactions_count']);
        // assertEquals, not assertSame: JSON gives back 10000 as an int.
        $this->assertEquals(10000, $ilesa['total_collected']);

        $quiet = collect($response->json('data'))->firstWhere('terminal_id', '999999XX');
        $this->assertSame(0, $quiet['transactions_count']);
    }

    public function test_a_terminal_listing_never_exposes_password_or_pin_hashes(): void
    {
        $admin = $this->admin();
        User::factory()->create(['pin' => '1234']);

        $body = $this->as($admin)->getJson('/api/v1/admin/terminals')->assertOk()->getContent();

        $this->assertStringNotContainsString('password', $body);
        $this->assertStringNotContainsString('$2y$', $body);
        $this->assertStringContainsString('has_pin', $body);
    }

    public function test_terminals_can_be_searched(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Ilesa Main', 'terminal_id' => '204401PG']);
        User::factory()->create(['name' => 'Osogbo Central', 'terminal_id' => '777777ZZ']);

        $this->as($admin)->getJson('/api/v1/admin/terminals?search=Ilesa')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.terminal_id', '204401PG');

        $this->as($admin)->getJson('/api/v1/admin/terminals?search=777777')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Osogbo Central');
    }

    public function test_an_admin_sees_transactions_across_every_terminal(): void
    {
        $admin = $this->admin();
        $first = User::factory()->create(['terminal_id' => '204401PG']);
        $second = User::factory()->create(['terminal_id' => '999999XX']);

        Payment::factory()->count(2)->for($first)->create(['terminal_id' => '204401PG']);
        Payment::factory()->for($second)->create(['terminal_id' => '999999XX']);

        $this->as($admin)->getJson('/api/v1/admin/transactions')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_transactions_can_be_filtered_to_one_terminal(): void
    {
        $admin = $this->admin();
        $first = User::factory()->create(['terminal_id' => '204401PG']);
        $second = User::factory()->create(['terminal_id' => '999999XX']);

        Payment::factory()->count(2)->for($first)->create(['terminal_id' => '204401PG']);
        Payment::factory()->for($second)->create(['terminal_id' => '999999XX']);

        $this->as($admin)->getJson('/api/v1/admin/transactions?terminal_id=204401PG')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_the_summary_totals_honour_the_same_filters_as_the_list(): void
    {
        $admin = $this->admin();
        $terminal = User::factory()->create(['terminal_id' => '204401PG']);
        $other = User::factory()->create(['terminal_id' => '999999XX']);

        Payment::factory()->count(2)->for($terminal)->paid()->create([
            'terminal_id' => '204401PG', 'amount_paid' => 3000, 'total_amount' => 3000,
        ]);
        Payment::factory()->for($terminal)->create(['terminal_id' => '204401PG', 'amount_paid' => 0]);
        Payment::factory()->for($other)->paid()->create(['terminal_id' => '999999XX', 'amount_paid' => 9999]);

        $this->as($admin)->getJson('/api/v1/admin/transactions/summary')
            ->assertOk()
            ->assertJsonPath('data.transactions', 4)
            ->assertJsonPath('data.terminals', 2);

        $this->as($admin)->getJson('/api/v1/admin/transactions/summary?terminal_id=204401PG')
            ->assertOk()
            ->assertJsonPath('data.transactions', 3)
            ->assertJsonPath('data.total_collected', 6000)
            ->assertJsonPath('data.paid', 2)
            ->assertJsonPath('data.pending', 1);
    }

    public function test_an_admin_can_reset_a_terminals_name_password_and_pin(): void
    {
        $admin = $this->admin();
        $terminal = User::factory()->create(['name' => 'Old Name', 'terminal_id' => '204401PG']);

        $this->as($admin)->patchJson('/api/v1/admin/terminals/'.$terminal->id, [
            'name' => 'Ilesa Main Terminal',
            'password' => 'brandnew123',
            'password_confirmation' => 'brandnew123',
            'pin' => '4321',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ilesa Main Terminal')
            ->assertJsonPath('data.has_pin', true);

        $terminal->refresh();

        $this->assertSame('Ilesa Main Terminal', $terminal->name);
        $this->assertTrue(Hash::check('brandnew123', $terminal->password), 'password should be rehashed');
        $this->assertTrue(Hash::check('4321', $terminal->pin), 'pin should be rehashed');
        $this->assertNotSame('4321', $terminal->pin, 'pin must not be stored in the clear');
    }

    public function test_a_terminal_id_can_be_changed_but_not_onto_another_terminal(): void
    {
        $admin = $this->admin();
        $terminal = User::factory()->create(['terminal_id' => '204401PG']);
        User::factory()->create(['terminal_id' => 'TAKEN01']);

        $this->as($admin)->patchJson('/api/v1/admin/terminals/'.$terminal->id, ['terminal_id' => 'TAKEN01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('terminal_id');

        $this->as($admin)->patchJson('/api/v1/admin/terminals/'.$terminal->id, ['terminal_id' => 'FREE001'])
            ->assertOk()
            ->assertJsonPath('data.terminal_id', 'FREE001');
    }

    public function test_resaving_a_terminal_unchanged_does_not_trip_its_own_uniqueness(): void
    {
        $admin = $this->admin();
        $terminal = User::factory()->create(['terminal_id' => '204401PG']);

        $this->as($admin)->patchJson('/api/v1/admin/terminals/'.$terminal->id, [
            'terminal_id' => '204401PG',
            'name' => 'Renamed',
        ])->assertOk();
    }

    public function test_a_short_password_or_malformed_pin_is_rejected(): void
    {
        $admin = $this->admin();
        $terminal = User::factory()->create();
        $originalPassword = $terminal->password;

        $this->as($admin)->patchJson('/api/v1/admin/terminals/'.$terminal->id, [
            'password' => 'short', 'password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->as($admin)->patchJson('/api/v1/admin/terminals/'.$terminal->id, ['pin' => '12'])
            ->assertUnprocessable()->assertJsonValidationErrors('pin');

        $this->as($admin)->patchJson('/api/v1/admin/terminals/'.$terminal->id, ['pin' => 'abcd'])
            ->assertUnprocessable()->assertJsonValidationErrors('pin');

        $this->assertSame($originalPassword, $terminal->refresh()->password);
    }

    public function test_a_partial_update_leaves_untouched_fields_alone(): void
    {
        $admin = $this->admin();
        $terminal = User::factory()->create(['name' => 'Keep Me', 'terminal_id' => '204401PG']);
        $password = $terminal->password;

        $this->as($admin)->patchJson('/api/v1/admin/terminals/'.$terminal->id, ['name' => 'New Name'])
            ->assertOk();

        $terminal->refresh();

        $this->assertSame('New Name', $terminal->name);
        $this->assertSame('204401PG', $terminal->terminal_id);
        $this->assertSame($password, $terminal->password, 'password must survive a name-only edit');
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function as(User $user): self
    {
        $token = JWTAuth::fromUser($user);

        $this->isolateNextRequest();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
