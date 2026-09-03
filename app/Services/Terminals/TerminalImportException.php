<?php

declare(strict_types=1);

namespace App\Services\Terminals;

use RuntimeException;

/**
 * Thrown when an uploaded sheet cannot be read at all, as opposed to a single
 * row failing.
 */
final class TerminalImportException extends RuntimeException
{
}
