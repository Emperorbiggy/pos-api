<?php

declare(strict_types=1);

namespace App\Services\OIRS\DTOs;

final readonly class InvoiceDetailsData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $ipn,
        public ?CustomerData $customer,
        public ?string $status,
        public float $amount,
        public float $totalAmount,
        public float $amountPaid,
        public ?string $description,
        public ?string $authorizationUrl,
        public ?string $revenueCode,
        public ?string $agencyCode,
        public ?string $paymentType,
        /** The untouched OIRS payload, so nothing this DTO does not name is lost. */
        public array $raw,
    ) {}
}
