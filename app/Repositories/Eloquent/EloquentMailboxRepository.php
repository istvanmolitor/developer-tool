<?php

namespace App\Repositories\Eloquent;

use App\Models\Mailbox;
use App\Repositories\Contracts\MailboxRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends EloquentRepository<Mailbox>
 */
class EloquentMailboxRepository extends EloquentRepository implements MailboxRepository
{
    protected function model(): string
    {
        return Mailbox::class;
    }

    public function allForIndex(): Collection
    {
        return $this->newQuery()
            ->with(['agent:id,name,avatar_path,updated_at', 'project:id,name'])
            ->withCount([
                'incomingEmails',
                'incomingEmails as unread_emails_count' => fn (Builder $query) => $query->whereNull('read_at'),
            ])
            ->orderBy('name')
            ->get();
    }

    public function toFetch(?string $identifier): Collection
    {
        return $this->newQuery()
            ->when(
                $identifier !== null,
                fn ($query) => $query->where('name', $identifier)->orWhere('id', $identifier),
                fn ($query) => $query->where('is_active', true),
            )
            ->orderBy('name')
            ->get();
    }
}
