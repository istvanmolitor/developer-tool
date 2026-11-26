<?php

namespace App\Repositories\Contracts;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for {@see Attachment} rows.
 *
 * @extends Repository<Attachment>
 */
interface AttachmentRepository extends Repository
{
    /**
     * Attachments for the given id set.
     *
     * @param  array<int, int|string>  $ids
     * @return Collection<int, Attachment>
     */
    public function byIds(array $ids): Collection;
}
