<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\OIRS\DTOs\PaymentNotificationData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PaymentNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PaymentNotificationData $payment */
        $payment = $this->resource;

        return [
            'ipn' => $payment->ipn,
            'amount_paid' => $payment->amountPaid,
            'terminal_id' => $payment->terminalId,
            'paid_at' => $payment->paidAt->toIso8601String(),
            'reference' => $payment->reference,
        ];
    }
}
