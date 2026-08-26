<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Payment $payment */
        $payment = $this->resource;

        return [
            'id' => $payment->id,
            'ipn' => $payment->ipn,
            'terminal_id' => $payment->terminal_id,
            'location' => $payment->location,
            'status' => $payment->status,
            'amount' => $payment->amount,
            'total_amount' => $payment->total_amount,
            'amount_paid' => $payment->amount_paid,
            'description' => $payment->description,
            'reference' => $payment->reference,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'customer' => [
                'id' => $payment->customer_id,
                'ipn' => $payment->customer_ipn,
                'name' => $payment->customer_name,
                'email' => $payment->customer_email,
                'phone' => $payment->customer_phone,
                'address' => $payment->customer_address,
            ],
            'created_at' => $payment->created_at?->toIso8601String(),
            'updated_at' => $payment->updated_at?->toIso8601String(),
        ];
    }
}
