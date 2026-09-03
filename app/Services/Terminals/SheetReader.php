<?php

declare(strict_types=1);

namespace App\Services\Terminals;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Throwable;

/**
 * Reads an uploaded .xlsx/.xls/.csv into plain rows.
 */
final class SheetReader
{
    /**
     * Create the scratch directory the reader unpacks uploads into.
     *
     * Every format goes through it, CSV included, so a missing or unwritable
     * directory fails each of them with the same opaque error. A fresh checkout
     * has no storage/framework/cache/laravel-excel, and nothing else creates it.
     */
    private function ensureTemporaryPathExists(): void
    {
        $path = config('excel.temporary_files.local_path');

        if (! is_string($path) || $path === '') {
            return;
        }

        if (! is_dir($path)) {
            // Suppressed: a race with a concurrent upload is harmless, and an
            // unwritable parent is reported by the check below instead.
            @mkdir($path, 0775, true);
        }

        if (! is_dir($path) || ! is_writable($path)) {
            throw new TerminalImportException(
                'The server cannot write its temporary import directory. '.
                "Create {$path} and make it writable by the web user."
            );
        }
    }

    /**
     * @return list<list<string|null>> The first row is the header.
     */
    public function rows(UploadedFile $file): array
    {
        $this->ensureTemporaryPathExists();

        try {
            $sheets = Excel::toArray(new RawSheetImport, $file);
        } catch (Throwable $exception) {
            // The caller gets a safe message, but the cause has to be findable:
            // "could not be read" covers a missing PHP extension, an unwritable
            // temp directory and a genuinely corrupt file alike.
            Log::error('Terminal import could not read the uploaded file.', [
                'original_name' => $file->getClientOriginalName(),
                'client_mime' => $file->getClientMimeType(),
                'guessed_extension' => $file->guessExtension(),
                'size' => $file->getSize(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new TerminalImportException(
                'The file could not be read. Upload a valid .xlsx, .xls or .csv file.',
                previous: $exception,
            );
        }

        $rows = $sheets[0] ?? [];

        if ($rows === []) {
            throw new TerminalImportException('The file is empty.');
        }

        return array_map(
            fn (array $row): array => array_map(
                fn ($cell): ?string => $cell === null ? null : (string) $cell,
                array_values($row),
            ),
            $rows,
        );
    }
}
