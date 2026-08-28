<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\OIRS\DTOs\InvoiceDetailsData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class InvoiceDetailsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var InvoiceDetailsData $invoice */
        $invoice = $this->resource;

        return [
            'ipn' => $invoice->ipn,
            'status' => $invoice->status,
            'amount' => $invoice->amount,
            'total_amount' => $invoice->totalAmount,
            'amount_paid' => $invoice->amountPaid,
            'description' => $invoice->description,
            'authorization_url' => $invoice->authorizationUrl,
            'revenue_code' => $invoice->revenueCode,
            'agency_code' => $invoice->agencyCode,
            'payment_type' => $invoice->paymentType,
            'customer' => $invoice->customer === null ? null : [
                'id' => $invoice->customer->id,
                'ipn' => $invoice->customer->ipn,
                'name' => $invoice->customer->name,
                'email' => $invoice->customer->email,
                'phone' => $invoice->customer->phone,
                'address' => $invoice->customer->address,
            ],
        ];
    }
}
