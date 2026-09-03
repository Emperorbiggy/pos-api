<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

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
        // Fallbacks are repeated here rather than relying solely on config():
        // a stale config cache resolves these keys to null, and casting that to
        // a string would quietly seed an account with an empty email that can
        // never log in. Defaults keep the seeder correct either way.
        $email = $this->setting('services.terminals.admin_email', 'admin@ecgpos.local');
        $name = $this->setting('services.terminals.admin_name', 'ECG POS Administrator');
        $terminalId = $this->setting('services.terminals.admin_terminal_id', 'ADMIN-001');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                "Refusing to seed an admin with an unusable email [{$email}]. ".
                'Set TERMINAL_ADMIN_EMAIL, then run `php artisan config:clear` before seeding.'
            );
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            // Set outside fill(): is_admin is deliberately not mass assignable,
            // so no request payload can ever promote an account.
            $existing->is_admin = true;
            $existing->save();

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

        $admin = new User();
        $admin->fill([
            'name' => $name,
            'email' => $email,
            'terminal_id' => $terminalId,
            'password' => $password,
        ]);
        // Assigned directly: is_admin is not mass assignable, so fill() drops it.
        $admin->is_admin = true;
        $admin->save();

        $this->command?->info('Admin created.');
        $this->command?->line("  email:    {$email}");

        if (is_string($configured) && $configured !== '') {
            $this->command?->line('  password: (from TERMINAL_ADMIN_PASSWORD)');

            return;
        }

        $this->command?->line("  password: {$password}");
        $this->command?->warn('  Copy this password now. It is hashed and cannot be shown again.');
    }

    /**
     * Read a config string, falling back when it is missing or blank.
     */
    private function setting(string $key, string $fallback): string
    {
        $value = config($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }
}
