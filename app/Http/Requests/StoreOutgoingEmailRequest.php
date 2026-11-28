<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreOutgoingEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accept the recipients as a comma- (or semicolon-) separated string from the
     * compose form and turn them into a clean list of addresses to validate.
     */
    protected function prepareForValidation(): void
    {
        $to = $this->input('to');

        if (is_string($to)) {
            $this->merge([
                'to' => collect(preg_split('/[,;]+/', $to) ?: [])
                    ->map(fn (string $address): string => trim($address))
                    ->filter()
                    ->values()
                    ->all(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['email'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'attachments' => ['nullable', 'array'],
            // Up to 25 MB per file; files are stored as-is and only ever sent as downloads.
            'attachments.*' => ['file', 'max:25600'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.required' => 'Adj meg legalább egy címzettet.',
            'to.*.email' => 'Minden címzettnek érvényes e-mail címnek kell lennie.',
        ];
    }

    /**
     * The validated recipients as a clean list of addresses.
     *
     * @return list<string>
     */
    public function recipients(): array
    {
        return array_values(array_unique(array_map(
            fn (string $address): string => Str::lower(trim($address)),
            $this->validated('to'),
        )));
    }
}
