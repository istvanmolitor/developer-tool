<?php

namespace App\Http\Requests;

use App\Concerns\MailboxValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreMailboxRequest extends FormRequest
{
    use MailboxValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->mailboxRules();
    }
}
