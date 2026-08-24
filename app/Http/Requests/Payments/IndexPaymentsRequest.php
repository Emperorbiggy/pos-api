<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

final class IndexPaymentsRequest extends FormRequest
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
            'status' => ['sometimes', 'string', 'max:50'],
            'terminal_id' => ['sometimes', 'string', 'max:50'],
            'ipn' => ['sometimes', 'string', 'max:50'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
