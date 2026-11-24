<?php

namespace App\Concerns;

use App\Enums\MailEncryption;
use App\Models\Mailbox;
use Illuminate\Validation\Rule;

trait MailboxValidationRules
{
    /**
     * Validation rules shared by the mailbox create and update requests.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function mailboxRules(?Mailbox $ignore = null): array
    {
        return [
            // Automatic reading hands every new message to the agent on the project, so both are needed.
            'agent_id' => ['nullable', 'required_if_accepted:auto_read', 'integer', 'exists:agents,id'],
            'project_id' => ['nullable', 'required_if_accepted:auto_read', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('mailboxes', 'name')->ignore($ignore)],
            'email' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            // Required on create; on update an empty value keeps the stored password.
            'password' => [$ignore === null ? 'required' : 'nullable', 'string', 'max:255'],
            'imap_host' => ['required', 'string', 'max:255'],
            'imap_port' => ['required', 'integer', 'between:1,65535'],
            'imap_encryption' => ['required', Rule::enum(MailEncryption::class)],
            'imap_folder' => ['required', 'string', 'max:255'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['required', Rule::enum(MailEncryption::class)],
            'is_active' => ['boolean'],
            'auto_read' => ['boolean'],
            'auto_reply' => ['boolean'],
        ];
    }

    /**
     * Validation messages shared by the mailbox create and update requests.
     *
     * @return array<string, string>
     */
    protected function mailboxMessages(): array
    {
        return [
            'agent_id.required_if_accepted' => 'Az automatikus olvasáshoz válassz agentet.',
            'project_id.required_if_accepted' => 'Az automatikus olvasáshoz válassz projektet.',
        ];
    }
}
