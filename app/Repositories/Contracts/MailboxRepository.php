<?php

namespace App\Repositories\Contracts;

use App\Models\Mailbox;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for {@see Mailbox} rows.
 *
 * @extends Repository<Mailbox>
 */
interface MailboxRepository extends Repository
{
    /**
     * Every mailbox, alphabetical, with its owning agent and project, plus
     * the incoming email count and unread email count — the mailboxes index.
     *
     * @return Collection<int, Mailbox>
     */
    public function allForIndex(): Collection;

    /**
     * The mailboxes to fetch: the one matching the given name or id, or every
     * active mailbox when no identifier is given, alphabetical.
     *
     * @return Collection<int, Mailbox>
     */
    public function toFetch(?string $identifier): Collection;
}
