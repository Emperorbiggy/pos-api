<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use RuntimeException;
use Tests\TestCase;

final class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_an_account_that_is_actually_an_admin(): void
    {
        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->sole();

        // is_admin is not mass assignable, so a seeder using create() would
        // silently produce a non-admin that its own endpoints reject.
        $this->assertTrue($admin->is_admin);
        $this->assertSame('admin@ecgpos.local', $admin->email);
        $this->assertSame('ADMIN-001', $admin->terminal_id);
    }

    public function test_the_seeded_admin_can_reach_the_import_endpoint(): void
    {
        config(['services.terminals.admin_password' => 'seeded-password']);

        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->sole();
        $token = JWTAuth::fromUser($admin);

        $this->isolateNextRequest();

        // 422 for the missing file proves the admin gate was passed, not 403.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/terminals/import', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_a_configured_password_is_used_verbatim(): void
    {
        config(['services.terminals.admin_password' => 'chosen-password']);

        $this->seed(AdminUserSeeder::class);

        $this->assertTrue(Hash::check('chosen-password', User::query()->sole()->password));
    }

    public function test_running_it_twice_leaves_the_password_alone(): void
    {
        config(['services.terminals.admin_password' => 'first-password']);
        $this->seed(AdminUserSeeder::class);

        $original = User::query()->sole()->password;

        config(['services.terminals.admin_password' => 'second-password']);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::query()->count());
        $this->assertSame($original, User::query()->sole()->password);
        $this->assertTrue(Hash::check('first-password', User::query()->sole()->password));
    }

    public function test_it_promotes_an_existing_account_of_the_same_email(): void
    {
        $existing = User::factory()->create([
            'email' => 'admin@ecgpos.local',
            'terminal_id' => 'SOMETHING-ELSE',
        ]);
        // refresh(): the column default only lands on the in-memory model once
        // it is read back from the database.
        $this->assertFalse($existing->refresh()->is_admin);

        $this->seed(AdminUserSeeder::class);

        $this->assertTrue($existing->refresh()->is_admin);
        $this->assertSame(1, User::query()->count());
    }

    public function test_a_stale_config_cache_cannot_seed_a_blank_email(): void
    {
        // What a server that cached its config before these keys existed sees.
        config(['services.terminals' => null]);

        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->sole();

        $this->assertSame('admin@ecgpos.local', $admin->email);
        $this->assertTrue($admin->is_admin);
    }

    public function test_an_unusable_email_aborts_instead_of_seeding_a_dead_account(): void
    {
        config(['services.terminals.admin_email' => 'not-an-email']);

        $this->expectException(RuntimeException::class);

        try {
            $this->seed(AdminUserSeeder::class);
        } finally {
            $this->assertSame(0, User::query()->count());
        }
    }
}
