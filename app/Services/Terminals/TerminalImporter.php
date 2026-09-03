<?php

declare(strict_types=1);

namespace App\Services\Terminals;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Turns a sheet of terminal IDs into merchant logins, one per terminal.
 */
final class TerminalImporter
{
    /**
     * Header labels accepted for the terminal column, lower-cased and stripped
     * of spaces, underscores and hyphens. Real spreadsheets are inconsistent.
     *
     * @var list<string>
     */
    private const TERMINAL_HEADERS = ['terminalid', 'terminal', 'tid', 'terminalno', 'terminalnumber'];

    /** @var list<string> */
    private const SERIAL_HEADERS = ['serialnumber', 'serial', 'serialno', 'sn'];

    private const PASSWORD_LENGTH = 12;

    /**
     * Import parsed rows, the first of which is the header.
     *
     * @param  list<list<string|null>>  $rows
     */
    public function import(array $rows, string $emailDomain): TerminalImportResult
    {
        $result = new TerminalImportResult;

        $header = $this->normaliseHeader(array_shift($rows) ?? []);
        $terminalColumn = $this->locateColumn($header, self::TERMINAL_HEADERS);

        if ($terminalColumn === null) {
            throw new TerminalImportException(
                'No terminal ID column found. Expected a header cell reading "Terminal ID".'
            );
        }

        $serialColumn = $this->locateColumn($header, self::SERIAL_HEADERS);

        // Terminals already taken, fetched once rather than per row.
        $existing = User::query()
            ->whereNotNull('terminal_id')
            ->pluck('terminal_id')
            ->map(fn (string $id): string => mb_strtolower($id))
            ->flip();

        // Guards against the same terminal appearing twice in one file, which
        // the database check alone would not catch.
        $seen = [];

        foreach ($rows as $index => $row) {
            // +2: one for the header, one because humans count from 1.
            $rowNumber = $index + 2;

            $terminalId = $this->cell($row, $terminalColumn);

            if ($terminalId === null) {
                if ($this->isBlankRow($row)) {
                    continue;
                }

                $result->failed($rowNumber, null, 'Terminal ID is empty.');

                continue;
            }

            if (mb_strlen($terminalId) > 50) {
                $result->failed($rowNumber, $terminalId, 'Terminal ID is longer than 50 characters.');

                continue;
            }

            $key = mb_strtolower($terminalId);

            if (isset($seen[$key])) {
                $result->skipped($rowNumber, $terminalId, 'Duplicated earlier in this file at row '.$seen[$key].'.');

                continue;
            }

            $seen[$key] = $rowNumber;

            if ($existing->has($key)) {
                $result->skipped($rowNumber, $terminalId, 'A login already exists for this terminal.');

                continue;
            }

            $email = $this->emailFor($terminalId, $emailDomain);

            if (! $this->isValidEmail($email)) {
                $result->failed($rowNumber, $terminalId, 'Terminal ID does not form a valid email address: '.$email);

                continue;
            }

            if (User::query()->where('email', $email)->exists()) {
                $result->skipped($rowNumber, $terminalId, 'The derived email '.$email.' is already registered.');

                continue;
            }

            $password = $this->generatePassword();

            User::query()->create([
                'name' => $this->nameFor($terminalId, $this->cell($row, $serialColumn)),
                'email' => $email,
                'terminal_id' => $terminalId,
                'password' => $password,
            ]);

            $result->created($rowNumber, $terminalId, $email, $password);
        }

        return $result;
    }

    /**
     * @param  list<string|null>  $header
     * @return list<string>
     */
    private function normaliseHeader(array $header): array
    {
        return array_map(
            fn (?string $cell): string => (string) preg_replace('/[\s_\-]+/', '', mb_strtolower(trim((string) $cell))),
            $header,
        );
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $candidates
     */
    private function locateColumn(array $header, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $position = array_search($candidate, $header, true);

            if ($position !== false) {
                return (int) $position;
            }
        }

        return null;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function cell(array $row, ?int $column): ?string
    {
        if ($column === null) {
            return null;
        }

        $value = trim((string) ($row[$column] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function emailFor(string $terminalId, string $domain): string
    {
        // Terminal IDs are alphanumeric in practice; anything else is dropped so
        // the local part stays a legal address.
        $local = mb_strtolower((string) preg_replace('/[^A-Za-z0-9._-]/', '', $terminalId));

        return $local.'@'.$domain;
    }

    private function isValidEmail(string $email): bool
    {
        return ! str_starts_with($email, '@')
            && Validator::make(['email' => $email], ['email' => ['email', 'max:255']])->passes();
    }

    private function nameFor(string $terminalId, ?string $serial): string
    {
        return mb_substr(
            $serial === null ? 'Terminal '.$terminalId : 'Terminal '.$terminalId.' ('.$serial.')',
            0,
            255,
        );
    }

    /**
     * Excludes characters that are misread off a printed sheet (O/0, l/1).
     */
    private function generatePassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $password = '';

        for ($i = 0; $i < self::PASSWORD_LENGTH; $i++) {
            $password .= $alphabet[random_int(0, mb_strlen($alphabet) - 1)];
        }

        return $password;
    }
}
