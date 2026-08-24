<?php

declare(strict_types=1);

namespace App\Services\OIRS\DTOs;

use Carbon\CarbonImmutable;

final readonly class PaymentNotificationData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $ipn,
        public float $amountPaid,
        public string $terminalId,
        public CarbonImmutable $paidAt,
        public ?string $reference,
        /** Payment status as reported by OIRS, null when it returns none. */
        public ?string $status,
        public array $raw,
    ) {}
}
