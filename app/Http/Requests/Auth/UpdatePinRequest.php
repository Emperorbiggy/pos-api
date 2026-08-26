<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

final class UpdatePinRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->user('api');

        return [
            // Kept as a string so a leading zero survives: "0042" is a valid PIN.
            // "confirmed" is added only when the client sends a confirmation,
            // since the rule would otherwise demand the field from everyone.
            'pin' => array_merge(
                ['required', 'string', 'regex:/^\d{4,6}$/'],
                $this->has('pin_confirmation') ? ['confirmed'] : [],
            ),
            // Changing a PIN means proving you know the old one. Left optional
            // when none is set so the controller can answer "create one first"
            // rather than a misleading validation error.
            'current_pin' => [
                Rule::requiredIf(fn (): bool => $user->pin !== null),
                'string',
            ],
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
            'current_pin.required' => 'Your current pin is required to change your pin.',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var User $user */
            $user = $this->user('api');

            if ($user->pin === null) {
                return;
            }

            $currentPin = $this->input('current_pin');

            if (! is_string($currentPin) || ! Hash::check($currentPin, $user->pin)) {
                $validator->errors()->add('current_pin', 'Your current pin is incorrect.');
            }
        });
    }
}
