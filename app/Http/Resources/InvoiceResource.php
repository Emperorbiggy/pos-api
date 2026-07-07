<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\OIRS\DTOs\InvoiceData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var InvoiceData $invoice */
        $invoice = $this->resource;

        return [
            'ipn' => $invoice->ipn,
            'authorization_url' => $invoice->authorizationUrl,
        ];
    }
}
