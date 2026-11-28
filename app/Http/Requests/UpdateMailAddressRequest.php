<?php

namespace App\Http\Requests;

use App\Concerns\MailAddressValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMailAddressRequest extends FormRequest
{
    use MailAddressValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->mailAddressRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mailAddressMessages();
    }
}
