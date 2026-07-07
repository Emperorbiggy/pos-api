<?php

declare(strict_types=1);

namespace App\Services\OIRS\DTOs;

final readonly class CustomerData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $id,
        public ?string $ipn,
        public ?string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $address,
        public array $raw,
    ) {}
}
