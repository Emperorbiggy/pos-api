<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class CreatePinRequest extends FormRequest
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
            // Kept as a string so a leading zero survives: "0042" is a valid PIN.
            // "confirmed" is added only when the client sends a confirmation,
            // since the rule would otherwise demand the field from everyone.
            'pin' => array_merge(
                ['required', 'string', 'regex:/^\d{4,6}$/'],
                $this->has('pin_confirmation') ? ['confirmed'] : [],
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pin.regex' => 'The pin must be 4 to 6 digits.',
            'pin.confirmed' => 'The pin confirmation does not match.',
        ];
    }
}
