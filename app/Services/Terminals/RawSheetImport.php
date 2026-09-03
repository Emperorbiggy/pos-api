<?php

declare(strict_types=1);

namespace App\Services\Terminals;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Hands back the sheet exactly as it is, header row included, so the importer
 * can match column titles itself.
 */
final class RawSheetImport implements ToArray
{
    /**
     * @param  array<int, array<int, mixed>>  $array
     * @return array<int, array<int, mixed>>
     */
    public function array(array $array): array
    {
        return $array;
    }
}
