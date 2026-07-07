<?php

declare(strict_types=1);

namespace App\Http\Requests\OIRS;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'revenue_code' => ['required', 'string', 'max:50'],
            'agency_code' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'pid' => ['required', 'string', 'max:50'],
            'payment_type' => ['required', 'string', 'in:individual,corporate'],
        ];
    }
}
