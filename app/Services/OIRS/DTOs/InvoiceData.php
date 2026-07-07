<?php

declare(strict_types=1);

namespace App\Services\OIRS\DTOs;

final readonly class InvoiceData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $ipn,
        public string $authorizationUrl,
        public array $raw,
    ) {}
}
