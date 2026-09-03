<?php

declare(strict_types=1);

namespace App\Services\Terminals;

/**
 * Outcome of one bulk terminal upload.
 *
 * Generated passwords live here and nowhere else: they are hashed on the way
 * into the database, so this response is the only chance to capture them.
 */
final class TerminalImportResult
{
    /** @var list<array{row: int, terminal_id: string, email: string, password: string}> */
    private array $created = [];

    /** @var list<array{row: int, terminal_id: string, reason: string}> */
    private array $skipped = [];

    /** @var list<array{row: int, terminal_id: string|null, reason: string}> */
    private array $failed = [];

    public function created(int $row, string $terminalId, string $email, string $password): void
    {
        $this->created[] = [
            'row' => $row,
            'terminal_id' => $terminalId,
            'email' => $email,
            'password' => $password,
        ];
    }

    public function skipped(int $row, string $terminalId, string $reason): void
    {
        $this->skipped[] = [
            'row' => $row,
            'terminal_id' => $terminalId,
            'reason' => $reason,
        ];
    }

    public function failed(int $row, ?string $terminalId, string $reason): void
    {
        $this->failed[] = [
            'row' => $row,
            'terminal_id' => $terminalId,
            'reason' => $reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'summary' => [
                'processed' => count($this->created) + count($this->skipped) + count($this->failed),
                'created' => count($this->created),
                'skipped' => count($this->skipped),
                'failed' => count($this->failed),
            ],
            'created' => $this->created,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
        ];
    }
}
