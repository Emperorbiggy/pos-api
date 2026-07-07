<?php

declare(strict_types=1);

namespace App\Http\Requests\OIRS;

use Illuminate\Foundation\Http\FormRequest;

final class PaymentNotificationRequest extends FormRequest
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
            'ipn' => ['required', 'string', 'max:50'],
            'amount_paid' => ['required', 'numeric', 'gt:0'],
            'terminal_id' => ['required', 'string', 'max:50'],
            'paid_at' => ['required', 'date'],
        ];
    }
}
