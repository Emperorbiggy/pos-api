<?php

declare(strict_types=1);

namespace App\Services\OIRS\DTOs;

final readonly class PaymentValidationData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $ipn,
        public string $terminalId,
        public ?CustomerData $customer,
        public ?string $status,
        public float $amount,
        public float $totalAmount,
        public float $amountPaid,
        public ?string $description,
        public array $raw,
    ) {}
}
