<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the first administrator, the account allowed to bulk import terminals.
 *
 * Safe to run more than once: an existing admin is promoted and left otherwise
 * untouched, so re-seeding never resets a working password.
 */
final class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('services.terminals.admin_email');
        $name = (string) config('services.terminals.admin_name');
        $terminalId = (string) config('services.terminals.admin_terminal_id');

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            $existing->update(['is_admin' => true]);

            $this->command?->info("Admin already exists: {$email} (promoted, password unchanged)");

            return;
        }

        // A password set in the environment is used as-is; otherwise one is
        // generated and printed once, so no account is ever created with a
        // password that is guessable from this source file.
        $configured = config('services.terminals.admin_password');
        $password = is_string($configured) && $configured !== ''
            ? $configured
            : Str::password(16, symbols: false);

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'terminal_id' => $terminalId,
            'password' => $password,
            'is_admin' => true,
        ]);

        $this->command?->info('Admin created.');
        $this->command?->line("  email:    {$email}");

        if (is_string($configured) && $configured !== '') {
            $this->command?->line('  password: (from TERMINAL_ADMIN_PASSWORD)');

            return;
        }

        $this->command?->line("  password: {$password}");
        $this->command?->warn('  Copy this password now. It is hashed and cannot be shown again.');
    }
}
