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
     * @return list<list<string|null>> The first row is the header.
     */
    public function rows(UploadedFile $file): array
    {
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
