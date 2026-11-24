<?php

namespace App\Http\Requests;

use App\Concerns\MailboxValidationRules;
use App\Models\Mailbox;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMailboxRequest extends FormRequest
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
        /** @var Mailbox $mailbox */
        $mailbox = $this->route('mailbox');

        return $this->mailboxRules($mailbox);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mailboxMessages();
    }
}
