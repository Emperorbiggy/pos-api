<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Both fields are optional so a caller can change one without resending the
     * other, but neither may be sent blank.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user('api');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'terminal_id' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                // A terminal belongs to one merchant, but keeping your own is not a clash.
                Rule::unique('users', 'terminal_id')->ignore($user->getKey()),
            ],
        ];
    }
}
