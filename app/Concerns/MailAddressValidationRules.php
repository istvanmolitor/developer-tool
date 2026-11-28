<?php

namespace App\Concerns;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Shared validation for creating and editing a mail address, so the Store and
 * Update requests stay in step. The address is lowercased before validation, so
 * uniqueness is case-insensitive and it is stored in a single canonical form.
 */
trait MailAddressValidationRules
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('mail'))) {
            $this->merge(['mail' => Str::lower(trim($this->input('mail')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function mailAddressRules(): array
    {
        return [
            'mail' => [
                'required',
                'email',
                'max:255',
                Rule::unique('mail_addresses', 'mail')->ignore($this->route('mailAddress')),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'is_spam' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mailAddressMessages(): array
    {
        return [
            'mail.required' => 'Add meg az e-mail címet.',
            'mail.email' => 'Érvényes e-mail címet adj meg.',
            'mail.unique' => 'Ez az e-mail cím már szerepel a listában.',
        ];
    }
}
