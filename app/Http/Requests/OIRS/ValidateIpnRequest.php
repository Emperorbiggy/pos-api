<?php

declare(strict_types=1);

namespace App\Http\Requests\OIRS;

use Illuminate\Foundation\Http\FormRequest;

final class ValidateIpnRequest extends FormRequest
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
            'terminal_id' => ['required', 'string', 'max:50'],
        ];
    }
}
