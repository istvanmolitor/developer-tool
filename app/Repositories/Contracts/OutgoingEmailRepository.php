<?php

namespace App\Repositories\Contracts;

use App\Models\OutgoingEmail;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Data access for {@see OutgoingEmail} rows.
 *
 * @extends Repository<OutgoingEmail>
 */
interface OutgoingEmailRepository extends Repository
{
    /**
     * Resolve a message by primary key, locking the row for update.
     *
     * @throws ModelNotFoundException
     */
    public function lockForUpdate(int|string $id): OutgoingEmail;
}
