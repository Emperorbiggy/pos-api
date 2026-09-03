<?php

declare(strict_types=1);

namespace App\Http\Requests\Terminals;

use Illuminate\Foundation\Http\FormRequest;

final class ImportTerminalsRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
            'email_domain' => ['sometimes', 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'The file must be an .xlsx, .xls or .csv spreadsheet.',
            'file.max' => 'The file may not be larger than 5MB.',
            'email_domain.regex' => 'The email domain must look like ecgpos.local or example.com.',
        ];
    }
}
