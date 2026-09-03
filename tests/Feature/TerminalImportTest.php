<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

final class TerminalImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sheet_of_terminals_becomes_one_login_each(): void
    {
        $response = $this->upload([
            ['Terminal ID', 'Serial Number'],
            ['204401PG', 'SN-99321'],
            ['118222XX', 'SN-77410'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.summary.processed', 2)
            ->assertJsonPath('data.summary.created', 2)
            ->assertJsonPath('data.summary.skipped', 0)
            ->assertJsonPath('data.summary.failed', 0)
            ->assertJsonPath('data.created.0.terminal_id', '204401PG')
            ->assertJsonPath('data.created.0.email', '204401pg@ecgpos.local');

        $this->assertDatabaseHas('users', [
            'terminal_id' => '204401PG',
            'email' => '204401pg@ecgpos.local',
            'name' => 'Terminal 204401PG (SN-99321)',
        ]);
        $this->assertDatabaseHas('users', ['terminal_id' => '118222XX']);
    }

    public function test_the_returned_password_actually_logs_the_terminal_in(): void
    {
        $response = $this->upload([
            ['Terminal ID', 'Serial Number'],
            ['204401PG', 'SN-99321'],
        ])->assertOk();

        $email = $response->json('data.created.0.email');
        $password = $response->json('data.created.0.password');

        $this->isolateNextRequest();

        $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password])
            ->assertOk()
            ->assertJsonPath('data.user.terminal_id', '204401PG');
    }

    public function test_passwords_are_stored_hashed_not_in_the_clear(): void
    {
        $response = $this->upload([
            ['Terminal ID'],
            ['204401PG'],
        ])->assertOk();

        $password = $response->json('data.created.0.password');
        $user = User::query()->where('terminal_id', '204401PG')->sole();

        $this->assertNotSame($password, $user->password);
        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function test_generated_passwords_differ_between_terminals(): void
    {
        $response = $this->upload([
            ['Terminal ID'],
            ['AAA111'],
            ['BBB222'],
            ['CCC333'],
        ])->assertOk();

        $passwords = array_column($response->json('data.created'), 'password');

        $this->assertCount(3, array_unique($passwords));
        foreach ($passwords as $password) {
            $this->assertSame(12, mb_strlen($password));
        }
    }

    public function test_a_terminal_that_already_has_a_login_is_skipped_not_touched(): void
    {
        $existing = User::factory()->create(['terminal_id' => '204401PG']);
        $originalPassword = $existing->password;

        $this->upload([
            ['Terminal ID'],
            ['204401PG'],
            ['118222XX'],
        ])
            ->assertOk()
            ->assertJsonPath('data.summary.created', 1)
            ->assertJsonPath('data.summary.skipped', 1)
            ->assertJsonPath('data.skipped.0.terminal_id', '204401PG')
            ->assertJsonPath('data.skipped.0.reason', 'A login already exists for this terminal.');

        // The working account keeps its password.
        $this->assertSame($originalPassword, $existing->refresh()->password);
    }

    public function test_re_uploading_the_same_file_creates_nothing_further(): void
    {
        $rows = [
            ['Terminal ID', 'Serial Number'],
            ['204401PG', 'SN-1'],
            ['118222XX', 'SN-2'],
        ];

        $admin = User::factory()->admin()->create(['terminal_id' => 'ADMIN001']);

        $this->uploadAs($admin, $rows)->assertOk()->assertJsonPath('data.summary.created', 2);

        $this->uploadAs($admin, $rows)
            ->assertOk()
            ->assertJsonPath('data.summary.created', 0)
            ->assertJsonPath('data.summary.skipped', 2);

        $this->assertSame(3, User::query()->count()); // 2 terminals + the admin uploading
    }

    public function test_a_terminal_repeated_within_one_file_is_only_created_once(): void
    {
        $this->upload([
            ['Terminal ID'],
            ['204401PG'],
            ['204401PG'],
        ])
            ->assertOk()
            ->assertJsonPath('data.summary.created', 1)
            ->assertJsonPath('data.summary.skipped', 1)
            ->assertJsonPath('data.skipped.0.reason', 'Duplicated earlier in this file at row 2.');
    }

    public function test_header_labels_are_matched_loosely(): void
    {
        $this->upload([
            ['  terminal_id  ', 'serial no'],
            ['204401PG', 'SN-1'],
        ])
            ->assertOk()
            ->assertJsonPath('data.summary.created', 1);
    }

    public function test_blank_rows_are_ignored_rather_than_reported_as_failures(): void
    {
        $this->upload([
            ['Terminal ID'],
            ['204401PG'],
            [''],
            [''],
            ['118222XX'],
        ])
            ->assertOk()
            ->assertJsonPath('data.summary.created', 2)
            ->assertJsonPath('data.summary.failed', 0);
    }

    public function test_a_bad_row_fails_alone_and_the_rest_still_import(): void
    {
        $this->upload([
            ['Terminal ID'],
            ['204401PG'],
            ['!!!'],
            ['118222XX'],
        ])
            ->assertOk()
            ->assertJsonPath('data.summary.created', 2)
            ->assertJsonPath('data.summary.failed', 1)
            ->assertJsonPath('data.failed.0.row', 3);

        $this->assertDatabaseHas('users', ['terminal_id' => '204401PG']);
        $this->assertDatabaseHas('users', ['terminal_id' => '118222XX']);
    }

    public function test_a_sheet_without_a_terminal_column_is_rejected(): void
    {
        $this->upload([
            ['Serial Number', 'Location'],
            ['SN-1', 'Ilesa'],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'No terminal ID column found. Expected a header cell reading "Terminal ID".');

        $this->assertSame(1, User::query()->count()); // only the uploader
    }

    public function test_the_email_domain_can_be_overridden(): void
    {
        $this->asAdmin()
            ->post('/api/v1/terminals/import', [
                'file' => $this->sheet([['Terminal ID'], ['204401PG']]),
                'email_domain' => 'terminals.ecg.ng',
            ])
            ->assertOk()
            ->assertJsonPath('data.created.0.email', '204401pg@terminals.ecg.ng');
    }

    public function test_a_non_spreadsheet_upload_is_rejected(): void
    {
        $this->asAdmin()
            ->post('/api/v1/terminals/import', [
                'file' => UploadedFile::fake()->create('terminals.pdf', 10, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_the_file_is_required(): void
    {
        $this->asAdmin()
            ->postJson('/api/v1/terminals/import', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_an_ordinary_terminal_cannot_bulk_create_logins(): void
    {
        $token = JWTAuth::fromUser(User::factory()->create(['terminal_id' => '204401PG']));

        $this->isolateNextRequest();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/terminals/import', [
                'file' => $this->sheet([['Terminal ID'], ['NEW001']]),
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This action requires an administrator account.');

        $this->assertDatabaseMissing('users', ['terminal_id' => 'NEW001']);
    }

    public function test_the_endpoint_requires_authentication(): void
    {
        $this->post('/api/v1/terminals/import', [
            'file' => $this->sheet([['Terminal ID'], ['204401PG']]),
        ])->assertUnauthorized();
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function upload(array $rows): TestResponse
    {
        return $this->asAdmin()
            ->post('/api/v1/terminals/import', ['file' => $this->sheet($rows)]);
    }

    /**
     * Upload as an existing admin, for tests that post more than once and so
     * must not mint a second admin with the same terminal ID.
     *
     * @param  list<list<string>>  $rows
     */
    private function uploadAs(User $admin, array $rows): TestResponse
    {
        $token = JWTAuth::fromUser($admin);

        $this->isolateNextRequest();

        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/terminals/import', ['file' => $this->sheet($rows)]);
    }

    /**
     * Build a real CSV upload rather than a fake, so the parser is exercised.
     *
     * @param  list<list<string>>  $rows
     */
    private function sheet(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'terminals').'.csv';
        $handle = fopen($path, 'w');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return new UploadedFile($path, 'terminals.csv', 'text/csv', null, true);
    }

    private function asAdmin(): self
    {
        $token = JWTAuth::fromUser(User::factory()->admin()->create(['terminal_id' => 'ADMIN001']));

        $this->isolateNextRequest();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
