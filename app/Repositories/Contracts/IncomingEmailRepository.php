<?php

namespace App\Repositories\Contracts;

use App\Models\IncomingEmail;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Data access for {@see IncomingEmail} rows.
 *
 * @extends Repository<IncomingEmail>
 */
interface IncomingEmailRepository extends Repository
{
    /**
     * Resolve a message by primary key, locking the row for update.
     *
     * @throws ModelNotFoundException
     */
    public function lockForUpdate(int|string $id): IncomingEmail;

    /**
     * Resolve a message handed to the given prompt run, or null when it was
     * not, so an agent cannot answer a message from another run.
     */
    public function findForPromptRun(int|string $id, int|string $promptRunId): ?IncomingEmail;
}
