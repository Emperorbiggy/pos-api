<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\OIRS\DTOs\PaymentValidationData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PaymentValidationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PaymentValidationData $validation */
        $validation = $this->resource;

        return [
            'ipn' => $validation->ipn,
            'terminal_id' => $validation->terminalId,
            'status' => $validation->status,
            'amount' => $validation->amount,
            'total_amount' => $validation->totalAmount,
            'amount_paid' => $validation->amountPaid,
            'description' => $validation->description,
            'customer' => $validation->customer === null ? null : [
                'id' => $validation->customer->id,
                'ipn' => $validation->customer->ipn,
                'name' => $validation->customer->name,
                'email' => $validation->customer->email,
                'phone' => $validation->customer->phone,
                'address' => $validation->customer->address,
            ],
        ];
    }
}
