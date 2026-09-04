<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        // The terminal being edited, not the admin doing the editing.
        $terminalId = $this->route('user')?->getKey();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'terminal_id' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                // A terminal belongs to one merchant, but keeping its own is not a clash.
                Rule::unique('users', 'terminal_id')->ignore($terminalId),
            ],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($terminalId),
            ],
            // An admin reset does not require the old password: the whole point
            // is recovering a terminal whose holder cannot supply it.
            'password' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
            // Kept as a string so a leading zero survives: "0042" is a valid PIN.
            'pin' => ['sometimes', 'required', 'string', 'regex:/^\d{4,6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pin.regex' => 'The pin must be 4 to 6 digits.',
        ];
    }
}
